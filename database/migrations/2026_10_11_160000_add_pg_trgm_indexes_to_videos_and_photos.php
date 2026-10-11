<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. 在 PostgreSQL 中启用 pg_trgm 扩展（三元组模糊索引支持）
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm;');

        // 2. 为 videos 表的原始标题 (name) 与中文标题 (name_zh) 创建 GIN 倒排三元组索引
        // 支持对 ILIKE '%keyword%' 模糊查询进行毫秒级索引快速检索
        DB::statement('CREATE INDEX IF NOT EXISTS idx_videos_name_trgm ON videos USING gin (name gin_trgm_ops);');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_videos_name_zh_trgm ON videos USING gin (name_zh gin_trgm_ops);');

        // 3. 为 photos 表的原始标题 (name) 与中文标题 (name_zh) 创建 GIN 倒排三元组索引
        DB::statement('CREATE INDEX IF NOT EXISTS idx_photos_name_trgm ON photos USING gin (name gin_trgm_ops);');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_photos_name_zh_trgm ON photos USING gin (name_zh gin_trgm_ops);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_videos_name_trgm;');
        DB::statement('DROP INDEX IF EXISTS idx_videos_name_zh_trgm;');
        DB::statement('DROP INDEX IF EXISTS idx_photos_name_trgm;');
        DB::statement('DROP INDEX IF EXISTS idx_photos_name_zh_trgm;');
    }
};

