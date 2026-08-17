<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * 运行迁移：向 users 表添加 avatar 字段
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 🎯 添加可空的 avatar 字段，用于存放头像路径或 URL
            $table->string('avatar')->nullable()->after('email');
        });
    }

    /**
     * 回滚迁移：撤销 avatar 字段
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar');
        });
    }
};
