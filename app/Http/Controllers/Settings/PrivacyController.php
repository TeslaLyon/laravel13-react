<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PrivacyController extends Controller
{
    /**
     * 显示用户的隐私设置页面
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $settings = [
            'profile_visibility' => $user->getPrivacySetting('profile_visibility', 'public'),
            'video_favorites' => $user->getPrivacySetting('video_favorites', 'public'),
            'liked_videos' => $user->getPrivacySetting('liked_videos', 'followers'),
        ];

        return Inertia::render('settings/privacy', [
            'settings' => $settings,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * 更新用户的隐私设置
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'profile_visibility' => ['required', 'string', 'in:public,followers,mutual,private'],
            'video_favorites' => ['nullable', 'string', 'in:public,followers,mutual,private'],
            'liked_videos' => ['nullable', 'string', 'in:public,followers,mutual,private'],
        ]);

        $user = $request->user();

        $currentSettings = $user->privacySetting?->settings ?? [
            'video_favorites' => 'public',
            'actor_favorites' => 'public',
            'liked_videos' => 'followers',
        ];

        $newSettings = array_merge($currentSettings, array_filter($validated, fn($val) => $val !== null));

        $user->privacySetting()->updateOrCreate(
            ['user_id' => $user->id],
            ['settings' => $newSettings]
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => '隐私设置已保存']);

        return back()->with('status', 'privacy-updated');
    }
}

