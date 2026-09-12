<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_check_in_card_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->comment('所属用户ID');

            // 变动数量 (正数为获取: +1, 负数为消耗: -1)
            $table->integer('change_count')->comment('变动张数');
            $table->unsignedInteger('balance_before')->comment('变动前卡片张数快照');
            $table->unsignedInteger('balance_after')->comment('变动后卡片张数快照');

            // 业务动作类型: milestone_reward(连签奖励), make_up_checkin(补签消耗), admin_grant(后台补发), shop_buy(商城兑换)
            $table->string('action', 32)->index()->comment('变动原因类型');
            $table->string('description', 255)->comment('明细描述 (如: 连续签到7天奖励、补签 2026-08-01 打卡)');

            // 关联源 (多态关联: 关联到的某次签到流水或补签记录)
            $table->nullableMorphs('source');

            $table->timestamps();

            // 索引优化
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_check_in_card_logs');
    }
};
