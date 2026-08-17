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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('username'); // 发帖人用户名冗余

            // 正文与楼层
            $table->text('message');                           // XenForo 命名为 message (支持 BBCode/HTML/Markdown)
            $table->unsignedInteger('position')->default(0);  // 楼层序号 (0 = 1楼/主帖正文)
            $table->boolean('is_first_post')->default(false);  // 快捷区分 1 楼标志

            // 状态与互动
            $table->string('message_state', 20)->default('visible'); // visible, moderated, deleted
            $table->integer('reaction_score')->default(0);          // 净获赞/表态分数 (XenForo reaction_score)

            // PostgreSQL 18 原生 inet 类型记录 IP
            $table->ipAddress('ip_address')->nullable();

            // 编辑历史
            $table->unsignedInteger('edit_count')->default(0);
            $table->timestamp('edited_at')->nullable();
            $table->foreignId('edited_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // 索引优化
            $table->index(['thread_id', 'position']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
