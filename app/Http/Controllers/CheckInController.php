<?php

namespace App\Http\Controllers;

use App\Services\CheckInConfigService;
use App\Services\CheckInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;
use Illuminate\Support\Sleep;
use Carbon\Carbon;

class CheckInController extends Controller
{
    public function __construct(
        protected CheckInService $checkInService,
        protected CheckInConfigService $configService
    ) {
    }

    /**
     * 每日打卡中心（支持 Inertia 延迟加载）
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $requestedMonth = $request->query('month');

        // 1. 动态生成仅允许访问的月份白名单（本月与上月）
        $now = Carbon::today();
        $allowedMonths = [
            $now->format('Y-m'),                    // 本月 (如 2026-08)
            $now->copy()->subMonth()->format('Y-m'), // 上月 (如 2026-07)
        ];

        // 2. 校验传入的月份参数，非法或超出范围时强制重置为本月
        $targetMonth = in_array($requestedMonth, $allowedMonths, true)
            ? $requestedMonth
            : $allowedMonths[0];

        return Inertia::render('CheckIn/Index', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '每日签到', 'href' => null],
            ],
            // 🌟 传入当月动态适配后的里程碑配置
            'milestonesConfig' => $this->configService->getMilestonesForMonth($targetMonth),
            'baseCoins' => $this->configService->getBaseCoins(),
            // 允许切换的月份元信息
            'allowedMonths' => $allowedMonths,
            // 延迟加载日历与用户资产
            'checkInData' => Inertia::defer(function () use ($user, $targetMonth) {
                return $this->checkInService->getMonthlyCalendarData($user, $targetMonth);
            }),
        ]);
    }

    /**
     * 执行打卡 / 补签接口（支持 JSON 异步响应与传统重定向）
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $date = $request->input('date'); // 若携带 date 参数则代表执行补签

        try {
            $result = $this->checkInService->execute($user, $date);

            // 🌟 1. 优先响应 JSON 异步请求，实现前端零刷新交互
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $date ? '补签成功！' : '打卡成功！',
                    'data' => array_merge($result, [
                        'signed_date' => $date ?: Carbon::today()->toDateString(),
                        'is_make_up' => (bool) $date,
                    ]),
                ]);
            }

            return back()->with('success_reward', [
                'message' => $date ? '补签成功！' : '打卡成功！',
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            // 🌟 2. 异常统一转为 422 JSON，前端精准捕获
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }
}
