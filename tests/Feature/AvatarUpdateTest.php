<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 每次测试开始前执行的环境初始化
     */
    protected function setUp(): void
    {
        parent::setUp();
        // 🎯 隔离磁盘环境：模拟 public 存储空间
        Storage::fake('public');
    }

    /**
     * 场景一：未登录用户（访客）无法上传头像
     */
    public function test_guest_cannot_upload_avatar(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->post(route('profile.avatar.update'), [
            'avatar' => $file,
        ]);

        // 断言重定向至登录页
        $response->assertRedirect(route('login'));
    }

    /**
     * 场景二：登录用户成功上传合法头像并更新数据与存储
     */
    public function test_authenticated_user_can_upload_valid_avatar(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('avatar.jpg', 400, 400)->size(150);

        $response = $this->actingAs($user)
            ->from(route('profile.avatar.edit'))
            ->post(route('profile.avatar.update'), [
                'avatar' => $file,
            ]);

        // 1. 断言重定向与无验证错误
        $response->assertRedirect(route('profile.avatar.edit'));
        $response->assertSessionHasNoErrors();

        // 2. 校验数据库更新
        $user->refresh();
        $this->assertNotNull($user->avatar);
        $this->assertStringStartsWith('/storage/avatars/', $user->avatar);

        // 3. 校验文件在 public 磁盘真实存在
        $relativePath = str_replace('/storage/', '', $user->avatar);
        Storage::disk('public')->assertExists($relativePath);
    }

    /**
     * 场景三：上传伪装为脚本的恶意文件（如 WebShell）时被拦截
     */
    public function test_malicious_script_upload_is_rejected(): void
    {
        $user = User::factory()->create();
        // 模拟攻击者上传 PHP 脚本
        $maliciousFile = UploadedFile::fake()->create('exploit.php', 10, 'application/x-php');

        $response = $this->actingAs($user)->post(route('profile.avatar.update'), [
            'avatar' => $maliciousFile,
        ]);

        // 断言验证失败并返回字段错误
        $response->assertSessionHasErrors(['avatar']);
        $this->assertNull($user->fresh()->avatar);
    }

    /**
     * 场景四：上传包含 XSS 攻击风险的 SVG 矢量图被拦截
     */
    public function test_svg_image_upload_is_rejected(): void
    {
        $user = User::factory()->create();
        $svgFile = UploadedFile::fake()->create('vector_xss.svg', 20, 'image/svg+xml');

        $response = $this->actingAs($user)->post(route('profile.avatar.update'), [
            'avatar' => $svgFile,
        ]);

        $response->assertSessionHasErrors(['avatar']);
        $this->assertNull($user->fresh()->avatar);
    }

    /**
     * 场景五：上传超过体积限制（如 > 2MB / 2048KB）的文件被拦截
     */
    public function test_oversized_image_upload_is_rejected(): void
    {
        $user = User::factory()->create();
        // 模拟 2.5MB (2560 KB) 超限文件
        $oversizedFile = UploadedFile::fake()->image('huge.jpg')->size(2560);

        $response = $this->actingAs($user)->post(route('profile.avatar.update'), [
            'avatar' => $oversizedFile,
        ]);

        $response->assertSessionHasErrors(['avatar']);
        $this->assertNull($user->fresh()->avatar);
    }

    /**
     * 场景六：用户更换新头像时，旧头像文件自动从磁盘彻底清理
     */
    public function test_old_avatar_file_is_deleted_when_updating_new_avatar(): void
    {
        $user = User::factory()->create();

        // 第一次上传头像
        $oldFile = UploadedFile::fake()->image('old_avatar.jpg');
        $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => $oldFile]);
        $oldAvatarPath = str_replace('/storage/', '', $user->fresh()->avatar);
        Storage::disk('public')->assertExists($oldAvatarPath);

        // 第二次更换为新头像
        $newFile = UploadedFile::fake()->image('new_avatar.jpg');
        $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => $newFile]);
        $newAvatarPath = str_replace('/storage/', '', $user->fresh()->avatar);

        // 🎯 核心断言：旧文件被删除，新文件正常留存
        Storage::disk('public')->assertMissing($oldAvatarPath);
        Storage::disk('public')->assertExists($newAvatarPath);
        $this->assertNotEquals($oldAvatarPath, $newAvatarPath);
    }
}
