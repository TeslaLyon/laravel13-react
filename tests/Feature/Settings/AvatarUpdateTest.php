<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // 🎯 1. 隔离测试环境：每次测试前模拟 public 磁盘
    Storage::fake('public');
});

test('未登录的访客用户无法上传头像', function () {
    $file = UploadedFile::fake()->image('avatar.jpg');

    $response = $this->post(route('profile.avatar.update'), [
        'avatar' => $file,
    ]);

    // 断言重定向到登录页面
    $response->assertRedirect(route('login'));
});

test('登录用户可以成功上传合法格式的头像', function () {
    $user = User::factory()->create();

    // 模拟一张 400x400 的合规 JPG 图片 (100KB)
    $file = UploadedFile::fake()->image('my-avatar.jpg', 400, 400)->size(100);

    $response = $this->actingAs($user)
        ->from(route('profile.avatar.edit'))
        ->post(route('profile.avatar.update'), [
            'avatar' => $file,
        ]);

    // 1. 断言重定向回头像设置页面
    $response->assertRedirect(route('profile.avatar.edit'));
    $response->assertSessionHasNoErrors();

    // 2. 重新从数据库拉取用户最新数据
    $user->refresh();

    // 3. 断言数据库中的 avatar 路径格式正确
    expect($user->avatar)->not->toBeNull()
        ->and($user->avatar)->toStartWith('/storage/avatars/');

    // 4. 断言磁盘上真实存在该文件
    $relativePath = str_replace('/storage/', '', $user->avatar);
    Storage::disk('public')->assertExists($relativePath);
});

test('上传非图片文件或伪装的脚本文件会被严格拒绝', function () {
    $user = User::factory()->create();

    // 🎯 安全测试：模拟一个伪装成图片的 PHP 后门脚本
    $maliciousFile = UploadedFile::fake()->create('shell.php', 10, 'application/x-php');

    $response = $this->actingAs($user)
        ->post(route('profile.avatar.update'), [
            'avatar' => $maliciousFile,
        ]);

    // 断言验证未通过，且 Session 包含 avatar 字段的错误信息
    $response->assertSessionHasErrors(['avatar']);

    // 断言数据库未发生任何变更
    expect($user->fresh()->avatar)->toBeNull();
});

test('上传未允许的图片类型(如带有XSS风险的SVG)会被拒绝', function () {
    $user = User::factory()->create();

    // 🎯 安全测试：SVG 容易潜藏 <script> 标签造成跨站脚本攻击
    $svgFile = UploadedFile::fake()->create('vector_xss.svg', 20, 'image/svg+xml');

    $response = $this->actingAs($user)
        ->post(route('profile.avatar.update'), [
            'avatar' => $svgFile,
        ]);

    $response->assertSessionHasErrors(['avatar']);
});

test('上传超过 2MB 大小的文件会被拒绝', function () {
    $user = User::factory()->create();

    // 🎯 安全测试：模拟 2.5MB (2560 KB) 的大图，超出 max:2048 限制
    $largeFile = UploadedFile::fake()->image('huge-photo.jpg')->size(2560);

    $response = $this->actingAs($user)
        ->post(route('profile.avatar.update'), [
            'avatar' => $largeFile,
        ]);

    $response->assertSessionHasErrors(['avatar']);
});

test('更换头像时会自动从磁盘删除旧的头像文件', function () {
    $user = User::factory()->create();

    // 1. 先上传第一张头像
    $oldFile = UploadedFile::fake()->image('old-avatar.jpg');
    $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => $oldFile]);

    $oldAvatarPath = str_replace('/storage/', '', $user->fresh()->avatar);
    Storage::disk('public')->assertExists($oldAvatarPath);

    // 2. 上传第二张新头像
    $newFile = UploadedFile::fake()->image('new-avatar.jpg');
    $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => $newFile]);

    $newAvatarPath = str_replace('/storage/', '', $user->fresh()->avatar);

    // 🎯 核心断言：旧头像已被彻底删除，新头像安全存在
    Storage::disk('public')->assertMissing($oldAvatarPath);
    Storage::disk('public')->assertExists($newAvatarPath);
    expect($oldAvatarPath)->not->toEqual($newAvatarPath);
});
