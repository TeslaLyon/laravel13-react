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
            $table->char('country', 2)->nullable(true)->after('channel_id')->comment('国家/地区 ISO 3166-1 两位代码');
            $table->index(['country', 'created_at'], 'idx_videos_country_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex('idx_videos_country_created');
            $table->dropColumn('country');
        });
    }
};
