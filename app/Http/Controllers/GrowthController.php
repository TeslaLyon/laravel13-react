<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GrowthService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GrowthController extends Controller
{
    public function __construct(
        protected GrowthService $growthService
    ) {
    }

    /**
     * 渲染成长等级中心页面（采用 Inertia 延迟加载）
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Growth/Index', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => '/'],
                ['title' => '成长等级中心', 'href' => null],
            ],
            // 🌟 使用 Inertia::defer 延迟加载成长中心聚合数据与日志流水
            'growthData' => Inertia::defer(fn() => $this->growthService->getGrowthCenterData($user)),
        ]);
    }
}
