<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 为 PostgreSQL 数据库的 videos 表添加 (COALESCE(release_at, created_at) DESC, id DESC) 函数表达式复合索引
        // 支持前台视频列表极速按最新发布时间倒序分页拉取，兼顾未标注 release_at 的视频
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_videos_published_timeline ON videos (COALESCE(release_at, created_at) DESC, id DESC);');
        } else {
            // MySQL 等环境降级使用 release_at 索引
            Schema::table('videos', function ($table) {
                $table->index(['release_at', 'id'], 'idx_videos_release_at_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_videos_published_timeline;');
        } else {
            Schema::table('videos', function ($table) {
                $table->dropIndex('idx_videos_release_at_id');
            });
        }
    }
};

