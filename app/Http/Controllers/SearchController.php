<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function index(Request $request): Response
    {
        $query = $request->input('q', '');

        // 初始化分组结果数组
        $results = [
            'help' => [],
            'blog' => [],
            'products' => [],
        ];

        if (!empty(trim($query))) {
            // 这里我们模拟从 Meilisearch 或数据库中查询不同模块的数据
            // 在实际项目中，你可以调用不同模型的 search() 方法

            // 1. 模拟搜索帮助中心
            $results['help'] = [
                ['id' => 'h1', 'title' => '如何修改密码？', 'excerpt' => '了解重置密码的详细步骤...', 'url' => '/help/article/h1'],
            ];

            // 2. 模拟搜索博客/动态
            $results['blog'] = [
                ['id' => 'b1', 'title' => 'Laravel 13 与 React 的完美结合', 'excerpt' => '探讨现代全栈开发的最佳实践...', 'url' => '/blog/b1'],
                ['id' => 'b2', 'title' => '2026年网站设计趋势', 'excerpt' => '极简主义与大圆角的回归...', 'url' => '/blog/b2'],
            ];

            // 3. 模拟搜索产品或服务
            $results['products'] = [
                ['id' => 'p1', 'title' => '高级订阅会员 (Pro)', 'excerpt' => '解锁所有功能，享受极速响应...', 'url' => '/pricing'],
            ];
        }

        return Inertia::render('search/index', [
            'query' => $query,
            'groupedResults' => $results,
        ]);
    }
}
