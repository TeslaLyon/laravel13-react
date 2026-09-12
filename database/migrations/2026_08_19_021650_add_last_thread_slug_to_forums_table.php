<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * 向 forums 表新增 last_thread_slug 字段
     */
    public function up(): void
    {
        Schema::table('forums', function (Blueprint $table) {
            // 🎯 在 last_thread_title 之后新增 last_thread_slug
            $table->string('last_thread_slug')
                ->nullable()
                ->after('last_thread_title')
                ->comment('最后回复主题的 Slug (供首页直链使用)');
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::table('forums', function (Blueprint $table) {
            $table->dropColumn('last_thread_slug');
        });
    }
};
