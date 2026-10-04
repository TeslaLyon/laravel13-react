import React, { useState, useEffect, useRef } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    Search as SearchIcon,
    Video as VideoIcon,
    Users,
    Clapperboard,
    LayoutGrid,
    Hash,
    MessageSquareX,
    Sparkles,
    X,
} from 'lucide-react';

import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { VideoCard } from '@/components/video/Card';

// --- 类型定义 ---
export interface SearchVideoItem {
    id: number;
    name: string;
    name_zh?: string | null;
    slug?: string;
    video_code?: string;
    channel_id?: number;
    list_img?: any;
    preview?: string;
    release_at?: string | null;
    country?: string | null;
    is_4k?: boolean;
    is_vr?: boolean;
    likes_count?: number;
    favorites_count?: number;
    created_at?: string;
    url: string;
    channel?: {
        id: number;
        name: string;
        slug: string;
        avatar?: string;
        data_crawl_type?: number;
    } | null;
}

export interface SearchActorItem {
    id: number;
    name: string;
    slug?: string;
    avatar?: string;
    url: string;
}

export interface SearchChannelItem {
    id: number;
    name: string;
    slug?: string;
    avatar?: string;
    logo?: string;
    url: string;
}

export interface SearchCategoryItem {
    id: number;
    name: string;
    name_zh?: string;
    slug?: string;
    url: string;
}

export interface GroupedResults {
    videos?: SearchVideoItem[];
    actors?: SearchActorItem[];
    channels?: SearchChannelItem[];
    categories?: SearchCategoryItem[];
}

interface GlobalSearchProps {
    query?: string;
    groupedResults?: GroupedResults;
}

type TabKey = 'all' | 'videos' | 'actors' | 'channels' | 'categories';

