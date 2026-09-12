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
        $params = $request->all();
        Log::info('收到第三方支付到账通知报文:', $params);

        // 1. 提取网关关键定位参数
        $gatewayOrderId = $request->input('order_id') ?? $request->input('payId');
        $reallyPrice = (int) ($request->input('really_price') ?? $request->input('price', 0));

        if (empty($gatewayOrderId)) {
            Log::warning('支付通知缺失核心网关单号');
            return response('fail', 400);
        }

        try {
            // 2. 开启事务保障资金绝对安全
            DB::transaction(function () use ($gatewayOrderId, $reallyPrice, $params) {
                // A. 加行级排他锁查找充值订单
                $order = WalletOrder::where('gateway_order_id', $gatewayOrderId)
                    ->lockForUpdate()
                    ->first();

                // 幂等性校验：如果订单不存在或早已处理完毕，直接跳过
                if (!$order) {
                    Log::warning("未找到匹配的本地订单，网关单号: [{$gatewayOrderId}]");
                    return;
                }

                if ($order->status === WalletOrder::STATUS_PAID) {
                    Log::info("订单 [{$order->order_no}] 已经入账过，跳过重复通知");
                    return;
                }

                // B. 加行级排他锁锁定该用户的钱包记录
                $wallet = Wallet::where('user_id', $order->user_id)
                    ->lockForUpdate()
                    ->first();

                if (!$wallet) {
                    throw new Exception("用户 [{$order->user_id}] 钱包主体不存在");
                }

                if ($wallet->status !== Wallet::STATUS_ACTIVE) {
                    throw new Exception("用户钱包已被冻结或禁用，暂停入账");
                }

                // C. 计算余额快照与校验和
                $creditAmount = $order->amount; // 充值金额（单位：分）
                $balanceBefore = $wallet->balance;
                $balanceAfter = $balanceBefore + $creditAmount;
                $newVersion = $wallet->version + 1;

                // 计算新的 HMAC 防篡改摘要
                $newChecksum = Wallet::generateChecksum(
                    $wallet->user_id,
                    $balanceAfter,
                    $wallet->coins,
                    $newVersion
                );

                // D. 更新钱包资金与审计字段
                $wallet->update([
                    'balance' => $balanceAfter,
                    'total_recharge' => $wallet->total_recharge + $creditAmount,
                    'version' => $newVersion,
                    'checksum' => $newChecksum,
                    'last_activity_at' => now(),
                ]);

                // E. 生成全局唯一交易流水号并写入 wallet_transactions
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
                        'really_amount' => $reallyPrice > 0 ? $reallyPrice : $creditAmount,
                        'payment_method' => $order->payment_method,
                    ],
                ]);

                // F. 更新充值订单状态为已完成
                $order->update([
                    'status' => WalletOrder::STATUS_PAID,
                    'really_amount' => $reallyPrice > 0 ? $reallyPrice : $creditAmount,
                    'paid_at' => now(),
                    'raw_callback' => $params,
                ]);

                Log::info("充值订单 [{$order->order_no}] 成功到账，流水号: [{$trxNo}]，新增余额: {$creditAmount} 分");
            });

            // 3. 按照网关规范返回纯文本 success
            return response('success', 200)->header('Content-Type', 'text/plain');

        } catch (Exception $e) {
            Log::error('支付回调入账发生异常: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response('error', 500);
        }
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
