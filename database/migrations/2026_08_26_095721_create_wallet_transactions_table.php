<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();

            // 1. 资产主体关联 (使用 restrictOnDelete 严禁级联删除财务流水)
            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete()
                ->comment('所属钱包ID');

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('所属用户ID');

            // 2. 交易单号与全局防重标识
            $table->string('trx_no', 64)
                ->unique()
                ->comment('系统全局唯一交易流水号 (例: TRX2026082612345678)');

            // 3. 资产与变动类型
            $table->string('currency_type', 32)
                ->default('balance')
                ->comment('资产类型: balance(可用余额), coins(虚拟金币), frozen_balance(冻结金额)');

            $table->string('type', 40)
                ->comment('业务类型: check_in(签到), recharge(充值), consume(消费), withdraw(提现), reward(打赏)');

            $table->tinyInteger('direction')
                ->comment('资金流向: 1-收入(+), -1-支出(-)');

            // 4. 金额与对账快照 (单位: 分或整型金币数)
            $table->bigInteger('amount')->comment('变动绝对值或带符号数值');
            $table->bigInteger('balance_before')->comment('变动前对应资产余额快照');
            $table->bigInteger('balance_after')->comment('变动后对应资产余额快照');

            // 5. 业务源关联 (多态关联: 可关联商品订单、签到表、充值订单等)
            $table->nullableMorphs('source'); // 自动生成 source_type (varchar) 和 source_id (bigint)
            $table->string('reference_id', 64)->nullable()->comment('外部三方支付单号/渠道单号(如微信支付订单号)');

            // 6. 描述与附加元数据
            $table->string('description', 255)->comment('账单展示文案 (如: 连续签到7天礼包奖励)');
            $table->json('metadata')->nullable()->comment('扩展信息 (操作IP、设备终端、风控标记等)');

            $table->timestamps();

            // 7. 高性能查询复合索引
            $table->index(['user_id', 'currency_type', 'created_at'], 'idx_user_currency_timeline');
            $table->index(['wallet_id', 'currency_type', 'created_at'], 'idx_wallet_currency_timeline');
            $table->index(['type', 'created_at'], 'idx_type_created');
            $table->index('reference_id', 'idx_reference_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
