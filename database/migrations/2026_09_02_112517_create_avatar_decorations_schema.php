<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * 运行数据库迁移
     */
    public function up(): void
    {
        // 1. 官方挂件字典配置表
        Schema::create('avatar_decorations', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100)->comment('挂件名称');
            $table->string('code', 50)->unique()->comment('唯一英文标识，如 starry_eyed');
            $table->string('image_url', 255)->comment('挂件动图 URL');
            $table->string('description', 255)->nullable()->comment('解锁条件描述');
            $table->json('criteria')->nullable()->comment('解锁规则 JSON 配置');
            $table->unsignedInteger('display_order')->default(0)->comment('显示排序，越小越靠前');
            $table->boolean('is_active')->default(true)->comment('是否上架启用');
            $table->timestamps();

            // 🌟 核心索引优化：
            // 覆盖前台商城展示：WHERE is_active = 1 ORDER BY display_order ASC
            $table->index(['is_active', 'display_order'], 'idx_active_display_order');
        });

        // 2. 用户已解锁挂件记录表 (包含时效控制)
        Schema::create('user_avatar_decorations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('avatar_decoration_id')->constrained()->cascadeOnDelete();
            $table->timestamp('unlocked_at')->useCurrent()->comment('解锁时间');
            $table->timestamp('expires_at')->nullable()->comment('过期时间，null 为永久有效');
            $table->timestamps();

            // 🌟 核心索引优化：
            // 1) 防重唯一索引：确保同一用户不重复插入同一挂件
            $table->unique(['user_id', 'avatar_decoration_id'], 'uniq_user_decoration');

            // 2) 复合索引：加速佩戴校验与有效性判断 WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())
            $table->index(['user_id', 'expires_at'], 'idx_user_expires');

            // 3) 复合索引：加速用户个人中心挂件背包的时间倒序展示 WHERE user_id = ? ORDER BY unlocked_at DESC
            $table->index(['user_id', 'unlocked_at'], 'idx_user_unlocked');

            // 4) 单列索引：供 Laravel Console 定时调度任务扫描过期挂件 WHERE expires_at <= NOW()
            $table->index('expires_at', 'idx_global_expires');
        });

        // 3. 用户表扩展当前佩戴字段
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('avatar_decoration_id')
                ->nullable()
                ->after('avatar')
                ->constrained('avatar_decorations')
                ->nullOnDelete()
                ->comment('当前佩戴的头像挂件ID');
            // 注意：Laravel 的 constrained() 语法在大多数数据库驱动中会自动建立外键索引
        });
    }

    /**
     * 回滚数据库迁移
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['avatar_decoration_id']);
            $table->dropColumn('avatar_decoration_id');
        });
        Schema::dropIfExists('user_avatar_decorations');
        Schema::dropIfExists('avatar_decorations');
    }
};
