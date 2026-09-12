<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Node;
use App\Models\ForumNotice;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ForumNoticeSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1. 获取管理员/作者用户与现有版块
            $adminUser = User::first() ?? User::factory()->create(['name' => '社区管理员']);
            $nodes = Node::all();

            // 如果已有公告数据，先清理旧数据保证幂等
            ForumNotice::query()->forceDelete();

            // 🎯 2. 创建【全站/首页级公共通告】(node_id = null)
            $globalNotices = [
                [
                    'node_id' => null,
                    'title' => '🎉 2026 社区创作者激励计划与夏季专题大赛正式启动！',
                    'content' => "为鼓励优质原创内容产出，本季度社区联合多家技术与设计先锋品牌设立专项创作基金。\n凡发布通过认证的高质量专栏或资源分享贴，均可参与瓜分万元奖池并解锁专属「先锋创作者」实体金牌勋章！",
                    'type' => 'success',
                    'link_url' => '/forum/threads/1',
                    'link_text' => '了解大赛章程与申报通道',
                    'is_active' => true,
                    'is_dismissible' => true,
                    'display_order' => 100, // 高优先级排在第一位
                    'starts_at' => now()->subDays(1),
                    'ends_at' => now()->addMonths(2),
                    'created_by' => $adminUser->id,
                ],
                [
                    'node_id' => null,
                    'title' => '⚠️ 社区服务器计划于本周日凌晨 02:00 - 04:00 进行数据库架构升级维护',
                    'content' => '维护期间论坛将临时开启只读保护模式，主题浏览与资料检索不受影响，发帖、回帖与个人中心修改将短暂暂停。请各位创作者提前保存草稿。',
                    'type' => 'warning',
                    'link_url' => null,
                    'link_text' => null,
                    'is_active' => true,
                    'is_dismissible' => true,
                    'display_order' => 80,
                    'starts_at' => now()->subHours(6),
                    'ends_at' => now()->addDays(5),
                    'created_by' => $adminUser->id,
                ],
            ];

            foreach ($globalNotices as $noticeData) {
                ForumNotice::create($noticeData);
            }

            // 🎯 3. 为各个具体版块创建【版块专属通告】
            if ($nodes->isNotEmpty()) {
                // 为第一个版块添加规范提示
                $firstNode = $nodes->first();
                ForumNotice::create([
                    'node_id' => $firstNode->id,
                    'title' => "📌 【{$firstNode->title}】版块发帖指引与精选推荐标准 (2026 修订版)",
                    'content' => "欢迎来到本版块！为了保障交流质量，发布技术解析与资源贴时请务必使用对应的分类前缀标签（如 [Verified] 或 [Tutorial]）。\n字数不少于 200 字且排版清晰的主题帖将自动收录至版块置顶精华区。",
                    'type' => 'info',
                    'link_url' => "/forum/nodes/{$firstNode->id}",
                    'link_text' => '查阅版块详细公约',
                    'is_active' => true,
                    'is_dismissible' => true,
                    'display_order' => 50,
                    'starts_at' => now()->subDays(3),
                    'ends_at' => null,
                    'created_by' => $adminUser->id,
                ]);

                // 如果存在多个版块，为第二个版块添加严厉警告类通告（不可关闭）
                if ($nodes->count() >= 2) {
                    $secondNode = $nodes->skip(1)->first();
                    ForumNotice::create([
                        'node_id' => $secondNode->id,
                        'title' => "🚫 【严厉警示】严禁在本版块发布任何形式的未授权引流与灰产交易信息",
                        'content' => '近期风控系统捕获多起高仿外链与钓鱼二维码推广。一经核实将永久封禁账号并公示关联 IP 地址，情节严重者将依法移交司法机关处理。请大家共同维护绿色网络生态。',
                        'type' => 'danger',
                        'link_url' => null,
                        'link_text' => null,
                        'is_active' => true,
                        'is_dismissible' => false, // 🎯 关键测试：不可被用户手动点击 X 关闭
                        'display_order' => 90,
                        'starts_at' => now()->subDays(1),
                        'ends_at' => null,
                        'created_by' => $adminUser->id,
                    ]);
                }
            }

            $totalCount = ForumNotice::count();
            $this->command->info("🎉 成功生成 {$totalCount} 条公告模拟数据（覆盖全站与具体版块）！");
        });
    }
}
