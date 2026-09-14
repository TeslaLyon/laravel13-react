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
use App\Models\WalletOrder;
use App\Models\Wallet;
use App\Enums\WalletStatus;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {
    }

    /**
     * 渲染钱包资产中心页面（双资产 + 异步延迟加载）
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('wallet/index', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '我的资产钱包', 'href' => null],
            ],
            // 核心资产数据：延迟加载
            'wallet' => Inertia::defer(function () use ($user) {
                $wallet = $this->walletService->getOrCreateWallet($user);

                return [
                    'id' => $wallet->id,
                    'user_id' => $wallet->user_id,
                    'balance' => is_numeric($wallet->balance) ? (float) $wallet->balance : 0.0,
                    'frozen_balance' => is_numeric($wallet->frozen_balance) ? (float) $wallet->frozen_balance : 0.0,
                    'coins' => (int) ($wallet->coins ?? 0),
                    'frozen_coins' => (int) ($wallet->frozen_coins ?? 0),
                    'total_recharge' => is_numeric($wallet->total_recharge) ? (float) $wallet->total_recharge : 0.0,
                    'total_spent' => is_numeric($wallet->total_spent) ? (float) $wallet->total_spent : 0.0,
                    'total_withdrawn' => is_numeric($wallet->total_withdrawn) ? (float) $wallet->total_withdrawn : 0.0,
                    'total_earned_coins' => (int) ($wallet->total_earned_coins ?? 0),
                    'status' => $wallet->status instanceof \BackedEnum ? $wallet->status->value : (int) $wallet->status,
                    'status_label' => method_exists($wallet->status, 'label') ? $wallet->status->label() : '正常',
                    'version' => (int) ($wallet->version ?? 0),
                ];
            }),

            // 交易流水列表：延迟加载
            'transactions' => Inertia::defer(function () use ($user) {
                $wallet = $this->walletService->getOrCreateWallet($user);

                return $wallet->transactions()
                    ->latest('id')
                    ->paginate(10)
                    ->withQueryString()
                    ->through(fn($tx) => [
                        'id' => $tx->id,
                        'trx_no' => $tx->trx_no ?? ('#' . $tx->id),
                        'currency_type' => $tx->currency_type ?? 'balance',
                        'type' => $tx->type instanceof \BackedEnum ? $tx->type->value : (string) $tx->type,
                        'type_label' => method_exists($tx->type, 'label') ? $tx->type->label() : (string) ($tx->type_label ?? $tx->type),
                        'direction' => (int) ($tx->direction ?? ($tx->amount >= 0 ? 1 : -1)),
                        'amount' => is_numeric($tx->amount) ? (float) $tx->amount : 0.0,
                        'balance_before' => is_numeric($tx->balance_before) ? (float) $tx->balance_before : 0.0,
                        'balance_after' => is_numeric($tx->balance_after) ? (float) $tx->balance_after : 0.0,
                        'description' => $tx->description ?? '',
                        'reference_id' => $tx->reference_id,
                        'created_at' => $tx->created_at?->format('Y-m-d H:i:s') ?? '',
                    ]);
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

            $walletOrder->update([
                'gateway_order_id' => $gatewayOrderId,
                'gateway_pay_id' => $gatewayData['pay_id'] ?? null,
                'pay_url' => $cashierPayUrl,
                'really_amount' => (int) ($gatewayData['really_price'] ?? $amountInCents),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'order_no' => $walletOrder->order_no,
                    'order_id' => $gatewayOrderId,
                    'pay_url' => $cashierPayUrl,
                    'tissues' => $tissues,
                ],
            ]);
        } catch (Exception $e) {
            $walletOrder->update(['status' => WalletOrder::STATUS_CLOSED]);

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
        Log::info('[NOTIFY-RECEIVE] 收到网关回调 (GET):', [
            'headers' => [
                'x-app-key' => $request->header('X-App-Key'),
                'x-timestamp' => $request->header('X-Timestamp'),
                'x-nonce' => $request->header('X-Nonce'),
                'x-signature' => $request->header('X-Signature'),
            ],
            'query' => $request->query(),
        ]);

        // 1. 校验 Header 签名与时间戳防重放
        if (!$this->verifyHeaderSignature($request)) {
            Log::warning('[NOTIFY-REJECT] Header 验签未通过或时间戳超时');
            return response('fail: signature verification failed', 401)->header('Content-Type', 'text/plain');
        }

        // 2. 获取 GET 参数
        $gatewayOrderId = (string) $request->query('order_id', '');
        $rawType = $request->query('type');
        $rawPrice = $request->query('price');
        $rawReallyPrice = $request->query('reallyPrice');

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

                // C. 解析实付金额（单位：分）并执行递增“防少付”拦截
                $reallyAmountInCents = $this->normalizeAmountToCents($rawReallyPrice, $order->amount);
                if ($reallyAmountInCents < $order->amount) {
                    throw new Exception("实付金额不足：订单标价={$order->amount}分, 实付={$reallyAmountInCents}分");
                }

                // D. 锁定用户钱包主体
                $wallet = Wallet::where('user_id', $order->user_id)
                    ->lockForUpdate()
                    ->first();

                if (!$wallet) {
                    throw new Exception("用户 [{$order->user_id}] 钱包主体不存在");
                }

                $tissuesFormatted = number_format($reallyAmountInCents / 100, 2, '.', '');

                // 🌟 核心：直接委托给 WalletService 完成资产入账、签名更新与流水落盘
                // 彻底替代手动加锁计算以及 4 参数调用 6 参数的报错！
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
                        'query_params' => $request->query(),
                    ]
                );

                // E. 更新充值订单状态
                $order->update([
                    'status' => WalletOrder::STATUS_PAID,
                    'really_amount' => $reallyAmountInCents,
                    'paid_at' => now(),
                    'raw_callback' => $request->query(),
                ]);

                Log::info("[NOTIFY-SUCCESS] 订单 [{$order->order_no}] 递增金额核验一致并成功入账！流水号: [{$transaction->trx_no}]");
            });

            // 3. 返回纯文本 success 告知网关终止重试
            return response('success', 200)->header('Content-Type', 'text/plain');

        } catch (Exception $e) {
            Log::error('[NOTIFY-ERROR] 回调核验入账失败: ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response('fail: ' . $e->getMessage(), 400)->header('Content-Type', 'text/plain');
        }
    }

    /**
     * 校验 Header 签名与时间戳防重放
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
        if (!hash_equals($configuredAppKey, $appKey)) {
            Log::warning("[VERIFY-FAIL] AppKey 不匹配: [{$appKey}]");
            return false;
        }

        if (!is_numeric($timestamp) || abs(time() - (int) $timestamp) > 300) {
            Log::warning("[VERIFY-FAIL] 时间戳超时或不合法: [{$timestamp}]");
            return false;
        }

        $appSecret = (string) config('services.vmq.app_secret');
        $signPayload = $appKey . $timestamp . $nonce;
        $expectedSignature = hash_hmac('sha256', $signPayload, $appSecret);

        return hash_equals(strtolower($expectedSignature), strtolower($signature));
    }

    /**
     * 核对 GET 参数一致性
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
     * 将金额统一规格化为“分”
     */
    protected function normalizeAmountToCents(mixed $rawAmount, int $defaultCents): int
    {
        if ($rawAmount === null || $rawAmount === '') {
            return $defaultCents;
        }

        $numericVal = (float) $rawAmount;
        if ($numericVal <= 0) {
            return $defaultCents;
        }

        $converted = (int) round($numericVal * 100);

        if ($converted >= $defaultCents) {
            return $converted;
        }

        if ((int) $numericVal >= $defaultCents) {
            return (int) $numericVal;
        }

        return $converted;
    }

    /**
     * 查询订单支付状态（供前端轮询）
     */
    public function checkOrderStatus(string $orderNo)
    {
        $order = WalletOrder::where('order_no', $orderNo)
            ->where('user_id', Auth::id())
            ->select(['order_no', 'status', 'really_amount', 'paid_at'])
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => '订单不存在'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_no' => $order->order_no,
                'is_paid' => $order->status === WalletOrder::STATUS_PAID,
                'status' => $order->status,
            ],
        ]);
    }
}
