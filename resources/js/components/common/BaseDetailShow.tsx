// src/components/common/BaseDetailShow.tsx
import React, { useState, useEffect } from 'react';
import { Head, router, Deferred, Link } from '@inertiajs/react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Search, X, ArrowRight, VideoOff, ImageOff } from 'lucide-react';

// 组件引入
import { FollowBtn, ModuleType } from '@/components/actor/FollowBtn';
import { ActorAbout } from '@/components/actor/ActorAbout';
import { EditActorDialog } from '@/components/actor/EditActorDialog';
import { XIcon, InstagramIcon, YoutubeIcon, FacebookIcon, WebsiteIcon } from '@/components/BrandIcons';
import { formatChineseUnit } from '@/lib/utils';
import { VideoCard } from "@/components/video/Card";
import { show } from '@/routes/videos';
import { SubscribeButton, NotificationType } from '@/components/subscribe-button';

// 独立视频分页组件
import { VideoPagination } from '@/components/VideoPagination';

export interface EntityData {
    id: string | number;
    slug?: string;
    name: string;
    avatar?: string;
    banner?: string;
    follow_num?: number;
    bio?: string;
    nicknames?: string[];
    socials?: {
        x?: string;
        instagram?: string;
        youtube?: string;
        facebook?: string;
        website?: string;
    };
    [key: string]: any;
}

export interface BaseDetailShowProps {
    moduleType: ModuleType;
    entity: EntityData;
    currentTab?: string;
    initisFollowed?: boolean;
    latestVideos?: any[];
    latestPhotos?: any[];
    paginatedVideos?: any;
    paginatedPhotos?: any;
    customAboutComponent?: React.ReactNode;
    filters?: {
        search?: string;
        [key: string]: any;
    };
    [key: string]: any;
}

