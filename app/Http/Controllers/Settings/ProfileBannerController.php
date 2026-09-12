<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;

class ProfileBannerController extends Controller
{
    /**
     * 显示修改横幅设置页
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/banner', [
            'status' => session('status'),
        ]);
    }

    /**
     * 更新频道横幅
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'banner' => [
                'required',
                File::image()
                    ->types(['jpeg', 'png', 'jpg', 'webp'])
                    ->max(6 * 1024), // 限制 6MB
            ],
        ]);

        $user = $request->user();


        // 1. 删除旧横幅文件（避免孤儿文件占用磁盘空间）
        if ($user->banner_url && str_starts_with($user->banner_url, '/storage/')) {
            $oldPath = str_replace('/storage/', 'public/', $user->banner_url);
            if (Storage::exists($oldPath)) {
                Storage::delete($oldPath);
            }
        }

        // 2. 存储裁剪后的新横幅
        $path = $request->file('banner')->store('banners', 'public');
        
        $publicUrl = Storage::url($path);

        // 3. 更新数据库字段
        $user->update([
            'banner_url' => $publicUrl,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' =>'横幅更新成功。']);

        return to_route('profile.banner.edit');
    }

    /**
     * 删除频道横幅并恢复默认
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->banner_url && str_starts_with($user->banner_url, '/storage/')) {
            $oldPath = str_replace('/storage/', 'public/', $user->banner_url);
            if (Storage::exists($oldPath)) {
                Storage::delete($oldPath);
            }
        }

        $user->update([
            'banner_url' => null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' =>'横幅已删除。']);

        return to_route('profile.banner.edit')->with('status', 'banner-deleted');
    }
}
