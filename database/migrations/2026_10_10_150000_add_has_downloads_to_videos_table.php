<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->boolean('has_downloads')
                ->default(false)
                ->index()
                ->comment('是否拥有下载资源')
                ->after('has_zh_subtitles');
        });

        // 1. 自动回填：video_downloads 表中已有有效下载项的视频
        DB::statement("
            UPDATE videos
            SET has_downloads = true
            WHERE id IN (
                SELECT DISTINCT video_id FROM video_downloads WHERE status = 1
            )
        ");

        // 2. 自动回填：video_details 表中已有有效 download_info JSON 数据的视频
        DB::statement("
            UPDATE videos
            SET has_downloads = true
            WHERE id IN (
                SELECT video_id FROM video_details 
                WHERE download_info IS NOT NULL 
                  AND download_info != '' 
                  AND download_info != '[]' 
                  AND download_info != 'null'
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex(['has_downloads']);
            $table->dropColumn('has_downloads');
        });
    }
};

