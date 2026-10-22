<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
// use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    public function editAvatar(Request $request): Response
    {
        return Inertia::render('settings/avatar');
    }

    /**
     * 🎯 获取直传 Cloudflare R2 的临时预签名上传凭证
     */
    public function presignedAvatarUrl(Request $request): JsonResponse
    {
        $request->validate([
            'content_type' => ['nullable', 'string', 'in:image/webp,image/jpeg,image/png'],
        ]);

        $user = $request->user();
        $contentType = $request->input('content_type', 'image/webp');
        $ext = $contentType === 'image/webp' ? 'webp' : ($contentType === 'image/png' ? 'png' : 'jpg');

        $key = sprintf('avatars/u%d_%s.%s', $user->id, Str::random(16), $ext);

        $presigned = Storage::disk('r2')->temporaryUploadUrl($key, now()->addMinutes(10), [
            'ContentType' => $contentType,
        ]);

        $publicUrl = Storage::disk('r2')->url($key);

        return response()->json([
            'upload_url' => $presigned['url'],
            'public_url' => $publicUrl,
            'key' => $key,
            'headers' => [
                'Content-Type' => $contentType,
            ],
        ]);
    }

    /**
     * 🎯 保存新头像 (支持前端直传 R2 后回传 URL，或传统文件直存 R2 兜底)
     */
    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar_url' => ['nullable', 'string', 'url'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $newAvatarUrl = null;

        // 模式 1: 前端已直传 R2，直接传递公网 URL
        if ($request->filled('avatar_url')) {
            $inputUrl = (string) $request->input('avatar_url');
            $r2BaseUrl = rtrim((string) config('filesystems.disks.r2.url'), '/');

            // 安全校验：上传后的 URL 必须严格属于本站配置的 R2 存储域名
            if (empty($r2BaseUrl) || !Str::startsWith($inputUrl, $r2BaseUrl)) {
                return back()->withErrors(['avatar' => '无效的头像存储地址']);
            }

            $newAvatarUrl = $inputUrl;
        }
        // 模式 2: 降级/传统模式，后端接收图片文件并存入 R2
        elseif ($request->hasFile('avatar')) {
            $ext = $request->file('avatar')->guessExtension() ?: 'webp';
            $key = sprintf('avatars/u%d_%s.%s', $user->id, Str::random(16), $ext);
            Storage::disk('r2')->put($key, file_get_contents($request->file('avatar')->getRealPath()), [
                'visibility' => 'public',
                'mimetype' => $request->file('avatar')->getMimeType(),
            ]);
            $newAvatarUrl = Storage::disk('r2')->url($key);
        } else {
            return back()->withErrors(['avatar' => '请先选择并上传头像图片']);
        }

        // 🎯 核心逻辑：成功更新前，清理 R2 上的旧头像（节省 R2 空间）及本地历史遗留旧头像
        $this->deleteOldAvatarFiles($user, $newAvatarUrl);

        // 更新数据库
        $user->update([
            'avatar' => $newAvatarUrl,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => '头像更新成功。']);

        return to_route('profile.avatar.edit');
    }

    /**
     * 清理用户的旧头像存储文件 (支持 R2 存储桶与本地 storage 自动识别，节省存储空间)
     */
    protected function deleteOldAvatarFiles(User $user, ?string $newAvatarUrl = null): void
    {
        $oldAvatar = $user->avatar;

        if (empty($oldAvatar) || $oldAvatar === $newAvatarUrl) {
            return;
        }

        // 1. 如果旧头像是存放在 R2 上的文件，从 R2 彻底删除以节省空间
        $r2BaseUrl = rtrim((string) config('filesystems.disks.r2.url'), '/');
        if (!empty($r2BaseUrl) && Str::startsWith($oldAvatar, $r2BaseUrl)) {
            $r2Key = ltrim(Str::after($oldAvatar, $r2BaseUrl), '/');
            if (!empty($r2Key)) {
                try {
                    if (Storage::disk('r2')->exists($r2Key)) {
                        Storage::disk('r2')->delete($r2Key);
                        Log::info("已删除 R2 旧头像文件: {$r2Key} [User ID: {$user->id}]");
                    }
                } catch (\Throwable $e) {
                    Log::warning("清理 R2 旧头像文件异常 [{$r2Key}]: " . $e->getMessage());
                }
            }
        }

        // 2. 如果旧头像是历史存放在本地 /storage/avatars/ 的文件，也同步清理
        if (Str::startsWith($oldAvatar, '/storage/')) {
            $localPath = Str::replaceFirst('/storage/', '', $oldAvatar);
            try {
                if (Storage::disk('public')->exists($localPath)) {
                    Storage::disk('public')->delete($localPath);
                    Log::info("已删除本地旧头像文件: {$localPath} [User ID: {$user->id}]");
                }
            } catch (\Throwable $e) {
                Log::warning("清理本地旧头像文件异常 [{$localPath}]: " . $e->getMessage());
            }
        }
    }

    /**
     * Delete the user's profile.
     */
    // public function destroy(ProfileDeleteRequest $request): RedirectResponse
    // {
    //     $user = $request->user();

    //     Auth::logout();

    //     $user->delete();

    //     $request->session()->invalidate();
    //     $request->session()->regenerateToken();

    //     return redirect('/');
    // }
}
