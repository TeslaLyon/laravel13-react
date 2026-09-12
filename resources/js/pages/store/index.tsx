import React from 'react';
import { Head, router, Deferred } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { ProductCard } from '@/components/store/Card';
import { StoreGridSkeleton } from '@/components/store/CardSkeleton';
import { BreadcrumbItem, StoreCategory, Product, PaginatedData } from '@/types/store';

interface Props {
    breadcrumbs: BreadcrumbItem[];
    categories: StoreCategory[];
    activeType?: string | null;
    products?: PaginatedData<Product>; // 延迟属性
}

export default function StoreFront({ categories, activeType, products }: Props) {
    // 切换分类时使用 Inertia 无刷新局部重载
    const handleCategoryClick = (type: string | null) => {
        router.get(
            `/store`,
            type ? { type } : {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['products', 'activeType'],
            }
        );
    };

    return (
        <>
            <Head title="数字资产商城" />

            <div className="container mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
                {/* 顶部标题与分类过滤 */}
                <div className="mb-10 space-y-6">
                    <h1 className="text-4xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                        探索创作者资源
                    </h1>

                    {/* 分类药丸按钮 (Pill Tabs) */}
                    <div className="flex items-center space-x-3 overflow-x-auto pb-2 scrollbar-hide">
                        {categories.map((cat) => {
                            const isActive = (cat.type === null && !activeType) || cat.type === activeType;
                            return (
                                <Button
                                    key={cat.label}
                                    variant={isActive ? 'default' : 'secondary'}
                                    className={`rounded-full px-5 font-medium transition-all ${isActive
                                            ? 'bg-zinc-900 text-white hover:bg-zinc-800 dark:bg-zinc-50 dark:text-zinc-900'
                                            : 'bg-zinc-100 hover:bg-zinc-200 text-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-300'
                                        }`}
                                    onClick={() => handleCategoryClick(cat.type)}
                                >
                                    {cat.label}
                                </Button>
                            );
                        })}
                    </div>
                </div>

                {/* 异步商品列表流：通过 Deferred 装载，展示 1:1 防抖动骨架 */}
                <Deferred
                    data="products"
                    fallback={<StoreGridSkeleton count={8} />}
                >
                    {products && products.data.length > 0 ? (
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-x-3 gap-y-6">
                            {products.data.map((product) => (
                                <ProductCard key={product.id} product={product} />
                            ))}
                        </div>
                    ) : (
                        <div className="flex flex-col items-center justify-center py-20 text-center">
                            <p className="text-lg font-medium text-muted-foreground">暂无相关资源商品</p>
                        </div>
                    )}
                </Deferred>
            </div>
        </>
    );
}
