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
            // 新增 has_zh_subtitles 字段，默认为 false，放置在方便查看的位置
            $table->boolean('has_zh_subtitles')
                ->default(false)
                ->comment('是否有中文字幕')
                ->after('name_zh');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('has_zh_subtitles');
        });
    }
};
