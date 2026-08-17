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
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            // 核心魔法：这一行会自动生成 feedbackable_type (字符串) 和 feedbackable_id (大整数) 两个字段
            $table->morphs('feedbackable');

            // 提交反馈的用户 (允许未登录用户反馈则设为 nullable)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // 反馈类型：playback_issue, content_issue 等
            $table->string('type', 50);
            // 补充说明
            $table->text('content')->nullable();
            // 处理状态：pending (待处理), processing (处理中), resolved (已解决)
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
