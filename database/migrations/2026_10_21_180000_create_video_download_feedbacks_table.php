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
        Schema::create('video_download_feedbacks', function (Blueprint $table) {
            $table->id();

            // 关联的视频 ID
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();

            // 关联的具体下载项 ID（如某条具体的磁力/网盘链接）
            $table->foreignId('video_download_id')->constrained()->cascadeOnDelete();

            // 提交反馈的用户 ID（可为空）
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // 反馈问题类型 (1-死链无速度, 2-内容不符, 3-密码错误, 4-清晰度不符, 5-恶意广告, 6-其他)
            $table->unsignedTinyInteger('type')
                ->index()
                ->comment('反馈类型: 1-死链无速度, 2-内容不符, 3-密码错误, 4-清晰度不符, 5-恶意广告, 6-其他');

            // 详细问题描述
            $table->text('content')->nullable()->comment('用户补充说明');

            // 处理状态: 1-待处理(pending), 2-已处理(resolved), 3-已忽略(ignored)
            $table->unsignedTinyInteger('status')
                ->default(1)
                ->index()
                ->comment('处理状态: 1-待处理, 2-已处理, 3-已忽略');

            // 管理员后台处理备注
            $table->text('admin_notes')->nullable()->comment('管理员备注');

            $table->timestamps();

            // 组合索引优化后台与详情页状态查询
            $table->index(['video_download_id', 'status']);
            $table->index(['video_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_download_feedbacks');
    }
};

