<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserGroupLevelSeeder extends Seeder
{
    public function run(): void
    {
        // 1. 积分成长等级组 (Lv.1 ~ Lv.8)
        $creditGroups = [
            [
                'id' => 1,
                'name' => 'level_1',
                'title' => 'Lv.1 见习会员',
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
            ],
            [
                'id' => 2,
                'name' => 'level_2',
                'title' => 'Lv.2 初级会员',
                'level' => 2,
                'is_credit_based' => true,
                'is_system' => false,
                'credits_min' => 50.00,
                'credits_max' => 199.99,
                'read_permission_level' => 20,
                'allow_search' => true,
                'allow_custom_title' => false,
                'allow_upload_attachment' => true,
                'daily_post_limit' => 50,
            ],
            [
                'id' => 3,
                'name' => 'level_3',
                'title' => 'Lv.3 中级会员',
                'level' => 3,
                'is_credit_based' => true,
                'is_system' => false,
                'credits_min' => 200.00,
                'credits_max' => 499.99,
                'read_permission_level' => 30,
                'allow_search' => true,
                'allow_custom_title' => false,
                'allow_upload_attachment' => true,
                'daily_post_limit' => 100,
            ],
            [
                'id' => 4,
                'name' => 'level_4',
                'title' => 'Lv.4 高级会员',
                'level' => 4,
                'is_credit_based' => true,
                'is_system' => false,
                'credits_min' => 500.00,
                'credits_max' => 1499.99,
                'read_permission_level' => 40,
                'allow_search' => true,
                'allow_custom_title' => true, // 解锁自定义头衔
                'allow_upload_attachment' => true,
                'daily_post_limit' => 200,
            ],
            [
                'id' => 5,
                'name' => 'level_5',
                'title' => 'Lv.5 核心会员',
                'level' => 5,
                'is_credit_based' => true,
                'is_system' => false,
                'credits_min' => 1500.00,
                'credits_max' => 3999.99,
                'read_permission_level' => 50,
                'allow_search' => true,
                'allow_custom_title' => true,
                'allow_upload_attachment' => true,
                'daily_post_limit' => 500,
            ],
            [
                'id' => 6,
                'name' => 'level_6',
                'title' => 'Lv.6 资深名士',
                'level' => 6,
                'is_credit_based' => true,
                'is_system' => false,
                'credits_min' => 4000.00,
                'credits_max' => 9999.99,
                'read_permission_level' => 60,
                'allow_search' => true,
                'allow_custom_title' => true,
                'allow_upload_attachment' => true,
                'daily_post_limit' => 1000,
            ],
            [
                'id' => 7,
                'name' => 'level_7',
                'title' => 'Lv.7 宗师学者',
                'level' => 7,
                'is_credit_based' => true,
                'is_system' => false,
                'credits_min' => 10000.00,
                'credits_max' => 29999.99,
                'read_permission_level' => 70,
                'allow_search' => true,
                'allow_custom_title' => true,
                'allow_upload_attachment' => true,
                'daily_post_limit' => 2000,
            ],
            [
                'id' => 8,
                'name' => 'level_8',
                'title' => 'Lv.8 泰斗元老',
                'level' => 8,
                'is_credit_based' => true,
                'is_system' => false,
                'credits_min' => 30000.00,
                'credits_max' => null, // 积分无上限
                'read_permission_level' => 90,
                'allow_search' => true,
                'allow_custom_title' => true,
                'allow_upload_attachment' => true,
                'daily_post_limit' => 9999,
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
            ],
        ];

        foreach (array_merge($creditGroups, $systemGroups) as $group) {
            DB::table('user_groups')->updateOrInsert(
                ['id' => $group['id']],
                array_merge($group, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // 针对 PostgreSQL 修正自增序列，防止后续新增用户组时主键冲突
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('user_groups', 'id'), coalesce(max(id), 1)) FROM user_groups;");
        }
    }
}
