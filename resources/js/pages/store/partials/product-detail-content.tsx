import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    Play,
    Share2,
    Heart,
    Check,
    ShieldCheck,
    MonitorPlay,
    Copy,
    ExternalLink,
    Layers,
    Clock,
    KeyRound,
    Sparkles
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { cn } from "@/lib/utils";
import { formatViews, getTagColorClass } from '@/lib/store-utils';
import { ProductShowData } from '@/types/store';
import DOMPurify from 'isomorphic-dompurify';

interface Props {
    product: ProductShowData;
}

export function ProductDetailContent({ product }: Props) {
    const [isPlaying, setIsPlaying] = useState(false);
    const [copiedKey, setCopiedKey] = useState<string | null>(null);

    const isSale = product.has_discount;
    const detail = product.detail;
    const hasPurchased = product.has_purchased;

    const handleCopy = (text: string, label: string) => {
        navigator.clipboard.writeText(text);
        setCopiedKey(label);
        setTimeout(() => setCopiedKey(null), 2000);
    };

    const handleBuyNow = () => {
        router.post(`/channels`, {}, {
            preserveScroll: true,
        });
    };

    return (
        /* 1. 增加 pb-24 防止移动端底部内容被悬浮栏遮挡，lg 屏幕恢复原样 */
        <div className="space-y-10 animate-in fade-in duration-300 pb-24 lg:pb-0">
            {/* 顶部 16:9 / 21:9 沉浸式预览交互区 */}
            <div className="relative w-full aspect-video md:aspect-[21/9] max-h-[560px] rounded-3xl overflow-hidden bg-black shadow-2xl group flex items-center justify-center border border-border/40">
                {isPlaying && product.preview_data?.video_url ? (
                    <video
                        src={product.preview_data.video_url}
                        controls
                        autoPlay
                        className="w-full h-full object-contain"
                    />
                ) : (
                    <>
                        <img
                            src={product.thumbnail || "https://images.unsplash.com/photo-1574717024653-61fd2cf4d44d?q=80&w=1920"}
                            alt={product.title}
                            className="w-full h-full object-cover opacity-85 transition-opacity duration-500 group-hover:opacity-75"
                        />

                        {product.type === 'video' && (
                            <button
                                type="button"
                                onClick={() => setIsPlaying(true)}
                                className="absolute inset-0 flex items-center justify-center group/btn cursor-pointer bg-black/20 hover:bg-black/40 transition-colors"
                                aria-label="播放试看视频"
                            >
                                <div className="w-20 h-20 md:w-24 md:h-24 rounded-full bg-white/25 backdrop-blur-xl flex items-center justify-center border border-white/40 shadow-2xl transition-transform duration-300 group-hover/btn:scale-110">
                                    <Play className="w-8 h-8 md:w-10 md:h-10 fill-white text-white ml-1.5" />
                                </div>
                                <span className="absolute bottom-6 px-3 py-1 rounded-full bg-black/70 backdrop-blur-md text-xs text-white/90 border border-white/10">
                                    点击观看高清试看片段
                                </span>
                            </button>
                        )}

                        <div className="absolute top-4 left-4 flex gap-2">
                            {product.resolution && (
                                <Badge className="bg-black/70 text-white backdrop-blur-md border-white/20 px-3 py-1 text-xs">
                                    {product.resolution}
                                </Badge>
                            )}
                            {product.duration && (
                                <Badge className="bg-black/70 text-white backdrop-blur-md border-white/20 px-3 py-1 text-xs flex items-center gap-1">
                                    <Clock className="w-3 h-3" /> {product.duration}
                                </Badge>
                            )}
                        </div>
                    </>
                )}
            </div>

            {/* 主体两栏布局 */}
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-10">

                {/* 左侧主要信息区 */}
                <div className="lg:col-span-8 space-y-8">
                    {/* 标题与色彩标签 */}
                    <div className="space-y-3">
                        {product.tags && product.tags.length > 0 && (
                            <div className="flex flex-wrap gap-2">
                                {product.tags.map((tag) => (
                                    <span
                                        key={tag.id}
                                        className={cn(
                                            "inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-semibold border transition-colors",
                                            getTagColorClass(tag.id || tag.name)
                                        )}
                                    >
                                        {tag.name_zh || tag.name}
                                    </span>
                                ))}
                            </div>
                        )}
                        <h1 className="text-2xl md:text-3xl lg:text-4xl font-bold text-foreground leading-tight tracking-tight">
                            {product.title}
                        </h1>
                    </div>

                    {/* 作者栏 */}
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-2xl bg-muted/30 border border-border/50">
                        <div className="flex items-center gap-3.5">
                            <Avatar className="h-12 w-12 ring-2 ring-primary/20 shadow-xs">
                                <AvatarImage src={product.author.avatar} />
                                <AvatarFallback>{product.author.name.slice(0, 2)}</AvatarFallback>
                            </Avatar>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h3 className="text-base font-bold text-foreground">{product.author.name}</h3>
                                    <Badge variant="secondary" className="text-[10px] h-4 font-normal">
                                        {product.author.title}
                                    </Badge>
                                </div>
                                <p className="text-xs text-muted-foreground line-clamp-1 mt-0.5">
                                    {product.author.bio}
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center gap-2 self-end sm:self-center">
                            <Button variant="outline" size="sm" className="rounded-full gap-1.5">
                                <Heart className="w-3.5 h-3.5 text-rose-500" /> 收藏
                            </Button>
                            <Button variant="outline" size="sm" className="rounded-full gap-1.5" onClick={() => handleCopy(window.location.href, 'share')}>
                                <Share2 className="w-3.5 h-3.5" />
                                {copiedKey === 'share' ? '链接已复制' : '分享'}
                            </Button>
                        </div>
                    </div>

                    {/* 规格参数矩阵 */}
                    {detail?.specs && Object.keys(detail.specs).length > 0 && (
                        <div className="rounded-2xl border border-border bg-card p-6 space-y-4 shadow-xs">
                            <h3 className="text-sm font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-2">
                                <Layers className="w-4 h-4 text-primary" /> 规格与技术指标
                            </h3>
                            <div className="grid grid-cols-2 sm:grid-cols-3 gap-4 pt-1">
                                {Object.entries(detail.specs).map(([key, value]) => (
                                    <div key={key} className="rounded-xl bg-muted/40 p-3.5 border border-border/40">
                                        <p className="text-xs text-muted-foreground font-medium">{key}</p>
                                        <p className="text-sm font-bold text-foreground mt-1 truncate">{value}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* 已购买用户的资产发货交付卡片 */}
                    {hasPurchased && detail?.delivery_content && (
                        <div className="rounded-2xl border-2 border-emerald-500/30 bg-emerald-50/40 dark:bg-emerald-950/20 p-6 space-y-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2 text-emerald-700 dark:text-emerald-300 font-bold">
                                    <KeyRound className="w-5 h-5" />
                                    <span>您已购买此商品，专属发货资产已解锁</span>
                                </div>
                                <Badge className="bg-emerald-600 text-white">已授权</Badge>
                            </div>

                            <div className="space-y-3 pt-2">
                                {detail.delivery_content.pan_url && (
                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between p-3.5 rounded-xl bg-background border border-emerald-500/20 gap-3">
                                        <div className="flex flex-col">
                                            <span className="text-xs text-muted-foreground">网盘下载地址</span>
                                            <span className="text-sm font-mono font-medium truncate max-w-md">
                                                {detail.delivery_content.pan_url}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2 shrink-0">
                                            <Button size="sm" variant="outline" onClick={() => handleCopy(detail.delivery_content!.pan_url!, 'pan_url')}>
                                                <Copy className="w-3.5 h-3.5 mr-1" />
                                                {copiedKey === 'pan_url' ? '已复制' : '复制链接'}
                                            </Button>
                                            <Button size="sm" className="bg-emerald-600 hover:bg-emerald-700 text-white" asChild>
                                                <a href={detail.delivery_content.pan_url} target="_blank" rel="noreferrer">
                                                    <ExternalLink className="w-3.5 h-3.5 mr-1" /> 打开网盘
                                                </a>
                                            </Button>
                                        </div>
                                    </div>
                                )}

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    {detail.delivery_content.pan_code && (
                                        <div className="flex items-center justify-between p-3 rounded-xl bg-background border border-emerald-500/20">
                                            <div>
                                                <p className="text-xs text-muted-foreground">提取码</p>
                                                <p className="text-base font-mono font-bold text-foreground">{detail.delivery_content.pan_code}</p>
                                            </div>
                                            <Button size="sm" variant="ghost" onClick={() => handleCopy(detail.delivery_content!.pan_code!, 'pan_code')}>
                                                <Copy className="w-3.5 h-3.5" />
                                            </Button>
                                        </div>
                                    )}

                                    {detail.delivery_content.unzip_password && (
                                        <div className="flex items-center justify-between p-3 rounded-xl bg-background border border-emerald-500/20">
                                            <div>
                                                <p className="text-xs text-muted-foreground">解压密码</p>
                                                <p className="text-base font-mono font-bold text-foreground">{detail.delivery_content.unzip_password}</p>
                                            </div>
                                            <Button size="sm" variant="ghost" onClick={() => handleCopy(detail.delivery_content!.unzip_password!, 'unzip_pw')}>
                                                <Copy className="w-3.5 h-3.5" />
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* 图文详情区块 */}
                    <div className="rounded-2xl border border-border bg-card p-6 md:p-8 space-y-4">
                        <div className="flex items-center gap-4 text-sm font-medium text-muted-foreground pb-4 border-b border-border/60">
                            <span>{formatViews(product.views_count)} 次浏览</span>
                            <span>•</span>
                            <span>发布于 {product.publish_date}</span>
                            <span>•</span>
                            <span>已售 {product.sales_count} 份</span>
                        </div>

                        {detail?.content ? (
                            <div
                                className="prose dark:prose-invert max-w-none text-sm md:text-base leading-relaxed pt-2 prose-img:rounded-2xl prose-img:shadow-md prose-a:text-primary prose-a:no-underline hover:prose-a:underline prose-headings:font-bold prose-headings:tracking-tight"
                                dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(detail.content) }}
                            />
                        ) : (
                            <p className="text-sm text-muted-foreground pt-2">暂无详细介绍</p>
                        )}
                    </div>
                </div>

                {/* 2. 右侧购买结算面板 (桌面端吸顶展示) */}
                <div className="lg:col-span-4">
                    <div className="sticky top-8 rounded-3xl border border-border bg-card shadow-xl p-6 md:p-8 flex flex-col gap-6">
                        <div>
                            <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-1">
                                {hasPurchased ? '已获得所有权' : '获取授权'}
                            </p>
                            <div className="flex items-baseline gap-2">
                                <span className="text-4xl font-bold tracking-tight text-foreground">
                                    <span className="text-2xl mr-1">¥</span>
                                    <span className={cn(isSale && !hasPurchased ? "text-red-500 dark:text-red-400" : "")}>
                                        {product.price.toFixed(2)}
                                    </span>
                                </span>
                            </div>
                            {isSale && !hasPurchased && (
                                <p className="text-xs text-muted-foreground flex items-center gap-2 mt-1.5">
                                    原价 <span className="line-through">¥{product.original_price?.toFixed(2)}</span>
                                    <span className="bg-red-500/10 text-red-600 dark:text-red-400 px-2 py-0.5 rounded-full font-bold">
                                        立省 ¥{(product.original_price! - product.price).toFixed(2)}
                                    </span>
                                </p>
                            )}
                        </div>

                        <div className="flex flex-col gap-3">
                            {hasPurchased ? (
                                <Button className="w-full h-12 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-base shadow-md gap-2 cursor-default">
                                    <Check className="w-5 h-5" /> 您已拥有此商品
                                </Button>
                            ) : (
                                <>
                                    <Button
                                        onClick={handleBuyNow}
                                        className="w-full h-12 rounded-xl bg-primary hover:bg-primary/90 text-primary-foreground font-bold text-base shadow-lg transition-transform active:scale-[0.98]"
                                    >
                                        立即获取
                                    </Button>
                                    <Button
                                        variant="outline"
                                        className="w-full h-12 rounded-xl font-semibold text-base border-border hover:bg-muted"
                                    >
                                        加入清单
                                    </Button>
                                </>
                            )}
                        </div>

                        <div className="pt-6 border-t border-border/60 space-y-3">
                            <h4 className="text-sm font-bold text-foreground flex items-center gap-2">
                                <MonitorPlay className="w-4 h-4 text-primary" /> 交付与服务权益
                            </h4>
                            <ul className="space-y-2.5 text-xs text-muted-foreground">
                                <li className="flex items-start gap-2.5">
                                    <Check className="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
                                    <span>{detail?.delivery_summary || '自动发货，即时生效'}</span>
                                </li>
                                <li className="flex items-start gap-2.5">
                                    <Check className="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
                                    <span>包含全套高清源文件与配套工程包</span>
                                </li>
                                <li className="flex items-start gap-2.5">
                                    <Check className="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
                                    <span>永久有效，支持无限次重复查阅与下载</span>
                                </li>
                            </ul>
                        </div>

                        <div className="rounded-xl bg-muted/40 p-3.5 flex items-start gap-2.5 border border-border/40">
                            <ShieldCheck className="w-4 h-4 text-emerald-500 shrink-0 mt-0.5" />
                            <p className="text-[11px] text-muted-foreground leading-snug">
                                数字资产付款后即刻自动发货，交易受平台安全保障。
                            </p>
                        </div>
                    </div>
                </div>

            </div>

            {/* 3. 移动端专属：底部固定悬浮支付栏 */}
            <div className="fixed bottom-0 left-0 right-0 z-50 p-4 bg-background/90 backdrop-blur-md border-t border-border/80 shadow-[0_-8px_20px_rgba(0,0,0,0.08)] lg:hidden">
                <div className="max-w-md mx-auto flex items-center justify-between gap-4">
                    {/* 左侧价格摘要 */}
                    <div className="flex flex-col">
                        <div className="flex items-baseline gap-1">
                            <span className="text-xs text-muted-foreground font-semibold">实付:</span>
                            <span className="text-2xl font-black text-foreground tracking-tight">
                                <span className="text-sm mr-0.5">¥</span>
                                <span className={cn(isSale && !hasPurchased ? "text-red-500 dark:text-red-400" : "")}>
                                    {product.price.toFixed(2)}
                                </span>
                            </span>
                        </div>
                        {isSale && !hasPurchased && (
                            <span className="text-[10px] text-muted-foreground line-through">
                                ¥{product.original_price?.toFixed(2)}
                            </span>
                        )}
                    </div>

                    {/* 右侧操作按钮 */}
                    <div className="flex items-center gap-2 flex-1 justify-end">
                        {hasPurchased ? (
                            <Button className="h-11 px-5 rounded-xl bg-emerald-600 text-white font-bold text-sm shadow-md gap-1.5 cursor-default flex-1 max-w-[220px]">
                                <Check className="w-4 h-4" /> 已获得授权
                            </Button>
                        ) : (
                            <Button
                                onClick={handleBuyNow}
                                className="h-11 px-6 rounded-xl bg-primary hover:bg-primary/90 text-primary-foreground font-bold text-sm shadow-lg active:scale-[0.98] transition-transform flex-1 max-w-[220px]"
                            >
                                <Sparkles className="w-4 h-4 mr-1.5" /> 立即获取
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
