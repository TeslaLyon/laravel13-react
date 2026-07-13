<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class HelpCenterController extends Controller
{
    /**
     * 我们的静态数据源（模拟数据库）
     */
    private array $data = [
        'categories' => [
            ['id' => '1', 'title' => '账户与个人资料', 'description' => '管理您的账号设置、密码及安全隐私。', 'icon' => 'User'],
            ['id' => '2', 'title' => '订阅与账单', 'description' => '查看支付历史、发票及管理您的订阅计划。', 'icon' => 'CreditCard'],
            ['id' => '3', 'title' => '内容与播放', 'description' => '解决视频播放、清晰度及离线下载相关问题。', 'icon' => 'MonitorPlay'],
        ],
        'faqs' => [
            ['id' => 'q1', 'question' => '如何重置我的登录密码？', 'answer' => '在登录页面点击“忘记密码”，输入您的注册邮箱。我们会向您发送一封包含密码重置链接的邮件。'],
            ['id' => 'q2', 'question' => '我可以与他人共享我的订阅账号吗？', 'answer' => '为了保证账户安全，我们建议每个账户仅限个人使用。分享账号可能会导致被系统暂时锁定。'],
        ],
        // 文章列表，按照 category_id 分类存放
        'articles' => [
            '1' => [
                ['id' => 'a1', 'title' => '如何修改我的登录密码？', 'excerpt' => '了解如何通过邮箱验证重置或修改您的账户密码。'],
                ['id' => 'a2', 'title' => '更新个人资料信息', 'excerpt' => '更改您的头像、昵称以及绑定的联系方式。'],
            ],
            '2' => [
                ['id' => 'a3', 'title' => '如何查看我的账单历史？', 'excerpt' => '您可以随时在设置中心下载过去的电子发票。'],
                ['id' => 'a4', 'title' => '取消订阅会怎样？', 'excerpt' => '取消后，您仍可使用当前服务直到本计费周期结束。'],
            ],
        ]
    ];

    /**
     * 渲染帮助中心首页
     */
    public function index(): Response
    {
        return Inertia::render('helpCenter/index', [
            'categories' => $this->data['categories'],
            'faqs' => $this->data['faqs'],
        ]);
    }

    /**
     * 渲染特定分类详情页
     * 因为没有数据库模型绑定了，我们直接接收 URL 中的 ID 字符串
     */
    public function category(string $id): Response
    {
        // 1. 在静态数组中查找对应的分类
        $category = collect($this->data['categories'])->firstWhere('id', $id);

        // 如果找不到该分类，抛出 404 错误
        if (!$category) {
            throw new NotFoundHttpException('未找到该帮助分类。');
        }

        // 2. 获取该分类下的文章（如果不存在则返回空数组）
        $articles = $this->data['articles'][$id] ?? [];

        return Inertia::render('helpCenter/category', [
            'category' => $category,
            'articles' => $articles,
        ]);
    }
}
