import React, { useState, useEffect, useRef, useCallback } from 'react';
import { router } from '@inertiajs/react';
import {
    Search,
    Video as VideoIcon,
    Users,
    Clapperboard,
    LayoutGrid,
    Loader2,
    X,
    ArrowRight,
    CornerDownLeft,
    Sparkles,
} from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useSidebar, SidebarMenu, SidebarMenuItem, SidebarMenuButton } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';

export interface SearchVideoItem {
    id: number;
    name: string;
    name_zh?: string | null;
    slug?: string;
    video_code?: string;
    list_img?: any;
    release_at?: string | null;
    url: string;
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

export interface GlobalSearchResponse {
    videos: SearchVideoItem[];
    actors: SearchActorItem[];
    channels: SearchChannelItem[];
    categories: SearchCategoryItem[];
}

const EMPTY_RESULTS: GlobalSearchResponse = {
    videos: [],
    actors: [],
    channels: [],
    categories: [],
};

export function SidebarSearch({ className }: { className?: string }) {
    const { state, isMobile } = useSidebar();
    const isCollapsed = state === 'collapsed' && !isMobile;

    const [isOpen, setIsOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<GlobalSearchResponse>(EMPTY_RESULTS);
    const [isLoading, setIsLoading] = useState(false);

    const isComposingRef = useRef(false);
    const debounceTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const abortControllerRef = useRef<AbortController | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    // 全局快捷键 ⌘K / Ctrl+K 唤醒
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setIsOpen((prev) => !prev);
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    // 弹窗打开时自动聚焦
    useEffect(() => {
        if (isOpen) {
            setTimeout(() => {
                inputRef.current?.focus();
            }, 50);
        } else {
            setQuery('');
            setResults(EMPTY_RESULTS);
            setIsLoading(false);
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }
        }
    }, [isOpen]);

    // 调用 Meilisearch 后端全局检索接口
    const executeSearch = useCallback((searchQuery: string) => {
        const trimmed = searchQuery.trim();
        if (!trimmed) {
            setResults(EMPTY_RESULTS);
            setIsLoading(false);
            return;
        }

        if (abortControllerRef.current) {
            abortControllerRef.current.abort();
        }
        const controller = new AbortController();
        abortControllerRef.current = controller;

        setIsLoading(true);

        fetch(`/search/global?q=${encodeURIComponent(trimmed)}&limit=6`, {
            signal: controller.signal,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(async (res) => {
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                const data: GlobalSearchResponse = await res.json();
                setResults({
                    videos: data.videos || [],
                    actors: data.actors || [],
                    channels: data.channels || [],
                    categories: data.categories || [],
                });
            })
            .catch((err) => {
                if (err.name !== 'AbortError') {
                    console.error('Search failed:', err);
                }
            })
            .finally(() => {
                setIsLoading(false);
            });
    }, []);

    // 防抖触发搜索 (350ms)
    const handleInputChange = (value: string) => {
        setQuery(value);

        if (debounceTimerRef.current) {
            clearTimeout(debounceTimerRef.current);
        }

        if (isComposingRef.current) return;

        if (!value.trim()) {
            setResults(EMPTY_RESULTS);
            setIsLoading(false);
            return;
        }

        debounceTimerRef.current = setTimeout(() => {
            executeSearch(value);
        }, 350);
    };

    // 中文输入法组合事件
    const handleCompositionStart = () => {
        isComposingRef.current = true;
    };

    const handleCompositionEnd = (e: React.CompositionEvent<HTMLInputElement>) => {
        isComposingRef.current = false;
        const value = e.currentTarget.value;
        if (debounceTimerRef.current) {
            clearTimeout(debounceTimerRef.current);
        }
        debounceTimerRef.current = setTimeout(() => {
            executeSearch(value);
        }, 350);
    };

    const handleItemClick = (url: string) => {
        setIsOpen(false);
        router.visit(url);
    };

    const handleViewAllSubmit = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        const trimmed = query.trim();
        if (!trimmed) return;
        setIsOpen(false);
        router.visit(`/search?q=${encodeURIComponent(trimmed)}`);
    };

    const hasResults =
        results.videos.length > 0 ||
        results.actors.length > 0 ||
        results.channels.length > 0 ||
        results.categories.length > 0;

    const getVideoThumbnail = (video: SearchVideoItem): string | null => {
        if (!video.list_img) return null;
        if (typeof video.list_img === 'string') return video.list_img;
        if (Array.isArray(video.list_img) && video.list_img[0]) {
            return video.list_img[0].src || video.list_img[0].src_source || null;
        }
        return video.list_img.src || null;
    };

    return (
        <>
            {/* 1. 侧边栏按钮触发器 */}
            {isCollapsed ? (
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            tooltip="快速搜索 (⌘K)"
                            onClick={() => setIsOpen(true)}
                            className={cn('justify-center rounded-xl hover:bg-accent text-muted-foreground hover:text-foreground', className)}
                        >
                            <Search className="size-4.5" />
                            <span className="sr-only">快速搜索 (⌘K)</span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            ) : (
                <div className="px-1">
                    <button
                        type="button"
                        onClick={() => setIsOpen(true)}
                        className={cn(
                            'group flex w-full items-center gap-2 rounded-xl border border-input/60 bg-muted/40 px-3 py-2 text-xs text-muted-foreground transition-all hover:border-input hover:bg-muted/70 hover:text-foreground focus:outline-none focus:ring-2 focus:ring-primary/20 shadow-xs',
                            className
                        )}
                    >
                        <Search className="size-3.5 text-muted-foreground group-hover:text-foreground transition-colors shrink-0" />
                        <span className="flex-1 text-left truncate">搜索视频、演员、片商...</span>
                        <kbd className="hidden sm:inline-flex items-center gap-0.5 rounded border border-border/80 bg-background px-1.5 py-0.5 text-[10px] font-mono text-muted-foreground shadow-2xs select-none">
                            ⌘K
                        </kbd>
                    </button>
                </div>
            )}

            {/* 2. 全局即时检索弹窗 (Command Palette) */}
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent
                    showCloseButton={false}
                    className="top-[15%] sm:top-[20%] translate-y-0 max-w-2xl w-[94vw] sm:w-full p-0 overflow-hidden border border-border/80 bg-card/95 backdrop-blur-xl shadow-2xl rounded-2xl gap-0"
                >
                    <DialogHeader className="sr-only">
                        <DialogTitle>全站快速检索</DialogTitle>
                        <DialogDescription>实时搜索视频、演员、片商与分类</DialogDescription>
                    </DialogHeader>

                    {/* 搜索输入头部 */}
                    <form onSubmit={handleViewAllSubmit} className="relative flex items-center border-b border-border/60 px-4 py-3.5">
                        <Search className="size-5 text-muted-foreground/80 shrink-0 mr-3" />
                        <input
                            ref={inputRef}
                            type="text"
                            value={query}
                            onChange={(e) => handleInputChange(e.target.value)}
                            onCompositionStart={handleCompositionStart}
                            onCompositionEnd={handleCompositionEnd}
                            placeholder="输入关键词搜索视频、演员、片商、分类..."
                            className="flex-1 bg-transparent text-sm sm:text-base text-foreground placeholder:text-muted-foreground/60 focus:outline-none"
                        />

                        {isLoading && (
                            <Loader2 className="size-4.5 animate-spin text-primary shrink-0 mr-2" />
                        )}

                        {query && !isLoading && (
                            <button
                                type="button"
                                onClick={() => {
                                    setQuery('');
                                    setResults(EMPTY_RESULTS);
                                    inputRef.current?.focus();
                                }}
                                className="p-1 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted mr-1.5 transition-colors"
                            >
                                <X className="size-4" />
                            </button>
                        )}

                        <kbd className="hidden sm:inline-flex items-center px-1.5 py-0.5 text-[11px] font-mono bg-muted text-muted-foreground border border-border/60 rounded select-none">
                            ESC
                        </kbd>
                    </form>

                    {/* 搜索结果区域 */}
                    <div className="max-h-[62vh] overflow-y-auto overscroll-contain p-3 space-y-4 scrollbar-thin scrollbar-thumb-muted-foreground/20">
                        {/* A. 有结果时展示各分组 */}
                        {hasResults && (
                            <div className="space-y-4">
                                {/* 1. 视频 (Videos) */}
                                {results.videos.length > 0 && (
                                    <div>
                                        <div className="flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                            <VideoIcon className="size-3.5 text-blue-500" />
                                            <span>视频 ({results.videos.length})</span>
                                        </div>
                                        <div className="mt-1 space-y-1">
                                            {results.videos.map((item) => {
                                                const thumb = getVideoThumbnail(item);
                                                return (
                                                    <div
                                                        key={`video-${item.id}`}
                                                        onClick={() => handleItemClick(item.url)}
                                                        className="group flex items-center gap-3 px-2.5 py-2 rounded-xl hover:bg-accent/80 cursor-pointer transition-colors"
                                                    >
                                                        <div className="relative size-12 sm:w-16 sm:h-11 rounded-lg overflow-hidden bg-muted shrink-0 border border-border/50">
                                                            {thumb ? (
                                                                <img
                                                                    src={thumb}
                                                                    alt={item.name}
                                                                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                                                                />
                                                            ) : (
                                                                <div className="w-full h-full flex items-center justify-center bg-muted">
                                                                    <VideoIcon className="size-5 text-muted-foreground/60" />
                                                                </div>
                                                            )}
                                                        </div>
                                                        <div className="flex-1 min-w-0">
                                                            <div className="flex items-center gap-2">
                                                                {item.video_code && (
                                                                    <span className="text-[11px] font-mono font-medium text-primary px-1.5 py-0.5 rounded bg-primary/10 shrink-0">
                                                                        {item.video_code}
                                                                    </span>
                                                                )}
                                                                <p className="text-sm font-medium text-foreground truncate group-hover:text-primary transition-colors">
                                                                    {item.name_zh || item.name}
                                                                </p>
                                                            </div>
                                                            {item.name_zh && item.name && item.name !== item.name_zh && (
                                                                <p className="text-xs text-muted-foreground truncate mt-0.5 font-mono">
                                                                    {item.name}
                                                                </p>
                                                            )}
                                                        </div>
                                                        <ArrowRight className="size-4 text-muted-foreground/40 group-hover:text-foreground group-hover:translate-x-0.5 transition-all shrink-0" />
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}

                                {/* 2. 演员 (Actors) */}
                                {results.actors.length > 0 && (
                                    <div>
                                        <div className="flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                            <Users className="size-3.5 text-pink-500" />
                                            <span>演员 ({results.actors.length})</span>
                                        </div>
                                        <div className="mt-1 grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                                            {results.actors.map((item) => (
                                                <div
                                                    key={`actor-${item.id}`}
                                                    onClick={() => handleItemClick(item.url)}
                                                    className="group flex items-center gap-2.5 px-2.5 py-2 rounded-xl hover:bg-accent/80 cursor-pointer transition-colors border border-transparent hover:border-border/40"
                                                >
                                                    <Avatar className="size-9 rounded-full border border-border/50">
                                                        <AvatarImage src={item.avatar || ''} alt={item.name} className="object-cover" />
                                                        <AvatarFallback className="text-xs font-medium bg-pink-500/10 text-pink-600">
                                                            {item.name.slice(0, 2)}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="flex-1 min-w-0">
                                                        <p className="text-sm font-medium text-foreground truncate group-hover:text-primary transition-colors">
                                                            {item.name}
                                                        </p>
                                                    </div>
                                                    <ArrowRight className="size-3.5 text-muted-foreground/40 group-hover:text-foreground transition-colors shrink-0" />
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* 3. 片商 (Channels) */}
                                {results.channels.length > 0 && (
                                    <div>
                                        <div className="flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                            <Clapperboard className="size-3.5 text-amber-500" />
                                            <span>片商 ({results.channels.length})</span>
                                        </div>
                                        <div className="mt-1 grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                                            {results.channels.map((item) => (
                                                <div
                                                    key={`channel-${item.id}`}
                                                    onClick={() => handleItemClick(item.url)}
                                                    className="group flex items-center gap-2.5 px-2.5 py-2 rounded-xl hover:bg-accent/80 cursor-pointer transition-colors border border-transparent hover:border-border/40"
                                                >
                                                    <Avatar className="size-9 rounded-lg border border-border/50">
                                                        <AvatarImage src={item.logo || item.avatar || ''} alt={item.name} className="object-cover" />
                                                        <AvatarFallback className="text-xs font-medium bg-amber-500/10 text-amber-600">
                                                            {item.name.slice(0, 2)}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="flex-1 min-w-0">
                                                        <p className="text-sm font-medium text-foreground truncate group-hover:text-primary transition-colors">
                                                            {item.name}
                                                        </p>
                                                    </div>
                                                    <ArrowRight className="size-3.5 text-muted-foreground/40 group-hover:text-foreground transition-colors shrink-0" />
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* 4. 分类 (Categories) */}
                                {results.categories.length > 0 && (
                                    <div>
                                        <div className="flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                            <LayoutGrid className="size-3.5 text-emerald-500" />
                                            <span>分类 ({results.categories.length})</span>
                                        </div>
                                        <div className="mt-1 flex flex-wrap gap-1.5 px-1">
                                            {results.categories.map((item) => (
                                                <div
                                                    key={`cat-${item.id}`}
                                                    onClick={() => handleItemClick(item.url)}
                                                    className="group flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-muted/60 hover:bg-primary/10 hover:text-primary cursor-pointer transition-colors text-xs font-medium border border-border/50"
                                                >
                                                    <span>{item.name_zh || item.name}</span>
                                                    {item.name_zh && item.name && item.name !== item.name_zh && (
                                                        <span className="text-[11px] text-muted-foreground font-normal">
                                                            ({item.name})
                                                        </span>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* B. 输入了内容但没有任何匹配 */}
                        {query && !isLoading && !hasResults && (
                            <div className="text-center py-10 px-4">
                                <div className="size-12 rounded-full bg-muted/80 flex items-center justify-center mx-auto mb-3 text-muted-foreground">
                                    <Search className="size-6" />
                                </div>
                                <p className="text-sm font-medium text-foreground">未找到与 “{query}” 相关的内容</p>
                                <p className="text-xs text-muted-foreground mt-1">
                                    尝试检查拼写，或输入更简短的番号、演员或片商名称
                                </p>
                            </div>
                        )}

                        {/* C. 初始空输入状态 */}
                        {!query && (
                            <div className="text-center py-8 px-4">
                                <div className="size-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center mx-auto mb-2.5">
                                    <Sparkles className="size-5" />
                                </div>
                                <p className="text-sm font-medium text-foreground">输入关键词以开始搜索</p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Meilisearch 毫秒级多维检索视频、演员、片商与分类
                                </p>
                            </div>
                        )}
                    </div>

                    {/* 底部功能条 */}
                    <div className="flex items-center justify-between border-t border-border/60 bg-muted/30 px-4 py-2.5 text-xs text-muted-foreground">
                        <div className="flex items-center gap-1.5">
                            <span className="inline-flex items-center gap-0.5">
                                <CornerDownLeft className="size-3" />
                                <span>按 Enter</span>
                            </span>
                            <span>查看所有搜索结果</span>
                        </div>

                        {query.trim() && (
                            <button
                                type="button"
                                onClick={handleViewAllSubmit}
                                className="inline-flex items-center gap-1 text-primary hover:underline font-medium"
                            >
                                <span>转到搜索大页</span>
                                <ArrowRight className="size-3" />
                            </button>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

export default SidebarSearch;
