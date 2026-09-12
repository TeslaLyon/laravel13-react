import React, { useState, useEffect, useRef } from 'react';
import { Link, router, useHttp } from '@inertiajs/react';
import { WalletData } from '@/types/wallet';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { toast } from 'sonner';
import {
    ArrowDownLeft,
    Layers,
    Coins,
    TrendingUp,
    TrendingDown,
    Sparkles,
    CalendarCheck2,
    Lock,
    Loader2,
    CheckCircle2,
    QrCode,
    ExternalLink,
    RotateCcw,
} from 'lucide-react';
import { paymentMethods as paymentMethodsRoute, deposit } from '@/actions/App/Http/Controllers/WalletController';

interface PaymentMethod {
    id: string;
    name: string;
    description: string;
    logo?: string;
    is_active: boolean;
}

interface DepositAmountOption {
    amount: number;   // 存储单位：分（如 1000, 5000）
    tissues: number;  // 显示单位：纸巾（如 10, 50）
    label: string;
    popular?: boolean;
}

interface Props {
    wallet: WalletData;
}

/**
 * Dialog 按钮标准化样式类（高质感红色主题）
 */
export const dialogButtonStyles = {
    cancel: "h-10 px-5 rounded-xl text-sm font-medium border-border/80 hover:bg-muted/70 transition-colors",
    primary: "h-10 px-5 rounded-xl text-sm font-medium text-white bg-red-600 hover:bg-red-700 shadow-xs transition-all gap-2 active:scale-[0.99] disabled:opacity-50",
};

/**
 * 金额辅助工具：将数据库存储的“分”转换为“纸巾”展示
 */
function toTissues(cents: number | undefined | null): string {
    if (cents === undefined || cents === null) return '0.00';
    return (cents / 100).toFixed(2);
}

/**
 * 红色主题的极简平滑加载指示器
 */
function TwitterSpinner() {
    return (
        <div className="flex flex-col items-center justify-center min-h-[300px] py-12 space-y-4">
            <div className="w-8 h-8 rounded-full border-[2.5px] border-red-500/15 border-t-red-600 animate-spin" />
            <p className="text-xs text-muted-foreground/80 tracking-wide font-normal">
                正在加载支付选项...
            </p>
        </div>
    );
}

