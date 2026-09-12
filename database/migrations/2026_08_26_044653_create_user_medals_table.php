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
        Schema::create('user_medals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('medal_id')->constrained('medals')->cascadeOnDelete();

            // 颁奖元数据 (XenForo 手动/自动颁奖核心追踪)
            $table->foreignId('awarded_by_user_id')->nullable()->constrained('users')->nullOnDelete()->comment('颁发者ID(为 null 则为系统自动派发)');
            $table->string('award_reason', 255)->nullable()->comment('授予理由/颁奖词');

            // 佩戴与主页展示控制
            $table->boolean('is_worn')->default(false)->comment('是否佩戴在个人主页/头像旁');
            $table->unsignedTinyInteger('wear_slot')->nullable()->comment('佩戴槽位编号 (如允许最多佩戴3个)');

            $table->timestamp('unlocked_at')->useCurrent()->comment('解锁/授予时间');
            $table->timestamps();

            // 唯一约束：同种勋章每人只发一份
            $table->unique(['user_id', 'medal_id']);
            $table->index(['user_id', 'is_worn']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_medals');
    }
};
