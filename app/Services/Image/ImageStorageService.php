<?php

namespace App\Services\Image;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ImageStorageService
{
    /**
     * 目标存储 Disk ('r2' 或 'public')
     */
    protected string $disk;

    /**
     * 上次上传时间戳（微秒），用于 R2 频控节流
     */
    protected float $lastUploadTime = 0.0;

    /**
     * 每次上传最小间隔（微秒）：默认 50,000 微秒（50ms），即单进程每秒最多上传 20 次，防止触发 R2 429 频控
     */
    protected int $throttleMicroseconds = 50000;

    public function __construct()
    {
        // 自动探测 R2 是否已配置密钥；若未配置则无缝平滑降级到本地 public 磁盘
        $r2Configured = !empty(config('filesystems.disks.r2.key'))
            && !empty(config('filesystems.disks.r2.secret'))
            && !empty(config('filesystems.disks.r2.bucket'));

        $this->disk = $r2Configured ? 'r2' : 'public';
    }

    /**
     * 获取当前使用的存储磁盘名称
     */
    public function getDisk(): string
    {
        return $this->disk;
    }

    /**
     * 下载远程图片、高效率 WebP 压缩优化并上传至 Cloudflare R2 / Public 存储
     *
     * @param string|null $remoteUrl 远程图片 CDN 地址
     * @param string $targetSubPath 目标相对路径（如 imag-vixen/videos/123/hash.webp）
     * @param bool $optimizeWebp 是否启用 GD 高效 WebP 压缩（默认 true）
     * @return array{path: string, url: string, disk: string}|null
     */
    public function downloadOptimizeAndStore(?string $remoteUrl, string $targetSubPath, bool $optimizeWebp = true): ?array
    {
        if (empty($remoteUrl)) {
            return null;
        }

        try {
            // 1. 规范化目标后缀：如果启用 WebP 优化且原扩展为 jpg/png，转换为 .webp
            $pathInfo = pathinfo($targetSubPath);
            $ext = strtolower($pathInfo['extension'] ?? 'jpg');
            if ($optimizeWebp && in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $targetSubPath = ($pathInfo['dirname'] !== '.' ? $pathInfo['dirname'] . '/' : '') . $pathInfo['filename'] . '.webp';
                $mimeType = 'image/webp';
            } else {
                $mimeType = match ($ext) {
                    'png'   => 'image/png',
                    'webp'  => 'image/webp',
                    'gif'   => 'image/gif',
                    'mp4'   => 'video/mp4',
                    default => 'image/jpeg',
                };
            }

            // 2. 幂等检测：如果 R2 或本地已经存在该文件，直接复用已有地址，避免重复下载与 R2 账单扣费
            if (Storage::disk($this->disk)->exists($targetSubPath)) {
                return [
                    'path' => $targetSubPath,
                    'url'  => $this->resolvePublicUrl($targetSubPath),
                    'disk' => $this->disk,
                ];
            }

            // 3. 远端下载图片（优先复用 FlareSolverr 缓存的真实浏览器 User-Agent、语言与代理支持）
            $imgUserAgent = config('services.crawler.user_agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36');
            try {
                $cachedUa = Redis::get('crawler:user_agent') ?: Redis::get('vixen_user_agent');
                if (!empty($cachedUa)) {
                    $imgUserAgent = $cachedUa;
                }
            } catch (Throwable) {
            }

            $imgRequest = Http::timeout(30)
                ->retry(3, 1000)
                ->withHeaders([
                    'user-agent'      => $imgUserAgent,
                    'accept'          => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                    'accept-language' => 'en-US,en;q=0.9',
                ]);

            $proxy = config('services.crawler.proxy');
            if (!empty($proxy)) {
                $imgRequest = $imgRequest->withOptions(['proxy' => $proxy]);
            }

            $response = $imgRequest->get($remoteUrl);

            if (!$response->successful()) {
                Log::warning("下载远程图片失败 [{$response->status()}]: {$remoteUrl}");
                return null;
            }

            $rawContent = $response->body();
            if (empty($rawContent)) {
                return null;
            }

            // 4. 图片文件体积高能优化（首选 Google 官方 cwebp，自动优雅降级为 GD）
            $finalContent = $rawContent;
            if ($optimizeWebp && in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $cwebpResult = $this->compressWithCwebp($rawContent, 80);
                if (!empty($cwebpResult)) {
                    $finalContent = $cwebpResult;
                    $mimeType = 'image/webp';
                } elseif (function_exists('imagecreatefromstring')) {
                    $gdResult = $this->compressToWebp($rawContent, 80);
                    if (!empty($gdResult) && strlen($gdResult) < strlen($rawContent)) {
                        $finalContent = $gdResult;
                        $mimeType = 'image/webp';
                    }
                }
            }

            // 5. 频控平滑节流（防 Cloudflare R2 429 Too Many Requests）
            $this->applyThrottle();

            // 6. 上传到存储介质（带指数退避重试）
            $this->uploadWithRetry($targetSubPath, $finalContent, $mimeType);

            return [
                'path' => $targetSubPath,
                'url'  => $this->resolvePublicUrl($targetSubPath),
                'disk' => $this->disk,
            ];
        } catch (Throwable $e) {
            Log::error("图片下载/优化/上传异常 [{$remoteUrl}]: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 获取系统中 cwebp 官方二进制工具路径
     */
    public function getCwebpBinary(): ?string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached ?: null;
        }

        $candidates = [
            '/usr/local/bin/cwebp',
            '/usr/bin/cwebp',
            'cwebp',
        ];

        foreach ($candidates as $bin) {
            $path = @exec("which {$bin} 2>/dev/null") ?: (file_exists($bin) ? $bin : null);
            if ($path && @is_executable($path)) {
                return $cached = $path;
            }
        }

        return $cached = '';
    }

    /**
     * 使用 Google 官方 cwebp 原生 C 工具进行多线程 SIMD 高能压缩
     * 既可将 JPEG/PNG 转为 WebP，也能对已有 WebP 再次压榨体积（影响极小画质）
     */
    protected function compressWithCwebp(string $rawContent, int $quality = 80): ?string
    {
        $bin = $this->getCwebpBinary();
        if (!$bin) {
            return null;
        }

        $tmpIn = tempnam(sys_get_temp_dir(), 'cwebp_in_');
        $tmpOut = tempnam(sys_get_temp_dir(), 'cwebp_out_');

        try {
            file_put_contents($tmpIn, $rawContent);

            // -q 80: 最佳质量与体积平衡点
            // -mt: 开启多线程并行加速
            // -m 4: 快速高效压缩模式
            // -quiet: 静默无额外控制台输出
            $cmd = sprintf(
                '%s -q %d -mt -m 4 -quiet %s -o %s 2>&1',
                escapeshellcmd($bin),
                $quality,
                escapeshellarg($tmpIn),
                escapeshellarg($tmpOut)
            );

            exec($cmd, $out, $ret);

            if ($ret === 0 && file_exists($tmpOut) && filesize($tmpOut) > 0) {
                $optimized = file_get_contents($tmpOut);
                if ($optimized !== false && strlen($optimized) > 0) {
                    // 若压缩后比原文件小，采用压缩后的；若原文件已经是极限压缩，保留原文件
                    if (strlen($optimized) < strlen($rawContent)) {
                        return $optimized;
                    }
                    return $rawContent;
                }
            }

            return null;
        } catch (Throwable $e) {
            return null;
        } finally {
            if ($tmpIn && file_exists($tmpIn)) {
                @unlink($tmpIn);
            }
            if ($tmpOut && file_exists($tmpOut)) {
                @unlink($tmpOut);
            }
        }
    }

    /**
     * 将二进制图片通过 GD 内存转换为 WebP 格式（通常缩减 60%~80% 体积，作为 cwebp 的降级方案）
     */
    protected function compressToWebp(string $rawContent, int $quality = 80): ?string
    {
        try {
            $image = @imagecreatefromstring($rawContent);
            if (!$image) {
                return null;
            }

            // 针对具有 Alpha 透明通道的 PNG 保持透明度
            imagealphablending($image, true);
            imagesavealpha($image, true);

            ob_start();
            imagewebp($image, null, $quality);
            $webpContent = ob_get_clean();
            imagedestroy($image);

            return $webpContent ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * 带有指数退避机制的上传写入
     */
    protected function uploadWithRetry(string $path, string $content, string $mimeType, int $maxAttempts = 3): void
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                Storage::disk($this->disk)->put($path, $content, [
                    'visibility'  => 'public',
                    'mimetype'    => $mimeType,
                    'ContentType' => $mimeType,
                ]);
                return;
            } catch (Throwable $e) {
                $lastException = $e;
                Log::warning("R2/Storage 上传异常 (第 {$attempt}/{$maxAttempts} 次重试): " . $e->getMessage());
                // 指数退避休眠（200ms, 400ms...）
                usleep(200000 * $attempt);
            }
        }

        if ($lastException) {
            throw $lastException;
        }
    }

    /**
     * 微秒级频控节流：限制上传请求速率，保护 R2 API 频控
     */
    protected function applyThrottle(): void
    {
        $now = microtime(true);
        $elapsedMicroseconds = (int) (($now - $this->lastUploadTime) * 1000000);

        if ($this->lastUploadTime > 0 && $elapsedMicroseconds < $this->throttleMicroseconds) {
            $sleepTime = $this->throttleMicroseconds - $elapsedMicroseconds;
            usleep($sleepTime);
        }

        $this->lastUploadTime = microtime(true);
    }

    /**
     * 解析生成前端可访问的完整公开 URL
     */
    public function resolvePublicUrl(string $path): string
    {
        $path = ltrim($path, '/');

        if ($this->disk === 'r2') {
            $r2CustomUrl = config('filesystems.disks.r2.url');
            if (!empty($r2CustomUrl)) {
                return rtrim($r2CustomUrl, '/') . '/' . $path;
            }

            try {
                return Storage::disk('r2')->url($path);
            } catch (Throwable $e) {
                return '/' . $path;
            }
        }

        return '/storage/' . $path;
    }
}

