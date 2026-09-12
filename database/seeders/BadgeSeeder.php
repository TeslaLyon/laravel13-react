<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\BadgeCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BadgeSeeder extends Seeder
{
    /**
     * 运行数据库填充
     */
    public function run(): void
    {
        DB::transaction(function () {
            // ─────────────────────────────────────────────────────────────
            // 1. 初始化勋章分类 (BadgeCategory)
            // ─────────────────────────────────────────────────────────────
            $categories = [
                [
                    'name' => '社区成就',
                    'slug' => 'achievements',
                    'description' => '通过在社区中积极发帖、互动获得的永久荣誉',
                    'display_order' => 1,
                    'is_active' => true,
                ],
                [
                    'name' => '身份认证',
                    'slug' => 'identity',
                    'description' => '创作者、版主及特殊官方认证头衔',
                    'display_order' => 2,
                    'is_active' => true,
                ],
                [
                    'name' => '限时活动',
                    'slug' => 'events',
                    'description' => '参与周年庆典或特定节日活动获得的限量勋章',
                    'display_order' => 3,
                    'is_active' => true,
                ],
            ];

            $categoryMap = [];
            foreach ($categories as $cat) {
                $created = BadgeCategory::updateOrCreate(['slug' => $cat['slug']], $cat);
                $categoryMap[$cat['slug']] = $created->id;
            }

            // ─────────────────────────────────────────────────────────────
            // 2. 初始化勋章列表 (Badge)
            // ─────────────────────────────────────────────────────────────
            $badges = [
                [
                    'category_id' => $categoryMap['events'],
                    'name' => '1 周年荣誉勋章',
                    'slug' => '1-year-anniversary',
                    'icon' => 'badges/5years2.png',
                    'description' => '入驻社区满 1 周年纪念，感谢一路同行的忠实伙伴！',
                    'display_order' => 1,
                    'is_active' => true,
                ],
                [
                    'category_id' => $categoryMap['identity'],
                    'name' => '创作者之星',
                    'slug' => 'creator-star',
                    'icon' => 'badges/100.png',
                    'description' => '在社区中持续产出高质量原创技术教程与深度专栏。',
                    'display_order' => 2,
                    'is_active' => true,
                ],
                [
                    'category_id' => $categoryMap['achievements'],
                    'name' => '技术先锋',
                    'slug' => 'tech-pioneer',
                    'icon' => 'badges/365dayssmg2.png',
                    'description' => '热心为社区解答超过 50 个疑难技术问答。',
                    'display_order' => 3,
                    'is_active' => true,
                ],
                [
                    'category_id' => $categoryMap['events'],
                    'name' => '内测开拓者',
                    'slug' => 'beta-tester',
                    'icon' => 'badges/medal2026.png',
                    'description' => '平台首期内测核心用户，参与多项核心功能测试与建设。',
                    'display_order' => 4,
                    'is_active' => true,
                ],
                [
                    'category_id' => $categoryMap['achievements'],
                    'name' => '活跃之星',
                    'slug' => 'active-star',
                    'icon' => 'badges/PopularFapperAward.png',
                    'description' => '连续签到或活跃超过 100 天获得的特别勋章。',
                    'display_order' => 5,
                    'is_active' => true,
                ],
            ];

            $createdBadges = [];
            foreach ($badges as $item) {
                $createdBadges[] = Badge::updateOrCreate(['slug' => $item['slug']], $item);
            }

            // ─────────────────────────────────────────────────────────────
            // 3. 🎯 核心补全：为用户授予勋章并设置佩戴状态 (badge_user)
            // ─────────────────────────────────────────────────────────────

            // 获取要授予勋章的目标用户（若无用户则自动创建一个测试用户）
            $targetUsers = User::limit(3)->get();

            if ($targetUsers->isEmpty()) {
                $targetUsers = collect([
                    User::factory()->create([
                        'name' => '测试管理员',
                        'email' => 'admin@example.com',
                    ]),
                ]);
            }

            // 组装中间表同步数据（设置前 4 个为佩戴展示状态 is_equipped = true）
            $syncData = [];
            foreach ($createdBadges as $index => $badge) {
                $syncData[$badge->id] = [
                    'is_equipped' => $index < 4, // 🎯 前 4 枚自动佩戴展示，第 5 枚及以后仅收藏
                    'awarded_at' => now()->subDays(rand(1, 30)), // 模拟不同的获得时间
                    'expires_at' => null, // 永久有效
                ];
            }

            // 为目标用户批量赋予这些勋章
            foreach ($targetUsers as $user) {
                $user->badges()->sync($syncData);
            }

            $this->command->info('✅ 勋章分类、勋章数据及用户佩戴记录已成功初始化！');
        });
    }
}
