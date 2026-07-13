import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Play, Image as ImageIcon, ShoppingCart } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { StoreMenu } from '@/components/store/Menu';
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { ProductCard } from '@/components/store/Card';

// 引入上面定义的类型 (如果在同一个文件可以直接写在上面)
import { Product, MediaType } from '@/types/store';

// 模拟数据 (实际开发中这部分将通过 Inertia 的 props 从 Laravel 控制器传入)
const MOCK_PRODUCTS: Product[] = [
    {
        id: 101,
        title: 'DaVinci Resolve 19 调色大师班 - 从入门到电影级色彩科学全解析 (2026版)',
        author: 'Colorist Pro',
        avatar: 'https://i.pravatar.cc/150?u=101',
        thumbnail: 'https://images.unsplash.com/photo-1574717024653-61fd2cf4d44d?q=80&w=1920&auto=format&fit=crop',
        type: 'video',
        duration: '12:45:00',
        views: '3.4万',
        price: 199.00,
        originalPrice: 299.00, // 触发特价
        tags: [
            { label: '4K超清', color: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' },
            { label: '独家首发', color: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' },
            { label: '附赠工程', color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400' }
        ]
    },
    {
        id: 102,
        title: '赛博朋克 2077 风格夜景建筑摄影素材包 (包含 50 张精修原图)',
        author: 'Neon Dreamer',
        avatar: 'https://i.pravatar.cc/150?u=102',
        thumbnail: 'https://images.unsplash.com/photo-1605810230434-7631ac76ec81?q=80&w=1920&auto=format&fit=crop',
        type: 'image',
        resolution: '8K RAW',
        views: '1.2万',
        price: 39.90, // 无原价，正常显示
        tags: [
            { label: '商用授权', color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' },
            { label: 'RAW', color: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-400' }
        ]
    },
    {
        id: 103,
        title: 'Blender 4.0 几何节点生成科幻城市完整工程文件',
        author: 'CGI Master',
        avatar: 'https://i.pravatar.cc/150?u=103',
        thumbnail: 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?q=80&w=1920&auto=format&fit=crop',
        type: 'video',
        duration: '03:15:20',
        views: '8900',
        price: 89.00,
        tags: [
            { label: '3D 模型', color: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400' },
            { label: '附赠贴图', color: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' }
        ]
    },
    {
        id: 104,
        title: '极简主义 UI 设计情绪板素材合集 (全面支持 Figma & Sketch)',
        author: 'UI/UX Daily',
        avatar: 'https://i.pravatar.cc/150?u=104',
        thumbnail: 'https://images.unsplash.com/photo-1561070791-2526d30994b5?q=80&w=1920&auto=format&fit=crop',
        type: 'image',
        resolution: 'Vector/PNG',
        views: '4.5万',
        price: 9.99,
        originalPrice: 29.99, // 触发特价
        tags: [
            { label: 'Figma', color: 'bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-400' },
            { label: '特惠', color: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400' }
        ]
    },
    {
        id: 105,
        title: 'Sony A7M4 S-Log3 电影感 LUTs 预设包 (附套用与微调教程)',
        author: 'Lens Story',
        avatar: 'https://i.pravatar.cc/150?u=105',
        thumbnail: 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?q=80&w=1920&auto=format&fit=crop',
        type: 'video',
        duration: '00:25:40',
        views: '2.1万',
        price: 59.00,
        originalPrice: 99.00, // 触发特价
        tags: [
            { label: 'LUTs', color: 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-400' },
            { label: '索尼', color: 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400' }
        ]
    },
    {
        id: 106,
        title: '冰川与极光 - 冰岛无人机航拍精选图集 (大画幅输出级)',
        author: 'Drone Explorer',
        avatar: 'https://i.pravatar.cc/150?u=106',
        thumbnail: 'https://images.unsplash.com/photo-1476610182048-b716b8518aae?q=80&w=1920&auto=format&fit=crop',
        type: 'image',
        resolution: '50MP',
        views: '6700',
        price: 129.00,
        tags: [
            { label: '航拍', color: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-400' },
            { label: '风光摄影', color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' }
        ]
    }
];

const CATEGORIES = ['全部', '视频教程', '高清图库', '素材工程', '3D 模型'];

export default function StoreFront() {
    const [activeCategory, setActiveCategory] = useState('全部');

    return (
        <>
            <Head title="数字资产商城" />

            {/* 中心内容容器，限制最大宽度并居中 */}
            <div className="container mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">

                {/* 顶部标题与分类 (Apple 风格排版：大标题，紧凑字距) */}
                <div className="mb-10 space-y-6">
                    <h1 className="text-4xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                        探索创作者资源
                    </h1>

                    {/* YouTube 风格的分类药丸药丸按钮 (Pill Tabs) */}
                    <div className="flex items-center space-x-3 overflow-x-auto pb-2 scrollbar-hide">
                        {CATEGORIES.map((category) => (
                            <Button
                                key={category}
                                variant={activeCategory === category ? 'default' : 'secondary'}
                                className={`rounded-full px-5 font-medium transition-all ${activeCategory === category
                                    ? 'bg-zinc-900 text-white hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900'
                                    : 'bg-zinc-100 hover:bg-zinc-200 text-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-300'
                                    }`}
                                onClick={() => setActiveCategory(category)}
                            >
                                {category}
                            </Button>
                        ))}
                    </div>
                </div>

                {/* 响应式网格布局 (YouTube 风格：自适应列数) */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-x-3 gap-y-8">
                    {MOCK_PRODUCTS.map((product) => (
                        <ProductCard key={product.id} product={product} />
                    ))}
                </div>
            </div>
        </>
    );
}
