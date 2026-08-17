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
        Schema::create('actor_video', function (Blueprint $table) {
            // 🎯 1. 声明外键并追加级联删除，防止数据孤岛与脏数据
            $table->foreignId('actor_id')->constrained('actors')->cascadeOnDelete();
            $table->foreignId('video_id')->constrained('videos')->cascadeOnDelete();

            // 🎯 3. 定义复合主键
            // 作用 A：防重，确保一个演员在同一部视频中只有一条关联
            // 作用 B：优化按 actor_id 查询视频的性能
            $table->primary(['actor_id', 'video_id']);

            // 🎯 4. 显式追加 video_id 单独索引
            // 作用：优化反向查询（按 video_id 查找关联的所有演员）
            $table->index('video_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actor_video');
    }
};
