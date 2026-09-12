import React from 'react';
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Play, Image as ImageIcon, ShoppingBag } from 'lucide-react';
import { StoreMenu } from '@/components/store/Menu';
import { Product } from '@/types/store';
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import { getCardHoverColor } from "@/lib/utils";
import { formatViews, getTagColorClass } from '@/lib/store-utils';
import { show } from '@/actions/App/Http/Controllers/StoreController';

export function ProductCard({ product }: { product: Product }) {
    const hoverBgStyle = getCardHoverColor ? getCardHoverColor(product.id) : '';
    const isSale = product.has_discount;

    // 右下角规格/时长标识
    const displayMeta = product.type === 'video'
        ? (product.duration || product.resolution)
        : (product.spec_badge?.label || product.resolution);

    return (
        <Link href={show.url({ product: product.id, slug: product.slug })}>
            <div className="group relative flex flex-col gap-1 cursor-pointer z-0">
                {/* 悬浮底层光效 */}
                <div className={cn(
                    "absolute -inset-3 rounded-2xl border border-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100 -z-10",
                    hoverBgStyle
                )} />

                {/* 封面区域 (严格 16:9 比例) */}
                <div className="relative w-full aspect-video rounded-xl overflow-hidden bg-muted border border-border/50">
                    <img
                        src={product.thumbnail || "https://images.unsplash.com/photo-1574717024653-61fd2cf4d44d?q=80&w=1920&auto=format&fit=crop"}
                        alt={product.title}
                        className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                        loading="lazy"
                    />

                    {/* 媒体类型角标 (左上角) */}
                    <div className="absolute top-2 left-2">
                        <div className="px-2 py-1 rounded-md bg-background/85 backdrop-blur-md shadow-xs flex items-center gap-1.5 border border-border/40">
                            {product.type === 'video' ? (
                                <Play className="w-3 h-3 fill-foreground text-foreground" />
                            ) : (
                                <ImageIcon className="w-3 h-3 text-foreground" />
                            )}
                            <span className="text-[10px] font-bold text-foreground tracking-wide uppercase">
                                {product.type === 'video' ? '视频' : '图片'}
                            </span>
                        </div>
                    </div>

                    {/* 时长/分辨率/规格标识 (右下角) */}
                    {displayMeta && (
                        <div className="absolute bottom-2 right-2">
                            <span className="px-1.5 py-0.5 rounded text-[11px] font-medium text-white bg-black/70 backdrop-blur-sm">
                                {displayMeta}
                            </span>
                        </div>
                    )}
                </div>

                {/* 信息区域 */}
                <div className="flex gap-3 px-1 mt-2">
                    <Avatar className="h-9 w-9 shrink-0 mt-0.5 ring-1 ring-border/50 shadow-xs">
                        <AvatarImage src={product.avatar} alt={product.author} />
                        <AvatarFallback>{product.author?.substring(0, 2) || 'UK'}</AvatarFallback>
                    </Avatar>

                    <div className="flex flex-col flex-1 min-w-0">
                        {/* 标题 & 快捷菜单 */}
                        <div className="flex items-start justify-between gap-2">
                            <h3 className="text-[16px] font-semibold leading-snug line-clamp-2 text-foreground group-hover:text-primary transition-colors pt-0.5">
                                {product.title}
                            </h3>
                            <div className="shrink-0 -mt-1 -mr-2 transition-opacity p-1 hover:bg-muted-foreground/20 rounded-full">
                                <StoreMenu videoId={product.id} slug={product.author} />
                            </div>
                        </div>

                        {/* 多彩微光标签组 */}
                        {product.tags && product.tags.length > 0 && (
                            <div className="flex flex-wrap gap-1.5 mt-2">
                                {product.tags.map((tag: any) => (
                                    <span
                                        key={tag.id}
                                        className={cn(
                                            "inline-flex items-center px-2 py-0.5 rounded-md text-[12px] font-medium leading-none border transition-all duration-200",
                                            getTagColorClass(tag.id || tag.name_zh || tag.name)
                                        )}
                                    >
                                        {tag.name_zh || tag.name}
                                    </span>
                                ))}
                            </div>
                        )}

                        {/* 作者与浏览量信息 */}
                        <p className="text-[13px] text-muted-foreground mt-2 hover:text-foreground transition-colors truncate">
                            {product.author}
                        </p>
                        <p className="text-[12px] text-muted-foreground/80 truncate mt-0.5">
                            {formatViews(product.views_count)}次浏览 • {product.created_at_human}
                        </p>

                        {/* 价格结算区 */}
                        <div className="flex items-center justify-between mt-3 pt-2.5 border-t border-border/40">
                            <div className="flex flex-col">
                                <div className="flex items-baseline gap-1">
                                    <span className="text-[12px] font-semibold text-foreground">¥</span>
                                    <span className={cn(
                                        "text-[17px] font-bold tracking-tight",
                                        isSale ? "text-red-500 dark:text-red-400" : "text-foreground"
                                    )}>
                                        {product.price.toFixed(2)}
                                    </span>
                                </div>
                                {isSale && product.original_price && (
                                    <span className="text-[11px] text-muted-foreground line-through -mt-0.5">
                                        ¥&nbsp;{product.original_price.toFixed(2)}
                                    </span>
                                )}
                            </div>

                            <button
                                type="button"
                                aria-label="加入购物车"
                                className="flex items-center justify-center h-8 w-8 rounded-full bg-primary/10 text-primary hover:bg-primary hover:text-primary-foreground transition-colors"
                            >
                                <ShoppingBag className="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Link>
    );
}
