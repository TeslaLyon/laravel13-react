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
        Schema::create('threads', function (Blueprint $table) {
            $table->id();
            // 🎯 关联所属节点 (对应 xf_thread.node_id)
            $table->foreignId('node_id')->constrained('nodes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('username'); // 作者用户名冗余

            // 主题属性
            $table->string('title');                          // 标题
            $table->string('slug')->nullable()->index();      // URL 别名
            $table->string('prefix', 30)->nullable();         // 前缀 (如: [求助])

            // 状态控制
            $table->boolean('sticky')->default(false);        // 是否置顶 (XenForo 命名为 sticky)
            $table->boolean('discussion_open')->default(true); // 是否允许回复 (XenForo 命名)
            $table->string('discussion_state', 20)->default('visible'); // visible, moderated, deleted

            // 统计指标
            $table->unsignedBigInteger('view_count')->default(0);   // 浏览数
            $table->unsignedBigInteger('reply_count')->default(0);  // 回复数
            $table->unsignedBigInteger('first_post_id')->nullable(); // 1 楼正文 post_id

            // 最新回复缓存
            $table->unsignedBigInteger('last_post_id')->nullable();
            $table->foreignId('last_post_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('last_post_username')->nullable();
            $table->timestamp('last_post_date')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // 高频查询复合索引 (节点内按置顶 + 最新活动时间倒序)
            $table->index(['node_id', 'sticky', 'last_post_date']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('threads');
    }
};
