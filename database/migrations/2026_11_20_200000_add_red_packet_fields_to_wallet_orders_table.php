<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * 运行迁移：为 wallet_orders 表增加支付宝口令红包及后台人工审核字段
     */
    public function up(): void
    {
        Schema::table('wallet_orders', function (Blueprint $table) {
            $table->string('red_packet_code', 128)
                ->nullable()
                ->index()
                ->after('payment_method')
                ->comment('支付宝口令红包口令');

            $table->string('admin_remark', 255)
                ->nullable()
                ->after('subject')
                ->comment('管理员审核备注/驳回原因');

            $table->foreignId('reviewed_by')
                ->nullable()
                ->after('paid_at')
                ->constrained('users')
                ->nullOnDelete()
                ->comment('审核管理员ID');

            $table->timestamp('reviewed_at')
                ->nullable()
                ->after('reviewed_by')
                ->comment('人工审核操作时间');
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::table('wallet_orders', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['red_packet_code', 'admin_remark', 'reviewed_by', 'reviewed_at']);
        });
    }
};
