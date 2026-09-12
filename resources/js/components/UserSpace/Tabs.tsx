import React, { useState, useRef, useEffect } from 'react';
import { Link, Deferred } from '@inertiajs/react';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Search,
    X,
    ArrowRight,
    VideoOff,
    ImageOff,
    Sparkles,
    Film,
    Image as ImageIcon,
    UserCircle2
} from 'lucide-react';

// 类型定义
export interface TabItem {
    value: string;
    label: string;
    count?: number;
    icon?: React.ComponentType<{ className?: string }>;
}

interface ChannelTabsProps {
    currentTab: string;
    onTabChange: (tab: string) => void;
    searchKeyword: string;
    onSearchKeywordChange: (val: string) => void;
    onSearchSubmit: (e: React.FormEvent) => void;
    // 数据字段
    latestVideos?: any[];
    latestPhotos?: any[];
    paginatedVideos?: { data: any[]; links?: any[] };
    paginatedPhotos?: { data: any[]; links?: any[] };
    moduleType?: 'actor' | 'user' | 'creator';
    entity?: any;
    customAboutComponent?: React.ReactNode;
    // 自定义子组件槽位
    VideoCardComponent: React.ComponentType<{ video: any }>;
    PhotoCardComponent: React.ComponentType<{ photo: any }>;
    VideoSkeleton: React.ComponentType<{ count: number }>;
    ImageSkeleton: React.ComponentType<{ count: number }>;
    PaginationComponent: React.ComponentType<{ links: any[] }>;
    EmptyStateComponent: React.ComponentType<{ icon: any; title: string; description: string }>;
    showRouteGenerator: (params: { video: string | number; slug?: string }) => string;
}

