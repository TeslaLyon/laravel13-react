<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. 通用索引：为 videos 表的 slug 字段添加普通索引（加速按 slug 检索）
        Schema::table('videos', function (Blueprint $table) {
            $table->index('slug', 'idx_videos_slug');
        });

        // 2. PostgreSQL 专属高级复合/局部索引优化
        if (DB::getDriverName() === 'pgsql') {
            // A. 按片商查看视频列表的时间轴索引（消除内存慢排序，提升分页性能）
            DB::statement('CREATE INDEX IF NOT EXISTS idx_videos_channel_timeline ON videos (channel_id, COALESCE(release_at, created_at) DESC, id DESC) WHERE deleted_at IS NULL;');

            // B. 带软删除局部条件的全站发布时间轴索引（精准匹配 Eloquent 的 WHERE deleted_at IS NULL）
            DB::statement('CREATE INDEX IF NOT EXISTS idx_videos_timeline_active ON videos (COALESCE(release_at, created_at) DESC, id DESC) WHERE deleted_at IS NULL;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_videos_channel_timeline;');
            DB::statement('DROP INDEX IF EXISTS idx_videos_timeline_active;');
        }

        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex('idx_videos_slug');
        });
    }
};

