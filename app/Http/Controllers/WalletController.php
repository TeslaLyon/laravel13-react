<?php

namespace App\Http\Controllers;

use App\Services\WalletService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Sleep;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Exception;
use App\Services\Payment\ThirdPartyApiClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\WalletOrder;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {
    }

    // TODO：加载流水分页数据时采用inertiajs的只读取 table 中的数据，而不是整个页面
    // TODO: table 表格中的业务说明文字太多无法完全展示，考虑 hover 后显示全部
    /**
     * 渲染钱包资产中心页面（双资产 + 异步延迟加载）
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // 🌟 移除外层多余的 getOrCreateWallet 调用，完全交给 defer 闭包按需执行

        return Inertia::render('wallet/index', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '我的资产钱包', 'href' => null], // 当前页没有 URL
            ],
            // 1. 核心资产数据（现金 + 虚拟金币）：延迟加载
            'wallet' => Inertia::defer(function () use ($user) {
                $wallet = $this->walletService->getOrCreateWallet($user);

                return [
                    'id' => $wallet->id,
                    'user_id' => $wallet->user_id,

                    // 现金法币维度
                    'balance' => is_numeric($wallet->balance) ? (float) $wallet->balance : 0.0,
                    'frozen_balance' => is_numeric($wallet->frozen_balance) ? (float) $wallet->frozen_balance : 0.0,

                    // 虚拟金币维度
                    'coins' => (int) ($wallet->coins ?? 0),
                    'frozen_coins' => (int) ($wallet->frozen_coins ?? 0),

                    // 财务累计统计指标
                    'total_recharge' => is_numeric($wallet->total_recharge) ? (float) $wallet->total_recharge : 0.0,
                    'total_spent' => is_numeric($wallet->total_spent) ? (float) $wallet->total_spent : 0.0,
                    'total_withdrawn' => is_numeric($wallet->total_withdrawn) ? (float) $wallet->total_withdrawn : 0.0,
                    'total_earned_coins' => (int) ($wallet->total_earned_coins ?? 0),

                    // 状态管控
                    'status' => $wallet->status instanceof \BackedEnum ? $wallet->status->value : (int) $wallet->status,
                    'status_label' => method_exists($wallet->status, 'label') ? $wallet->status->label() : '正常',
                    'version' => (int) ($wallet->version ?? 0),
                ];
            }),

            // 2. 交易流水列表（支持现金/金币双币种与倒序分页）：延迟加载
            'transactions' => Inertia::defer(function () use ($user) {
                $wallet = $this->walletService->getOrCreateWallet($user);

                return $wallet->transactions()
                    ->latest('id') // 🌟 最新交易流水排在最前
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
                // 🌟 将相对路径转换为可直接访问的资源 URL
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
     * 充值下单：本地记录先落库，再调用第三方接口补全支付信息
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

            // 🌟 核心：从配置中读取收银台域名，并与返回的 order_id 进行拼接
            $cashierBaseUrl = rtrim(config('services.vmq.cashier_url', 'https://pay.536969.xyz'), '/');
            $gatewayOrderId = $gatewayData['order_id'] ?? '';

            // 构造真实的网页收银台 URL: https://pay.536969.xyz/VMQ2026...
            $cashierPayUrl = !empty($gatewayOrderId)
                ? "{$cashierBaseUrl}/{$gatewayOrderId}"
                : ($gatewayData['pay_url'] ?? '');

            // 更新本地订单记录
            $walletOrder->update([
                'gateway_order_id' => $gatewayOrderId,
                'gateway_pay_id' => $gatewayData['pay_id'] ?? null,
                'pay_url' => $cashierPayUrl, // 存入拼接好的收银台地址
                'really_amount' => (int) ($gatewayData['really_price'] ?? $amountInCents),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'order_no' => $walletOrder->order_no,
                    'order_id' => $gatewayOrderId,
                    'pay_url' => $cashierPayUrl, // 传递拼接后的完整地址
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
     * 支付回调处理：杜绝 TODO，实现严格幂等入账
     */
    public function notify(Request $request)
    {
        // 1. 记录原始请求以便审计排查
        Log::info('收到第三方支付回调通知 (GET):', [
            'headers' => [
                'x-app-key' => $request->header('X-App-Key'),
                'x-timestamp' => $request->header('X-Timestamp'),
                'x-nonce' => $request->header('X-Nonce'),
                'x-signature' => $request->header('X-Signature'),
            ],
            'query' => $request->query(),
        ]);

        // 🌟 2. 校验 Header 中的凭证与 HMAC-SHA256 签名
        if (!$this->verifyHeaderSignature($request)) {
            Log::warning('支付通知 Header 验签失败或请求超时，拒绝处理');
            return response('fail: signature verification failed', 401)
                ->header('Content-Type', 'text/plain');
        }

        // 🌟 3. 提取 GET 查询参数
        $gatewayOrderId = (string) $request->query('order_id', '');
        $rawType = $request->query('type');
        $rawPrice = $request->query('price');
        $rawReallyPrice = $request->query('reallyPrice');

        if (empty($gatewayOrderId)) {
            Log::warning('支付通知缺失核心参数: order_id');
            return response('fail: missing order_id', 400)
                ->header('Content-Type', 'text/plain');
        }

        try {
            // 🌟 4. 开启数据库事务，原子比对参数并执行入账
            DB::transaction(function () use ($gatewayOrderId, $rawType, $rawPrice, $rawReallyPrice, $request) {
                // A. 加行级排他锁锁定充值订单
                $order = WalletOrder::where('gateway_order_id', $gatewayOrderId)
                    ->lockForUpdate()
                    ->first();

                // 幂等性防御：如果订单不存在或已处理完毕，安全退出
                if (!$order) {
                    Log::warning("未找到匹配的本地订单，网关单号: [{$gatewayOrderId}]");
                    throw new Exception("订单不存在");
                }

                if ($order->status === WalletOrder::STATUS_PAID) {
                    Log::info("订单 [{$order->order_no}] 早已成功入账，直接幂等返回");
                    return;
                }

                // 🌟 B. 逐项严格对比 GET 参数与本地数据库订单
                $this->assertOrderParametersMatch($order, $rawType, $rawPrice);

                // C. 解析实付金额（单位：分）
                // 若网关以元为单位返回，将其转换为分；若已是分则直接使用
                $reallyAmountInCents = $this->normalizeAmountToCents($rawReallyPrice, $order->amount);

                // D. 加行级排他锁锁定用户钱包
                $wallet = Wallet::where('user_id', $order->user_id)
                    ->lockForUpdate()
                    ->first();

                if (!$wallet) {
                    throw new Exception("用户 [{$order->user_id}] 钱包主体不存在");
                }

                if ($wallet->status !== Wallet::STATUS_ACTIVE) {
                    throw new Exception("用户钱包已被冻结或禁用，暂停入账");
                }

                // E. 计算余额快照与校验和
                $creditAmount = $order->amount; // 充值金额（单位：分）
                $balanceBefore = $wallet->balance;
                $balanceAfter = $balanceBefore + $creditAmount;
                $newVersion = $wallet->version + 1;

                // 生成新的 HMAC 防篡改摘要
                $newChecksum = Wallet::generateChecksum(
                    $wallet->user_id,
                    $balanceAfter,
                    $wallet->coins,
                    $newVersion
                );

                // F. 更新钱包资产
                $wallet->update([
                    'balance' => $balanceAfter,
                    'total_recharge' => $wallet->total_recharge + $creditAmount,
                    'version' => $newVersion,
                    'checksum' => $newChecksum,
                    'last_activity_at' => now(),
                ]);

                // G. 写入钱包审计流水
                $trxNo = 'TRX' . date('YmdHis') . strtoupper(Str::random(8));
                $tissues = $creditAmount / 100;

                WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'user_id' => $wallet->user_id,
                    'trx_no' => $trxNo,
                    'currency_type' => 'balance',
                    'type' => 'recharge',
                    'direction' => 1, // 收入 (+)
                    'amount' => $creditAmount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'source_type' => WalletOrder::class,
                    'source_id' => $order->id,
                    'reference_id' => $order->gateway_order_id,
                    'description' => "充值获取 {$tissues} 纸巾",
                    'metadata' => [
                        'order_no' => $order->order_no,
                        'gateway_pay_id' => $order->gateway_pay_id,
                        'really_amount' => $reallyAmountInCents,
                        'payment_method' => $order->payment_method,
                        'query_params' => $request->query(),
                    ],
                ]);

                // H. 更新订单状态为已支付
                $order->update([
                    'status' => WalletOrder::STATUS_PAID,
                    'really_amount' => $reallyAmountInCents,
                    'paid_at' => now(),
                    'raw_callback' => $request->query(),
                ]);

                Log::info("充值订单 [{$order->order_no}] 参数对比一致并成功入账，流水号: [{$trxNo}]");
            });

            // 5. 按照免签网关规范，输出纯文本小写 success，触发网关停止重试
            return response('success', 200)->header('Content-Type', 'text/plain');

        } catch (Exception $e) {
            Log::error('支付回调核验入账失败: ' . $e->getMessage());
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

        // 1. 基础完整性检查
        if (empty($appKey) || empty($timestamp) || empty($nonce) || empty($signature)) {
            Log::warning('验签失败：缺少必要的认证 Header 参数');
            return false;
        }

        // 2. 校验 AppKey 是否匹配本系统配置
        $configuredAppKey = (string) config('services.vmq.app_key');
        if (!hash_equals($configuredAppKey, $appKey)) {
            Log::warning("验签失败：AppKey 不匹配，接收到: [{$appKey}]");
            return false;
        }

        // 3. 时间戳格式与防重放窗口检查（限制 300 秒以内）
        if (!is_numeric($timestamp)) {
            return false;
        }

        $now = time();
        if (abs($now - (int) $timestamp) > 300) {
            Log::warning("验签失败：请求时间戳超出 300 秒允许窗口，当前时间: {$now}, 传入: {$timestamp}");
            return false;
        }

        // 4. 计算预期签名并比对
        $appSecret = (string) config('services.vmq.app_secret');
        if (empty($appSecret)) {
            Log::error('系统未配置 VMQ_APP_SECRET');
            return false;
        }

        // 拼接签名规则：AppKey + Timestamp + Nonce
        $signPayload = $appKey . $timestamp . $nonce;
        $expectedSignature = hash_hmac('sha256', $signPayload, $appSecret);

        // 使用恒定时间比较防止时序攻击，不区分大小写
        return hash_equals(strtolower($expectedSignature), strtolower($signature));
    }

    /**
     * 逐项核对 GET 参数与本地订单的一致性
     */
    protected function assertOrderParametersMatch(WalletOrder $order, mixed $rawType, mixed $rawPrice): void
    {
        // 1. 对比支付渠道类型（1=微信，2=支付宝）
        if ($rawType !== null) {
            $expectedChannel = match ((int) $rawType) {
                1 => 'wechat',
                2 => 'alipay',
                default => 'unknown',
            };

            if ($order->payment_method !== $expectedChannel) {
                throw new Exception("支付渠道类型不匹配：订单记录为 [{$order->payment_method}]，网关回调为 [{$expectedChannel}]");
            }
        }

        // 2. 对比标价金额（单位换算兼容）
        if ($rawPrice !== null) {
            $incomingPrice = (float) $rawPrice;
            $orderCents = (int) $order->amount;

            // 如果网关传的是“元”（例如 10.00），换算为分：10.00 * 100 = 1000
            $incomingCents = (int) round($incomingPrice * 100);

            // 同时兼容网关直接传“分”（例如 1000）的情况
            $isMatch = ($incomingCents === $orderCents) || ((int) $incomingPrice === $orderCents);

            if (!$isMatch) {
                throw new Exception("订单金额不匹配：订单标价分值为 [{$orderCents}]，网关回调金额为 [{$rawPrice}]");
            }
        }
    }

    /**
     * 将金额参数安全转换为“分”
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

        // 优先按“元”换算为“分”
        $converted = (int) round($numericVal * 100);

        // 如果换算后的值与原订单金额一致，则采用换算值
        if ($converted === $defaultCents) {
            return $converted;
        }

        // 若数值本身就已经等于分值，直接采用原整型
        if ((int) $numericVal === $defaultCents) {
            return (int) $numericVal;
        }

        return $converted;
    }

    /**
     * 查询指定充值订单的当前支付状态（供前端轮询）
     */
    public function checkOrderStatus(string $orderNo)
    {
        $order = \App\Models\WalletOrder::where('order_no', $orderNo)
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
                'is_paid' => $order->status === \App\Models\WalletOrder::STATUS_PAID,
                'status' => $order->status,
            ],
        ]);
    }
}
