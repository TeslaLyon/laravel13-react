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
        Schema::create('video_subtitles', function (Blueprint $table) {
            $table->id();
            // 关联的视频ID
            $table->foreignId('video_id')->constrained()->onDelete('cascade');

            // 上传者的用户ID（如果是官方直接添加的，可以允许为空）
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');

            $table->string('title')->nullable()->comment('字幕标题/说明');

            // 字幕语言，默认为 'zh' (中文)
            $table->string('language', 10)->default('zh')->comment('字幕语言');

            // 字幕文件在服务器或 OSS 上的存储路径
            $table->string('file_path')->nullable()->comment('本地文件路径或外部链接');
            $table->boolean('is_external')->default(false)->comment('是否为外部链接');

            // 字幕文件格式 (srt, ass, vtt 等)
            $table->string('format', 10)->nullable()->comment('字幕格式');

            // 【核心设计】状态字段：
            // pending: 用户刚上传，待审核
            // approved: 审核通过，对外展示并提供下载
            // rejected: 审核驳回
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->comment('审核状态');

            // 记录文件大小，便于前端展示
            $table->unsignedBigInteger('file_size')->nullable()->comment('文件大小(字节)');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_subtitles');
    }
};
