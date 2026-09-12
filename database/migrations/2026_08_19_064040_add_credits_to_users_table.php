<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * 运行迁移：为 users 表扩展活跃指标、总积分与主用户组外键
     */
    public function up(): void
    {
        // 🎯 1. 核心加固：动态感知 user_groups 表字段，安全补齐默认用户组 (id=1)
        if (Schema::hasTable('user_groups')) {
            $hasDefaultGroup = DB::table('user_groups')->where('id', 1)->exists();

            if (!$hasDefaultGroup) {
                // 基础通用字段
                $groupData = [
                    'id' => 1,
                    'name' => 'registered',
                    'title' => '注册会员',
                ];

                // 动态检测可选字段：只有当数据表中确实存在该列时才写入，杜绝 Undefined column 报错
                $optionalFields = [
                    'display_style_priority' => 0,
                    'banner_bg_color' => '#2563eb',
                    'banner_text_color' => '#ffffff',
                    'is_system' => true,
                    'is_staff' => false,
                    'is_banned' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                foreach ($optionalFields as $column => $value) {
                    if (Schema::hasColumn('user_groups', $column)) {
                        $groupData[$column] = $value;
                    }
                }

                DB::table('user_groups')->insert($groupData);

                // 针对 PostgreSQL 修正自增序列，避免后续自增 ID 发生主键冲突
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement("SELECT setval(pg_get_serial_sequence('user_groups', 'id'), coalesce(max(id), 1)) FROM user_groups;");
                }
            }
        }

        // 🎯 2. 为 users 表追加字段与外键约束
        Schema::table('users', function (Blueprint $table) {
            // 绑定主用户组
            if (!Schema::hasColumn('users', 'primary_group_id')) {
                $table->unsignedInteger('primary_group_id')
                    ->default(1)
                    ->after('password')
                    ->comment('主用户组 ID');

                $table->foreign('primary_group_id')
                    ->references('id')
                    ->on('user_groups')
                    ->restrictOnDelete();
            }

            // 头衔系统
            if (!Schema::hasColumn('users', 'custom_title')) {
                $table->string('custom_title', 100)->nullable()->after('avatar_url')->comment('自定义头衔');
            }
            if (!Schema::hasColumn('users', 'cached_title')) {
                $table->string('cached_title', 100)->nullable()->after('custom_title')->comment('阶梯头衔缓存');
            }

            // 基础互动统计
            if (!Schema::hasColumn('users', 'post_count')) {
                $table->unsignedInteger('post_count')->default(0)->after('cached_title')->comment('发帖与回复总数');
            }
            if (!Schema::hasColumn('users', 'thread_count')) {
                $table->unsignedInteger('thread_count')->default(0)->after('post_count')->comment('主题帖总数');
            }
            if (!Schema::hasColumn('users', 'reaction_score')) {
                $table->integer('reaction_score')->default(0)->after('thread_count')->comment('获赞/表态总分');
            }
            if (!Schema::hasColumn('users', 'trophy_points')) {
                $table->unsignedInteger('trophy_points')->default(0)->after('reaction_score')->comment('成就点数');
            }

            // 活跃与声誉指标
            if (!Schema::hasColumn('users', 'enthusiasm_points')) {
                $table->unsignedInteger('enthusiasm_points')->default(0)->after('trophy_points')->comment('热心值');
            }
            if (!Schema::hasColumn('users', 'bounty_points')) {
                $table->unsignedInteger('bounty_points')->default(0)->after('enthusiasm_points')->comment('悬赏值');
            }
            if (!Schema::hasColumn('users', 'contribution_points')) {
                $table->unsignedInteger('contribution_points')->default(0)->after('bounty_points')->comment('贡献值');
            }
            if (!Schema::hasColumn('users', 'prestige_points')) {
                $table->integer('prestige_points')->default(0)->after('contribution_points')->comment('威望');
            }
            if (!Schema::hasColumn('users', 'digest_thread_count')) {
                $table->unsignedInteger('digest_thread_count')->default(0)->after('prestige_points')->comment('精华帖总数');
            }
            if (!Schema::hasColumn('users', 'violation_count')) {
                $table->unsignedInteger('violation_count')->default(0)->after('digest_thread_count')->comment('违规警告次数');
            }

            // 综合总积分
            if (!Schema::hasColumn('users', 'total_credits')) {
                $table->decimal('total_credits', 12, 2)->default(0.00)->after('violation_count')->index()->comment('综合总积分');
            }
            if (!Schema::hasColumn('users', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable()->after('total_credits')->index()->comment('最后活跃时间');
            }

            // 索引优化
            $table->index('post_count');
            $table->index('prestige_points');
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'primary_group_id')) {
                $table->dropForeign(['primary_group_id']);
                $table->dropColumn('primary_group_id');
            }

            $table->dropIndex(['total_credits']);
            $table->dropIndex(['last_activity_at']);
            $table->dropIndex(['post_count']);
            $table->dropIndex(['prestige_points']);

            $table->dropColumn([
                'custom_title',
                'cached_title',
                'post_count',
                'thread_count',
                'reaction_score',
                'trophy_points',
                'enthusiasm_points',
                'bounty_points',
                'contribution_points',
                'prestige_points',
                'digest_thread_count',
                'violation_count',
                'total_credits',
                'last_activity_at',
            ]);
        });
    }
};
