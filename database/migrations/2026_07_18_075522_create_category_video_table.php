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
        Schema::create('category_video', function (Blueprint $table) {
            // 声明外键并追加级联删除
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            // 🎯 1. 设置联合主键（替代 $table->id()）
            // - 数据库层强制防重，防止同一个视频被多次绑定相同分类
            // - 天然提供 (video_id, category_id) 的复合索引，优化按 video_id 查询分类的速度
            $table->primary(['video_id', 'category_id']);

            // 🎯 2. 为 category_id 建立索引
            // - 大幅提升反向查询速度：根据分类 ID 快速检索出所有关联视频
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_video');
    }
};
