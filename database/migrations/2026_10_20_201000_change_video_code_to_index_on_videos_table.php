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
        Schema::table('videos', function (Blueprint $table) {
            // 移除全局唯一约束，支持多版本/跨片商同番号入库
            $table->dropUnique('videos_video_code_unique');
            // 保留普通索引，保障毫秒级搜索和筛选性能
            $table->index('video_code', 'videos_video_code_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex('videos_video_code_index');
            $table->unique('video_code', 'videos_video_code_unique');
        });
    }
};

