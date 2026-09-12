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
        Schema::create('forum_notices', function (Blueprint $table) {
            $table->id();
            // 关联所属版块 (为 null 时表示全站所有版块通用的全局公告)
            $table->foreignId('node_id')->nullable()->constrained('nodes')->nullOnDelete();

            // 公告内容
            $table->string('title');                        // 公告标题
            $table->text('content')->nullable();            // 公告详细描述 (支持文本或短 HTML)
            $table->string('type', 20)->default('info');     // 视觉类型: info (信息), warning (警示), danger (紧急), success (活动)
            $table->string('link_url')->nullable();         // 点击跳转目标 (可为帖子链接或外链)
            $table->string('link_text', 50)->nullable();    // 按钮文案 (如: "查看版规"、"立即参与")

            // 控制开关与交互
            $table->boolean('is_active')->default(true);     // 是否启用
            $table->boolean('is_dismissible')->default(true);// 是否允许用户手动点击叉号隐藏
            $table->unsignedInteger('display_order')->default(0); // 排序权重 (越大越靠前)

            // 发布时间范围控制 (排期支持)
            $table->timestamp('starts_at')->nullable();     // 生效开始时间
            $table->timestamp('ends_at')->nullable();       // 生效结束时间

            // 发布者审计
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // 复合索引：加速版块公告按状态与权重的查询
            $table->index(['node_id', 'is_active', 'display_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forum_notices');
    }
};
