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
        Schema::create('tag_video', function (Blueprint $table) {
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            // 🎯 1. 设置联合主键：代替默认的 $table->id()
            // 作用 A：天然在数据库层面防止同一个视频重复添加同一个 tag
            // 作用 B：天然提供 (video_id, tag_id) 的复合索引，提升按 video_id 查询标签的效率
            $table->primary(['video_id', 'tag_id']);

            // 🎯 2. 额外给 tag_id 加单独索引
            // 作用：提升反向查询（按 tag_id 查找所有视频）时的检索速度
            $table->index('tag_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tag_video');
    }
};
