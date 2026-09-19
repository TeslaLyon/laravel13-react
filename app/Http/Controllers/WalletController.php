<?php

namespace App\Http\Controllers;

use App\Services\WalletService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Exception;
use App\Services\Payment\ThirdPartyApiClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use App\Models\WalletOrder;
use App\Models\Wallet;
use App\Enums\WalletStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Sleep;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {
    }

    /**
     * 渲染钱包资产中心页面（双资产 + 延迟加载 + 记忆化去重 + 纯整型输出）
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // 🌟 1. 请求级记忆化闭包：消除双 defer 闭包并发解析时的重复数据库查询
        $cachedWallet = null;
        $getWallet = function () use ($user, &$cachedWallet) {
            return $cachedWallet ??= $this->walletService->getOrCreateWallet($user);
        };

        return Inertia::render('wallet/index', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '我的资产钱包', 'href' => null],
            ],

            // 🌟 2. 核心资产数据：延迟加载，全部字段统一为 (int) 整型分
            'wallet' => Inertia::defer(function () use ($getWallet) {
                $wallet = $getWallet();

                return [
                    'id' => $wallet->id,
                    'user_id' => $wallet->user_id,

                    // 现金法币维度（单位：分，直出整型，由前端展示层除以 100 格式化）
                    'balance' => (int) ($wallet->balance ?? 0),
                    'frozen_balance' => (int) ($wallet->frozen_balance ?? 0),

                    // 虚拟金币维度（单位：个）
                    'coins' => (int) ($wallet->coins ?? 0),
                    'frozen_coins' => (int) ($wallet->frozen_coins ?? 0),

                    // 财务累计统计指标（单位：分或个）
                    'total_recharge' => (int) ($wallet->total_recharge ?? 0),
                    'total_spent' => (int) ($wallet->total_spent ?? 0),
                    'total_withdrawn' => (int) ($wallet->total_withdrawn ?? 0),
                    'total_earned_coins' => (int) ($wallet->total_earned_coins ?? 0),

                    // 状态管控
                    'status' => $wallet->status instanceof \BackedEnum ? $wallet->status->value : (int) $wallet->status,
                    'status_label' => method_exists($wallet->status, 'label') ? $wallet->status->label() : '正常',
                    'version' => (int) ($wallet->version ?? 0),
                ];
            }),

            // 🌟 3. 交易流水列表：延迟加载，支持局部刷新与整型分
            'transactions' => Inertia::defer(function () use ($getWallet) {
                $wallet = $getWallet();

                return $wallet->transactions()
                    ->with('source')
                    ->latest('id')
                    ->paginate(10)
                    ->withQueryString()
                    ->through(function ($tx) {
                        $type = $tx->type instanceof \BackedEnum ? $tx->type->value : (string) $tx->type;
                        $typeLabel = method_exists($tx->type, 'label') ? $tx->type->label() : (string) ($tx->type_label ?? $tx->type);

                        // 🌟 提取充值方式：优先从流水 metadata，其次从关联充值订单 source
                        $paymentMethod = $tx->metadata['payment_method'] ?? null;
                        if (!$paymentMethod && !empty($tx->metadata['request_payload']['type'])) {
                            $paymentMethod = match ((int) $tx->metadata['request_payload']['type']) {
                                1 => 'wechat',
                                2 => 'alipay',
                                default => null,
                            };
                        }
                        if (!$paymentMethod && $tx->source instanceof \App\Models\WalletOrder) {
                            $paymentMethod = $tx->source->payment_method ?? null;
                        }

                        $paymentMethodLabel = match ($paymentMethod) {
                            'wechat', 'wxpay' => '微信',
                            'alipay' => '支付宝',
                            default => null,
                        };

                        // 若为充值业务类型，将充值方式融合进业务类型名称（如“微信充值”、“支付宝充值”）
                        if ($type === 'recharge' && $paymentMethodLabel) {
                            $typeLabel = "{$paymentMethodLabel}充值";
                        }

                        return [
                            'id' => $tx->id,
                            'trx_no' => $tx->trx_no ?? ('#' . $tx->id),
                            'currency_type' => $tx->currency_type ?? 'balance',
                            'type' => $type,
                            'type_label' => $typeLabel,
                            'payment_method' => $paymentMethod,
                            'payment_method_label' => $paymentMethodLabel,
                            'direction' => (int) ($tx->direction ?? ($tx->amount >= 0 ? 1 : -1)),

                            // 核心金额字段：直出整型分，杜绝 float 精度丢失
                            'amount' => (int) $tx->amount,
                            'balance_before' => (int) $tx->balance_before,
                            'balance_after' => (int) $tx->balance_after,

                            'description' => $tx->description ?? '',
                            'reference_id' => $tx->reference_id,
                            'created_at' => $tx->created_at?->format('Y-m-d H:i:s') ?? '',
                        ];
                    });
            }),
        ]);
    }

    /**
     * 获取系统支持的充值渠道及预设充值档位
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        $methods = collect(config('wallet.payment_methods', []))
            ->where('is_active', true)
            ->map(function ($method) {
                if (!empty($method['logo'])) {
                    $method['logo'] = asset($method['logo']);
                }
                return $method;
            })
            ->values();

        $depositAmounts = config('wallet.deposit_amounts', []);

        return response()->json([
            'success' => true,
            'data' => [
                'methods' => $methods,
                'deposit_amounts' => $depositAmounts,
            ],
        ]);
    }

    /**
     * 充值下单：本地预先落库，再调用第三方网关接口
     */
    public function deposit(Request $request, ThirdPartyApiClient $apiClient)
    {
        $allowedAmounts = collect(config('wallet.deposit_amounts', []))->pluck('amount')->toArray();

        $validated = $request->validate([
            'amount' => ['required', 'integer', Rule::in($allowedAmounts)],
            'payment_method' => ['required', 'string', Rule::in(['wechat', 'alipay'])],
        ]);

        $user = $request->user();
        $amountInCents = (int) $validated['amount'];
        $tissues = $amountInCents / 100;
        $subject = "获取 {$tissues} 纸巾";
        $orderNo = 'REC_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));

        $walletOrder = WalletOrder::create([
            'user_id' => $user->id,
            'order_no' => $orderNo,
            'payment_method' => $validated['payment_method'],
            'amount' => $amountInCents,
            'status' => WalletOrder::STATUS_PENDING,
            'subject' => $subject,
        ]);

        try {
            $gatewayData = $apiClient->createPaymentOrder(
                userId: 1,
                amountInCents: $amountInCents,
                channel: $validated['payment_method'],
                subject: $subject,
                notifyUrl: route('wallet.notify', [], true),
                returnUrl: route('wallet.index', [], true)
            );

            $cashierBaseUrl = rtrim(config('services.vmq.cashier_url', 'https://pay.536969.xyz'), '/');
            $gatewayOrderId = $gatewayData['order_id'] ?? '';

            $cashierPayUrl = !empty($gatewayOrderId)
                ? "{$cashierBaseUrl}/{$gatewayOrderId}"
                : ($gatewayData['pay_url'] ?? '');

            $reallyAmountInCents = $this->normalizeAmountToCents($gatewayData['really_price'] ?? null, $amountInCents);
            $reallyTissues = (float) round($reallyAmountInCents / 100, 2);

            $walletOrder->update([
                'gateway_order_id' => $gatewayOrderId,
                'gateway_pay_id' => $gatewayData['pay_id'] ?? null,
                'pay_url' => $cashierPayUrl,
                'really_amount' => $reallyAmountInCents,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'order_no' => $walletOrder->order_no,
                    'order_id' => $gatewayOrderId,
                    'pay_url' => $cashierPayUrl,
                    'tissues' => $reallyTissues,
                    'really_amount' => $reallyAmountInCents,
                    'amount' => $amountInCents,
                ],
            ]);
        } catch (Exception $e) {
            $walletOrder->update(['status' => WalletOrder::STATUS_CLOSED]);

            Log::error("充值下单异常 [{$walletOrder->order_no}]: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * 支付异步回调：支持递增金额实付入账，通过 WalletService 实现严格原子幂等入账
     */
    public function notify(Request $request)
    {
        Log::info('[NOTIFY-RECEIVE] 收到网关回调:', [
            'headers' => [
                'x-app-key' => $request->header('X-App-Key'),
                'x-timestamp' => $request->header('X-Timestamp'),
                'x-nonce' => $request->header('X-Nonce'),
                'x-signature' => $request->header('X-Signature'),
            ],
            'payload' => $request->all(),
        ]);

        // 1. 校验 Header 签名、时间戳与 Nonce 防重放
        if (!$this->verifyHeaderSignature($request)) {
            Log::warning('[NOTIFY-REJECT] Header 验签未通过、时间戳超时或 Nonce 重放');
            return response('fail: signature verification failed', 401)->header('Content-Type', 'text/plain');
        }

        // 2. 获取参数（全面兼容 GET、POST 表单及 JSON 请求体，并支持下划线与驼峰命名）
        $gatewayOrderId = (string) ($request->input('order_id') ?? $request->input('orderId') ?? '');
        $rawType = $request->input('type');
        $rawPrice = $request->input('price');
        $rawReallyPrice = $request->input('reallyPrice') ?? $request->input('really_price');

        if (empty($gatewayOrderId)) {
            Log::warning('[NOTIFY-REJECT] 缺少核心参数: order_id');
            return response('fail: missing order_id', 400)->header('Content-Type', 'text/plain');
        }

        try {
            DB::transaction(function () use ($gatewayOrderId, $rawType, $rawPrice, $rawReallyPrice, $request) {
                // A. 加行级排他锁锁定充值订单
                $order = WalletOrder::where('gateway_order_id', $gatewayOrderId)
                    ->lockForUpdate()
                    ->first();

                if (!$order) {
                    throw new Exception("订单不存在: [{$gatewayOrderId}]");
                }

                // 幂等防重：若订单早已入账，直接幂等返回
                if ($order->status === WalletOrder::STATUS_PAID) {
                    Log::info("[NOTIFY-SKIP] 订单 [{$order->order_no}] 早已成功入账，直接幂等返回");
                    return;
                }

                // B. 核对渠道与标价一致性
                $this->assertOrderParametersMatch($order, $rawType, $rawPrice);

                // C. 准确解析实付金额（单位：分）并执行递增“防少付”拦截
                $reallyAmountInCents = $this->normalizeAmountToCents($rawReallyPrice, $order->amount);
                if ($reallyAmountInCents < $order->amount) {
                    throw new Exception("实付金额不足：订单标价={$order->amount}分, 实付={$reallyAmountInCents}分");
                }

                // D. 获取或自动初始化用户钱包（确保即便首次充值的用户也能顺利创建钱包入账）
                $user = $order->user ?? User::findOrFail($order->user_id);
                $wallet = $this->walletService->getOrCreateWallet($user);

                $tissuesFormatted = number_format($reallyAmountInCents / 100, 2, '.', '');

                // 🌟 核心：直接委托给 WalletService 完成资产入账、签名更新与流水落盘
                $transaction = $this->walletService->changeBalance(
                    wallet: $wallet,
                    amountCents: $reallyAmountInCents,
                    type: 'recharge',
                    description: "充值获取 {$tissuesFormatted} 纸巾",
                    source: $order,
                    referenceId: $order->gateway_order_id,
                    metadata: [
                        'order_no' => $order->order_no,
                        'gateway_pay_id' => $order->gateway_pay_id,
                        'order_amount' => $order->amount,
                        'really_amount' => $reallyAmountInCents,
                        'payment_method' => $order->payment_method,
                        'request_payload' => $request->all(),
                    ]
                );

                // E. 更新充值订单状态
                $order->update([
                    'status' => WalletOrder::STATUS_PAID,
                    'really_amount' => $reallyAmountInCents,
                    'paid_at' => now(),
                    'raw_callback' => $request->all(),
                ]);

                Log::info("[NOTIFY-SUCCESS] 订单 [{$order->order_no}] 递增金额核验一致并成功入账！流水号: [{$transaction->trx_no}]");
            });

            // 3. 返回纯文本 success 告知网关终止重试
            return response('success', 200)->header('Content-Type', 'text/plain');

        } catch (Exception $e) {
            Log::error('[NOTIFY-ERROR] 回调核验入账失败: ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response('fail: verification or processing failed', 400)->header('Content-Type', 'text/plain');
        }
    }

    /**
     * 校验 Header 签名与时间戳、Nonce 防重放
     */
    protected function verifyHeaderSignature(Request $request): bool
    {
        $appKey = (string) $request->header('X-App-Key', '');
        $timestamp = (string) $request->header('X-Timestamp', '');
        $nonce = (string) $request->header('X-Nonce', '');
        $signature = (string) $request->header('X-Signature', '');

        if (empty($appKey) || empty($timestamp) || empty($nonce) || empty($signature)) {
            Log::warning('[VERIFY-FAIL] 缺失认证 Header');
            return false;
        }

        $configuredAppKey = (string) config('services.vmq.app_key');
        $appSecret = (string) config('services.vmq.app_secret');

        if (empty($configuredAppKey) || empty($appSecret)) {
            Log::error('[VERIFY-FAIL] 系统未正确配置 VMQ_APP_KEY 或 VMQ_APP_SECRET');
            return false;
        }

        if (!hash_equals($configuredAppKey, $appKey)) {
            Log::warning("[VERIFY-FAIL] AppKey 不匹配: [{$appKey}]");
            return false;
        }

        // 时间戳窗口限制在 300 秒以内
        if (!is_numeric($timestamp) || abs(time() - (int) $timestamp) > 300) {
            Log::warning("[VERIFY-FAIL] 时间戳超时或不合法: [{$timestamp}]");
            return false;
        }

        // 🌟 核心防重放：利用 Cache 原子锁，300 秒内同一个 Nonce 仅允许消费一次
        $nonceCacheKey = "vmq:notify:nonce:{$nonce}";
        if (!Cache::add($nonceCacheKey, 1, 300)) {
            Log::warning("[VERIFY-FAIL] Nonce 已被使用，拒绝重放请求: [{$nonce}]");
            return false;
        }

        // 计算预期签名并恒定时间比对
        $signPayload = $appKey . $timestamp . $nonce;
        $expectedSignature = hash_hmac('sha256', $signPayload, $appSecret);

        return hash_equals(strtolower($expectedSignature), strtolower($signature));
    }

    /**
     * 核对 GET/POST 参数一致性
     */
    protected function assertOrderParametersMatch(WalletOrder $order, mixed $rawType, mixed $rawPrice): void
    {
        if ($rawType !== null) {
            $expectedChannel = match ((int) $rawType) {
                1 => 'wechat',
                2 => 'alipay',
                default => 'unknown',
            };

            if ($order->payment_method !== $expectedChannel) {
                throw new Exception("渠道类型不匹配: 订单[{$order->payment_method}] vs 网关[{$expectedChannel}]");
            }
        }

        if ($rawPrice !== null) {
            $incomingPrice = (float) $rawPrice;
            $orderCents = (int) $order->amount;
            $incomingCents = (int) round($incomingPrice * 100);

            $isMatch = ($incomingCents === $orderCents) || ((int) $incomingPrice === $orderCents);
            if (!$isMatch) {
                throw new Exception("标价金额不匹配: 订单[{$orderCents}分] vs 网关[{$incomingCents}分]");
            }
        }
    }

    /**
     * 将网关参数安全解析为“分”
     * 严谨兼容：浮点“元”（10.01 / 10.00）、整型“分”（1001）、纯整型“元”（10）
     */
    protected function normalizeAmountToCents(mixed $rawAmount, int $defaultCents): int
    {
        if ($rawAmount === null || $rawAmount === '') {
            return $defaultCents;
        }

        $rawStr = trim((string) $rawAmount);
        if (!is_numeric($rawStr)) {
            return $defaultCents;
        }

        $numericVal = (float) $rawStr;
        if ($numericVal <= 0) {
            return $defaultCents;
        }

        // 1. 如果包含小数点（例如 "10.01" 或 "10.00"），绝对是以“元”为单位，换算为分
        if (str_contains($rawStr, '.')) {
            return (int) round($numericVal * 100);
        }

        // 2. 如果不带小数点且数值本身已经大于等于标价分值（例如 1000、1001），说明本身就是“分”
        if ((int) $numericVal >= $defaultCents) {
            return (int) $numericVal;
        }

        // 3. 不带小数点的整型“元”（例如标价 1000 分，网关回传整型 10 元）
        return (int) round($numericVal * 100);
    }

    /**
     * 查询订单支付状态（供前端轮询）
     */
    public function checkOrderStatus(string $orderNo)
    {
        $order = WalletOrder::where('order_no', $orderNo)
            ->where('user_id', Auth::id())
            ->select(['order_no', 'status', 'amount', 'really_amount', 'paid_at'])
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => '订单不存在'], 404);
        }

        $effectiveAmount = (int) ($order->really_amount ?: $order->amount);
        $reallyTissues = (float) round($effectiveAmount / 100, 2);

        return response()->json([
            'success' => true,
            'data' => [
                'order_no' => $order->order_no,
                'is_paid' => $order->status === WalletOrder::STATUS_PAID,
                'status' => $order->status,
                'amount' => $order->amount,
                'really_amount' => $effectiveAmount,
                'tissues' => $reallyTissues,
            ],
        ]);
    }
}