export default function GlobalSearch({ query = '', groupedResults }: GlobalSearchProps) {
    const [searchQuery, setSearchQuery] = useState(query);
    const [activeTab, setActiveTab] = useState<TabKey>('all');
    const inputRef = useRef<HTMLInputElement>(null);

    const results: GroupedResults = groupedResults || {
        videos: [],
        actors: [],
        channels: [],
        categories: [],
    };

    const videos = results.videos || [];
    const actors = results.actors || [];
    const channels = results.channels || [];
    const categories = results.categories || [];

    const totalResults = videos.length + actors.length + channels.length + categories.length;

    // 快捷键 ⌘K / Ctrl+K 聚焦输入框
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                inputRef.current?.focus();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    // 监听 props.query 变化同步至输入框
    useEffect(() => {
        setSearchQuery(query);
    }, [query]);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        const trimmed = searchQuery.trim();
        if (!trimmed) return;

        router.get('/search', { q: trimmed }, { preserveState: true });
    };

    const handleClear = () => {
        setSearchQuery('');
        inputRef.current?.focus();
    };

    const tabs: { key: TabKey; label: string; count: number; icon: React.ReactNode }[] = [
        { key: 'all', label: '全部', count: totalResults, icon: <Sparkles className="size-3.5" /> },
        { key: 'videos', label: '视频', count: videos.length, icon: <VideoIcon className="size-3.5" /> },
        { key: 'actors', label: '演员', count: actors.length, icon: <Users className="size-3.5" /> },
        { key: 'channels', label: '片商', count: channels.length, icon: <Clapperboard className="size-3.5" /> },
        { key: 'categories', label: '分类', count: categories.length, icon: <LayoutGrid className="size-3.5" /> },
    ];

    return (
        <>
            <Head title={query ? `"${query}" 的搜索结果` : '全站检索'} />

            <div className="container mx-auto px-4 py-8 sm:py-14 max-w-5xl space-y-8">
                {/* 1. 顶部搜索区域 */}
                <header className="space-y-4 text-center max-w-2xl mx-auto">
                    <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                        全站即时检索
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        支持搜索视频番号与标题、演员姓名、片商厂牌及分类标签
                    </p>

                    <div className="pt-2 max-w-2xl mx-auto w-full">
                        <form onSubmit={handleSearch} className="relative group flex items-center w-full">
                            <SearchIcon className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground/70 size-5 transition-colors group-focus-within:text-primary pointer-events-none z-10" />

                            <Input
                                ref={inputRef}
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="w-full pl-12 pr-36 h-13 text-base rounded-2xl bg-card border-border/70 hover:border-border focus-visible:ring-4 focus-visible:ring-primary/15 focus-visible:border-primary transition-all shadow-sm"
                                placeholder="输入视频名、番号、演员、片商或分类..."
                                autoFocus
                            />

                            <div className="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-2">
                                {searchQuery && (
                                    <button
                                        type="button"
                                        onClick={handleClear}
                                        className="p-1 rounded-md text-muted-foreground hover:text-foreground transition-colors"
                                        title="清空"
                                    >
                                        <X className="size-4" />
                                    </button>
                                )}

                                <div className="hidden sm:flex items-center text-muted-foreground/60 pointer-events-none">
                                    <kbd className="font-mono text-[11px] border border-border/80 rounded px-1.5 py-0.5 bg-muted">⌘K</kbd>
                                </div>

                                <Button
                                    type="submit"
                                    className="h-9.5 rounded-xl px-4 text-sm font-medium shadow-xs"
                                >
                                    搜索
                                </Button>
                            </div>
                        </form>
                    </div>
                </header>

                {/* 2. 检索结果分类标签栏 */}
                {query && (
                    <div className="flex flex-col sm:flex-row items-center justify-between gap-3 border-b border-border/60 pb-4">
                        <div className="flex items-center gap-1.5 overflow-x-auto w-full sm:w-auto pb-1 sm:pb-0 scrollbar-none">
                            {tabs.map((tab) => {
                                const isActive = activeTab === tab.key;
                                return (
                                    <button
                                        key={tab.key}
                                        type="button"
                                        onClick={() => setActiveTab(tab.key)}
                                        className={`flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-colors shrink-0 ${
                                            isActive
                                                ? 'bg-primary text-primary-foreground shadow-xs'
                                                : 'bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground'
                                        }`}
                                    >
                                        {tab.icon}
                                        <span>{tab.label}</span>
                                        <span className={`px-1.5 py-0.2 rounded-full text-[10px] ${
                                            isActive ? 'bg-primary-foreground/20 text-primary-foreground' : 'bg-background text-muted-foreground'
                                        }`}>
                                            {tab.count}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>

                        <div className="text-xs text-muted-foreground shrink-0 self-end sm:self-center">
                            共检索到 <span className="font-semibold text-foreground">{totalResults}</span> 条结果
                        </div>
                    </div>
                )}

                {/* 3. 搜索结果展示区 */}
                {query ? (
                    totalResults > 0 ? (
                        <div className="space-y-10">
                            {/* 1. 视频结果 */}
                            {(activeTab === 'all' || activeTab === 'videos') && videos.length > 0 && (
                                <section className="space-y-3">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <VideoIcon className="size-4.5 text-blue-500" />
                                            <h2 className="text-base font-semibold text-foreground">视频</h2>
                                            <Badge variant="secondary" className="text-xs px-2 py-0">
                                                {videos.length}
                                            </Badge>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-3 gap-y-8">
                                        {videos.map((video) => (
                                            <Link
                                                key={`video-${video.id}`}
                                                href={video.url}
                                            >
                                                <VideoCard video={video as any} />
                                            </Link>
                                        ))}
                                    </div>
                                </section>
                            )}

                            {/* 2. 演员结果 */}
                            {(activeTab === 'all' || activeTab === 'actors') && actors.length > 0 && (
                                <section className="space-y-3">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Users className="size-4.5 text-pink-500" />
                                            <h2 className="text-base font-semibold text-foreground">演员</h2>
                                            <Badge variant="secondary" className="text-xs px-2 py-0">
                                                {actors.length}
                                            </Badge>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                                        {actors.map((actor) => (
                                            <Link
                                                key={`actor-${actor.id}`}
                                                href={actor.url}
                                                className="group flex flex-col items-center p-3.5 rounded-2xl border border-border/60 bg-card hover:border-border hover:shadow-md transition-all text-center"
                                            >
                                                <Avatar className="size-16 rounded-full border border-border/50 group-hover:scale-105 transition-transform duration-200">
                                                    <AvatarImage src={actor.avatar || ''} alt={actor.name} className="object-cover" />
                                                    <AvatarFallback className="text-sm font-semibold bg-pink-500/10 text-pink-600">
                                                        {actor.name.slice(0, 2)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="mt-2.5 w-full">
                                                    <div className="text-xs font-medium text-foreground truncate group-hover:text-primary transition-colors">
                                                        {actor.name}
                                                    </div>
                                                </div>
                                            </Link>
                                        ))}
                                    </div>
                                </section>
                            )}

                            {/* 3. 片商结果 */}
                            {(activeTab === 'all' || activeTab === 'channels') && channels.length > 0 && (
                                <section className="space-y-3">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Clapperboard className="size-4.5 text-amber-500" />
                                            <h2 className="text-base font-semibold text-foreground">片商</h2>
                                            <Badge variant="secondary" className="text-xs px-2 py-0">
                                                {channels.length}
                                            </Badge>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                                        {channels.map((channel) => (
                                            <Link
                                                key={`channel-${channel.id}`}
                                                href={channel.url}
                                                className="group flex flex-col items-center p-3.5 rounded-2xl border border-border/60 bg-card hover:border-border hover:shadow-md transition-all text-center"
                                            >
                                                <Avatar className="size-16 rounded-2xl border border-border/50 group-hover:scale-105 transition-transform duration-200">
                                                    <AvatarImage src={channel.logo || channel.avatar || ''} alt={channel.name} className="object-cover" />
                                                    <AvatarFallback className="text-sm font-semibold bg-amber-500/10 text-amber-600">
                                                        {channel.name.slice(0, 2)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="mt-2.5 w-full">
                                                    <div className="text-xs font-medium text-foreground truncate group-hover:text-primary transition-colors">
                                                        {channel.name}
                                                    </div>
                                                </div>
                                            </Link>
                                        ))}
                                    </div>
                                </section>
                            )}

                            {/* 4. 分类结果 */}
                            {(activeTab === 'all' || activeTab === 'categories') && categories.length > 0 && (
                                <section className="space-y-3">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <LayoutGrid className="size-4.5 text-emerald-500" />
                                            <h2 className="text-base font-semibold text-foreground">分类</h2>
                                            <Badge variant="secondary" className="text-xs px-2 py-0">
                                                {categories.length}
                                            </Badge>
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap gap-2.5">
                                        {categories.map((cat) => (
                                            <Link
                                                key={`cat-${cat.id}`}
                                                href={cat.url}
                                                className="group inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-border/70 bg-card hover:border-primary/50 hover:bg-primary/5 hover:text-primary transition-all text-xs font-medium"
                                            >
                                                <Hash className="size-3 text-muted-foreground group-hover:text-primary transition-colors" />
                                                <span>{cat.name_zh || cat.name}</span>
                                                {cat.name_zh && cat.name && cat.name !== cat.name_zh && (
                                                    <span className="text-[11px] text-muted-foreground/80 font-normal">
                                                        ({cat.name})
                                                    </span>
                                                )}
                                            </Link>
                                        ))}
                                    </div>
                                </section>
                            )}
                        </div>
                    ) : (
                        /* 空搜索状态 */
                        <div className="text-center py-16 px-6 rounded-3xl border border-dashed border-border/80 bg-card/50">
                            <div className="size-16 rounded-full bg-muted/80 flex items-center justify-center mx-auto mb-4 text-muted-foreground">
                                <MessageSquareX className="size-8" />
                            </div>
                            <h2 className="text-lg font-semibold text-foreground mb-1">未找到相关结果</h2>
                            <p className="text-sm text-muted-foreground max-w-sm mx-auto">
                                全站暂未发现与 “<span className="font-medium text-foreground">{query}</span>” 匹配的内容。请尝试更换关键词或检查拼写。
                            </p>
                        </div>
                    )
                ) : (
                    /* 初始未搜索推荐/引导 */
                    <div className="text-center py-16 px-6 rounded-3xl border border-border/60 bg-card/40">
                        <div className="size-14 rounded-2xl bg-primary/10 text-primary flex items-center justify-center mx-auto mb-3">
                            <Sparkles className="size-7" />
                        </div>
                        <h2 className="text-base font-semibold text-foreground mb-1">请输入搜索关键词</h2>
                        <p className="text-xs text-muted-foreground max-w-sm mx-auto">
                            支持按番号、视频标题、演员名字、片商或分类进行多维全站检索。
                        </p>
                    </div>
                )}
            </div>
        </>
    );
}
