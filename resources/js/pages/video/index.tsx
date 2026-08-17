import React, { useState, useRef, useMemo } from 'react';
import { VideoSkeleton } from "@/components/video/VideoSkeleton";
import { VideoCard } from "@/components/video/Card";
import { VideoPagination } from '@/components/VideoPagination';
import { Button } from "@/components/ui/button";
import { SlidersHorizontal, ChevronUp } from "lucide-react";
import { Link, Deferred, router, useHttp } from '@inertiajs/react';
import { show } from '@/routes/videos';
import { Video, PaginatedResponse } from "@/types/video";
import { toast } from 'sonner';

import {
    AdvancedFilterPanel,
    FilterGroupData,
    SelectedFilterState
} from "@/components/video/AdvancedFilterPanel";
import VideoController from '@/actions/App/Http/Controllers/VideoController';

export interface QuickFilterItem {
    id: string;
    name: string;
    type: 'all' | 'category' | 'tag' | 'actor';
    value: string;
}

interface CascadeFilterResponse {
    actors: FilterGroupData['actors'];
    tags: FilterGroupData['tags'];
    channels: FilterGroupData['channels'];
}

const EMPTY_FILTER_DATA: FilterGroupData = {
    actors: [],
    tags: [],
    channels: [],
};

// 缓存策略配置
const CACHE_TTL_MS = 5 * 60 * 1000;
const CACHE_PREFIX = 'cascade_filter_v1_';

const generateCacheKey = (selectedState?: SelectedFilterState): string => {
    const sortedActors = [...(selectedState?.actors || [])].sort().join(',');
    const sortedTags = [...(selectedState?.tags || [])].sort().join(',');
    const sortedChannels = [...(selectedState?.channels || [])].sort().join(',');

    return `${CACHE_PREFIX}actors:[${sortedActors}]_tags:[${sortedTags}]_channels:[${sortedChannels}]`;
};

const getFilterFromCache = (key: string): FilterGroupData | null => {
    try {
        const cachedStr = sessionStorage.getItem(key);
        if (!cachedStr) return null;

        const { data, timestamp } = JSON.parse(cachedStr);
        if (Date.now() - timestamp > CACHE_TTL_MS) {
            sessionStorage.removeItem(key);
            return null;
        }

        return data as FilterGroupData;
    } catch (e) {
        return null;
    }
};

const setFilterToCache = (key: string, data: FilterGroupData) => {
    try {
        sessionStorage.setItem(key, JSON.stringify({ data, timestamp: Date.now() }));
    } catch (e) {
        console.warn('SessionStorage 写入失败:', e);
    }
};

const parseUrlIds = (raw?: string): (string | number)[] => {
    if (!raw) return [];
    return raw.split(',').filter(Boolean).map(id => {
        return !isNaN(Number(id)) ? Number(id) : id;
    });
};

// 🎯 1. 新增 URL 参数清洗工具：自动过滤空值，保持 URL 清爽干净
const cleanParams = (params: Record<string, string | number | undefined | null>) => {
    const clean: Record<string, string> = {};
    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '' && value !== '全部') {
            clean[key] = String(value);
        }
    });
    return clean;
};

function QuickFilterSkeleton() {
    return (
        <div className="flex items-center gap-2 overflow-x-auto pb-4 mb-6 scrollbar-none animate-pulse">
            {[64, 80, 96, 72, 88, 76, 84].map((width, index) => (
                <div
                    key={index}
                    style={{ width: `${width}px` }}
                    className="h-8 bg-muted/80 rounded-xl shrink-0"
                />
            ))}
        </div>
    );
}

interface YoutubeVideoGridProps {
    videos: PaginatedResponse<Video>;
    initialFilterOptions?: FilterGroupData;
    quickFilters?: QuickFilterItem[];
    filters?: {
        category?: string;
        actors?: string;
        tags?: string;
        channels?: string;
    };
}

