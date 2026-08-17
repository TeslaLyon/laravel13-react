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
     * 🎯 保存裁剪后的新头像
     */
    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ]);

        $user = $request->user();

        // 自动清理旧头像文件
        if ($user->avatar && Str::startsWith($user->avatar, '/storage/')) {
            $oldPath = Str::replaceFirst('/storage/', '', $user->avatar);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        // 保存新文件
        $path = $request->file('avatar')->store('avatars', 'public');

        // 更新数据库
        $user->update([
            'avatar' => Storage::url($path),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Avatar updated successfully.')]);

        return to_route('profile.avatar.edit');
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
