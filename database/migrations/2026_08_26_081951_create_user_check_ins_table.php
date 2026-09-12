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
        Schema::create('user_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('check_in_date')->comment('签到日期 Y-m-d');
            $table->string('type', 20)->default('normal')->comment('类型: normal, make_up');
            $table->unsignedInteger('continuous_days_snapshot')->default(1)->comment('连签快照');
            $table->unsignedInteger('total_days_snapshot')->default(1)->comment('总天数快照');
            $table->unsignedInteger('reward_coins')->default(10)->comment('基础金币');
            $table->unsignedInteger('bonus_coins')->default(0)->comment('里程碑额外金币');
            $table->json('extra_rewards')->nullable()->comment('额外奖励详情快照');
            $table->timestamps();

            $table->unique(['user_id', 'check_in_date']);
            $table->index(['user_id', 'check_in_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_check_ins');
    }
};
