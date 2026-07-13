import React from 'react';
import { Head } from '@inertiajs/react';
import { Play, Share2, Heart, Download, Check, ShieldCheck, MonitorPlay } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { cn } from "@/lib/utils";
import { Product } from '@/types/store';

// 扩展一下详情页专用的模拟数据
const PRODUCT_DETAIL = {
    id: '101',
    title: 'DaVinci Resolve 19 调色大师班 - 从入门到电影级色彩科学全解析 (2026版)',
    author: 'Colorist Pro',
    avatar: 'https://i.pravatar.cc/150?u=101',
    thumbnail: 'https://images.unsplash.com/photo-1574717024653-61fd2cf4d44d?q=80&w=1920&auto=format&fit=crop',
    type: 'video',
    price: 199.00,
    originalPrice: 299.00,
    views: '3.4万',
    publishDate: '2026-06-15',
    tags: [
        { label: '4K超清', color: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' },
        { label: '独家首发', color: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' }
    ],
    description: "本教程专为有志于成为专业调色师的创作者打造。在超过 12 小时的课程中，我们将深入剖析 DaVinci Resolve 19 的全新色彩引擎。从基础的节点逻辑，到好莱坞级别的色彩空间转换（ACES/DaVinci Wide Gamut），再到 HDR 杜比视界的母版制作，为你构建完整的色彩科学体系。\n\n内含 50GB 原始 ARRI RAW 练习素材及独家 LUTs 预设包。",
    features: [
        "12.5 小时 4K 超清视频教程",
        "50GB ARRI/RED 原格式练习素材",
        "配套全套工程文件与节点图",
        "讲师一对一社群答疑专属资格",
        "永久观看，免费获取后续更新"
    ]
};

export default function ProductDetail() {
    const isSale = PRODUCT_DETAIL.originalPrice && PRODUCT_DETAIL.originalPrice > PRODUCT_DETAIL.price;

    return (
        <>
            <Head title={`${PRODUCT_DETAIL.title} - 商城`} />

            <div className="container mx-auto px-4 py-6 md:py-10 max-w-[1400px]">

                {/* 1. 顶部 16:9 沉浸式预览区 (Apple 大圆角 + YouTube 比例) */}
                <div className="relative w-full aspect-video rounded-3xl overflow-hidden bg-black shadow-lg mb-8 group flex items-center justify-center">
                    <img
                        src={PRODUCT_DETAIL.thumbnail}
                        alt="Preview"
                        className="w-full h-full object-cover opacity-80 transition-opacity duration-500 group-hover:opacity-60"
                    />

                    {/* 模拟视频播放按钮 (毛玻璃质感) */}
                    {PRODUCT_DETAIL.type === 'video' && (
                        <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                            <div className="w-20 h-20 md:w-28 md:h-28 rounded-full bg-white/20 backdrop-blur-xl flex items-center justify-center border border-white/30 shadow-2xl transition-transform duration-300 group-hover:scale-110">
                                <Play className="w-10 h-10 md:w-12 md:h-12 fill-white text-white ml-2" />
                            </div>
                        </div>
                    )}
                </div>

                {/* 2. 主体两栏布局 */}
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-10">

                    {/* 左侧主要信息区 (占比 8/12) */}
                    <div className="lg:col-span-8 space-y-6">

                        {/* 标题与标签 */}
                        <div>
                            <div className="flex flex-wrap gap-2 mb-3">
                                {PRODUCT_DETAIL.tags.map((tag, idx) => (
                                    <span key={idx} className={cn("px-2 py-0.5 rounded-md text-xs font-bold uppercase tracking-wider", tag.color)}>
                                        {tag.label}
                                    </span>
                                ))}
                            </div>
                            <h1 className="text-2xl md:text-3xl font-bold text-foreground leading-tight tracking-tight">
                                {PRODUCT_DETAIL.title}
                            </h1>
                        </div>

                        {/* YouTube 风格的作者栏及操作按钮 */}
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 py-2">
                            <div className="flex items-center gap-3">
                                <Avatar className="h-12 w-12 ring-1 ring-border shadow-sm cursor-pointer hover:opacity-80 transition-opacity">
                                    <AvatarImage src={PRODUCT_DETAIL.avatar} />
                                    <AvatarFallback>CP</AvatarFallback>
                                </Avatar>
                                <div>
                                    <h3 className="text-base font-semibold text-foreground cursor-pointer hover:text-blue-600 transition-colors">
                                        {PRODUCT_DETAIL.author}
                                    </h3>
                                    <p className="text-sm text-muted-foreground">12.5万 订阅者</p>
                                </div>
                                <Button variant="secondary" className="ml-2 rounded-full font-semibold">
                                    关注
                                </Button>
                            </div>

                            <div className="flex items-center gap-2">
                                <Button variant="secondary" className="rounded-full px-4 flex items-center gap-2">
                                    <Heart className="w-4 h-4" /> 收藏
                                </Button>
                                <Button variant="secondary" className="rounded-full px-4 flex items-center gap-2">
                                    <Share2 className="w-4 h-4" /> 分享
                                </Button>
                            </div>
                        </div>

                        {/* Apple 风格的描述区块 (柔和底色，大圆角) */}
                        <div className="bg-muted/50 rounded-2xl p-6 text-sm text-foreground/90 leading-relaxed whitespace-pre-wrap mt-6">
                            <div className="flex items-center gap-4 text-muted-foreground font-medium mb-4 pb-4 border-b border-border/50">
                                <span>{PRODUCT_DETAIL.views} 次观看</span>
                                <span>发布于 {PRODUCT_DETAIL.publishDate}</span>
                            </div>
                            {PRODUCT_DETAIL.description}
                        </div>
                    </div>

                    {/* 右侧购买面板 (占比 4/12，Sticky 悬浮) */}
                    <div className="lg:col-span-4">
                        <div className="sticky top-8 bg-background rounded-3xl border border-border shadow-xl p-6 md:p-8 flex flex-col gap-6">

                            {/* 价格区 */}
                            <div>
                                <div className="flex items-end gap-2 mb-1">
                                    <span className="text-3xl font-bold text-foreground">
                                        <span className="text-2xl mr-1">¥</span>
                                        <span className={isSale ? "text-red-500 dark:text-red-400" : ""}>
                                            {PRODUCT_DETAIL.price.toFixed(2)}
                                        </span>
                                    </span>
                                </div>
                                {isSale && (
                                    <p className="text-sm text-muted-foreground flex items-center gap-2">
                                        原价 <span className="line-through">¥{PRODUCT_DETAIL.originalPrice?.toFixed(2)}</span>
                                        <span className="bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400 px-1.5 py-0.5 rounded text-xs font-bold">
                                            立省 ¥{(PRODUCT_DETAIL.originalPrice! - PRODUCT_DETAIL.price).toFixed(2)}
                                        </span>
                                    </p>
                                )}
                            </div>

                            {/* 购买按钮组合 (Apple App Store 风格) */}
                            <div className="flex flex-col gap-3">
                                <Button className="w-full h-12 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-base transition-colors shadow-md">
                                    立即获取
                                </Button>
                                <Button variant="outline" className="w-full h-12 rounded-xl font-semibold text-base border-border hover:bg-muted transition-colors">
                                    加入购物车
                                </Button>
                            </div>

                            {/* 权益保障与特性列表 */}
                            <div className="pt-6 border-t border-border/60">
                                <h4 className="text-sm font-bold text-foreground mb-4 flex items-center gap-2">
                                    <MonitorPlay className="w-4 h-4" /> 包含内容
                                </h4>
                                <ul className="space-y-3">
                                    {PRODUCT_DETAIL.features.map((feat, idx) => (
                                        <li key={idx} className="flex items-start gap-3 text-sm text-muted-foreground">
                                            <Check className="w-4 h-4 text-blue-500 shrink-0 mt-0.5" />
                                            <span>{feat}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            {/* 底部保障提示 */}
                            <div className="mt-2 bg-muted/50 rounded-xl p-4 flex items-start gap-3">
                                <ShieldCheck className="w-5 h-5 text-emerald-500 shrink-0" />
                                <p className="text-xs text-muted-foreground leading-snug">
                                    平台提供 7 天无理由退款保障（素材未下载/未观看前提下）。支付过程经由 256 位加密传输。
                                </p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </>
    );
}
