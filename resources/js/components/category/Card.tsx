import React from "react";
import { router } from "@inertiajs/react";
import { getCardHoverColor } from "@/lib/utils";
import { CategoryItem } from "@/types/category";
import CategoryActionMenu from "./ActionMenu";

/**
 * 分类卡片组件 Props 声明
 */
export interface CategoryCardProps {
    /** 分类数据对象 */
    category: CategoryItem;
    /** 当前是否已关注该分类 */
    isFollowing?: boolean;
    /** 是否正在获取最新状态 (用于菜单内部 Skeleton 骨架屏) */
    isGetting?: boolean;
    /** 是否正在提交关注/取消关注请求 (用于菜单内部 Loading 旋转) */
    isPosting?: boolean;
    /** 点击关注/取消关注的回调函数 */
    onToggleFollow?: (targetId: number | string) => void | Promise<void>;
    /** 点击卡片整体时的自定义回调 (若不传，则默认跳转到分类详情页) */
    onClick?: (category: CategoryItem) => void;
}

export default function CategoryCard({
    category,
    isFollowing = false,
    isGetting = false,
    isPosting = false,
    onToggleFollow,
    onClick,
}: CategoryCardProps) {
    // 根据 ID 获取一致性的悬浮发光背景样式
    const hoverBgStyle = getCardHoverColor(category.id);

    // 🎯 处理卡片点击跳转到详情页逻辑
    const handleCardClick = () => {
        // 如果外部传入了自定义 onClick 事件，优先使用外部逻辑
        if (onClick) {
            onClick(category);
            return;
        }

        // 默认跳转逻辑：拼装 /categories/{id}/{slug} 页面路径
        const targetSlug = category.slug ? `/${category.slug}` : '';
        const detailUrl = `/categories/${category.id}${targetSlug}`;

        // 执行 Inertia 无刷新跳转
        router.visit(detailUrl, {
            preserveScroll: false, // 跳转新页面时恢复滚动条至顶部
            preserveState: false,
        });
    };

    return (
        /* 🎯 卡片外层容器：绑定 handleCardClick */
        <div
            className="group relative flex flex-col z-0 w-full mt-3 cursor-pointer"
            onClick={handleCardClick}
        >
            {/* 🎯 光晕发散层：悬停时向外扩展显现 */}
            <div className={`absolute -inset-3 rounded-[24px] border border-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100 -z-10 ${hoverBgStyle}`} />

            {/* 🎯 卡片主体：16:9 统一长宽比 */}
            <div className="relative w-full aspect-video rounded-2xl overflow-hidden bg-muted shadow-sm">

                {/* 1. 高斯模糊衬底背景 (图片比例不齐时垫底填充，防留空白边) */}
                <img
                    src={category.coverImage}
                    alt=""
                    aria-hidden="true"
                    className="absolute inset-0 w-full h-full object-cover blur-xl scale-125 opacity-50 pointer-events-none"
                />

                {/* 2. 封面大图主层 */}
                <img
                    src={category.coverImage}
                    alt={category.name}
                    className="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out z-10"
                />

                {/* 3. 渐变深色遮罩 (保障文字对比度) */}
                <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-black/5 group-hover:from-black/80 transition-colors duration-300 z-20" />

                {/* 🎯 4. 顶部右上角：仅保留精简的内容数量角标 */}
                <div className="absolute top-3 right-3 z-30">
                    <span className="bg-white/20 backdrop-blur-md text-white text-[11px] px-2.5 py-1 rounded-full font-medium tracking-wide border border-white/10 select-none">
                        {category.itemCount} 内容
                    </span>
                </div>

                {/* 🎯 5. 底部文本信息与右下角三个点菜单按钮 */}
                <div className="absolute inset-0 p-4 sm:p-5 flex flex-col justify-end z-30">
                    <div className="flex justify-between items-end gap-3">

                        {/* 左侧：分类名称与清晰的描述文本 */}
                        <div className="flex flex-col min-w-0 flex-1">
                            <h3 className="text-xl font-bold text-white mb-1.5 tracking-tight group-hover:text-white/90 transition-colors truncate">
                                {category.name}
                            </h3>
                            <p className="text-sm text-white/85 line-clamp-2 font-normal leading-relaxed drop-shadow-sm">
                                {category.description}
                            </p>
                        </div>

                        {/* 🎯 右下角：三个点下拉菜单（内部包含 e.stopPropagation()，阻止卡片跳转触发） */}
                        <div className="opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-300 shrink-0">
                            <CategoryActionMenu
                                targetId={category.id}
                                isFollowing={isFollowing}
                                isGetting={isGetting}
                                isPosting={isPosting}
                                onToggleFollow={onToggleFollow}
                            />
                        </div>

                    </div>
                </div>

            </div>
        </div>
    );
}
