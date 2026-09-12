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
        Schema::create('user_check_in_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->date('last_check_in_date')->nullable()->comment('最后签到日期');
            $table->unsignedInteger('continuous_days')->default(0)->comment('当前连签天数');
            $table->unsignedInteger('longest_continuous_days')->default(0)->comment('历史最长连签天数');
            $table->unsignedInteger('total_days')->default(0)->comment('累计签到总天数');
            $table->unsignedInteger('make_up_cards')->default(0)->comment('拥有补签卡数');
            $table->unsignedInteger('month_make_up_count')->default(0)->comment('当月已补签次数');
            $table->timestamps();

            $table->index(['continuous_days']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_check_in_stats');
    }
};
