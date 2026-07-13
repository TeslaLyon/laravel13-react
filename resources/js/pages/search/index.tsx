import React, { useState, useEffect, useRef } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    Search as SearchIcon,
    BookOpen,
    FileText,
    Package,
    ChevronRight,
    MessageSquareX
} from 'lucide-react';

import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';

// --- 类型定义 ---
interface SearchItem {
    id: string;
    title: string;
    excerpt: string;
    url: string;
}

interface GroupedResults {
    help?: SearchItem[];
    blog?: SearchItem[];
    products?: SearchItem[];
}

interface GlobalSearchProps {
    query?: string;
    groupedResults?: GroupedResults;
}

export default function GlobalSearch({ query = '', groupedResults }: GlobalSearchProps) {
    const [searchQuery, setSearchQuery] = useState(query);
    const inputRef = useRef<HTMLInputElement>(null);

    // 默认演示数据 (如果 Laravel 后端未传递 props)
    const displayResults = groupedResults || {
        products: [{ id: 'p1', title: '高级订阅会员 (Pro)', excerpt: '解锁所有功能，享受极速响应...', url: '/pricing' }],
        help: [{ id: 'h1', title: '如何修改密码？', excerpt: '了解重置密码的详细步骤...', url: '/help/article/h1' }],
        blog: [{ id: 'b1', title: '2026年网站设计趋势', excerpt: '极简主义与大圆角的回归...', url: '/blog/b1' }]
    };

    // 监听 Cmd+K 或 Ctrl+K 快捷键，自动聚焦搜索框
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault(); // 阻止浏览器默认行为（如 Chrome 的搜索栏）
                inputRef.current?.focus();
            }
        };

        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, []);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        if (!searchQuery.trim()) return;

        // 发起 Inertia 搜索请求
        router.get('/search', { q: searchQuery }, { preserveState: true });
    };

    const totalResults =
        (displayResults.help?.length || 0) +
        (displayResults.blog?.length || 0) +
        (displayResults.products?.length || 0);

    // 渲染单个分组结果的辅助函数
    const renderGroup = (title: string, icon: React.ReactNode, items?: SearchItem[]) => {
        if (!items || items.length === 0) return null;

        return (
            <div className="mb-10 last:mb-0">
                <div className="flex items-center space-x-2 mb-4 px-2">
                    <div className="text-blue-600">{icon}</div>
                    <h2 className="text-lg font-semibold text-slate-900">{title}</h2>
                    <span className="text-xs font-medium bg-slate-100 text-slate-500 px-2 py-1 rounded-full">
                        {items.length}
                    </span>
                </div>

                <div className="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden divide-y divide-slate-100">
                    {items.map((item) => (
                        <Link
                            key={item.id}
                            href={item.url}
                            className="group flex items-start sm:items-center p-5 sm:px-6 hover:bg-slate-50 transition-colors block"
                        >
                            <div className="flex-1 pr-4">
                                <h3 className="text-base font-medium text-slate-900 group-hover:text-blue-600 transition-colors">
                                    {item.title}
                                </h3>
                                {/* 实际应用中，如果接入了 Meilisearch 的高亮，这里可以使用 dangerouslySetInnerHTML */}
                                <p className="text-slate-500 text-sm mt-1 line-clamp-1">
                                    {item.excerpt}
                                </p>
                            </div>
                            <div className="ml-auto pt-1 sm:pt-0">
                                <ChevronRight className="w-5 h-5 text-slate-300 group-hover:text-blue-500 transition-transform group-hover:translate-x-1" />
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        );
    };

    return (
        <>
            <Head title={query ? `"${query}" 的搜索结果` : '全站搜索'} />

            <div className="container mx-auto px-4 py-12 sm:py-20 space-y-12">

                {/* 1. 顶部搜索区域 */}
                <header className="space-y-6 text-center">
                    <h1 className="text-3xl sm:text-4xl font-semibold tracking-tight text-slate-900">
                        在全站寻找答案
                    </h1>

                    <form onSubmit={handleSearch} className="relative group max-w-3xl w-full mx-auto">
                        <SearchIcon className="absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 w-6 h-6 transition-colors group-focus-within:text-blue-500 z-10" />

                        <Input
                            ref={inputRef}
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="w-full pl-14 pr-28 h-16 text-lg rounded-2xl bg-slate-50 border-transparent hover:border-slate-200 focus-visible:ring-4 focus-visible:ring-blue-500/20 focus-visible:border-blue-500 transition-all shadow-sm"
                            placeholder="搜索文章、产品、帮助文档..."
                            autoFocus
                        />

                        {/* 快捷键提示 (仅在大屏幕显示，增添细节感) */}
                        <div className="absolute right-28 top-1/2 -translate-y-1/2 hidden sm:flex items-center space-x-1 text-slate-300 pointer-events-none">
                            <kbd className="font-sans text-xs border border-slate-200 rounded px-1.5 py-0.5 bg-white">⌘</kbd>
                            <kbd className="font-sans text-xs border border-slate-200 rounded px-1.5 py-0.5 bg-white">K</kbd>
                        </div>

                        <Button
                            type="submit"
                            className="absolute right-2 top-1/2 -translate-y-1/2 h-12 rounded-xl px-6 bg-blue-600 hover:bg-blue-700 text-white shadow-sm transition-colors"
                        >
                            搜索
                        </Button>
                    </form>
                </header>

                {/* 2. 搜索结果展示区 */}
                {(query || totalResults > 0) && (
                    <section className="max-w-3xl mx-auto">
                        {totalResults > 0 ? (
                            <div className="space-y-2">
                                <p className="text-sm text-slate-500 mb-8 px-2 text-center sm:text-left">
                                    为您找到 {totalResults} 条关于 "{query || '默认展示'}" 的结果
                                </p>

                                {/* 渲染各个分组 */}
                                {renderGroup('产品与服务', <Package className="w-5 h-5" />, displayResults.products)}
                                {renderGroup('帮助文档', <BookOpen className="w-5 h-5" />, displayResults.help)}
                                {renderGroup('博客文章', <FileText className="w-5 h-5" />, displayResults.blog)}
                            </div>
                        ) : (
                            /* 空状态 */
                            <div className="text-center py-20 px-6 bg-slate-50 rounded-3xl border border-slate-100 border-dashed">
                                <div className="w-20 h-20 bg-white rounded-full flex items-center justify-center mx-auto mb-6 shadow-sm">
                                    <MessageSquareX className="w-10 h-10 text-slate-400" />
                                </div>
                                <h2 className="text-xl font-semibold text-slate-900 mb-2">未找到相关内容</h2>
                                <p className="text-slate-500 max-w-md mx-auto">
                                    我们在全站都没有找到与“<span className="font-medium text-slate-700">{query}</span>”匹配的信息。请尝试精简您的搜索词。
                                </p>
                            </div>
                        )}
                    </section>
                )}
            </div>
        </>
    );
}
