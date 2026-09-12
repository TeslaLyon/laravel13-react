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
        Schema::table('user_check_in_stats', function (Blueprint $table) {
            // 防御性判断：仅在字段不存在时执行新增
            if (!Schema::hasColumn('user_check_in_stats', 'total_make_up_cards_earned')) {
                $table->unsignedInteger('total_make_up_cards_earned')
                    ->default(0)
                    ->comment('历史累计获得的补签卡总数');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_check_in_stats', function (Blueprint $table) {
            if (Schema::hasColumn('user_check_in_stats', 'total_make_up_cards_earned')) {
                $table->dropColumn('total_make_up_cards_earned');
            }
        });
    }
};
