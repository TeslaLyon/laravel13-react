<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * 运行迁移：创建充值订单表
     */
    public function up(): void
    {
        Schema::create('wallet_orders', function (Blueprint $table) {
            $table->id();

            // 关联系统用户
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('用户ID');

            // 订单标识
            $table->string('order_no', 64)->unique()->comment('本地系统商户订单号');
            $table->string('gateway_order_id', 64)->nullable()->index()->comment('第三方支付网关订单号');
            $table->string('gateway_pay_id', 64)->nullable()->comment('网关收银流水号');

            // 支付配置
            $table->string('payment_method', 32)->comment('支付方式: wechat, alipay');

            // 金额计算（🌟 核心：以“分”为整数单位存储）
            $table->unsignedBigInteger('amount')->comment('应付金额（分）');
            $table->unsignedBigInteger('really_amount')->default(0)->comment('实付金额（分）');

            // 状态机: 0-待支付, 1-支付成功, 2-已取消/已失效
            $table->unsignedTinyInteger('status')->default(0)->index()->comment('订单状态: 0=待支付, 1=已完成, 2=已关闭');

            // 凭证与链接
            $table->text('pay_url')->nullable()->comment('支付二维码/收银台跳转URL');
            $table->string('subject', 128)->nullable()->comment('商品标题/充值说明');

            // 时间戳与归档数据
            $table->timestamp('paid_at')->nullable()->comment('支付完成到账时间');
            $table->json('raw_callback')->nullable()->comment('第三方异步通知原始报文');
            $table->timestamps();
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_orders');
    }
};
