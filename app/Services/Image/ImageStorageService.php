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
     * 下载远程图片、高效率 WebP/AVIF 压缩优化并上传至 Cloudflare R2 / Public 存储
     *
     * @param string|null $remoteUrl 远程图片 CDN 地址
     * @param string $targetSubPath 目标相对路径（如 images/actors/hash.avif 或 images/actors/hash.webp）
     * @param bool|string $optimizeFormat 是否启用压缩，支持 true('webp')、'webp'、'avif' 或 false
     * @param int|null $maxWidth 可选最大宽度限制（等比缩放）
     * @param int $maxSizeBytes 可选目标最大文件体积（字节，超出自动精细压榨，如 20KB = 20*1024）
     * @param bool $force 是否强制覆盖已存在的文件
     * @return array{path: string, url: string, disk: string}|null
     */
    public function downloadOptimizeAndStore(
        ?string $remoteUrl,
        string $targetSubPath,
        bool|string $optimizeFormat = true,
        ?int $maxWidth = null,
        int $maxSizeBytes = 0,
        bool $force = false
    ): ?array {
        if (empty($remoteUrl)) {
            return null;
        }

        try {
            // 1. 规范化目标后缀与格式识别
            $pathInfo = pathinfo($targetSubPath);
            $ext = strtolower($pathInfo['extension'] ?? 'jpg');
            $targetFormat = is_string($optimizeFormat) ? strtolower($optimizeFormat) : ($optimizeFormat ? 'webp' : null);

            if ($targetFormat === 'avif' || $ext === 'avif') {
                $targetSubPath = ($pathInfo['dirname'] !== '.' ? $pathInfo['dirname'] . '/' : '') . $pathInfo['filename'] . '.avif';
                $mimeType = 'image/avif';
                $targetFormat = 'avif';
            } elseif ($targetFormat === 'webp' || $ext === 'webp') {
                $targetSubPath = ($pathInfo['dirname'] !== '.' ? $pathInfo['dirname'] . '/' : '') . $pathInfo['filename'] . '.webp';
                $mimeType = 'image/webp';
                $targetFormat = 'webp';
            } else {
                $mimeType = match ($ext) {
                    'png'   => 'image/png',
                    'webp'  => 'image/webp',
                    'avif'  => 'image/avif',
                    'gif'   => 'image/gif',
                    'mp4'   => 'video/mp4',
                    default => 'image/jpeg',
                };
            }

            // 🎯 演员头像默认规范：限定最大宽度 280px，体积严格限制在 20KB 内
            if (str_starts_with($targetSubPath, 'images/actors/')) {
                if ($maxWidth === null) {
                    $maxWidth = 280;
                }
                if ($maxSizeBytes <= 0) {
                    $maxSizeBytes = 20 * 1024; // 20KB
                }
            }

            // 2. 幂等检测：如果 R2 或本地已经存在该文件且体积合规，直接复用已有地址，避免重复下载与 R2 账单扣费
            if (!$force && Storage::disk($this->disk)->exists($targetSubPath)) {
                $existingSize = 0;
                try {
                    $existingSize = Storage::disk($this->disk)->size($targetSubPath);
                } catch (Throwable) {}

                // 若设置了体积上限，确保已有文件未超标才跳过；若已有文件超标，则强制重新优化压缩
                if ($maxSizeBytes <= 0 || ($existingSize > 0 && $existingSize <= $maxSizeBytes)) {
                    return [
                        'path' => $targetSubPath,
                        'url'  => $this->resolvePublicUrl($targetSubPath),
                        'disk' => $this->disk,
                    ];
                }
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

            // 4. 图片文件体积高能优化（支持 AVIF 与 WebP，优先官方命令行工具，优雅降级为 PHP GD）
            $finalContent = $rawContent;
            if ($targetFormat === 'avif') {
                $avifQuality = ($maxSizeBytes > 0) ? 50 : 75;
                $avifResult = $this->compressWithAvifenc($rawContent, $avifQuality, $maxWidth, $maxSizeBytes);
                if (!empty($avifResult)) {
                    $finalContent = $avifResult;
                } elseif (function_exists('imageavif') && function_exists('imagecreatefromstring')) {
                    $gdAvif = $this->compressToAvif($rawContent, $avifQuality, $maxWidth, $maxSizeBytes);
                    if (!empty($gdAvif)) {
                        $finalContent = $gdAvif;
                    }
                }
                $mimeType = 'image/avif';
            } elseif ($targetFormat === 'webp' && in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
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
     * 获取系统中 avifenc 官方二进制工具路径
     */
    public function getAvifencBinary(): ?string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached ?: null;
        }

        $candidates = [
            '/usr/local/bin/avifenc',
            '/usr/bin/avifenc',
            'avifenc',
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
     * 使用官方 avifenc 工具进行高质量 AVIF 编码，支持缩放与体积自适应压榨
     */
    protected function compressWithAvifenc(string $rawContent, int $quality = 50, ?int $maxWidth = null, int $maxSizeBytes = 0): ?string
    {
        $bin = $this->getAvifencBinary();
        if (!$bin) {
            return null;
        }

        $tmpIn = tempnam(sys_get_temp_dir(), 'avif_in_') . '.png';
        $tmpOut = tempnam(sys_get_temp_dir(), 'avif_out_') . '.avif';

        try {
            $image = @imagecreatefromstring($rawContent);
            if (!$image) {
                return null;
            }

            // 1. 若指定了最大宽度且原图尺寸超出，等比缩小
            $w = imagesx($image);
            $h = imagesy($image);
            if ($maxWidth !== null && $maxWidth > 0 && $w > $maxWidth) {
                $newHeight = (int) max(1, round($h * ($maxWidth / $w)));
                $scaled = imagescale($image, $maxWidth, $newHeight);
                if ($scaled) {
                    imagedestroy($image);
                    $image = $scaled;
                }
            }

            imagealphablending($image, true);
            imagesavealpha($image, true);
            imagepng($image, $tmpIn);
            imagedestroy($image);

            $currentQuality = $quality;
            do {
                // -s 6: 速度预设 6
                // -j 2: 双线程并行
                $cmd = sprintf(
                    '%s -s 6 -j 2 -q %d %s %s 2>&1',
                    escapeshellcmd($bin),
                    $currentQuality,
                    escapeshellarg($tmpIn),
                    escapeshellarg($tmpOut)
                );

                exec($cmd, $out, $ret);

                if ($ret === 0 && file_exists($tmpOut) && filesize($tmpOut) > 0) {
                    $size = filesize($tmpOut);
                    if ($maxSizeBytes <= 0 || $size <= $maxSizeBytes || $currentQuality <= 28) {
                        return file_get_contents($tmpOut);
                    }
                } else {
                    break;
                }

                $currentQuality -= 5;
            } while (true);

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
     * 使用 PHP GD 原生 imageavif 扩展进行 AVIF 编码，支持尺寸缩放与目标体积自适应微调
     */
    protected function compressToAvif(string $rawContent, int $quality = 50, ?int $maxWidth = null, int $maxSizeBytes = 0): ?string
    {
        try {
            $image = @imagecreatefromstring($rawContent);
            if (!$image) {
                return null;
            }

            // 1. 若指定了最大宽度且原图尺寸超出，执行等比高质量重采样
            $w = imagesx($image);
            $h = imagesy($image);
            if ($maxWidth !== null && $maxWidth > 0 && $w > $maxWidth) {
                $newHeight = (int) max(1, round($h * ($maxWidth / $w)));
                $scaled = imagescale($image, $maxWidth, $newHeight);
                if ($scaled) {
                    imagedestroy($image);
                    $image = $scaled;
                }
            }

            imagealphablending($image, true);
            imagesavealpha($image, true);

            // 2. 自适应画质循环压榨（确保在画质清晰的前提下严格控制在目标体积内，如 <= 20KB）
            $currentQuality = $quality;
            $avifContent = null;

            do {
                ob_start();
                imageavif($image, null, $currentQuality);
                $avifContent = ob_get_clean();

                if (empty($avifContent)) {
                    break;
                }

                $size = strlen($avifContent);
                // 若未指定上限或当前体积已达标，或画质已降至安全阈值 28，则结束循环
                if ($maxSizeBytes <= 0 || $size <= $maxSizeBytes || $currentQuality <= 28) {
                    break;
                }

                $currentQuality -= 5;
            } while (true);

            imagedestroy($image);

            return $avifContent ?: null;
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

