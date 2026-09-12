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
        Schema::create('medals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('medal_categories')->nullOnDelete();

            // 基础标识与展示
            $table->string('code', 64)->unique()->comment('全局唯一编码，如: streak_365, anniversary_5y');
            $table->string('title', 100)->comment('勋章名称');
            $table->string('description', 255)->comment('勋章介绍文案');
            $table->string('condition_text', 255)->comment('获取途径简述（未解锁时展示）');
            $table->string('icon_url', 255)->comment('勋章图片地址');

            // 视觉与等级 (参考 XF Rarity 与 CSS 样式拓展)
            $table->string('rarity', 32)->default('common')->comment('稀有度: common, rare, epic, legendary');
            $table->string('badge_color', 32)->nullable()->comment('徽章主色调HEX或Tailwind颜色类');
            $table->unsignedInteger('trophy_points')->default(10)->comment('成就点数权重 (XenForo核心字段)');

            // 规则引擎与派发策略
            $table->string('award_type', 32)->default('auto')->comment('派发模式: auto(系统自动), manual(人工授予), claimable(手动申领)');
            $table->json('criteria')->nullable()->comment('XF风格规则引擎JSON: {"registered_days":365, "post_count":100}');

            // 状态与权限控制
            $table->boolean('is_hidden')->default(false)->comment('是否为隐藏彩蛋勋章（未获得前不展示规则）');
            $table->boolean('is_active')->default(true)->comment('是否上架');
            $table->unsignedInteger('display_order')->default(0)->comment('展示排序');

            $table->timestamps();

            $table->index(['category_id', 'is_active', 'display_order']);
            $table->index(['award_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medals');
    }
};
