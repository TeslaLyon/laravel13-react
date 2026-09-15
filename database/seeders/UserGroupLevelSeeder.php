<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserGroupLevelSeeder extends Seeder
{
    /**
     * 运行用户成长等级与系统用户组数据填充
     *
     * 积分计算公式 (App\Services\CreditService):
     * 总积分 = 发帖×0.1 + 热心×1.2 + 悬赏×1.5 + 贡献×1.5 + 威望×20.0 + 精华×100.0 - 违规×20.0
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 1. 积分成长等级组 (Lv.1 ~ Lv.7 渐进激励阶梯)
            $creditGroups = [
                [
                    'id' => 1,
                    'name' => 'level_1',
                    'title' => 'Lv.1 循规初试',
                    'level' => 1,
                    'is_credit_based' => true,
                    'is_system' => false,
                    'credits_min' => 0.00,
                    'credits_max' => 49.99,
                    'read_permission_level' => 10,
                    'allow_search' => true,
                    'allow_custom_title' => false,
                    'allow_upload_attachment' => false,
                    'daily_post_limit' => 20,
                    'banner_text' => '循规初试',
                    'banner_bg_color' => '#64748b', // Slate 沉稳灰蓝
                    'banner_text_color' => '#ffffff',
                    'banner_icon' => 'lucide:sprout',
                    'display_style_priority' => 10,
                ],
                [
                    'id' => 2,
                    'name' => 'level_2',
                    'title' => 'Lv.2 侧卧听泉',
                    'level' => 2,
                    'is_credit_based' => true,
                    'is_system' => false,
                    'credits_min' => 50.00,
                    'credits_max' => 199.99,
                    'read_permission_level' => 20,
                    'allow_search' => true,
                    'allow_custom_title' => false,
                    'allow_upload_attachment' => true, // 核心激励点 1：解锁附件与图片上传
                    'daily_post_limit' => 50,
                    'banner_text' => '侧卧听泉',
                    'banner_bg_color' => '#0284c7', // Sky 清泉水蓝
                    'banner_text_color' => '#ffffff',
                    'banner_icon' => 'lucide:waves',
                    'display_style_priority' => 20,
                ],
                [
                    'id' => 3,
                    'name' => 'level_3',
                    'title' => 'Lv.3 游龙后顾',
                    'level' => 3,
                    'is_credit_based' => true,
                    'is_system' => false,
                    'credits_min' => 200.00,
                    'credits_max' => 599.99,
                    'read_permission_level' => 30,
                    'allow_search' => true,
                    'allow_custom_title' => false,
                    'allow_upload_attachment' => true,
                    'daily_post_limit' => 100,
                    'banner_text' => '游龙后顾',
                    'banner_bg_color' => '#059669', // Emerald 游龙碧翡
                    'banner_text_color' => '#ffffff',
                    'banner_icon' => 'lucide:feather',
                    'display_style_priority' => 30,
                ],
                [
                    'id' => 4,
                    'name' => 'level_4',
                    'title' => 'Lv.4 倒挂金钩',
                    'level' => 4,
                    'is_credit_based' => true,
                    'is_system' => false,
                    'credits_min' => 600.00,
                    'credits_max' => 1799.99,
                    'read_permission_level' => 40,
                    'allow_search' => true,
                    'allow_custom_title' => true, // 核心激励点 2（关键分水岭）：解锁个性自定义头衔
                    'allow_upload_attachment' => true,
                    'daily_post_limit' => 200,
                    'banner_text' => '倒挂金钩',
                    'banner_bg_color' => '#d97706', // Amber 灿金色
                    'banner_text_color' => '#ffffff',
                    'banner_icon' => 'lucide:award',
                    'display_style_priority' => 40,
                ],
                [
                    'id' => 5,
                    'name' => 'level_5',
                    'title' => 'Lv.5 观音坐莲',
                    'level' => 5,
                    'is_credit_based' => true,
                    'is_system' => false,
                    'credits_min' => 1800.00,
                    'credits_max' => 4999.99,
                    'read_permission_level' => 50,
                    'allow_search' => true,
                    'allow_custom_title' => true,
                    'allow_upload_attachment' => true,
                    'daily_post_limit' => 500,
                    'banner_text' => '观音坐莲',
                    'banner_bg_color' => '#9333ea', // Purple 莲花仙紫
                    'banner_text_color' => '#ffffff',
                    'banner_icon' => 'lucide:flower-2',
                    'display_style_priority' => 50,
                ],
                [
                    'id' => 6,
                    'name' => 'level_6',
                    'title' => 'Lv.6 老树盘根',
                    'level' => 6,
                    'is_credit_based' => true,
                    'is_system' => false,
                    'credits_min' => 5000.00,
                    'credits_max' => 14999.99,
                    'read_permission_level' => 60,
                    'allow_search' => true,
                    'allow_custom_title' => true,
                    'allow_upload_attachment' => true,
                    'daily_post_limit' => 1000,
                    'banner_text' => '老树盘根',
                    'banner_bg_color' => '#b45309', // Warm Bronze 苍劲褐金
                    'banner_text_color' => '#ffffff',
                    'banner_icon' => 'lucide:tree-pine',
                    'display_style_priority' => 60,
                ],
                [
                    'id' => 7,
                    'name' => 'level_7',
                    'title' => 'Lv.7 颠鸾倒凤',
                    'level' => 7,
                    'is_credit_based' => true,
                    'is_system' => false,
                    'credits_min' => 15000.00,
                    'credits_max' => null, // 终极殿堂：积分无上限
                    'read_permission_level' => 90,
                    'allow_search' => true,
                    'allow_custom_title' => true,
                    'allow_upload_attachment' => true,
                    'daily_post_limit' => 9999,
                    'banner_text' => '颠鸾倒凤',
                    'banner_bg_color' => '#e11d48', // Ruby 极意红宝石/至尊色
                    'banner_text_color' => '#ffffff',
                    'banner_icon' => 'lucide:crown',
                    'display_style_priority' => 70,
                ],
            ];

            // 2. 系统特殊组
            $systemGroups = [
                [
                    'id' => 10,
                    'name' => 'banned',
                    'title' => '禁止发言组',
                    'level' => 0,
                    'is_credit_based' => false,
                    'is_system' => true,
                    'credits_min' => 0.00,
                    'credits_max' => null,
                    'read_permission_level' => 0,
                    'allow_search' => false,
                    'allow_custom_title' => false,
                    'allow_upload_attachment' => false,
                    'daily_post_limit' => 0,
                    'banner_text' => '已封禁',
                    'banner_bg_color' => '#475569',
                    'banner_text_color' => '#ffffff',
                    'banner_icon' => 'lucide:ban',
                    'display_style_priority' => 0,
                ],
                [
                    'id' => 99,
                    'name' => 'administrator',
                    'title' => '超级管理员',
                    'level' => 99,
                    'is_credit_based' => false,
                    'is_system' => true,
                    'credits_min' => 0.00,
                    'credits_max' => null,
                    'read_permission_level' => 255,
                    'allow_search' => true,
                    'allow_custom_title' => true,
                    'allow_upload_attachment' => true,
                    'daily_post_limit' => 0, // 0 代表无限制
                    'banner_text' => '超级管理员',
                    'banner_bg_color' => '#dc2626',
                    'banner_text_color' => '#ffffff',
                    'banner_icon' => 'lucide:shield-alert',
                    'display_style_priority' => 1000,
                ],
            ];

            // 3. 执行幂等更新或插入
            foreach (array_merge($creditGroups, $systemGroups) as $group) {
                DB::table('user_groups')->updateOrInsert(
                    ['id' => $group['id']],
                    array_merge($group, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                );
            }

            // 4. 生产环境数据平滑迁移与旧等级清理：
            // 如果历史数据存在旧版 Lv.8 (ID: 8) 或更高未定义积分组，平移用户至顶级组 (ID: 7) 并安全清理冗余组
            DB::table('users')
                ->where('primary_group_id', '>', 7)
                ->where('primary_group_id', '<', 10) // 排除系统组 (10 banned, 99 admin)
                ->update(['primary_group_id' => 7]);

            DB::table('user_groups')
                ->where('is_credit_based', true)
                ->where('level', '>', 7)
                ->delete();

            // 5. 针对 PostgreSQL 修正自增序列，防止后续新增用户组时主键冲突
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SELECT setval(pg_get_serial_sequence('user_groups', 'id'), coalesce(max(id), 1)) FROM user_groups;");
            }
        });
    }
}
