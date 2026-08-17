import React from 'react';
import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight } from 'lucide-react';

interface MediaGridProps {
    items: any[];
    type: 'video' | 'image';
    // 假设后端返回的分页数据结构
    pagination?: {
        currentPage: number;
        lastPage: number;
    };
    onPageChange?: (page: number) => void;
}

export function MediaGrid({ items, type, pagination, onPageChange }: MediaGridProps) {
    return (
        <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                {items.map((item, index) => (
                    <div key={item.id || index} className="w-full">
                        {/* 💡 在这里替换成你自己的视频/图片卡片组件 */}
                        {/* 例如: <VideoCard video={item} /> */}
                        <div className="aspect-video bg-muted/50 rounded-2xl border border-border flex flex-col items-center justify-center text-sm text-muted-foreground hover:bg-muted transition-colors cursor-pointer overflow-hidden">
                            <span>{type === 'video' ? '视频卡片组件占位' : '图片卡片组件占位'}</span>
                            <span className="text-xs">{item.title || `Item ${index + 1}`}</span>
                        </div>
                    </div>
                ))}
            </div>

            {/* 分页控制区 */}
            {pagination && pagination.lastPage > 1 && (
                <div className="flex items-center justify-center gap-2 mt-8 pb-4">
                    <Button
                        variant="outline"
                        size="icon"
                        disabled={pagination.currentPage === 1}
                        onClick={() => onPageChange?.(pagination.currentPage - 1)}
                    >
                        <ChevronLeft className="h-4 w-4" />
                    </Button>
                    <span className="text-sm text-muted-foreground mx-4">
                        第 {pagination.currentPage} 页 / 共 {pagination.lastPage} 页
                    </span>
                    <Button
                        variant="outline"
                        size="icon"
                        disabled={pagination.currentPage === pagination.lastPage}
                        onClick={() => onPageChange?.(pagination.currentPage + 1)}
                    >
                        <ChevronRight className="h-4 w-4" />
                    </Button>
                </div>
            )}
        </div>
    );
}
