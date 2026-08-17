import React from "react";
import ActorCard from "@/components/actor/Card";
import { PaginatedResponse, Actor } from "@/types/actor";
import { Link, router, usePage, Deferred } from "@inertiajs/react";
import { show } from "@/routes/actors";
import { VideoPagination } from '@/components/VideoPagination';
import ActorCardSkeleton from "@/components/actor/CardSkeleton";
import { Skeleton } from "@/components/ui/skeleton"; // 1. 引入 Skeleton 骨架屏组件

// 快捷筛选菜单配置
interface FilterOption {
    id: string;
    label: string;
}

const FILTER_OPTIONS: FilterOption[] = [
    { id: "all", label: "全部演员" },
    { id: "most_subscribed", label: "🔥 最多订阅" },
    { id: "top_rated", label: "⭐ 高评分" },
    { id: "large_bust", label: "丰满胸围" },
    { id: "big_booty", label: "性感美臀" },
    { id: "newbie", label: "✨ 新人首发" },
    { id: "most_works", label: "🎬 热门高产" },
];

// 定义 Props 接口：接收异步的 actors 和即时送达的 perPage
interface ActorListPageProps {
    actors?: PaginatedResponse<Actor>;
    perPage?: number;
}

export default function ActorListPage({ actors, perPage = 12 }: ActorListPageProps) {
    const { url } = usePage();
    const searchParams = new URLSearchParams(url.split("?")[1] || "");
    const activeFilter = searchParams.get("filter") || "all";

    // 由于 perPage 是同步发来的，骨架屏渲染时可以直接使用准确的 perPage 数量！
    const skeletonCount = perPage;

    // 处理菜单点击
    const handleFilterClick = (filterId: string) => {
        if (filterId === "all") {
            router.get("/actors", {}, { preserveState: true, preserveScroll: true });
        } else {
            router.get("/actors", { filter: filterId }, { preserveState: true, preserveScroll: true });
        }
    };

    return (
        <div className="w-full p-4 md:p-8 bg-background min-h-screen">

            {/* 页面标题 */}
            <h1 className="text-2xl font-bold mb-4">推荐演员</h1>

            {/* 横向快捷筛选菜单栏（隐藏滚动条） */}
            <div className="flex items-center gap-2.5 overflow-x-auto pb-3 mb-6 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                {FILTER_OPTIONS.map((option) => {
                    const isActive = activeFilter === option.id;
                    return (
                        <button
                            key={option.id}
                            onClick={() => handleFilterClick(option.id)}
                            className={`px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition-all duration-200 cursor-pointer select-none ${isActive
                                ? "bg-primary text-primary-foreground shadow-md scale-105"
                                : "bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground"
                                }`}
                        >
                            {option.label}
                        </button>
                    );
                })}
            </div>

            {/* 1. 演员网格列表与动态骨架屏 */}
            <Deferred
                data="actors"
                fallback={
                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-5 gap-x-3 gap-y-8">
                        {Array.from({ length: skeletonCount }).map((_, index) => (
                            <ActorCardSkeleton key={index} />
                        ))}
                    </div>
                }
            >
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-5 gap-x-3 gap-y-8">
                    {actors?.data.map((actor) => (
                        <Link href={show({ actor: actor.id, slug: actor.slug })} key={actor.id}>
                            <ActorCard actor={actor} />
                        </Link>
                    ))}
                </div>
            </Deferred>

            {/* 2. 分页组件：包裹在 Deferred 中，未加载完成时展示与实际组件等高的骨架屏 */}
            <Deferred
                data="actors"
                fallback={
                    <div className="flex items-center justify-center mt-16 mb-12">
                        {/* 模拟分页组件栏的骨架：包含高度 h-9 和宽度 w-72/w-80，与真实 Pagination 高度一致 */}
                        <Skeleton className="h-9 w-72 sm:w-80 rounded-lg" />
                    </div>
                }
            >
                <div className="flex flex-col items-center justify-center mt-10 mb-12 gap-2">
                    <VideoPagination links={actors?.links} />
                </div>
            </Deferred>

        </div>
    );
}