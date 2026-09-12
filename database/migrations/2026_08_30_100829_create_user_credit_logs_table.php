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
        Schema::create('user_credit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index()->comment('接收积分变动的用户 ID');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作人 ID (系统自动变更则为 null)');

            // 变动分类与变动维度
            $table->string('action', 50)->index()->comment('行为标识, 如: post_created, thread_digest, award_prestige, violation_penalty');
            $table->string('field', 30)->index()->comment('变动的指标字段, 如: prestige_points, enthusiasm_points, total_credits');
            $table->decimal('change_amount', 10, 2)->comment('变动数值 (正数为增加，负数为扣除)');
            $table->decimal('after_value', 12, 2)->comment('变动后的该指标即时数值');
            $table->decimal('current_total_credits', 12, 2)->comment('变动后的综合总积分快照');

            // 描述与溯源拓展
            $table->string('remark', 255)->nullable()->comment('变动说明 / 关联主题标题 / 惩处原因');
            $table->string('source_type', 100)->nullable()->comment('关联来源模型');
            $table->unsignedBigInteger('source_id')->nullable()->comment('关联来源 ID');
            $table->ipAddress('ip_address')->nullable()->comment('触发操作时的客户端 IP');

            $table->timestamp('created_at')->useCurrent()->index()->comment('变动发生时间');

            // 索引与外键约束
            $table->index(['source_type', 'source_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('operator_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_credit_logs');
    }
};
