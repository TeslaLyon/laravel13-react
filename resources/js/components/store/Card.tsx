import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Play, Image as ImageIcon, ShoppingBag } from 'lucide-react';
import { StoreMenu } from '@/components/store/Menu';
import { Product } from '@/types/store';
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";

export function ProductCard({ product }: { product: Product }) {
    // 动态悬浮背景色池 (保留你精妙的设计)
    const hoverColorPool = [
        "bg-red-500/10 dark:bg-red-500/20",
        "bg-blue-500/10 dark:bg-blue-500/20",
        "bg-green-500/10 dark:bg-green-500/20",
        "bg-yellow-500/10 dark:bg-yellow-500/20",
        "bg-purple-500/10 dark:bg-purple-500/20",
        "bg-pink-500/10 dark:bg-pink-500/20",
        "bg-indigo-500/10 dark:bg-indigo-500/20",
        "bg-orange-500/10 dark:bg-orange-500/20",
    ];
    // 为了防止 product.id 为数字或字符串时报错，这里加个容错处理
    const colorIndex = Number(product.id) % hoverColorPool.length;
    console.log("colorIndex: ", colorIndex);
    console.log("productId: ", product.id);
    const hoverBgStyle = hoverColorPool[colorIndex];

    // 判断是否为特价商品（有原价且原价大于现价）
    const isSale = product.originalPrice && product.originalPrice > product.price;

    return (
        <>
            <Link href={`/store/product`}>
                <div className="group relative flex flex-col gap-1 cursor-pointer z-0">
                    {/* 炫酷的底层悬浮光效 */}
                    <div className={`absolute -inset-3 rounded-2xl border border-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100 -z-10 ${hoverBgStyle}`}></div>

                    {/* 封面区域 (严格 16:9 + 悬浮缩放) */}
                    <div className="relative w-full aspect-video rounded-xl overflow-hidden bg-muted border border-border/50">
                        <img
                            // 如果 product 没有 thumbnail，则使用你的默认占位图
                            src={product.thumbnail || "https://media-public-ht.project1content.com/m=eaFaWHbWx/1bd/c20/e85/07c/486/5a5/67b/b43/a5f/47f/26/poster/poster_01.jpg"}
                            alt={product.title}
                            className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                        />

                        {/* 媒体类型角标 (左上角) */}
                        <div className="absolute top-2 left-2">
                            <div className="px-2 py-1 rounded-md bg-background/80 backdrop-blur-md shadow-sm flex items-center gap-1.5">
                                {product.type === 'video' ? (
                                    <Play className="w-3 h-3 fill-foreground text-foreground" />
                                ) : (
                                    <ImageIcon className="w-3 h-3 text-foreground" />
                                )}
                                <span className="text-[10px] font-bold text-foreground tracking-wide uppercase">
                                    {product.type === 'video' ? 'Video' : 'Image'}
                                </span>
                            </div>
                        </div>

                        {/* 时长/分辨率信息 (右下角) */}
                        <div className="absolute bottom-2 right-2">
                            <span className="px-1.5 py-0.5 rounded text-[11px] font-medium text-white bg-black/70 backdrop-blur-sm">
                                {product.type === 'video' ? product.duration : product.resolution}
                            </span>
                        </div>
                    </div>

                    {/* 信息区域 */}
                    <div className="flex gap-3 px-1 mt-2">
                        <Avatar className="h-9 w-9 shrink-0 mt-0.5 ring-1 ring-border/50 shadow-sm">
                            <AvatarImage src={product.avatar} alt={product.author} />
                            <AvatarFallback>{product.author?.substring(0, 2) || 'UK'}</AvatarFallback>
                        </Avatar>

                        <div className="flex flex-col flex-1 min-w-0">
                            {/* 标题 & 右侧菜单 */}
                            <div className="flex items-start justify-between gap-2">
                                <h3 className="text-[15px] font-semibold leading-tight line-clamp-2 text-primary group-hover:text-blue-500 transition-colors pt-1">
                                    {product.title}
                                </h3>
                                <div className="shrink-0 -mt-1 -mr-2 transition-opacity p-1 hover:bg-muted-foreground/20 rounded-full">
                                    <StoreMenu videoId={product.id} slug={product.author} />
                                </div>
                            </div>

                            {/* 彩色属性标签组 */}
                            {product.tags && product.tags.length > 0 && (
                                <div className="flex flex-wrap gap-1.5 mt-1.5">
                                    {product.tags.map((tag, idx) => (
                                        <span
                                            key={idx}
                                            className={cn(
                                                "px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider",
                                                tag.color || "bg-muted text-muted-foreground" // 默认颜色
                                            )}
                                        >
                                            {tag.label}
                                        </span>
                                    ))}
                                </div>
                            )}

                            {/* 作者与数据 (我修复了原先重复显示 title 的问题) */}
                            <p className="text-[13px] text-muted-foreground mt-1.5 hover:text-primary transition-colors truncate">
                                {product.author}
                            </p>
                            <p className="text-[12px] text-muted-foreground truncate mt-0.5">
                                {product.views || '0'}次观看 • 2小时前
                            </p>

                            {/* 价格与购物车区域 (带分割线增加层级感) */}
                            <div className="flex items-center justify-between mt-3 pt-2.5 border-t border-border/40">
                                <div className="flex flex-col">
                                    {/* 现价显示 */}
                                    <div className="flex items-baseline gap-1">
                                        <span className="text-[12px] font-semibold text-foreground">¥</span>
                                        <span className={cn(
                                            "text-[17px] font-bold tracking-tight",
                                            isSale ? "text-red-500 dark:text-red-400" : "text-foreground" // 特价红色逻辑
                                        )}>
                                            {product.price?.toFixed(2) || '0.00'}
                                        </span>
                                    </div>
                                    {/* 原价删除线 (仅特价时显示) */}
                                    {isSale && (
                                        <span className="text-[11px] text-muted-foreground line-through -mt-0.5">
                                            ¥&nbsp;{product.originalPrice?.toFixed(2)}
                                        </span>
                                    )}
                                </div>

                                {/* 迷你购买按钮 */}
                                <button className="flex items-center justify-center h-8 w-8 rounded-full bg-primary/10 text-primary hover:bg-primary hover:text-primary-foreground transition-colors">
                                    <ShoppingBag className="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </Link>
        </>

    );
}