export const ChannelTabs: React.FC<ChannelTabsProps> = ({
    currentTab,
    onTabChange,
    searchKeyword,
    onSearchKeywordChange,
    onSearchSubmit,
    latestVideos,
    latestPhotos,
    paginatedVideos,
    paginatedPhotos,
    moduleType = 'user',
    entity,
    customAboutComponent,
    VideoCardComponent,
    PhotoCardComponent,
    VideoSkeleton,
    ImageSkeleton,
    PaginationComponent,
    EmptyStateComponent,
    showRouteGenerator,
}) => {
    const [isSearchOpen, setIsSearchOpen] = useState(false);
    const searchInputRef = useRef<HTMLInputElement>(null);

    // Tab 项配置列表
    const tabList: TabItem[] = [
        { value: 'home', label: '首页', icon: Sparkles },
        { value: 'videos', label: '视频', count: paginatedVideos?.data?.length, icon: Film },
        { value: 'photos', label: '图片', count: paginatedPhotos?.data?.length, icon: ImageIcon },
        ...(moduleType === 'actor' ? [{ value: 'about', label: '简介', icon: UserCircle2 }] : []),
    ];

    // 当搜索框展开时自动聚焦
    useEffect(() => {
        if (isSearchOpen && searchInputRef.current) {
            searchInputRef.current.focus();
        }
    }, [isSearchOpen]);

    const handleClearSearch = () => {
        onSearchKeywordChange('');
        if (searchInputRef.current) {
            searchInputRef.current.focus();
        }
    };

    return (
        <div className="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
            <Tabs value={currentTab} onValueChange={onTabChange} className="w-full">
                {/* 顶部 Tab 控制栏 + 搜索框 */}
                <div className="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-4 pb-4 border-b border-border/50 mb-8">

                    {/* 1. 胶囊风格 TabsList (支持移动端平滑滚动) */}
                    <div className="overflow-x-auto no-scrollbar py-1">
                        <TabsList className="inline-flex h-11 items-center gap-1.5 rounded-full bg-muted/60 p-1.5 text-muted-foreground backdrop-blur-md border border-border/40 shadow-inner">
                            {tabList.map((tab) => {
                                const Icon = tab.icon;
                                return (
                                    <TabsTrigger
                                        key={tab.value}
                                        value={tab.value}
                                        className="relative inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-medium transition-all duration-200 ease-out
                                        data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm data-[state=active]:font-semibold
                                        hover:text-foreground hover:bg-background/50 focus-visible:outline-none shrink-0"
                                    >
                                        {Icon && <Icon className="w-4 h-4 opacity-70" />}
                                        <span>{tab.label}</span>

                                        {/* 数量标记徽章 */}
                                        {typeof tab.count === 'number' && tab.count > 0 && (
                                            <Badge
                                                variant="secondary"
                                                className="px-1.5 py-0 text-[11px] h-4 rounded-full bg-muted/80 text-muted-foreground font-normal group-data-[state=active]:bg-primary/10 group-data-[state=active]:text-primary"
                                            >
                                                {tab.count}
                                            </Badge>
                                        )}
                                    </TabsTrigger>
                                );
                            })}
                        </TabsList>
                    </div>

                    {/* 2. 现代化交互搜索组件 */}
                    <div className="flex items-center justify-end">
                        <form
                            onSubmit={onSearchSubmit}
                            className={`relative flex items-center transition-all duration-300 ease-in-out ${isSearchOpen
                                    ? 'w-full sm:w-72 md:w-80 opacity-100'
                                    : 'w-10 opacity-90 sm:opacity-100'
                                }`}
                        >
                            {/* 输入框 */}
                            <input
                                ref={searchInputRef}
                                type="text"
                                value={searchKeyword}
                                onChange={(e) => onSearchKeywordChange(e.target.value)}
                                placeholder="搜索该频道作品..."
                                className={`w-full h-10 text-sm bg-muted/40 text-foreground border border-border/80 rounded-full transition-all duration-300 ease-in-out
                                placeholder:text-muted-foreground/60 focus:bg-background focus:border-ring focus:ring-2 focus:ring-ring/20 focus:outline-none ${isSearchOpen
                                        ? 'pl-4 pr-16 opacity-100 pointer-events-auto'
                                        : 'p-0 opacity-0 pointer-events-none border-transparent'
                                    }`}
                            />

                            {/* 右侧操作按钮组 */}
                            <div className="absolute right-1 flex items-center gap-0.5">
                                {/* 清空输入内容按钮 */}
                                {isSearchOpen && searchKeyword && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={handleClearSearch}
                                        className="h-7 w-7 rounded-full text-muted-foreground hover:text-foreground hover:bg-muted/80"
                                        title="清空"
                                    >
                                        <X className="h-3.5 w-3.5" />
                                    </Button>
                                )}

                                {/* 搜索打开/折叠切换按钮 */}
                                <Button
                                    type="button"
                                    variant={isSearchOpen ? 'ghost' : 'secondary'}
                                    size="icon"
                                    onClick={() => {
                                        if (isSearchOpen && searchKeyword) {
                                            // 如果已经打开且有词，点击直接提交
                                            onSearchSubmit({ preventDefault: () => { } } as any);
                                        } else {
                                            setIsSearchOpen(!isSearchOpen);
                                        }
                                    }}
                                    className={`h-8 w-8 rounded-full transition-colors shrink-0 ${!isSearchOpen ? 'bg-muted/60 hover:bg-muted border border-border/40' : ''
                                        }`}
                                    title={isSearchOpen ? '确认搜索' : '打开搜索'}
                                >
                                    <Search className="h-4 w-4 text-foreground/80" />
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>

                {/* 3. Tabs 内容展示区 */}
                <div className="min-h-[450px]">
                    {/* 🎯 首页 Tab */}
                    <TabsContent
                        value="home"
                        className="space-y-12 animate-in fade-in-50 slide-in-from-bottom-2 duration-300 focus-visible:outline-none"
                    >
                        {/* 最新视频区块 */}
                        <section className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div className="space-y-1">
                                    <h3 className="text-xl font-bold tracking-tight flex items-center gap-2">
                                        <Film className="w-5 h-5 text-primary" />
                                        最新视频
                                    </h3>
                                </div>
                                {latestVideos && latestVideos.length > 0 && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => onTabChange('videos')}
                                        className="text-muted-foreground hover:text-foreground rounded-full hover:bg-muted/60"
                                    >
                                        查看全部 <ArrowRight className="w-4 h-4 ml-1" />
                                    </Button>
                                )}
                            </div>

                            <Deferred data="latestVideos" fallback={<VideoSkeleton count={3} />}>
                                {latestVideos && latestVideos.length > 0 ? (
                                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
                                        {latestVideos.map((v) => (
                                            <Link
                                                href={showRouteGenerator({ video: v.id, slug: v.slug })}
                                                key={v.id}
                                                className="group block transition-transform duration-200 hover:-translate-y-0.5"
                                            >
                                                <VideoCardComponent video={v} />
                                            </Link>
                                        ))}
                                    </div>
                                ) : (
                                    <EmptyStateComponent
                                        icon={VideoOff}
                                        title="暂无最新视频"
                                        description="该频道下暂未发布任何视频作品"
                                    />
                                )}
                            </Deferred>
                        </section>

                        {/* 最新图片区块 */}
                        <section className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div className="space-y-1">
                                    <h3 className="text-xl font-bold tracking-tight flex items-center gap-2">
                                        <ImageIcon className="w-5 h-5 text-primary" />
                                        最新图片
                                    </h3>
                                </div>
                                {latestPhotos && latestPhotos.length > 0 && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => onTabChange('photos')}
                                        className="text-muted-foreground hover:text-foreground rounded-full hover:bg-muted/60"
                                    >
                                        查看全部 <ArrowRight className="w-4 h-4 ml-1" />
                                    </Button>
                                )}
                            </div>

                            <Deferred data="latestPhotos" fallback={<ImageSkeleton count={5} />}>
                                {latestPhotos && latestPhotos.length > 0 ? (
                                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                                        {latestPhotos.map((img, i) => (
                                            <PhotoCardComponent key={img.id || i} photo={img} />
                                        ))}
                                    </div>
                                ) : (
                                    <EmptyStateComponent
                                        icon={ImageOff}
                                        title="暂无最新图片"
                                        description="该频道下暂未发布任何图片动态"
                                    />
                                )}
                            </Deferred>
                        </section>
                    </TabsContent>

                    {/* 🎯 视频全量列表 Tab */}
                    <TabsContent
                        value="videos"
                        className="animate-in fade-in-50 slide-in-from-bottom-2 duration-300 focus-visible:outline-none"
                    >
                        <Deferred data="paginatedVideos" fallback={<VideoSkeleton count={6} />}>
                            {paginatedVideos?.data && paginatedVideos.data.length > 0 ? (
                                <div className="space-y-8">
                                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
                                        {paginatedVideos.data.map((video: any) => (
                                            <Link
                                                href={showRouteGenerator({ video: video.id, slug: video.slug })}
                                                key={video.id}
                                                className="group block transition-transform duration-200 hover:-translate-y-0.5"
                                            >
                                                <VideoCardComponent video={video} />
                                            </Link>
                                        ))}
                                    </div>

                                    {paginatedVideos?.links && (
                                        <div className="flex justify-center pt-6 pb-12">
                                            <PaginationComponent links={paginatedVideos.links} />
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <EmptyStateComponent
                                    icon={VideoOff}
                                    title="未找到相关视频"
                                    description="该列表中暂无符合条件的视频资源"
                                />
                            )}
                        </Deferred>
                    </TabsContent>

                    {/* 🎯 图片全量列表 Tab */}
                    <TabsContent
                        value="photos"
                        className="animate-in fade-in-50 slide-in-from-bottom-2 duration-300 focus-visible:outline-none"
                    >
                        <Deferred data="paginatedPhotos" fallback={<ImageSkeleton count={10} />}>
                            {paginatedPhotos?.data && paginatedPhotos.data.length > 0 ? (
                                <div className="space-y-8">
                                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                                        {paginatedPhotos.data.map((img: any, idx: number) => (
                                            <PhotoCardComponent key={img.id || idx} photo={img} />
                                        ))}
                                    </div>

                                    {paginatedPhotos?.links && (
                                        <div className="flex justify-center pt-6 pb-12">
                                            <PaginationComponent links={paginatedPhotos.links} />
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <EmptyStateComponent
                                    icon={ImageOff}
                                    title="未找到相关图片"
                                    description="该列表中暂无符合条件的图片资源"
                                />
                            )}
                        </Deferred>
                    </TabsContent>

                    {/* 🎯 简介 Tab */}
                    {moduleType === 'actor' && (
                        <TabsContent
                            value="about"
                            className="animate-in fade-in-50 slide-in-from-bottom-2 duration-300 focus-visible:outline-none"
                        >
                            <div className="max-w-3xl rounded-2xl border border-border/60 bg-card p-6 shadow-sm">
                                {customAboutComponent}
                            </div>
                        </TabsContent>
                    )}
                </div>
            </Tabs>
        </div>
    );
};
