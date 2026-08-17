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
        Schema::create('forums', function (Blueprint $table) {
            // 🎯 node_id 既是主键，又是关联 nodes 表的外键 (1:1 关系)
            $table->foreignId('node_id')->primary()->constrained('nodes')->cascadeOnDelete();

            // 🎯 版块特有属性
            $table->boolean('allow_posting')->default(true); // 是否允许发表主题
            $table->string('link_url')->nullable();         // 若节点为外链 (link)，存放目标 URL

            // 🎯 XenForo 统计缓存 (Denormalized Counters)
            $table->unsignedBigInteger('discussion_count')->default(0); // 主题总数 (threads)
            $table->unsignedBigInteger('message_count')->default(0);    // 帖子/回复总数 (posts)

            // 🎯 XenForo 右侧最新动态 (Last Post) 高效缓存
            $table->unsignedBigInteger('last_thread_id')->nullable();
            $table->string('last_thread_title')->nullable();
            $table->unsignedBigInteger('last_post_id')->nullable();
            $table->foreignId('last_post_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('last_post_username')->nullable();
            $table->timestamp('last_post_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forums');
    }
};
