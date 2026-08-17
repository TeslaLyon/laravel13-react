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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->comment('作者用户 ID (关联 users.id)')
                ->constrained('users') // 自动关联 users 表的 id 字段
                ->cascadeOnDelete();   // 用户被删除时，自动清理其文章

            // 文章关键信息
            $table->string('slug')->unique()->comment('URL 别名，用于伪静态链接');
            $table->string('title')->comment('文章标题');
            $table->string('excerpt', 500)->nullable()->comment('文章摘要/简介');
            $table->string('cover_image', 512)->nullable()->comment('封面图 URL');

            // 展示与统计控制
            $table->string('read_time', 32)->default('5 分钟阅读')->comment('预计阅读时间');
            $table->unsignedInteger('views_count')->default(0)->comment('浏览量/阅读数');
            $table->tinyInteger('status')->default(1)->comment('状态: 0-草稿, 1-已发布, 2-已下架');
            $table->timestamp('published_at')->nullable()->comment('发布时间');

            // 时间戳
            $table->timestamps();

            // 1. 列表排序索引
            $table->index(['status', 'published_at'], 'idx_status_published');
            // 3. 按作者查询列表索引 (用于个人中心/作者专页)
            $table->index(['user_id', 'status'], 'idx_user_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
