import React, { useState } from 'react';
import { Crown, CheckCircle2, Zap, MonitorPlay, DownloadCloud, Code } from 'lucide-react';

// 🎯 1. 定义 VIP 权益列表数据
const VIP_FEATURES = [
    { icon: <MonitorPlay className="w-5 h-5" />, title: "全站视频免费看", desc: "解锁所有精品教程与4K素材" },
    { icon: <DownloadCloud className="w-5 h-5" />, title: "不限速下载", desc: "专享源文件高速下载通道" },
    { icon: <Code className="w-5 h-5" />, title: "工程源码获取", desc: "提供配套的 AE/PR/代码 源文件" },
    { icon: <Zap className="w-5 h-5" />, title: "专属无广告", desc: "沉浸式学习与挑选，纯净体验" },
];

// 🎯 2. 定义套餐数据
const PRICING_PLANS = [
    {
        id: 'monthly',
        name: "连续包月",
        price: "39",
        originalPrice: "59",
        duration: "个月",
        isRecommended: false,
        features: ["基础视频解锁", "1080P画质", "每月限下30次"],
    },
    {
        id: 'yearly',
        name: "年度大会员",
        price: "198",
        originalPrice: "399",
        duration: "年",
        isRecommended: true, // 推荐套餐
        badge: "最受欢迎",
        features: ["全站视频解锁", "4K超清画质", "无限次下载", "工程源码全开"],
    },
    {
        id: 'lifetime',
        name: "终身买断",
        price: "698",
        originalPrice: "1299",
        duration: "永久",
        isRecommended: false,
        features: ["终身全站解锁", "永久免费更新", "无限次下载", "专属1对1客服"],
    },
];

export default function VipUpgradePage() {
    // 记录用户当前选中的套餐，默认选中推荐的年度套餐
    const [selectedPlan, setSelectedPlan] = useState<string>('yearly');

    return (
        <div className="min-h-screen bg-background py-12 px-4 sm:px-6 lg:px-8 font-sans">
            <div className="max-w-5xl mx-auto flex flex-col items-center">

                {/* 🎯 头部引导区 */}
                <div className="text-center mb-12 space-y-4">
                    <div className="inline-flex items-center justify-center p-3 bg-amber-100 dark:bg-amber-900/30 rounded-full mb-2">
                        <Crown className="w-8 h-8 text-amber-500" />
                    </div>
                    <h1 className="text-3xl md:text-4xl font-extrabold tracking-tight text-primary">
                        升级 VIP，解锁无限创作潜能
                    </h1>
                    <p className="text-lg text-muted-foreground max-w-2xl mx-auto">
                        成为尊贵的 VIP 会员，畅享全站海量高清视频、专属源码及不限速下载服务。
                    </p>
                </div>

                {/* 🎯 四大核心权益横向排布 */}
                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 w-full mb-16">
                    {VIP_FEATURES.map((feature, idx) => (
                        <div key={idx} className="flex flex-col items-center text-center p-4 bg-muted/30 rounded-2xl">
                            <div className="text-amber-500 mb-3">{feature.icon}</div>
                            <h3 className="font-semibold text-sm mb-1 text-primary">{feature.title}</h3>
                            <p className="text-xs text-muted-foreground">{feature.desc}</p>
                        </div>
                    ))}
                </div>

                {/* 🎯 套餐卡片网格区 */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-8 w-full max-w-4xl mb-12 items-center">
                    {PRICING_PLANS.map((plan) => {
                        const isSelected = selectedPlan === plan.id;
                        return (
                            <div
                                key={plan.id}
                                onClick={() => setSelectedPlan(plan.id)}
                                className={`relative cursor-pointer flex flex-col p-6 rounded-3xl transition-all duration-300 border-2
                                ${plan.isRecommended ? 'bg-card shadow-xl md:-translate-y-4' : 'bg-card/50 shadow-sm hover:shadow-md'}
                                ${isSelected ? 'border-amber-500 ring-4 ring-amber-500/10' : 'border-border hover:border-amber-500/50'}`}
                            >
                                {/* 推荐角标 */}
                                {plan.badge && (
                                    <div className="absolute -top-4 left-1/2 -translate-x-1/2 bg-gradient-to-r from-amber-400 to-orange-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow-sm">
                                        {plan.badge}
                                    </div>
                                )}

                                <div className="mb-4">
                                    <h3 className="text-lg font-medium text-muted-foreground">{plan.name}</h3>
                                    <div className="mt-2 flex items-baseline gap-1">
                                        <span className="text-2xl font-semibold text-primary">￥</span>
                                        <span className="text-5xl font-extrabold tracking-tight text-primary">{plan.price}</span>
                                        <span className="text-sm text-muted-foreground ml-1">/ {plan.duration}</span>
                                    </div>
                                    <div className="text-sm text-muted-foreground line-through mt-1">
                                        原价 ￥{plan.originalPrice}
                                    </div>
                                </div>

                                {/* 套餐包含内容 */}
                                <ul className="flex-1 space-y-3 mt-6 mb-8 border-t border-border/50 pt-6">
                                    {plan.features.map((item, idx) => (
                                        <li key={idx} className="flex items-start gap-3">
                                            <CheckCircle2 className={`w-5 h-5 shrink-0 ${plan.isRecommended ? 'text-amber-500' : 'text-muted-foreground'}`} />
                                            <span className="text-sm text-primary/80">{item}</span>
                                        </li>
                                    ))}
                                </ul>

                                {/* 占位按钮：如果没被选中，显示灰色；选中时显示强调色 */}
                                <button className={`w-full py-3 rounded-xl font-bold transition-colors ${
                                    isSelected
                                    ? 'bg-gradient-to-r from-amber-400 to-orange-500 text-white shadow-md hover:opacity-90'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80'
                                }`}>
                                    {isSelected ? '立即开通' : '选择此套餐'}
                                </button>
                            </div>
                        );
                    })}
                </div>

                {/* 🎯 底部保障信息 */}
                <div className="text-center text-sm text-muted-foreground">
                    <p>支付即表示同意 <a href="#" className="text-primary hover:underline">《VIP会员服务条款》</a></p>
                    <p className="mt-2 flex items-center justify-center gap-1">
                        🔒 采用企业级加密支付，保障您的交易安全
                    </p>
                </div>

            </div>
        </div>
    );
}