// 🎯 1. 统一的空状态占位组件 (EmptyState)
function EmptyState({
    icon: Icon,
    title = "暂无内容",
    description = "这里还没有发布任何相关内容哦",
    action
}: {
    icon: React.ElementType;
    title?: string;
    description?: string;
    action?: React.ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center py-12 px-4 text-center border border-dashed border-border/70 rounded-2xl bg-muted/20 my-2 transition-all">
            <div className="w-12 h-12 rounded-full bg-muted/80 flex items-center justify-center mb-3 text-muted-foreground/70">
                <Icon className="w-6 h-6" />
            </div>
            <h4 className="text-sm font-semibold text-foreground mb-1">{title}</h4>
            <p className="text-xs text-muted-foreground max-w-xs leading-relaxed">{description}</p>
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}

// 🎯 2. 精致的图片卡片组件 (PhotoCard)
function PhotoCard({ photo }: { photo: any }) {
    const imageUrl = photo?.url || photo?.cover_image || photo?.src;
    const title = photo?.title || photo?.name || "精选图片";

    return (
        <div className="group relative aspect-square bg-muted/40 rounded-xl border border-border/60 overflow-hidden shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer">
            {imageUrl ? (
                <img
                    src={imageUrl}
                    alt={title}
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 ease-out"
                />
            ) : (
                <div className="w-full h-full flex flex-col items-center justify-center text-xs text-muted-foreground/60 p-2 text-center bg-muted/30">
                    <ImageOff className="w-6 h-6 mb-1 opacity-40" />
                    <span>暂无缩略图</span>
                </div>
            )}

            {/* 悬浮黑色渐变与标题 */}
            <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end p-3">
                <span className="text-xs text-white font-medium line-clamp-1 drop-shadow-sm">
                    {title}
                </span>
            </div>
        </div>
    );
}

// 视频列表加载骨架屏（Skeleton）
const VideoSkeletonGrid = ({ count = 4 }: { count?: number }) => (
    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
        {Array.from({ length: count }).map((_, i) => (
            <div
                key={i}
                className="aspect-video bg-muted/50 animate-pulse rounded-xl border border-border/50 flex items-center justify-center"
            >
                <div className="w-10 h-10 rounded-full bg-muted-foreground/10" />
            </div>
        ))}
    </div>
);

// 图片列表加载骨架屏（Skeleton）
const ImageSkeletonGrid = ({ count = 5 }: { count?: number }) => (
    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
        {Array.from({ length: count }).map((_, i) => (
            <div
                key={i}
                className="aspect-square bg-muted/50 animate-pulse rounded-xl border border-border/50"
            />
        ))}
    </div>
);

export default function BaseDetailShow({
    moduleType,
    entity,
    currentTab = 'home',
    initisFollowed = false,
    latestVideos,
    latestPhotos,
    paginatedVideos,
    paginatedPhotos,
    customAboutComponent,
    filters
}: BaseDetailShowProps) {
    const activeSearch = (filters?.search || '').trim();
    const [searchKeyword, setSearchKeyword] = useState(activeSearch);
    const [isSearchOpen, setIsSearchOpen] = useState(Boolean(activeSearch));
    const searchInputRef = React.useRef<HTMLInputElement>(null);
    const [subscribersCount, setSubscribersCount] = useState(entity.subscribersCount ?? entity.follow_num ?? 0);

    // 只有在视频和图片 Tab 下才支持搜索功能
    const isSearchableTab = currentTab === 'videos' || currentTab === 'photos';

    // 路由前缀
    const routePrefix = moduleType === 'category' ? '/categories' : `/${moduleType}s`;

    React.useEffect(() => {
        if (typeof entity.subscribersCount === 'number') {
            setSubscribersCount(entity.subscribersCount);
        } else if (typeof entity.follow_num === 'number') {
            setSubscribersCount(entity.follow_num);
        }
    }, [entity.subscribersCount, entity.follow_num]);

    // 当 filters.search 发生变化时同步输入框状态
    useEffect(() => {
        setSearchKeyword(activeSearch);
        if (activeSearch) {
            setIsSearchOpen(true);
        }
    }, [activeSearch]);

    // 动态根据 moduleType 构建 Tab 菜单项
    const tabs = [
        { value: 'home', label: '首页' },
        { value: 'videos', label: '视频' },
        { value: 'photos', label: '图片' },
        ...(moduleType === 'actor' ? [{ value: 'about', label: '简介' }] : []),
    ];

    // 切换 Tab：由受控的 currentTab 和 router.visit 驱动
    const handleTabChange = (value: string) => {
        // 1. 如果点击的是当前已激活的 Tab，拦截无需重复请求
        if (value === currentTab) {
            return;
        }

        const baseUrl = `${routePrefix}/${entity.id}/${entity.slug || ''}`;
        const newUrl = value === 'home' ? baseUrl : `${baseUrl}/${value}`;

        // 2. 根据不同的 Tab 目标，指定只请求对应的增量数据键名
        const onlyFields = ['currentTab', 'filters'];
        if (value === 'videos') {
            onlyFields.push('paginatedVideos');
        } else if (value === 'photos') {
            onlyFields.push('paginatedPhotos');
        } else if (value === 'home') {
            onlyFields.push('latestVideos', 'latestPhotos');
        }

        // 切换 Tab 时重置搜索关键词输入状态
        setSearchKeyword('');
        setIsSearchOpen(false);

        // 3. 执行无缝增量刷新（不保留搜索等状态，展示目标 Tab 的完整全量列表）
        router.visit(newUrl, {
            only: onlyFields,
            preserveState: false,
            preserveScroll: true,
            replace: true,
        });
    };

    // 提交搜索：仅在当前激活的视频或图片 Tab 中根据标题过滤
    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!isSearchableTab) return;

        const keyword = searchKeyword.trim();
        if (!keyword) {
            handleResetSearch();
            return;
        }

        const baseUrl = `${routePrefix}/${entity.id}/${entity.slug || ''}`;
        const targetUrl = `${baseUrl}/${currentTab}`;

        router.visit(targetUrl, {
            data: { search: keyword },
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    // 重置搜索：清空搜索词并恢复列表
    const handleResetSearch = () => {
        setSearchKeyword('');
        const baseUrl = `${routePrefix}/${entity.id}/${entity.slug || ''}`;
        const targetUrl = `${baseUrl}/${currentTab}`;

        router.visit(targetUrl, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    // 渲染当前激活搜索词提示栏组件
    const renderActiveSearchBanner = () => {
        if (!activeSearch) return null;

        return (
            <div className="flex flex-wrap items-center justify-between gap-3 mb-6 px-4 py-2.5 rounded-xl bg-muted/40 border border-border/70 text-sm animate-in fade-in duration-300">
                <div className="flex items-center gap-2">
                    <Search className="w-4 h-4 text-primary shrink-0" />
                    <span className="text-muted-foreground text-xs sm:text-sm">当前搜索标题：</span>
                    <Badge variant="secondary" className="px-2.5 py-0.5 font-semibold text-xs sm:text-sm bg-primary/10 text-primary border-primary/20">
                        "{activeSearch}"
                    </Badge>
                </div>
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={handleResetSearch}
                    className="h-7 px-2.5 text-xs text-muted-foreground hover:text-foreground hover:bg-muted"
                >
                    <X className="w-3.5 h-3.5 mr-1" />
                    重置搜索
                </Button>
            </div>
        );
    };

    const nicknamesList = entity.nicknames || ["热门", "推荐"];

    return (
        <div className="min-h-screen bg-background">
            <Head title={`${entity?.name || '详情'} - 详情`} />

            <main className="container mx-auto pb-12">
                {/* 顶部 Banner */}
                {entity?.banner && (
                    <div className="w-full px-4 pt-4 sm:px-6 lg:px-8">
                        <div
                            className="w-full aspect-video md:aspect-[21/9] lg:aspect-[3/1] rounded-2xl bg-muted bg-cover bg-center shadow-inner"
                            style={{ backgroundImage: `url(${entity?.banner})` }}
                        />
                    </div>
                )}

                {/* 个人/频道/分类/标签 信息头部 */}
                <div className="px-4 sm:px-6 lg:px-8 mt-4 md:mt-6 flex flex-col md:flex-row items-start md:items-center gap-6">
                    <Avatar className="w-24 h-24 md:w-40 md:h-40 border-4 border-background shadow-md shrink-0">
                        <AvatarImage src={entity?.avatar} alt={entity?.name} />
                        <AvatarFallback className="text-3xl font-bold">
                            {entity?.name?.charAt(0)}
                        </AvatarFallback>
                    </Avatar>

                    <div className="flex-1 w-full">
                        <h1 className="text-2xl md:text-4xl font-extrabold text-foreground tracking-tight">
                            {entity?.name}
                        </h1>

                        <div className="flex flex-wrap items-center gap-2 mt-3 text-sm text-muted-foreground">
                            <span className="font-semibold text-foreground bg-muted px-2 py-0.5 rounded-md">
                                {formatChineseUnit(subscribersCount.toLocaleString() || 0)}位订阅者
                            </span>
                            <div className="flex flex-wrap gap-1.5 ml-2">
                                {nicknamesList.map((nickname, index) => (
                                    <Badge key={index} variant="outline" className="font-normal text-muted-foreground bg-background hover:bg-muted transition-colors">
                                        {nickname}
                                    </Badge>
                                ))}
                            </div>
                        </div>

                        <p className="mt-3 text-sm text-muted-foreground/90 max-w-2xl leading-relaxed">
                            {entity?.bio || '暂无简介描述信息...'}
                        </p>

                        <div className="mt-5 flex flex-wrap items-center gap-3">
                            {entity?.id && (
                                <>
                                    <SubscribeButton
                                        type={moduleType}
                                        id={entity.id}
                                        name={entity.name_zh || entity.name}
                                        initialIsSubscribed={initisFollowed ?? false}
                                        initialNotificationType={entity.notificationType || 'personalized'}
                                        onSubscriptionChange={(_, delta) => setSubscribersCount((prev: number) => Math.max(0, prev + delta))}
                                    />
                                </>
                            )}

                            {moduleType === 'actor' && entity && (
                                <EditActorDialog actor={entity} />
                            )}

                            {entity?.socials && Object.keys(entity.socials).length > 0 && (
                                <div className="flex items-center gap-3 border-l border-border pl-3 ml-1">
                                    {entity.socials.x && (
                                        <a href={entity.socials.x} target="_blank" rel="noreferrer" className="text-muted-foreground hover:text-foreground transition-all duration-300 hover:scale-110">
                                            <XIcon className="w-5 h-5" />
                                        </a>
                                    )}
                                    {entity.socials.instagram && (
                                        <a href={entity.socials.instagram} target="_blank" rel="noreferrer" className="transition-all duration-300 hover:scale-110 hover:opacity-80">
                                            <InstagramIcon className="w-5 h-5" />
                                        </a>
                                    )}
                                    {entity.socials.youtube && (
                                        <a href={entity.socials.youtube} target="_blank" rel="noreferrer" className="transition-all duration-300 hover:scale-110 hover:opacity-80">
                                            <YoutubeIcon className="w-5 h-5" />
                                        </a>
                                    )}
                                    {entity.socials.facebook && (
                                        <a href={entity.socials.facebook} target="_blank" rel="noreferrer" className="transition-all duration-300 hover:scale-110 hover:opacity-80">
                                            <FacebookIcon className="w-5 h-5" />
                                        </a>
                                    )}
                                    {entity.socials.website && (
                                        <a href={entity.socials.website} target="_blank" rel="noreferrer" className="text-muted-foreground hover:text-foreground transition-all duration-300 hover:scale-110">
                                            <WebsiteIcon className="w-5 h-5" />
                                        </a>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* 底部 Tab 切换区 */}
                <div className="px-4 sm:px-6 lg:px-8 mt-6">
                    <Tabs value={currentTab} onValueChange={handleTabChange} className="w-full">
                        <div className="flex flex-wrap sm:flex-nowrap items-center justify-between gap-4 mb-8">
                            <TabsList className="inline-flex h-11 items-center justify-center rounded-xl bg-muted/80 p-1 text-muted-foreground w-full sm:w-[500px]">
                                {tabs.map((tab) => (
                                    <TabsTrigger
                                        key={tab.value}
                                        value={tab.value}
                                        className="inline-flex flex-1 items-center justify-center whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition-all data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm hover:text-foreground"
                                    >
                                        {tab.label}
                                    </TabsTrigger>
                                ))}
                            </TabsList>

                            {/* 搜索框：仅在 tab 为视频或图片时才可以使用搜索功能 */}
                            {isSearchableTab && (
                                <form onSubmit={handleSearchSubmit} className="flex items-center justify-end w-full sm:w-auto ml-auto">
                                    <div className={`flex items-center transition-all duration-300 ease-in-out overflow-hidden ${isSearchOpen ? 'w-full sm:w-72 opacity-100 mr-2' : 'w-0 opacity-0 mr-0'}`}>
                                        <div className="relative w-full flex items-center">
                                            <input
                                                ref={searchInputRef}
                                                type="text"
                                                value={searchKeyword}
                                                onChange={(e) => setSearchKeyword(e.target.value)}
                                                placeholder={currentTab === 'videos' ? '搜索视频标题...' : '搜索图片标题...'}
                                                className="w-full h-10 pl-4 pr-8 rounded-full border border-border bg-muted/30 text-sm text-foreground transition-shadow focus:border-foreground focus:outline-none"
                                            />
                                            {searchKeyword && (
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setSearchKeyword('');
                                                        if (activeSearch) {
                                                            handleResetSearch();
                                                        }
                                                    }}
                                                    className="absolute right-2.5 text-muted-foreground hover:text-foreground p-0.5 rounded-full"
                                                    title="清空并重置"
                                                >
                                                    <X className="h-3.5 w-3.5" />
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                    <Button
                                        type={isSearchOpen ? 'submit' : 'button'}
                                        variant={isSearchOpen ? 'secondary' : 'ghost'}
                                        size="icon"
                                        onClick={() => {
                                            if (!isSearchOpen) {
                                                setIsSearchOpen(true);
                                                setTimeout(() => searchInputRef.current?.focus(), 50);
                                            }
                                        }}
                                        className="rounded-full text-muted-foreground hover:text-foreground hover:bg-muted shrink-0"
                                        title={isSearchOpen ? '执行搜索' : '展开搜索'}
                                    >
                                        <Search className="h-5 w-5" />
                                    </Button>
                                    {isSearchOpen && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => {
                                                setIsSearchOpen(false);
                                                if (activeSearch) {
                                                    handleResetSearch();
                                                } else {
                                                    setSearchKeyword('');
                                                }
                                            }}
                                            className="rounded-full text-muted-foreground hover:text-foreground hover:bg-muted shrink-0 ml-1"
                                            title="关闭搜索"
                                        >
                                            <X className="h-4 w-4" />
                                        </Button>
                                    )}
                                </form>
                            )}
                        </div>

                        {/* Tabs 内容区 */}
                        <div className="mt-2 min-h-[400px]">
                            {/* 🎯 首页 Tab */}
                            <TabsContent value="home" className="space-y-12 animate-in fade-in slide-in-from-bottom-4 duration-500">
                                {/* 最新视频区块 */}
                                <section>
                                    <div className="flex items-center justify-between mb-6">
                                        <h3 className="text-xl font-bold tracking-tight">最新视频</h3>
                                        {latestVideos && latestVideos.length > 0 && (
                                            <Button variant="ghost" size="sm" onClick={() => handleTabChange('videos')} className="text-muted-foreground hover:text-foreground">
                                                查看全部 <ArrowRight className="w-4 h-4 ml-1" />
                                            </Button>
                                        )}
                                    </div>
                                    <Deferred data="latestVideos" fallback={<VideoSkeletonGrid count={4} />}>
                                        {latestVideos && latestVideos.length > 0 ? (
                                            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-x-3 gap-y-5">
                                                {latestVideos.map((v) => (
                                                    <Link href={show({ video: v.id, slug: v.slug })} key={v.id}>
                                                        <VideoCard video={v} />
                                                    </Link>
                                                ))}
                                            </div>
                                        ) : (
                                            <EmptyState
                                                icon={VideoOff}
                                                title="暂无最新视频"
                                                description="该频道下暂未发布任何视频作品"
                                            />
                                        )}
                                    </Deferred>
                                </section>

                                {/* 最新图片区块 */}
                                <section>
                                    <div className="flex items-center justify-between mb-6">
                                        <h3 className="text-xl font-bold tracking-tight">最新图片</h3>
                                        {latestPhotos && latestPhotos.length > 0 && (
                                            <Button variant="ghost" size="sm" onClick={() => handleTabChange('photos')} className="text-muted-foreground hover:text-foreground">
                                                查看全部 <ArrowRight className="w-4 h-4 ml-1" />
                                            </Button>
                                        )}
                                    </div>
                                    <Deferred data="latestPhotos" fallback={<ImageSkeletonGrid count={5} />}>
                                        {latestPhotos && latestPhotos.length > 0 ? (
                                            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                                                {latestPhotos.map((img, i) => (
                                                    <PhotoCard key={img.id || i} photo={img} />
                                                ))}
                                            </div>
                                        ) : (
                                            <EmptyState
                                                icon={ImageOff}
                                                title="暂无最新图片"
                                                description="该频道下暂未发布任何图片动态"
                                            />
                                        )}
                                    </Deferred>
                                </section>
                            </TabsContent>

                            {/* 🎯 视频 Tab */}
                            <TabsContent value="videos" className="animate-in fade-in slide-in-from-bottom-4 duration-500">
                                {renderActiveSearchBanner()}
                                <Deferred data="paginatedVideos" fallback={<VideoSkeletonGrid count={12} />}>
                                    {paginatedVideos?.data && paginatedVideos.data.length > 0 ? (
                                        <>
                                            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-x-3 gap-y-5">
                                                {paginatedVideos.data.map((video: any) => (
                                                    <Link href={show({ video: video.id, slug: video.slug })} key={video.id}>
                                                        <VideoCard video={video} />
                                                    </Link>
                                                ))}
                                            </div>

                                            {paginatedVideos?.links && (
                                                <div className="flex flex-col items-center justify-center mt-10 mb-12 gap-2">
                                                    <VideoPagination links={paginatedVideos.links} only={['paginatedVideos']} />
                                                </div>
                                            )}
                                        </>
                                    ) : (
                                        <EmptyState
                                            icon={VideoOff}
                                            title={activeSearch ? "未找到匹配视频" : "未找到相关视频"}
                                            description={
                                                activeSearch
                                                    ? `未找到标题包含 "${activeSearch}" 的视频作品，请尝试更换关键词或重置搜索`
                                                    : "该列表中暂无符合条件的视频资源"
                                            }
                                            action={
                                                activeSearch ? (
                                                    <Button variant="outline" size="sm" onClick={handleResetSearch} className="text-xs">
                                                        <X className="w-3.5 h-3.5 mr-1" /> 重置搜索
                                                    </Button>
                                                ) : undefined
                                            }
                                        />
                                    )}
                                </Deferred>
                            </TabsContent>

                            {/* 🎯 图片 Tab */}
                            <TabsContent value="photos" className="animate-in fade-in slide-in-from-bottom-4 duration-500">
                                {renderActiveSearchBanner()}
                                <Deferred data="paginatedPhotos" fallback={<ImageSkeletonGrid count={10} />}>
                                    {paginatedPhotos?.data && paginatedPhotos.data.length > 0 ? (
                                        <>
                                            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                                                {paginatedPhotos.data.map((img: any, idx: number) => (
                                                    <PhotoCard key={img.id || idx} photo={img} />
                                                ))}
                                            </div>

                                            {paginatedPhotos?.links && (
                                                <div className="flex flex-col items-center justify-center mt-10 mb-12 gap-2">
                                                    <VideoPagination links={paginatedPhotos.links} only={['paginatedPhotos']} />
                                                </div>
                                            )}
                                        </>
                                    ) : (
                                        <EmptyState
                                            icon={ImageOff}
                                            title={activeSearch ? "未找到匹配图片" : "未找到相关图片"}
                                            description={
                                                activeSearch
                                                    ? `未找到标题包含 "${activeSearch}" 的图片资源，请尝试更换关键词或重置搜索`
                                                    : "该列表中暂无符合条件的图片资源"
                                            }
                                            action={
                                                activeSearch ? (
                                                    <Button variant="outline" size="sm" onClick={handleResetSearch} className="text-xs">
                                                        <X className="w-3.5 h-3.5 mr-1" /> 重置搜索
                                                    </Button>
                                                ) : undefined
                                            }
                                        />
                                    )}
                                </Deferred>
                            </TabsContent>

                            {/* 简介 Tab */}
                            {moduleType === 'actor' && (
                                <TabsContent value="about">
                                    {customAboutComponent || <ActorAbout actor={entity} />}
                                </TabsContent>
                            )}
                        </div>
                    </Tabs>
                </div>
            </main>
        </div>
    );
}