export function WalletCards({ wallet }: Props) {
    const [depositOpen, setDepositOpen] = useState(false);
    const [isReady, setIsReady] = useState(false);

    // 🌟 1. 弹窗交互状态机：'select' (选择中) | 'pending' (等待支付) | 'success' (支付成功)
    const [dialogStep, setDialogStep] = useState<'select' | 'pending' | 'success'>('select');
    const [currentOrder, setCurrentOrder] = useState<{
        orderNo: string;
        payUrl: string;
        tissues: number;
    } | null>(null);

    const [paymentMethods, setPaymentMethods] = useState<PaymentMethod[]>([]);
    const [depositOptions, setDepositOptions] = useState<DepositAmountOption[]>([]);

    const [selectedAmount, setSelectedAmount] = useState<number>(5000);
    const [selectedMethod, setSelectedMethod] = useState<string>('wechat');

    // 轮询定时器引用，防止重复触发或未及时清理
    // ✅ 优雅自适应写法（自动匹配当前环境的定时器返回值类型）
    const pollTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);

    // 获取支付方式 GET 实例
    const { get: getMethods, processing: methodsLoading } = useHttp();

    // 充值提交 POST 实例（解构出 setData 用于动态更新表单数据）
    const { post: postDeposit, processing: depositProcessing, setData } = useHttp({
        amount: selectedAmount,
        payment_method: selectedMethod,
        description: '购买纸巾',
    });

    // 🌟 2. 封装统一的同步切换处理器，确保 UI 与 useHttp 表单数据严格同步
    const handleSelectAmount = (amount: number) => {
        setSelectedAmount(amount);
        if (typeof setData === 'function') {
            setData('amount', amount);
        }
    };

    const handleSelectMethod = (methodId: string) => {
        setSelectedMethod(methodId);
        if (typeof setData === 'function') {
            setData('payment_method', methodId);
        }
    };

    // 响应式状态保底同步
    useEffect(() => {
        if (typeof setData === 'function') {
            setData('amount', selectedAmount);
        }
    }, [selectedAmount]);

    useEffect(() => {
        if (typeof setData === 'function') {
            setData('payment_method', selectedMethod);
        }
    }, [selectedMethod]);

    // 停止并清理轮询定时器
    const stopPolling = () => {
        if (pollTimerRef.current) {
            clearInterval(pollTimerRef.current);
            pollTimerRef.current = null;
        }
    };

    // 重置弹窗全部状态
    const resetDialogState = () => {
        stopPolling();
        setDialogStep('select');
        setCurrentOrder(null);
    };

    // 监听弹窗打开/关闭
    const handleOpenChange = (open: boolean) => {
        setDepositOpen(open);
        if (!open) {
            resetDialogState();
        }
    };

    // 🌟 3. 核心轮询监听器：当状态进入 pending 且有单号时自动触发
    useEffect(() => {
        if (dialogStep === 'pending' && currentOrder?.orderNo) {
            stopPolling();

            // 每 2.5 秒静默轮询一次
            pollTimerRef.current = setInterval(async () => {
                try {
                    const response = await fetch(`/wallet/orders/${currentOrder.orderNo}/status`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const result = await response.json();

                    if (result?.success && result?.data?.is_paid) {
                        stopPolling();
                        setDialogStep('success');

                        // 局部刷新 Inertia 钱包数据，平滑无白屏
                        router.reload({ only: ['wallet'] });

                        // 停留 2 秒展示成功勾选状态，随后优雅关闭
                        setTimeout(() => {
                            setDepositOpen(false);
                            resetDialogState();
                        }, 2200);
                    }
                } catch {
                    // 静默捕获偶发网络波动，不中断轮询循环
                }
            }, 2500);
        }

        return () => stopPolling();
    }, [dialogStep, currentOrder]);

    // 异步拉取后端配置
    useEffect(() => {
        if (depositOpen && dialogStep === 'select') {
            setIsReady(false);

            getMethods(paymentMethodsRoute.url(), {
                onSuccess: (response: any) => {
                    const resData = response?.data?.data || response?.data || response;

                    if (resData?.methods && Array.isArray(resData.methods)) {
                        setPaymentMethods(resData.methods);
                        if (resData.methods.length > 0) {
                            handleSelectMethod(resData.methods[0].id);
                        }
                    }

                    if (resData?.deposit_amounts && Array.isArray(resData.deposit_amounts)) {
                        setDepositOptions(resData.deposit_amounts);
                        if (resData.deposit_amounts.length > 0) {
                            handleSelectAmount(resData.deposit_amounts[0].amount);
                        }
                    }

                    setIsReady(true);
                },
                onError: () => {
                    toast.error('加载充值配置失败，请稍后重试');
                    setIsReady(true);
                },
                onNetworkError: () => {
                    toast.error('网络连接错误，请检查网络后重试');
                },
            });
        }
    }, [depositOpen]);

    // 🌟 4. 提交充值表单并在新标签页打开收银台
    const submitDeposit = (e: React.FormEvent) => {
        e.preventDefault();

        if (typeof setData === 'function') {
            setData('amount', selectedAmount);
            setData('payment_method', selectedMethod);
        }

        // 同步事件中提前打开空白标签页，确保 100% 免疫浏览器拦截
        const paymentWindow = window.open('about:blank', '_blank');

        postDeposit(deposit.url(), {
            onSuccess: (response: any) => {
                const resData = response?.data?.data || response?.data || response;
                const cashierDomain = 'https://pay.536969.xyz';
                let targetUrl = resData?.pay_url;

                // 针对 V 免签收银台拼接规范进行保底
                if (!targetUrl && resData?.order_id) {
                    targetUrl = `${cashierDomain}/${resData.order_id}`;
                }

                if (targetUrl && paymentWindow) {
                    paymentWindow.opener = null;
                    paymentWindow.location.href = targetUrl;
                } else if (targetUrl) {
                    window.location.href = targetUrl;
                }

                // 🌟 切换到 pending 状态，不关闭弹窗
                setCurrentOrder({
                    orderNo: resData?.order_no,
                    payUrl: targetUrl,
                    tissues: selectedAmount / 100,
                });
                setDialogStep('pending');
            },
            onError: () => {
                if (paymentWindow) paymentWindow.close();
                toast.error('充值发起失败，请稍后重试。');
            },
            onNetworkError: () => {
                if (paymentWindow) paymentWindow.close();
                toast.error('网络错误，请检查网络连接并重试');
            },
        });
    };

    // 🌟 5. 用户手动点击“我已完成支付”的主动核查动作
    const handleManualVerify = async () => {
        if (!currentOrder?.orderNo) return;

        try {
            const response = await fetch(`/wallet/orders/${currentOrder.orderNo}/status`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const result = await response.json();

            if (result?.success && result?.data?.is_paid) {
                stopPolling();
                setDialogStep('success');
                router.reload({ only: ['wallet'] });

                setTimeout(() => {
                    setDepositOpen(false);
                    resetDialogState();
                }, 1800);
            } else {
                toast.warning('支付网关暂未确认到账，请在完成付款后稍候片刻');
            }
        } catch {
            toast.error('网络核验异常，请刷新页面查看余额');
        }
    };

    return (
        <div className="grid gap-5 md:grid-cols-3">
            {/* 1. 纸巾资产主卡片 */}
            <Card className="rounded-2xl border-border/70 bg-card/60 shadow-xs backdrop-blur-xs flex flex-col justify-between">
                <CardHeader className="flex flex-row items-center justify-between pb-3 pt-6 px-6">
                    <CardTitle className="text-sm sm:text-base font-semibold text-foreground/80 tracking-normal">
                        可用纸巾
                    </CardTitle>
                    <div className="w-8 h-8 rounded-full bg-red-500/10 flex items-center justify-center">
                        <Layers className="h-4 w-4 text-red-600 dark:text-red-500" />
                    </div>
                </CardHeader>
                <CardContent className="px-6 pb-6 space-y-5">
                    <div className="flex items-baseline text-foreground font-bold tracking-tight">
                        <span className="text-3xl sm:text-[34px] font-bold tabular-nums">
                            {toTissues(wallet.balance)}
                        </span>
                        <span className="text-base font-normal text-muted-foreground ml-2">纸巾</span>
                    </div>

                    <div className="flex flex-wrap items-center gap-3 text-sm">
                        <div className="flex items-center gap-2 bg-muted/60 px-3 py-1 rounded-lg border border-border/40">
                            <Lock className="w-3.5 h-3.5 text-muted-foreground/80" />
                            <span className="text-muted-foreground text-xs sm:text-sm">冻结</span>
                            <span className="font-mono font-medium text-foreground tracking-tight ml-1.5 text-xs sm:text-sm">
                                {toTissues(wallet.frozen_balance)} 纸巾
                            </span>
                        </div>
                        <Badge
                            variant={wallet.status === 1 ? 'outline' : 'destructive'}
                            className="text-xs px-2.5 py-0.5 rounded-full font-medium"
                        >
                            {wallet.status_label}
                        </Badge>
                    </div>

                    <div className="pt-1">
                        <Dialog open={depositOpen} onOpenChange={handleOpenChange}>
                            <DialogTrigger asChild>
                                <Button
                                    className="w-full h-10 gap-2 rounded-xl text-sm font-medium shadow-xs bg-red-600 hover:bg-red-700 text-white transition-all active:scale-[0.99]"
                                    disabled={wallet.status !== 1}
                                >
                                    <ArrowDownLeft className="h-4 w-4" />
                                    <span>快速获取纸巾</span>
                                </Button>
                            </DialogTrigger>

                            <DialogContent className="sm:max-w-[480px] rounded-2xl p-6">
                                {/* ================= STEP 1: 选择金额与支付渠道 ================= */}
                                {dialogStep === 'select' && (
                                    <form onSubmit={submitDeposit} className="space-y-5">
                                        <DialogHeader>
                                            <DialogTitle className="text-lg font-bold text-foreground">
                                                获取纸巾
                                            </DialogTitle>
                                            <DialogDescription className="text-xs text-muted-foreground">
                                                选择充值数量与支付渠道，支付成功后系统将自动发放纸巾。
                                            </DialogDescription>
                                        </DialogHeader>

                                        {!isReady || methodsLoading ? (
                                            <TwitterSpinner />
                                        ) : (
                                            <>
                                                <div className="space-y-5 py-1">
                                                    {/* 支付方式列表 */}
                                                    <div className="space-y-2">
                                                        <Label className="text-xs font-semibold text-foreground/80">
                                                            支付方式
                                                        </Label>
                                                        <div className="space-y-2.5">
                                                            {paymentMethods.map((method) => {
                                                                const isSelected = selectedMethod === method.id;
                                                                return (
                                                                    <div
                                                                        key={method.id}
                                                                        onClick={() => handleSelectMethod(method.id)}
                                                                        className={`relative flex items-center justify-between p-3.5 rounded-xl border cursor-pointer transition-all ${isSelected
                                                                            ? 'border-red-500/80 bg-red-500/[0.04] shadow-xs'
                                                                            : 'border-border/70 hover:border-border hover:bg-muted/30'
                                                                            }`}
                                                                    >
                                                                        <div className="flex items-start gap-3">
                                                                            <div className="w-9 h-9 rounded-xl flex items-center justify-center bg-muted/40 border border-border/40 p-1.5 shrink-0 mt-0.5 overflow-hidden">
                                                                                {method.logo ? (
                                                                                    <img
                                                                                        src={method.logo}
                                                                                        alt={method.name}
                                                                                        className="w-full h-full object-contain aspect-square select-none pointer-events-none"
                                                                                        loading="lazy"
                                                                                        onError={(e) => {
                                                                                            e.currentTarget.style.display = 'none';
                                                                                            const fallback = e.currentTarget.parentElement?.querySelector('.logo-fallback');
                                                                                            if (fallback) fallback.classList.remove('hidden');
                                                                                        }}
                                                                                    />
                                                                                ) : null}
                                                                                <span
                                                                                    className={`logo-fallback text-xs font-bold ${method.logo ? 'hidden' : ''
                                                                                        } ${method.id === 'wechat'
                                                                                            ? 'text-emerald-600'
                                                                                            : 'text-sky-600'
                                                                                        }`}
                                                                                >
                                                                                    {method.name.slice(0, 1)}
                                                                                </span>
                                                                            </div>

                                                                            <div className="space-y-0.5 pr-2">
                                                                                <div className="text-sm font-semibold text-foreground">
                                                                                    {method.name}
                                                                                </div>
                                                                                <p className="text-xs text-muted-foreground leading-relaxed">
                                                                                    {method.description}
                                                                                </p>
                                                                            </div>
                                                                        </div>

                                                                        <div className="shrink-0 pl-2">
                                                                            {isSelected ? (
                                                                                <CheckCircle2 className="w-5 h-5 text-red-600 dark:text-red-500" />
                                                                            ) : (
                                                                                <div className="w-5 h-5 rounded-full border border-border/80" />
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                );
                                                            })}
                                                        </div>
                                                    </div>

                                                    {/* 固定面额选项 */}
                                                    <div className="space-y-2">
                                                        <div className="flex items-center justify-between">
                                                            <Label className="text-xs font-semibold text-foreground/80">
                                                                选择充值数量
                                                            </Label>
                                                            <span className="text-[11px] text-muted-foreground">
                                                                固定面额档位
                                                            </span>
                                                        </div>
                                                        <div className="grid grid-cols-3 gap-2.5">
                                                            {depositOptions.map((item) => {
                                                                const isSelected = selectedAmount === item.amount;
                                                                return (
                                                                    <button
                                                                        key={item.amount}
                                                                        type="button"
                                                                        onClick={() => handleSelectAmount(item.amount)}
                                                                        className={`relative h-14 flex flex-col items-center justify-center rounded-xl border transition-all ${isSelected
                                                                            ? 'border-red-500 bg-red-500/[0.06] text-red-600 dark:text-red-500 font-bold shadow-xs'
                                                                            : 'border-border/70 bg-card hover:bg-muted/40 text-foreground'
                                                                            }`}
                                                                    >
                                                                        {item.popular && (
                                                                            <span className="absolute -top-2 right-1 text-[9px] bg-red-600 text-white px-1.5 py-0.5 rounded-full font-semibold leading-none shadow-xs">
                                                                                推荐
                                                                            </span>
                                                                        )}
                                                                        <span className="font-mono text-base tabular-nums">
                                                                            {item.tissues ?? item.amount / 100}
                                                                        </span>
                                                                        <span className="text-[10px] text-muted-foreground mt-0.5">
                                                                            纸巾
                                                                        </span>
                                                                    </button>
                                                                );
                                                            })}
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* 底部操作栏 */}
                                                <DialogFooter className="pt-4 border-t border-border/40 flex-row justify-end gap-3 sm:gap-3">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        className={dialogButtonStyles.cancel}
                                                        onClick={() => setDepositOpen(false)}
                                                    >
                                                        取消
                                                    </Button>
                                                    <Button
                                                        type="submit"
                                                        className={dialogButtonStyles.primary}
                                                        disabled={depositProcessing}
                                                    >
                                                        {depositProcessing ? (
                                                            <>
                                                                <Loader2 className="w-4 h-4 animate-spin" />
                                                                <span>正在发起支付...</span>
                                                            </>
                                                        ) : (
                                                            <>
                                                                <QrCode className="w-4 h-4" />
                                                                <span>获取 {selectedAmount / 100} 纸巾</span>
                                                            </>
                                                        )}
                                                    </Button>
                                                </DialogFooter>
                                            </>
                                        )}
                                    </form>
                                )}

                                {/* ================= STEP 2: 等待支付状态面板 ================= */}
                                {dialogStep === 'pending' && (
                                    <div className="py-4 flex flex-col items-center text-center space-y-5">
                                        {/* 呼吸脉冲光环 */}
                                        <div className="relative flex items-center justify-center">
                                            <div className="w-16 h-16 rounded-full bg-red-500/10 flex items-center justify-center animate-pulse">
                                                <Loader2 className="w-8 h-8 text-red-600 animate-spin" />
                                            </div>
                                        </div>

                                        <div className="space-y-1.5">
                                            <h3 className="text-base font-bold text-foreground">
                                                支付页面已在新标签页打开
                                            </h3>
                                            <p className="text-xs text-muted-foreground max-w-[340px] leading-relaxed">
                                                请在打开的收银台完成付款。系统检测到到账通知后将自动增加可用纸巾。
                                            </p>
                                        </div>

                                        {/* 订单信息摘要 */}
                                        <div className="w-full bg-muted/40 rounded-xl p-3.5 border border-border/60 text-left space-y-2">
                                            <div className="flex items-center justify-between text-xs">
                                                <span className="text-muted-foreground">充值商品</span>
                                                <span className="font-semibold text-foreground">
                                                    {currentOrder?.tissues} 纸巾 (¥{currentOrder ? currentOrder.tissues : 0}.00)
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between text-xs">
                                                <span className="text-muted-foreground">商户订单号</span>
                                                <span className="font-mono text-[11px] text-foreground/80">
                                                    {currentOrder?.orderNo}
                                                </span>
                                            </div>
                                        </div>

                                        {/* 交互操作栏 */}
                                        <div className="w-full space-y-2.5 pt-2">
                                            <Button
                                                type="button"
                                                onClick={handleManualVerify}
                                                className="w-full h-10 rounded-xl text-sm font-medium bg-red-600 hover:bg-red-700 text-white shadow-xs transition-all"
                                            >
                                                我已完成支付
                                            </Button>

                                            <div className="flex items-center justify-between gap-3 pt-1">
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        if (currentOrder?.payUrl) {
                                                            window.open(currentOrder.payUrl, '_blank');
                                                        }
                                                    }}
                                                    className="text-xs text-muted-foreground hover:text-foreground flex items-center gap-1 transition-colors"
                                                >
                                                    <ExternalLink className="w-3.5 h-3.5" />
                                                    <span>重新打开支付页</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    onClick={resetDialogState}
                                                    className="text-xs text-muted-foreground hover:text-rose-600 flex items-center gap-1 transition-colors"
                                                >
                                                    <RotateCcw className="w-3.5 h-3.5" />
                                                    <span>更换支付方式</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* ================= STEP 3: 支付成功动效 ================= */}
                                {dialogStep === 'success' && (
                                    <div className="py-8 flex flex-col items-center text-center space-y-4">
                                        <div className="w-14 h-14 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-600 animate-in zoom-in-75 duration-300">
                                            <CheckCircle2 className="w-8 h-8" />
                                        </div>
                                        <div className="space-y-1">
                                            <h3 className="text-base font-bold text-foreground">
                                                充值成功！
                                            </h3>
                                            <p className="text-xs text-muted-foreground">
                                                已成功增加 {currentOrder?.tissues} 纸巾，正在同步钱包数据...
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </DialogContent>
                        </Dialog>
                    </div>
                </CardContent>
            </Card>

            {/* 2. 社区金币资产卡片 */}
            <Card className="rounded-2xl border-amber-500/20 bg-card/60 shadow-xs backdrop-blur-xs flex flex-col justify-between">
                <CardHeader className="flex flex-row items-center justify-between pb-3 pt-6 px-6">
                    <CardTitle className="text-sm sm:text-base font-semibold text-foreground/80 tracking-normal">
                        可用社区金币
                    </CardTitle>
                    <div className="w-8 h-8 rounded-full bg-amber-500/10 flex items-center justify-center">
                        <Coins className="h-4 w-4 text-amber-500" />
                    </div>
                </CardHeader>
                <CardContent className="px-6 pb-6 space-y-5">
                    <div className="flex items-baseline font-bold tracking-tight text-amber-600 dark:text-amber-400">
                        <span className="text-3xl sm:text-[34px] tabular-nums">
                            {wallet.coins.toLocaleString()}
                        </span>
                        <span className="text-base font-medium text-muted-foreground ml-2">币</span>
                    </div>

                    <div className="flex flex-wrap items-center gap-2.5 text-sm text-muted-foreground">
                        <span className="flex items-center gap-1.5 bg-muted/50 px-2 py-0.5 rounded-md">
                            <Lock className="w-3.5 h-3.5 text-muted-foreground/80" />
                            <span>冻结 {wallet.frozen_coins} 币</span>
                        </span>
                        <span className="text-xs text-muted-foreground/70">•</span>
                        <span className="truncate">累计获得 {wallet.total_earned_coins.toLocaleString()} 币</span>
                    </div>

                    <div className="pt-1">
                        <Button
                            asChild
                            className="w-full h-10 rounded-xl text-sm bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-semibold gap-2 shadow-xs transition-all"
                        >
                            <Link href="/checkin">
                                <CalendarCheck2 className="w-4 h-4" />
                                <span>每日签到赚金币</span>
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* 3. 财务统计汇总卡片 */}
            <Card className="rounded-2xl border-border/70 bg-card/60 shadow-xs backdrop-blur-xs flex flex-col justify-between">
                <CardHeader className="pb-3 pt-6 px-6">
                    <CardTitle className="text-sm sm:text-base font-semibold text-foreground/80 tracking-normal">
                        财务统计汇总
                    </CardTitle>
                </CardHeader>
                <CardContent className="px-6 pb-6 space-y-3.5">
                    <div className="flex items-center justify-between text-sm py-0.5">
                        <span className="text-muted-foreground flex items-center gap-2">
                            <TrendingUp className="h-4 w-4 text-emerald-500" /> 累计充值纸巾
                        </span>
                        <span className="font-mono font-semibold tabular-nums text-foreground">
                            {toTissues(wallet.total_recharge)} 纸巾
                        </span>
                    </div>
                    <div className="flex items-center justify-between text-sm py-0.5">
                        <span className="text-muted-foreground flex items-center gap-2">
                            <TrendingDown className="h-4 w-4 text-rose-500" /> 累计消耗纸巾
                        </span>
                        <span className="font-mono font-semibold tabular-nums text-foreground">
                            {toTissues(wallet.total_spent)} 纸巾
                        </span>
                    </div>
                    <div className="flex items-center justify-between text-sm border-t border-border/50 pt-3 mt-1">
                        <span className="text-muted-foreground flex items-center gap-2 font-medium">
                            <Sparkles className="h-4 w-4 text-amber-500" /> 历史金币收益
                        </span>
                        <span className="font-mono font-semibold tabular-nums text-amber-600 dark:text-amber-400">
                            +{wallet.total_earned_coins.toLocaleString()} 币
                        </span>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
