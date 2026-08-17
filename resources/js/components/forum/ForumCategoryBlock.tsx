import React, { useState } from 'react';
import ForumNodeItem from './ForumNodeItem';
import { ForumCategory } from '@/types/forum';
import { ChevronDown } from 'lucide-react';

export default function ForumCategoryBlock({ category }: { category: ForumCategory }) {
    const [isCollapsed, setIsCollapsed] = useState(false);

    return (
        <div className="w-full bg-card rounded-2xl border border-border/80 shadow-sm overflow-hidden mb-6">
            {/* 分类标题栏 */}
            <div
                className="flex items-center justify-between px-5 py-3.5 bg-muted/60 border-b border-border/80 cursor-pointer select-none hover:bg-muted/80 transition-colors"
                onClick={() => setIsCollapsed(!isCollapsed)}
            >
                <h2 className="text-base font-bold text-foreground tracking-tight">
                    {category.name}
                </h2>
                <button type="button" className="text-muted-foreground">
                    <ChevronDown className={`w-4 h-4 transition-transform duration-200 ${isCollapsed ? '-rotate-90' : ''}`} />
                </button>
            </div>

            {/* 版块列表 */}
            {!isCollapsed && (
                <div className="divide-y divide-border/40">
                    {category.nodes && category.nodes.length > 0 ? (
                        category.nodes.map((node) => (
                            <ForumNodeItem key={node.id} node={node} />
                        ))
                    ) : (
                        <div className="p-6 text-center text-xs text-muted-foreground">暂无版块</div>
                    )}
                </div>
            )}
        </div>
    );
}
