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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('视频原始标题');
            $table->string('name_zh')->default('')->comment('视频中文标题');
            $table->string('slug')->nullable(false)->comment('视频slug');
            $table->string('source_uuid')->unique()->comment('视频源uuid');
            $table->text('list_img')->nullable()->comment('列表图片');
            $table->text('preview')->nullable()->comment('视频预览:1-视频;2-动态图;3-多张图片');
            $table->timestamp('release_at')->nullable()->comment('视频发布时间');
            $table->text('video_code')->unique()->comment('视频编码');
            $table->unsignedTinyInteger('channel_id')->nullable()->comment('片商 id');
            $table->boolean('is_4k')->default(false)->comment('是否为 4k 画质');
            $table->boolean('is_vr')->default(false)->comment('是否为 VR');
            $table->unsignedTinyInteger('sexual_orientation')->default(0)->comment('性取向：[0-未知;1-异性;2-女同性恋]');
            $table->boolean('is_trans_model')->default(false)->comment('演员是否为变性人');
            $table->string('max_quality', 20)->default('')->comment('最高画质，例：2160p,1080p');
            $table->unsignedTinyInteger('status')->default(value: 0)->comment('状态：[0-下架;1-上架]');
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('favorites_count')->default(0);
            $table->timestamps();

            $table->softDeletes();

            // --- 高级复合索引优化 ---
            // 场景 1: 前台经常查询 "未删除" 且 "已上架" 的视频
            $table->index(['status', 'deleted_at'], 'idx_status_deleted');

            // 场景 2: 根据片商查找 "未删除" 的视频
            $table->index(['channel_id', 'deleted_at'], 'idx_channel_deleted');

            // 场景 3: 按最新创建时间查找 "未删除" 的视频 (如果首页常用)
            $table->index(['created_at', 'deleted_at'], 'idx_created_deleted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
