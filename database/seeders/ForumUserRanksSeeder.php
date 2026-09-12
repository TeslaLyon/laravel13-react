<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\UserGroup;
use App\Models\UserTitleLadder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ForumUserRanksSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 🎯 1. 初始化 XenForo 风格基础用户组
            $groups = [
                [
                    'id' => 1,
                    'name' => 'registered',
                    'title' => '注册会员',
                    'username_css' => null,
                    'display_style_priority' => 0,
                    'banner_text' => null,
                    'banner_bg_color' => '#2563eb',
                    'banner_text_color' => '#ffffff',
                    'banner_icon' => null,
                    'is_system' => true,
                    'is_staff' => false,
                    'is_banned' => false,
                ],
                [
                    'id' => 2,
                    'name' => 'administrative',
                    'title' => '超级管理员',
                    'username_css' => 'color: #ef4444; font-weight: 800;',
                    'display_style_priority' => 1000,
                    'banner_text' => 'Administrator',
                    'banner_bg_color' => '#dc2626',
                    'banner_text_color' => '#ffffff',
                    'banner_icon' => 'lucide:shield-alert',
                    'is_system' => true,
                    'is_staff' => true,
                    'is_banned' => false,
                ],
                [
                    'id' => 3,
                    'name' => 'moderator',
                    'title' => '社区版主',
                    'username_css' => 'color: #10b981; font-weight: 700;',
                    'display_style_priority' => 800,
                    'banner_text' => 'Moderator',
                    'banner_bg_color' => '#059669',
                    'banner_text_color' => '#ffffff',
                    'banner_icon' => 'lucide:shield',
                    'is_system' => true,
                    'is_staff' => true,
                    'is_banned' => false,
                ],
                [
                    'id' => 4,
                    'name' => 'contributor',
                    'title' => '认证贡献者',
                    'username_css' => 'color: #16a34a; font-weight: 600;',
                    'display_style_priority' => 500,
                    'banner_text' => 'Contributor',
                    'banner_bg_color' => '#15803d',
                    'banner_text_color' => '#ffffff',
                    'banner_icon' => 'lucide:sparkles',
                    'is_system' => false,
                    'is_staff' => false,
                    'is_banned' => false,
                ],
                [
                    'id' => 5,
                    'name' => 'advanced_leaker',
                    'title' => '进阶发布者',
                    'username_css' => 'color: #2563eb; font-weight: 600;',
                    'display_style_priority' => 400,
                    'banner_text' => 'Advanced Leaker',
                    'banner_bg_color' => '#1d4ed8',
                    'banner_text_color' => '#ffffff',
                    'banner_icon' => null,
                    'is_system' => false,
                    'is_staff' => false,
                    'is_banned' => false,
                ],
            ];

            foreach ($groups as $group) {
                UserGroup::updateOrCreate(['id' => $group['id']], $group);
            }

            // 🎯 针对 PostgreSQL 修正 user_groups 表的自增 Sequence
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SELECT setval(pg_get_serial_sequence('user_groups', 'id'), coalesce(max(id), 1)) FROM user_groups;");
            }

            // 🎯 2. 初始化阶梯等级头衔 (如果 user_title_ladders 也无时间戳，模型中同样需设置 $timestamps = false)
            $ladders = [
                ['title' => 'New member', 'min_credits' => 0.00, 'text_color' => '#94a3b8'],
                ['title' => 'Active member', 'min_credits' => 50.00, 'text_color' => '#38bdf8'],
                ['title' => 'Well-known member', 'min_credits' => 200.00, 'text_color' => '#4ade80'],
                ['title' => 'Distinguished member', 'min_credits' => 800.00, 'text_color' => '#a78bfa'],
                ['title' => 'Grandmaster', 'min_credits' => 3000.00, 'text_color' => '#f59e0b'],
            ];

            foreach ($ladders as $ladder) {
                UserTitleLadder::updateOrCreate(['title' => $ladder['title']], $ladder);
            }

            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SELECT setval(pg_get_serial_sequence('user_title_ladders', 'id'), coalesce(max(id), 1)) FROM user_title_ladders;");
            }
        });
    }
}
