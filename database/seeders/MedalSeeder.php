<?php

namespace Database\Seeders;

use App\Models\Medal;
use App\Models\MedalCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class MedalSeeder extends Seeder
{
    public function run(): void
    {
        // 1. 创建勋章分类
        $milestone = MedalCategory::firstOrCreate(['slug' => 'milestone'], [
            'name' => '社区里程碑',
            'description' => '见证社区发展与个人成长的崇高荣誉',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $activity = MedalCategory::firstOrCreate(['slug' => 'activity'], [
            'name' => '活跃打卡',
            'description' => '坚持日常签到与互动的活跃勋章',
            'display_order' => 2,
            'is_active' => true,
        ]);

        $special = MedalCategory::firstOrCreate(['slug' => 'special'], [
            'name' => '限定活动',
            'description' => '特定节日与全站大型活动限定纪念',
            'display_order' => 3,
            'is_active' => true,
        ]);

        // 2. 勋章实体基础数据
        $medals = [
            [
                'category_id' => $milestone->id,
                'code' => 'anniversary_5y',
                'title' => '5周年元老开拓者',
                'description' => '加入社区满 5 年，见证平台从诞生到繁荣的每一步',
                'condition_text' => '注册账号满 1825 天',
                'icon_url' => '/storage/badges/5years2.png',
                'rarity' => 'legendary',
                'trophy_points' => 50,
                'award_type' => 'auto',
                'criteria' => ['registered_days' => 1825],
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'category_id' => $activity->id,
                'code' => 'streak_365d',
                'title' => '365天连击大师',
                'description' => '连续签到打卡满 1 整年，持之以恒的社区常驻者',
                'condition_text' => '连续每日签到满 365 天',
                'icon_url' => '/storage/badges/365dayssmg2.png',
                'rarity' => 'epic',
                'trophy_points' => 30,
                'award_type' => 'auto',
                'criteria' => ['continuous_checkin' => 365],
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'category_id' => $activity->id,
                'code' => 'streak_100d',
                'title' => '百日连续活跃',
                'description' => '达成连续 100 天活跃创作与社区互动',
                'condition_text' => '连续每日签到满 100 天',
                'icon_url' => '/storage/badges/100.png',
                'rarity' => 'rare',
                'trophy_points' => 15,
                'award_type' => 'auto',
                'criteria' => ['continuous_checkin' => 100],
                'display_order' => 3,
                'is_active' => true,
            ],
            [
                'category_id' => $special->id,
                'code' => 'new_year_2026',
                'title' => '2026 新年专属纪念',
                'description' => '参与 2026 元旦跨年盛典活动专属限定获得',
                'condition_text' => '2026 元旦期间登录并发布新年寄语',
                'icon_url' => '/storage/badges/medal2026.png',
                'rarity' => 'epic',
                'trophy_points' => 20,
                'award_type' => 'manual',
                'display_order' => 4,
                'is_active' => true,
            ],
            [
                'category_id' => $milestone->id,
                'code' => 'popular_star',
                'title' => '年度人气之星',
                'description' => '深受全站读者喜爱，作品累计获得大量点赞与认可',
                'condition_text' => '全站作品累计获赞达到 1,000 次',
                'icon_url' => '/storage/badges/PopularFapperAward.png',
                'rarity' => 'rare',
                'trophy_points' => 25,
                'award_type' => 'auto',
                'criteria' => ['likes_count' => 1000],
                'display_order' => 5,
                'is_active' => true,
            ],
        ];

        foreach ($medals as $medalData) {
            Medal::updateOrCreate(['code' => $medalData['code']], $medalData);
        }

        // 3. 为测试用户关联勋章（默认给前 3 个用户分配勋章）
        $allMedals = Medal::all();
        $users = User::take(3)->get();

        foreach ($users as $user) {
            foreach ($allMedals as $index => $medal) {
                $user->medals()->syncWithoutDetaching([
                    $medal->id => [
                        'is_worn' => $index < 4, // 默认佩戴前 4 枚
                        'wear_slot' => $index < 4 ? $index + 1 : null,
                        'unlocked_at' => now()->subDays(rand(10, 180)),
                        'award_reason' => '系统达成自动授予',
                    ],
                ]);
            }
        }
    }
}
