<?php

namespace Database\Seeders;

use App\Models\Medal;
use App\Models\MedalCategory;
use Illuminate\Database\Seeder;

class Medal1Seeder extends Seeder
{
    public function run(): void
    {
        // 1. 初始化勋章分类
        $categories = [
            'activity' => MedalCategory::updateOrCreate(['slug' => 'activity'], [
                'name' => '活跃打卡',
                'description' => '坚持日常签到、登录与社区互动的活跃奖励',
                'display_order' => 1,
                'is_active' => true,
            ]),
            'creation' => MedalCategory::updateOrCreate(['slug' => 'creation'], [
                'name' => '创作达人',
                'description' => '深耕优质内容发布、教程编写与知识分享',
                'display_order' => 2,
                'is_active' => true,
            ]),
            'interaction' => MedalCategory::updateOrCreate(['slug' => 'interaction'], [
                'name' => '社交与互动',
                'description' => '参与评论讨论、点赞交流与热心互助',
                'display_order' => 3,
                'is_active' => true,
            ]),
            'milestone' => MedalCategory::updateOrCreate(['slug' => 'milestone'], [
                'name' => '社区里程碑',
                'description' => '见证社区发展与账号资历成长的崇高荣誉',
                'display_order' => 4,
                'is_active' => true,
            ]),
            'special' => MedalCategory::updateOrCreate(['slug' => 'special'], [
                'name' => '限定与特殊贡献',
                'description' => '特定节日跨年盛典及社区特殊贡献专属纪念',
                'display_order' => 5,
                'is_active' => true,
            ]),
        ];

        // 2. 定义 16 枚全站勋章
        $medals = [
            // ================= 活跃打卡 (Activity) =================
            [
                'category_id' => $categories['activity']->id,
                'code' => 'streak_7d',
                'title' => '新人启航',
                'description' => '初来乍到，连续完成第 1 周的签到打卡',
                'condition_text' => '连续每日签到满 7 天',
                'icon_url' => '/storage/badges/streak_7d.png',
                'rarity' => 'common',
                'trophy_points' => 5,
                'award_type' => 'auto',
                'criteria' => ['continuous_checkin' => 7],
                'display_order' => 10,
            ],
            [
                'category_id' => $categories['activity']->id,
                'code' => 'streak_30d',
                'title' => '月度自律达人',
                'description' => '一个月风雨无阻，持之以恒的社区探索者',
                'condition_text' => '连续每日签到满 30 天',
                'icon_url' => '/storage/badges/streak_30d.png',
                'rarity' => 'rare',
                'trophy_points' => 10,
                'award_type' => 'auto',
                'criteria' => ['continuous_checkin' => 30],
                'display_order' => 20,
            ],
            [
                'category_id' => $categories['activity']->id,
                'code' => 'streak_100d',
                'title' => '百日连续活跃',
                'description' => '达成连续 100 天活跃创作与社区互动',
                'condition_text' => '连续每日签到满 100 天',
                'icon_url' => '/storage/badges/100.png',
                'rarity' => 'rare',
                'trophy_points' => 15,
                'award_type' => 'auto',
                'criteria' => ['continuous_checkin' => 100],
                'display_order' => 30,
            ],
            [
                'category_id' => $categories['activity']->id,
                'code' => 'streak_365d',
                'title' => '365天连击大师',
                'description' => '连续签到打卡满 1 整年，持之以恒的社区常驻者',
                'condition_text' => '连续每日签到满 365 天',
                'icon_url' => '/storage/badges/365dayssmg2.png',
                'rarity' => 'epic',
                'trophy_points' => 30,
                'award_type' => 'auto',
                'criteria' => ['continuous_checkin' => 365],
                'display_order' => 40,
            ],

            // ================= 创作达人 (Creation) =================
            [
                'category_id' => $categories['creation']->id,
                'code' => 'post_starter',
                'title' => '初试啼声',
                'description' => '在社区迈出创作者的第一步，公开发布首篇作品',
                'condition_text' => '累计公开发布作品满 1 篇',
                'icon_url' => '/storage/badges/post_starter.png',
                'rarity' => 'common',
                'trophy_points' => 5,
                'award_type' => 'auto',
                'criteria' => ['posts_count' => 1],
                'display_order' => 50,
            ],
            [
                'category_id' => $categories['creation']->id,
                'code' => 'post_prolific',
                'title' => '高产著作家',
                'description' => '笔耕不辍，在平台累计创作产出大量高质内容',
                'condition_text' => '累计公开发布作品达到 50 篇',
                'icon_url' => '/storage/badges/post_prolific.png',
                'rarity' => 'epic',
                'trophy_points' => 25,
                'award_type' => 'auto',
                'criteria' => ['posts_count' => 50],
                'display_order' => 60,
            ],
            [
                'category_id' => $categories['creation']->id,
                'code' => 'popular_star',
                'title' => '年度人气之星',
                'description' => '深受全站读者喜爱，作品累计获得海量点赞与认可',
                'condition_text' => '全站作品累计获赞达到 1,000 次',
                'icon_url' => '/storage/badges/PopularFapperAward.png',
                'rarity' => 'rare',
                'trophy_points' => 20,
                'award_type' => 'auto',
                'criteria' => ['likes_received' => 1000],
                'display_order' => 70,
            ],
            [
                'category_id' => $categories['creation']->id,
                'code' => 'top_collected',
                'title' => '宝藏收藏家',
                'description' => '内容极具实用价值与深度，被社区用户广泛收藏',
                'condition_text' => '作品被加入收藏夹总次数满 500 次',
                'icon_url' => '/storage/badges/top_collected.png',
                'rarity' => 'epic',
                'trophy_points' => 30,
                'award_type' => 'auto',
                'criteria' => ['collections_received' => 500],
                'display_order' => 80,
            ],

            // ================= 社交与互动 (Interaction) =================
            [
                'category_id' => $categories['interaction']->id,
                'code' => 'comment_enthusiast',
                'title' => '热心点评官',
                'description' => '乐于交流表达，在评论区留下了大量真知灼见',
                'condition_text' => '累计发表有效评论达到 100 条',
                'icon_url' => '/storage/badges/comment_enthusiast.png',
                'rarity' => 'common',
                'trophy_points' => 10,
                'award_type' => 'auto',
                'criteria' => ['comments_count' => 100],
                'display_order' => 90,
            ],
            [
                'category_id' => $categories['interaction']->id,
                'code' => 'generous_supporter',
                'title' => '慷慨伯乐',
                'description' => '积极支持优质创作者，购买并打赏心仪的原创数字资产',
                'condition_text' => '累计完成商城资产购买或支持满 10 次',
                'icon_url' => '/storage/badges/generous_supporter.png',
                'rarity' => 'rare',
                'trophy_points' => 20,
                'award_type' => 'auto',
                'criteria' => ['purchases_count' => 10],
                'display_order' => 100,
            ],
            [
                'category_id' => $categories['interaction']->id,
                'code' => 'trend_setter',
                'title' => '领航拓路者',
                'description' => '引人注目的社交焦点，收获了大量忠实频道的追随者',
                'condition_text' => '个人频道订阅者数量突破 100 位',
                'icon_url' => '/storage/badges/trend_setter.png',
                'rarity' => 'epic',
                'trophy_points' => 35,
                'award_type' => 'auto',
                'criteria' => ['subscribers_count' => 100],
                'display_order' => 110,
            ],

            // ================= 社区里程碑 (Milestone) =================
            [
                'category_id' => $categories['milestone']->id,
                'code' => 'anniversary_1y',
                'title' => '一载相知',
                'description' => '入驻社区满 1 周年，感谢一路风雨同行',
                'condition_text' => '注册账号时间达到 365 天',
                'icon_url' => '/storage/badges/anniversary_1y.png',
                'rarity' => 'rare',
                'trophy_points' => 15,
                'award_type' => 'auto',
                'criteria' => ['registered_days' => 365],
                'display_order' => 120,
            ],
            [
                'category_id' => $categories['milestone']->id,
                'code' => 'anniversary_3y',
                'title' => '三载风华',
                'description' => '入驻社区满 3 周年，已然成为平台的坚实中坚',
                'condition_text' => '注册账号时间达到 1095 天',
                'icon_url' => '/storage/badges/anniversary_3y.png',
                'rarity' => 'epic',
                'trophy_points' => 30,
                'award_type' => 'auto',
                'criteria' => ['registered_days' => 1095],
                'display_order' => 130,
            ],
            [
                'category_id' => $categories['milestone']->id,
                'code' => 'anniversary_5y',
                'title' => '5周年元老开拓者',
                'description' => '加入社区满 5 年，见证平台从诞生到繁荣的每一步',
                'condition_text' => '注册账号满 1825 天',
                'icon_url' => '/storage/badges/5years2.png',
                'rarity' => 'legendary',
                'trophy_points' => 50,
                'award_type' => 'auto',
                'criteria' => ['registered_days' => 1825],
                'display_order' => 140,
            ],

            // ================= 限定与特殊贡献 (Special) =================
            [
                'category_id' => $categories['special']->id,
                'code' => 'new_year_2026',
                'title' => '2026 新年专属纪念',
                'description' => '参与 2026 元旦跨年盛典活动专属限定获得',
                'condition_text' => '2026 元旦期间登录并发布新年寄语',
                'icon_url' => '/storage/badges/medal2026.png',
                'rarity' => 'epic',
                'trophy_points' => 20,
                'award_type' => 'manual',
                'criteria' => null,
                'display_order' => 150,
            ],
            [
                'category_id' => $categories['special']->id,
                'code' => 'community_guardian',
                'title' => '社区秩序守望者',
                'description' => '热心提交平台安全漏洞、反馈重大 Bug 或维护合规社区氛围',
                'condition_text' => '由管理员手动评定与特别颁发',
                'icon_url' => '/storage/badges/guardian.png',
                'rarity' => 'legendary',
                'trophy_points' => 50,
                'award_type' => 'manual',
                'criteria' => null,
                'display_order' => 160,
            ],
        ];

        // 3. 批量更新/入库
        foreach ($medals as $medalData) {
            Medal::updateOrCreate(
                ['code' => $medalData['code']],
                array_merge($medalData, ['is_active' => true])
            );
        }
    }
}