export default function YoutubeVideoGrid({
    videos,
    initialFilterOptions = EMPTY_FILTER_DATA,
    quickFilters = [],
    filters = {}
}: YoutubeVideoGridProps) {
    const [isFilterOpen, setIsFilterOpen] = useState(false);
    const [hasFetchedFilters, setHasFetchedFilters] = useState(false);
    const [filterData, setFilterData] = useState<FilterGroupData>(initialFilterOptions);

    // 解析当前 URL 参数作为高级筛选面板的默认状态
    const initialSelectedState: SelectedFilterState = useMemo(() => ({
        actors: parseUrlIds(filters.actors),
        tags: parseUrlIds(filters.tags),
        channels: parseUrlIds(filters.channels),
    }), [filters.actors, filters.tags, filters.channels]);

    // 高级筛选面板内部维护的选择状态（不使用 useEffect 进行强行联动）
    const [currentSelected, setCurrentSelected] = useState<SelectedFilterState>(initialSelectedState);

    // 计算高级筛选选中的数量
    const selectedCount = useMemo(() => {
        const actorsCount = currentSelected.actors?.length || 0;
        const tagsCount = currentSelected.tags?.length || 0;
        const channelsCount = currentSelected.channels?.length || 0;
        return actorsCount + tagsCount + channelsCount;
    }, [currentSelected]);

    const cachedDefaultData = useRef<FilterGroupData | null>(null);

    const { post, cancel, processing, transform } = useHttp({
        actors: [] as (string | number)[],
        tags: [] as (string | number)[],
        channels: [] as (string | number)[],
    });

    // 判断横向快捷分类高亮状态（纯根据 URL 参数判断）
    const isQuickFilterActive = (item: QuickFilterItem): boolean => {
        if (item.type === 'all') {
            return !filters.category && !filters.actors && !filters.tags && !filters.channels;
        }
        if (item.type === 'category') {
            return filters.category === item.value;
        }
        if (item.type === 'actor') {
            const currentActors = parseUrlIds(filters.actors);
            return currentActors.includes(item.value) || currentActors.includes(Number(item.value));
        }
        if (item.type === 'tag') {
            const currentTags = parseUrlIds(filters.tags);
            return currentTags.includes(item.value) || currentTags.includes(Number(item.value)) || currentTags.includes(`tag_${item.value}`);
        }
        return false;
    };

    // 🎯 2. 点击横向分类栏：只发起单次精简的 GET 请求
    const handleQuickFilterClick = (item: QuickFilterItem) => {
        if (item.type === 'all') {
            router.get('/videos', {}, { preserveState: true, preserveScroll: true });
            return;
        }

        const rawParams = {
            category: item.type === 'category' ? item.value : '',
            actors: item.type === 'actor' ? item.value : '',
            tags: item.type === 'tag' ? item.value : '',
        };

        // 使用 cleanParams 清洗后，只保留有值的字段，杜绝参数污染
        router.get('/videos', cleanParams(rawParams), { preserveState: true, preserveScroll: true });
    };

    const fetchCascadeFilters = (selectedState?: SelectedFilterState) => {
        const stateToUse = selectedState || currentSelected;

        const isInitialFetch = (
            (!stateToUse.actors || stateToUse.actors.length === 0) &&
            (!stateToUse.tags || stateToUse.tags.length === 0) &&
            (!stateToUse.channels || stateToUse.channels.length === 0)
        );

        const cacheKey = generateCacheKey(stateToUse);

        const cachedData = getFilterFromCache(cacheKey);
        if (cachedData) {
            setFilterData(cachedData);
            setHasFetchedFilters(true);
            if (isInitialFetch) {
                cachedDefaultData.current = cachedData;
            }
            return;
        }

        if (processing) cancel();

        transform(() => ({
            actors: stateToUse.actors || [],
            tags: stateToUse.tags || [],
            channels: stateToUse.channels || [],
        }));

        post(VideoController.getCascadeFilters.url(), {
            onSuccess: (response: unknown) => {
                const filterResponse = response as CascadeFilterResponse;
                const fetchedData: FilterGroupData = {
                    actors: filterResponse.actors || [],
                    tags: filterResponse.tags || [],
                    channels: filterResponse.channels || [],
                };

                setFilterData(fetchedData);
                setHasFetchedFilters(true);
                setFilterToCache(cacheKey, fetchedData);

                if (isInitialFetch) {
                    cachedDefaultData.current = fetchedData;
                }
            },
            onError: () => {
                toast.error('获取关联筛选数据失败');
            }
        });
    };

    const handleToggleFilterPanel = () => {
        const nextState = !isFilterOpen;
        setIsFilterOpen(nextState);

        if (nextState && !hasFetchedFilters) {
            fetchCascadeFilters(currentSelected);
        }
    };

    const handleSelectionChange = (selectedState: SelectedFilterState) => {
        setCurrentSelected(selectedState);
        fetchCascadeFilters(selectedState);
    };

    // 🎯 3. 高级筛选应用：清洗空参数后再提交
    const handleApplyFilter = (selected: SelectedFilterState) => {
        setIsFilterOpen(false);

        const rawParams = {
            category: filters.category || "",
            actors: selected.actors.join(','),
            tags: selected.tags.join(','),
            channels: selected.channels.join(','),
        };

        router.get('/videos', cleanParams(rawParams), { preserveState: true, preserveScroll: true });
    };

    const handleResetFilter = () => {
        const emptyState = { actors: [], tags: [], channels: [] };
        setCurrentSelected(emptyState);

        if (cachedDefaultData.current) {
            setFilterData(cachedDefaultData.current);
        } else {
            fetchCascadeFilters(emptyState);
        }

        router.get('/videos', {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <div className="container mx-auto p-4 md:p-8 bg-background min-h-screen">
            {/* 顶部标题与高级筛选按钮 */}
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-2xl md:text-3xl font-bold tracking-tight text-primary">推荐视频</h1>
                    <p className="text-sm text-muted-foreground mt-1">根据你的喜好实时更新</p>
                </div>

                <Button
                    type="button"
                    onClick={handleToggleFilterPanel}
                    className={`flex items-center gap-2 rounded-full px-4.5 py-2 sm:px-5 sm:py-2.5 text-xs sm:text-sm font-medium transition-all duration-200 shadow-none border ${selectedCount > 0
                            ? "bg-red-600 text-white border-red-600 hover:bg-red-700 hover:text-white"
                            : isFilterOpen
                                ? "bg-primary text-primary-foreground border-primary hover:bg-primary/90 hover:text-primary-foreground"
                                : "bg-background text-foreground border-input/80 hover:bg-muted/80 hover:text-foreground"
                        }`}
                >
                    {isFilterOpen ? (
                        <ChevronUp className="w-4 h-4 shrink-0" />
                    ) : (
                        <SlidersHorizontal className="w-4 h-4 shrink-0" />
                    )}
                    <span>高级筛选</span>

                    {selectedCount > 0 && (
                        <span className="ml-0.5 min-w-5 h-5 px-1 flex items-center justify-center text-[11px] font-bold bg-white text-red-600 rounded-full leading-none shadow-xs">
                            {selectedCount}
                        </span>
                    )}
                </Button>
            </div>

            {/* 横向快捷分类栏（Deferred 异步加载） */}
            <Deferred data="quickFilters" fallback={<QuickFilterSkeleton />}>
                <div className="flex items-center gap-2 overflow-x-auto pb-4 mb-6 scrollbar-none snap-x">
                    {quickFilters.map((item) => {
                        const active = isQuickFilterActive(item);
                        return (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => handleQuickFilterClick(item)}
                                className={`px-4 py-1.5 rounded-xl text-sm font-medium transition-all shrink-0 snap-center ${active
                                        ? 'bg-primary text-primary-foreground shadow-sm scale-[1.02]'
                                        : 'bg-muted hover:bg-muted/80 text-muted-foreground hover:text-primary'
                                    }`}
                            >
                                {item.name}
                            </button>
                        );
                    })}
                </div>
            </Deferred>

            {/* 高级筛选面板 */}
            {isFilterOpen && (
                <div className="mb-8 animate-in fade-in slide-in-from-top-4 duration-300">
                    <AdvancedFilterPanel
                        data={filterData}
                        initialSelected={currentSelected}
                        isLoading={processing}
                        onSelectionChange={handleSelectionChange}
                        onApply={handleApplyFilter}
                        onReset={handleResetFilter}
                    />
                </div>
            )}

            {/* 视频网格 */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-3 gap-y-8">
                <Deferred data="videos" fallback={
                    Array.from({ length: 12 }).map((_, index) => (
                        <VideoSkeleton key={index} />
                    ))
                }>
                    {videos?.data?.map((video) => (
                        <Link href={show({ video: video.id, slug: video.slug })} key={video.id}>
                            <VideoCard key={video.id} video={video} />
                        </Link>
                    ))}
                </Deferred>
            </div>

            {/* 底部分页 */}
            <div className="flex flex-col items-center justify-center mt-10 mb-12 gap-2">
                <VideoPagination links={videos?.links} />
            </div>
        </div>
    );
}