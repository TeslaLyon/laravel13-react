<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('用户组标识, 如: administrative, moderator, contributor');
            $table->string('title', 60)->comment('用户组显示名称, 如: 管理团队, 认证贡献者, 尊贵VIP');

            // 🎯 XF 2.3 经典设计：用户名样式与显示优先级
            $table->string('username_css', 255)->nullable()->comment('用户名自定义 CSS (如: color: #38bdf8; font-weight: bold;)');
            $table->unsignedInteger('display_style_priority')->default(0)->index()->comment('显示样式优先级 (多组重叠时取最高优先级渲染颜色与主横幅)');

            // 🎯 XF 2.3 经典设计：组横幅 Ribbon 体系
            $table->string('banner_text', 60)->nullable()->comment('横幅显示文字, 如: Contributor, Staff Member');
            $table->string('banner_bg_color', 30)->default('#2563eb')->comment('横幅背景色/十六进制颜色值');
            $table->string('banner_text_color', 30)->default('#ffffff')->comment('横幅文字颜色');
            $table->string('banner_icon', 100)->nullable()->comment('横幅前置图标 (如: lucide:shield-check)');

            // 系统内置与管理标志
            $table->boolean('is_system')->default(false)->comment('是否为系统内置组 (内置组不可随意删除)');
            $table->boolean('is_staff')->default(false)->comment('是否为管理团队成员');
            $table->boolean('is_banned')->default(false)->comment('是否为封禁惩罚组');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_groups');
    }
};
