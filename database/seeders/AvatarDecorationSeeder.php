<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AvatarDecoration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AvatarDecorationSeeder extends Seeder
{
    /**
     * 运行数据库填充
     */
    public function run(): void
    {
        // 1. 预设官方头像挂件列表
        $decorations = [
            [
                'title' => '璀璨星芒',
                'code' => 'starry_eyed',
                'image_url' => '/images/decorations/starry_eyed.webp', // 放置你的动图资源
                'description' => '累计获得 1,000 社区积分即可点亮。',
                'criteria' => [
                    ['rule' => 'points', 'value' => 1000],
                ],
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'title' => '岁序常春',
                'code' => 'annual_checkin',
                'image_url' => '/images/decorations/annual_checkin.webp',
                'description' => '持之以恒，完成连续签到 365 天专属特权。',
                'criteria' => [
                    ['rule' => 'consecutive_checkin', 'value' => 365],
                ],
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'title' => '至臻金曜',
                'code' => 'golden_nobility',
                'image_url' => '/images/decorations/golden_nobility.webp',
                'description' => '平台尊享，累计赞助或充值达到 500 元。',
                'criteria' => [
                    ['rule' => 'recharge_amount', 'value' => 500],
                ],
                'display_order' => 3,
                'is_active' => true,
            ],
            [
                'title' => '卓越创作人',
                'code' => 'master_creator',
                'image_url' => '/images/decorations/master_creator.webp',
                'description' => '积分达到 5,000 且公开发布达到 50 篇优质内容。',
                'criteria' => [
                    ['rule' => 'points', 'value' => 5000],
                    ['rule' => 'works_count', 'value' => 50],
                ],
                'display_order' => 4,
                'is_active' => true,
            ],
            [
                'title' => '赛博幻境',
                'code' => 'cyber_neon',
                'image_url' => '/images/decorations/cyber_neon.webp',
                'description' => '社区活跃先锋，积分达到 2,500 自动解锁。',
                'criteria' => [
                    ['rule' => 'points', 'value' => 2500],
                ],
                'display_order' => 5,
                'is_active' => true,
            ],
        ];

        // 2. 批量写入或更新基础配置
        foreach ($decorations as $item) {
            AvatarDecoration::updateOrCreate(
                ['code' => $item['code']],
                [
                    'title' => $item['title'],
                    'image_url' => $item['image_url'],
                    'description' => $item['description'],
                    'criteria' => $item['criteria'],
                    'display_order' => $item['display_order'],
                    'is_active' => $item['is_active'],
                ]
            );
        }

        // 3. 为测试用户（以 ID = 1 为例）配置模拟解锁与佩戴数据
        $firstUser = User::first();
        if ($firstUser) {
            $starryEyed = AvatarDecoration::where('code', 'starry_eyed')->first();
            $annualCheckin = AvatarDecoration::where('code', 'annual_checkin')->first();

            // 为该用户解锁「璀璨星芒」(永久) 与「岁序常春」(30天后过期)
            if ($starryEyed) {
                $firstUser->unlockedDecorations()->syncWithoutDetaching([
                    $starryEyed->id => [
                        'unlocked_at' => Carbon::now()->subDays(5),
                        'expires_at' => null,
                    ],
                ]);

                // 默认佩戴「璀璨星芒」
                $firstUser->update(['avatar_decoration_id' => $starryEyed->id]);
            }

            if ($annualCheckin) {
                $firstUser->unlockedDecorations()->syncWithoutDetaching([
                    $annualCheckin->id => [
                        'unlocked_at' => Carbon::now()->subDays(2),
                        'expires_at' => Carbon::now()->addDays(30),
                    ],
                ]);
            }
        }
    }
}
