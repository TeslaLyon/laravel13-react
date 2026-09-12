<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();

            // 1. 用户唯一绑定 (1:1 关系，财务表推荐 restrictOnDelete 防止误删用户导致资金数据丢失)
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->restrictOnDelete()
                ->comment('用户ID (全局唯一)');

            // 2. 核心资金 (单位：分，默认 0)
            $table->bigInteger('balance')->default(0)->comment('当前可用余额(分)');
            $table->bigInteger('frozen_balance')->default(0)->comment('冻结金额(分，如提现审核中/预扣款)');
            $table->unsignedBigInteger('coins')->default(0)->comment('当前可用虚拟金币总额');
            $table->unsignedBigInteger('frozen_coins')->default(0)->comment('冻结虚拟金币(如打赏预扣/交易锁定)');

            // 3. 累计统计字段 (用于风控、用户画像与等级成长)
            $table->bigInteger('total_recharge')->default(0)->comment('累计充值总额(分)');
            $table->bigInteger('total_withdrawn')->default(0)->comment('累计提现总额(分)');
            $table->bigInteger('total_spent')->default(0)->comment('累计消费总额(分)');
            $table->unsignedBigInteger('total_earned_coins')->default(0)->comment('历史累计获得金币总额');

            // 4. 账户状态管控
            $table->unsignedTinyInteger('status')->default(1)->index()->comment('状态: 1-正常(active), 2-已冻结(frozen), 3-已禁用(disabled)');
            $table->string('freeze_reason')->nullable()->comment('账户冻结或异常原因说明');

            // 5. 高并发与数据完整性
            $table->unsignedBigInteger('version')->default(0)->comment('乐观锁版本号，每次变动自增 1');
            $table->string('checksum', 64)->nullable()->comment('防篡改校验和 (HMAC-SHA256: user_id + balance + coins + version)');

            // 6. 审计与时间戳
            $table->timestamp('last_activity_at')->nullable()->comment('最后一次资金变动时间');
            $table->timestamps();
        });

        // 7. 数据库级安全兜底：PostgreSQL / MySQL 8.0+ Check 约束 (严格禁止出现负资产)
        DB::statement('ALTER TABLE wallets ADD CONSTRAINT check_balance_non_negative CHECK (balance >= 0);');
        DB::statement('ALTER TABLE wallets ADD CONSTRAINT check_frozen_balance_non_negative CHECK (frozen_balance >= 0);');
        DB::statement('ALTER TABLE wallets ADD CONSTRAINT check_coins_non_negative CHECK (coins >= 0);');
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
