import React from 'react';
import { Head, Deferred } from '@inertiajs/react';
import { BreadcrumbItem, ProductShowData } from '@/types/store';
import { ProductDetailSkeleton } from './partials/product-detail-skeleton';
import { ProductDetailContent } from './partials/product-detail-content';

interface Props {
    breadcrumbs: BreadcrumbItem[];
    product?: ProductShowData; // 延迟属性，初次加载为 undefined
}

export default function ProductDetail({ breadcrumbs, product }: Props) {
    return (
        <>
            <Head title={product ? `${product.title} - 数字资产商城` : '商品详情 - 加载中...'} />

            <div className="container mx-auto px-4 py-6 md:py-10 max-w-[1360px]">
                {/* 异步商品详情流：Deferred 延迟加载并展示 1:1 骨架屏 */}
                <Deferred
                    data="product"
                    fallback={<ProductDetailSkeleton />}
                >
                    <ProductDetailContent product={product!} />
                </Deferred>
            </div>
        </>
    );
}
