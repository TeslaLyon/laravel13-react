<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_title_ladders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title', 60)->comment('阶梯头衔名称, 如: Well-known member, 社区宗师');

            // 🎯 核心门槛：综合总积分门槛
            $table->decimal('min_credits', 12, 2)->default(0.00)->comment('晋升所需最低综合总积分');
            $table->unsignedInteger('min_post_count')->default(0)->comment('晋升所需最低发帖量 (辅助校验)');

            // 视觉样式扩展
            $table->string('text_color', 30)->nullable()->comment('头衔文字颜色, 如: #38bdf8');
            $table->string('bg_color', 30)->nullable()->comment('头衔标签底色 (可选)');
            $table->string('icon', 100)->nullable()->comment('头衔图标标识');

            $table->boolean('is_active')->default(true)->comment('是否启用该等级');
            $table->timestamps();

            // 索引优化
            $table->index(['is_active', 'min_credits'], 'idx_active_min_credits');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_title_ladders');
    }
};
