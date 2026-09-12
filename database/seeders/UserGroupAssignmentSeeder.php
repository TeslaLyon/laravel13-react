<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserGroupAssignmentSeeder extends Seeder
{
    /**
     * 运行数据填充：为指定 ID 用户分配主组、次要组与活跃度数据
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 🎯 定义 6 位目标用户的属性与组装配置
            $usersMockData = [
                // 1. 创始人 / 超级管理员 (ID = 1)
                1 => [
                    'name' => 'Admin',
                    'username' => 'admin',
                    'email' => 'admin@example.com',
                    'primary_group_id' => 2, // administrative
                    'secondary_group_ids' => [
                        4 => ['is_displayed' => true], // Contributor
                    ],
                    'metrics' => [
                        'post_count' => 320,
                        'thread_count' => 45,
                        'reaction_score' => 1250,
                        'trophy_points' => 450,
                        'enthusiasm_points' => 80,
                        'bounty_points' => 50,
                        'contribution_points' => 200,
                        'prestige_points' => 50,     // 高威望
                        'digest_thread_count' => 8,  // 8 篇精华帖
                        'violation_count' => 0,
                    ],
                ],

                // 2. 社区大版主 (ID = 2)
                2 => [
                    'name' => 'Tech Moderator',
                    'username' => 'moderator_pro',
                    'email' => 'mod@example.com',
                    'primary_group_id' => 3, // moderator
                    'secondary_group_ids' => [
                        4 => ['is_displayed' => true], // Contributor
                        5 => ['is_displayed' => true], // Advanced Leaker
                    ],
                    'metrics' => [
                        'post_count' => 260,
                        'thread_count' => 30,
                        'reaction_score' => 890,
                        'trophy_points' => 280,
                        'enthusiasm_points' => 150,
                        'bounty_points' => 80,
                        'contribution_points' => 120,
                        'prestige_points' => 20,
                        'digest_thread_count' => 3,
                        'violation_count' => 0,
                    ],
                ],

                // 3. 进阶发布者 / 创作者 (ID = 5)
                5 => [
                    'name' => 'Football Da Boys',
                    'username' => 'footballdaboys',
                    'email' => 'football@example.com',
                    'primary_group_id' => 1, // registered
                    'secondary_group_ids' => [
                        4 => ['is_displayed' => true], // Contributor (绿色横幅)
                        5 => ['is_displayed' => true], // Advanced Leaker (蓝色横幅)
                    ],
                    'metrics' => [
                        'post_count' => 140,
                        'thread_count' => 22,
                        'reaction_score' => 430,
                        'trophy_points' => 180,
                        'enthusiasm_points' => 45,
                        'bounty_points' => 30,
                        'contribution_points' => 85,
                        'prestige_points' => 10,
                        'digest_thread_count' => 2,
                        'violation_count' => 0,
                    ],
                ],

                // 4. 互助答题达人 (ID = 6)
                6 => [
                    'name' => 'Helper Star',
                    'username' => 'helper_star',
                    'email' => 'helper@example.com',
                    'primary_group_id' => 1, // registered
                    'secondary_group_ids' => [
                        4 => ['is_displayed' => true], // Contributor
                    ],
                    'metrics' => [
                        'post_count' => 85,
                        'thread_count' => 5,
                        'reaction_score' => 210,
                        'trophy_points' => 120,
                        'enthusiasm_points' => 200, // 极高热心值
                        'bounty_points' => 65,
                        'contribution_points' => 20,
                        'prestige_points' => 5,
                        'digest_thread_count' => 0,
                        'violation_count' => 0,
                    ],
                ],

                // 5. 新晋发布者 (ID = 7)
                7 => [
                    'name' => 'Resource Finder',
                    'username' => 'resource_finder',
                    'email' => 'finder@example.com',
                    'primary_group_id' => 1, // registered
                    'secondary_group_ids' => [
                        5 => ['is_displayed' => true], // Advanced Leaker
                    ],
                    'metrics' => [
                        'post_count' => 42,
                        'thread_count' => 8,
                        'reaction_score' => 95,
                        'trophy_points' => 60,
                        'enthusiasm_points' => 15,
                        'bounty_points' => 35,
                        'contribution_points' => 40,
                        'prestige_points' => 2,
                        'digest_thread_count' => 0,
                        'violation_count' => 0,
                    ],
                ],

                // 6. 普通萌新会员 (ID = 8)
                8 => [
                    'name' => 'New Explorer',
                    'username' => 'new_explorer',
                    'email' => 'explorer@example.com',
                    'primary_group_id' => 1, // registered
                    'secondary_group_ids' => [], // 无次要横幅
                    'metrics' => [
                        'post_count' => 6,
                        'thread_count' => 1,
                        'reaction_score' => 12,
                        'trophy_points' => 20,
                        'enthusiasm_points' => 2,
                        'bounty_points' => 0,
                        'contribution_points' => 0,
                        'prestige_points' => 0,
                        'digest_thread_count' => 0,
                        'violation_count' => 0,
                    ],
                ],
            ];

            // 🎯 批量处理与持久化
            foreach ($usersMockData as $id => $data) {
                // 1. 如果用户已存在则更新，不存在则创建
                $user = User::query()->find($id);

                if (!$user) {
                    $user = new User();
                    $user->id = $id;
                    $user->name = $data['name'];
                    $user->username = $data['username'];
                    $user->email = $data['email'];
                    $user->password = Hash::make('password123');
                    $user->avatar_url = "https://api.dicebear.com/7.x/bottts/svg?seed={$data['username']}";
                }

                // 2. 绑定主用户组与各项指标字段
                $user->primary_group_id = $data['primary_group_id'];
                foreach ($data['metrics'] as $metricKey => $metricValue) {
                    $user->{$metricKey} = $metricValue;
                }
                $user->last_activity_at = now()->subMinutes(rand(1, 120));
                $user->save();

                // 3. 同步次要用户组 (绑定多对多中间表并设置 is_displayed)
                $user->secondaryGroups()->sync($data['secondary_group_ids']);

                // 4. 触发总积分重算与阶梯头衔自动匹配
                $user->refreshTotalCredits();
            }

            // 🎯 针对 PostgreSQL 修复 users 表的序列，防止未来手动创建用户时主键冲突
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("SELECT setval(pg_get_serial_sequence('users', 'id'), coalesce(max(id), 1)) FROM users;");
            }
        });
    }
}
