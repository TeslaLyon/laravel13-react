import React, { useState, useMemo, useRef, useEffect } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { X, Search, Loader2 } from "lucide-react";
import { toast } from 'sonner';
import { useHttp } from '@inertiajs/react';
import { Video } from '@/types/video';
import { store as videoCorrectionStore } from '@/actions/App/Http/Controllers/VideoCorrectionController';
import { useRequireAuth } from '@/components/require-auth-provider';

export interface SuggestOption {
    id: string | number;
    name: string;
    name_zh?: string;
    avatar?: string;
    subtitle?: string;
}

interface EntitySuggestInputProps {
    placeholder: string;
    searchUrl: string;
    selected: SuggestOption[];
    onChange: (items: SuggestOption[]) => void;
    showAvatar?: boolean;
}

function areArraysEqual(arr1: (string | number)[], arr2: (string | number)[]) {
    if (arr1.length !== arr2.length) return false;
    const set2 = new Set(arr2);
    return arr1.every(id => set2.has(id));
}

function EntitySuggestInput({
    placeholder,
    searchUrl,
    selected = [],
    onChange,
    showAvatar = false
}: EntitySuggestInputProps) {
    const [query, setQuery] = useState('');
    const [isOpen, setIsOpen] = useState(false);
    const [options, setOptions] = useState<SuggestOption[]>([]);

    const [isSearching, setIsSearching] = useState(false);
    const { get, cancel, processing } = useHttp();
    const containerRef = useRef<HTMLDivElement>(null);
    const isLoading = processing || isSearching;

    useEffect(() => {
        if (!query.trim()) {
            cancel();
            setOptions([]);
            setIsSearching(false);
            return;
        }

        setIsSearching(true);

        const timer = setTimeout(() => {
            cancel();
            get(`${searchUrl}?q=${encodeURIComponent(query.trim())}`, {
                onSuccess: (response: any) => {
                    const searchResults = response?.results || response?.data || response || [];
                    setOptions(searchResults);
                    setIsOpen(true);
                    setIsSearching(false);
                },
                onError: () => {
                    toast.error("搜索失败，请稍后重试");
                    setIsSearching(false);
                }
            });
        }, 300);

        return () => clearTimeout(timer);
    }, [query, searchUrl]);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            cancel();
        };
    }, []);

    const filteredOptions = useMemo(() => {
        const selectedIds = new Set(selected.map(item => item.id));
        return options.filter(opt => !selectedIds.has(opt.id));
    }, [options, selected]);

    const handleSelect = (item: SuggestOption) => {
        onChange([...selected, item]);
        setQuery('');
        setIsOpen(false);
    };

    const handleRemove = (id: string | number) => {
        onChange(selected.filter(item => item.id !== id));
    };

    return (
        <div className="flex flex-col gap-3">
            <div className="relative" ref={containerRef}>
                <div className="relative flex items-center">
                    {isLoading ? (
                        <Loader2 className="absolute left-3 w-4 h-4 text-primary animate-spin pointer-events-none" />
                    ) : (
                        <Search className="absolute left-3 w-4 h-4 text-muted-foreground pointer-events-none transition-opacity" />
                    )}
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onFocus={() => {
                            if (options.length > 0) setIsOpen(true);
                        }}
                        placeholder={placeholder}
                        className="pl-9 pr-4 bg-muted/40 focus-visible:bg-background transition-colors h-11 sm:h-10 text-base sm:text-sm"
                    />
                </div>

                {isOpen && query.trim().length > 0 && (
                    <div className="relative">
                        <div className="absolute top-full left-0 right-0 mt-1.5 bg-popover border border-border/80 rounded-xl shadow-xl z-50 max-h-[40vh] overflow-y-auto divide-y divide-border/40">
                            {isLoading && filteredOptions.length > 0 && (
                                <div className="absolute inset-0 bg-popover/40 backdrop-blur-[1px] flex items-center justify-center z-20 rounded-xl cursor-not-allowed select-none transition-all" />
                            )}
                            {filteredOptions.length > 0 ? (
                                filteredOptions.map((option) => (
                                    <div
                                        key={option.id}
                                        onClick={() => !isLoading && handleSelect(option)}
                                        className={`flex items-center gap-3 p-3.5 sm:p-3 transition-colors ${isLoading
                                            ? 'cursor-not-allowed opacity-60'
                                            : 'hover:bg-accent/80 cursor-pointer active:bg-accent'
                                            }`}
                                    >
                                        {showAvatar && (
                                            <Avatar className="w-10 h-10 border shrink-0">
                                                <AvatarImage src={option.avatar} alt={option.name} />
                                                <AvatarFallback>{option.name ? option.name[0] : '?'}</AvatarFallback>
                                            </Avatar>
                                        )}
                                        <div className="flex flex-col min-w-0">
                                            <div className="flex items-baseline gap-1.5 truncate">
                                                <span className="font-semibold text-sm text-popover-foreground">
                                                    {option.name}
                                                </span>
                                                {option.name_zh && (
                                                    <span className="text-sm text-muted-foreground">
                                                        ({option.name_zh})
                                                    </span>
                                                )}
                                            </div>
                                            {option.subtitle && (
                                                <span className="text-xs text-muted-foreground truncate">
                                                    {option.subtitle}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                ))
                            ) : (
                                isLoading ? (
                                    <div className="p-6 flex flex-col items-center justify-center gap-2 text-muted-foreground">
                                        <Loader2 className="w-5 h-5 animate-spin text-primary/70" />
                                        <span className="text-xs">正在搜索匹配项...</span>
                                    </div>
                                ) : (
                                    <div className="p-6 text-center text-xs text-muted-foreground">
                                        未找到匹配结果
                                    </div>
                                )
                            )}
                        </div>
                    </div>
                )}
            </div>

            {selected.length > 0 && (
                <div className="flex flex-wrap sm:flex-col gap-2">
                    {selected.map((item) => (
                        <div
                            key={item.id}
                            className="flex items-center justify-between p-2 sm:p-2.5 rounded-xl border border-border/60 bg-card hover:bg-muted/30 transition-colors shadow-sm w-full"
                        >
                            <div className="flex items-center gap-3 min-w-0">
                                {showAvatar && (
                                    <Avatar className="w-9 h-9 sm:w-10 sm:h-10 border shrink-0 transition-all duration-300 hover:scale-125 hover:shadow-md hover:z-10 cursor-pointer relative">
                                        <AvatarImage src={item.avatar} alt={item.name} />
                                        <AvatarFallback>{item.name ? item.name[0] : '?'}</AvatarFallback>
                                    </Avatar>
                                )}
                                <span className="font-semibold text-sm text-foreground truncate">
                                    {showAvatar ? item.name : `# ${item.name}`}
                                    {!showAvatar && item.name_zh && (
                                        <span className="text-muted-foreground font-normal ml-1">
                                            ({item.name_zh})
                                        </span>
                                    )}
                                </span>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => handleRemove(item.id)}
                                className="h-9 w-9 sm:h-8 sm:w-8 text-muted-foreground hover:text-foreground rounded-full shrink-0"
                            >
                                <X className="w-4 h-4" />
                            </Button>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

// 1. 在类型定义中新增 'name_zh' 支持
interface VideoCorrectionDialogProps {
    video?: Video;
    trigger?: React.ReactNode;
    type?: 'all' | 'actors' | 'categories' | 'tags' | 'name_zh';
}

export default function VideoCorrectionDialog({
    video,
    trigger,
    type = 'all'
}: VideoCorrectionDialogProps) {
    const [isOpen, setIsOpen] = useState(false);
    const { requireAuth } = useRequireAuth();

    // 2. 新增：触发按钮点击拦截器
    const handleTriggerClick = (e?: React.MouseEvent) => {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // 核心拦截点：未登录唤醒登录框；已登录才执行打开弹窗
        requireAuth(() => {
            handleOpenChange(true);
        });
    };

    // 2. 新增对视频中文译名的状态管理
    const [nameZh, setNameZh] = useState('');

    const [selectedActors, setSelectedActors] = useState<SuggestOption[]>([]);
    const [selectedCategories, setSelectedCategories] = useState<SuggestOption[]>([]);
    const [selectedTags, setSelectedTags] = useState<SuggestOption[]>([]);

    const { post, processing, transform } = useHttp({
        video_id: 0 as number | string,
        type,
        // 将 payload 的类型定义放宽为 any，以便能够同时处理数组和字符串
        payload: [] as any,
    });

    const handleOpenChange = (open: boolean) => {
        if (open) {
            const safeActors = Array.isArray(video?.actors) ? video.actors : [];
            const safeCategories = Array.isArray(video?.categories) ? video.categories : [];
            const safeTags = Array.isArray(video?.tags) ? video.tags : [];

            // 3. 在弹窗打开时，初始化 nameZh 状态为当前视频的中文名
            setNameZh(video?.name_zh || '');

            setSelectedActors(
                safeActors.map(a => ({
                    id: a.id,
                    name: a.name,
                    avatar: a.avatar,
                }))
            );
            setSelectedCategories(
                safeCategories.map((c: any) => ({ id: c.id, name: c.name, name_zh: c.name_zh }))
            );
            setSelectedTags(
                safeTags.map((t: any) => ({ id: t.id, name: t.name, name_zh: t.name_zh }))
            );
        }
        setIsOpen(open);
    };

    // 4. 判断当前应当渲染哪些模块
    const showNameZh = type === 'all' || type === 'name_zh';
    const showActors = type === 'all' || type === 'actors';
    const showCategories = type === 'all' || type === 'categories';
    const showTags = type === 'all' || type === 'tags';

    const hasChanges = useMemo(() => {
        const safeActors = Array.isArray(video?.actors) ? video.actors : [];
        const safeCategories = Array.isArray(video?.categories) ? video.categories : [];
        const safeTags = Array.isArray(video?.tags) ? video.tags : [];

        const initialActorIds = safeActors.map(a => a.id);
        const initialCategoryIds = safeCategories.map(c => c.id);
        const initialTagIds = safeTags.map(t => t.id);

        const currentActorIds = selectedActors.map(a => a.id);
        const currentCategoryIds = selectedCategories.map(c => c.id);
        const currentTagIds = selectedTags.map(t => t.id);

        // 5. 校验中文译名是否发生变化
        const nameZhChanged = showNameZh && (nameZh !== (video?.name_zh || ''));
        const actorsChanged = showActors && !areArraysEqual(initialActorIds, currentActorIds);
        const categoriesChanged = showCategories && !areArraysEqual(initialCategoryIds, currentCategoryIds);
        const tagsChanged = showTags && !areArraysEqual(initialTagIds, currentTagIds);

        return nameZhChanged || actorsChanged || categoriesChanged || tagsChanged;
    }, [video, selectedActors, selectedCategories, selectedTags, nameZh, showActors, showCategories, showTags, showNameZh]);

    const getDialogTitle = () => {
        switch (type) {
            case 'name_zh': return '修正中文译名';
            case 'actors': return '修正演员资料';
            case 'categories': return '修正视频分类';
            case 'tags': return '修正视频标签';
            default: return '全面修正视频资料';
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!video?.id) {
            toast.error("视频数据加载中，请稍后再试！");
            return;
        }

        let currentPayload: any = [];

        // 6. 根据提交的类型，组织对应的 payload 数据
        switch (type) {
            case 'name_zh':
                currentPayload = nameZh; // 针对译名，直接传递字符串
                break;
            case 'actors':
                currentPayload = selectedActors.map(a => a.id);
                break;
            case 'categories':
                currentPayload = selectedCategories.map(c => c.id);
                break;
            case 'tags':
                currentPayload = selectedTags.map(t => t.id);
                break;
            case 'all':
                // 如果是 'all'，则构造一个包含所有修改数据的对象
                currentPayload = {
                    name_zh: nameZh,
                    actors: selectedActors.map(a => a.id),
                    categories: selectedCategories.map(c => c.id),
                    tags: selectedTags.map(t => t.id)
                };
                break;
        }

        transform((data: any) => ({
            ...data,
            type,
            payload: currentPayload,
        }));

        post(videoCorrectionStore.url({ video: video.id, slug: video.slug }), {
            onSuccess: () => {
                toast.success("修正建议提交成功，感谢您的贡献！", {
                    duration: 8000,
                    description: "我们将在审核后更新视频信息，并发放对应的奖励，再次感谢您的时间和努力！",
                });
                setIsOpen(false);
            },
            onError: () => {
                toast.error("提交失败，请稍后重试。");
            },
        });
    };

    return (
        <>
            {trigger && React.isValidElement(trigger) ? (
                React.cloneElement(trigger as React.ReactElement<any>, {
                    onClick: (e: React.MouseEvent) => {
                        const originalOnClick = (trigger as React.ReactElement<any>).props?.onClick;
                        if (originalOnClick) originalOnClick(e);

                        handleTriggerClick(e);
                    }
                })
            ) : (
                <Button variant="secondary" size="sm" onClick={handleTriggerClick}>
                    修正资料
                </Button>
            )}
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="w-[95vw] sm:max-w-[520px] p-4 sm:p-6 rounded-2xl sm:rounded-xl">
                    <form onSubmit={handleSubmit} className="flex flex-col">
                        <DialogHeader className="mb-4 text-left">
                            <DialogTitle className="text-lg sm:text-xl font-bold">{getDialogTitle()}</DialogTitle>
                            <DialogDescription className="text-xs text-muted-foreground mt-1.5">
                                如果您发现视频资料有误，请在下方搜索并添加建议。采纳后将获得贡献和积分奖励！
                            </DialogDescription>
                        </DialogHeader>

                        <div className="flex flex-col gap-6 py-2 max-h-[55dvh] sm:max-h-[55vh] min-h-[300px] pb-28 overflow-y-auto px-1">

                            {/* 7. 中文译名编辑输入区域 */}
                            {showNameZh && (
                                <div className="flex flex-col gap-2.5">
                                    <label className="text-sm font-semibold text-foreground">
                                        视频中文译名
                                    </label>
                                    <Input
                                        value={nameZh}
                                        onChange={(e) => setNameZh(e.target.value)}
                                        placeholder="请输入准确的中文译名..."
                                        className="h-11 sm:h-10 bg-muted/40 focus-visible:bg-background transition-colors text-base sm:text-sm"
                                    />
                                </div>
                            )}

                            {showActors && (
                                <div className="flex flex-col gap-2.5">
                                    <label className="text-sm font-semibold text-foreground">
                                        演员信息
                                    </label>
                                    <EntitySuggestInput
                                        placeholder="输入演员姓名或别名搜索..."
                                        searchUrl="/search/actors"
                                        selected={selectedActors}
                                        onChange={setSelectedActors}
                                        showAvatar={true}
                                    />
                                </div>
                            )}

                            {showCategories && (
                                <div className="flex flex-col gap-2.5">
                                    <label className="text-sm font-semibold text-foreground">
                                        视频分类
                                    </label>
                                    <EntitySuggestInput
                                        placeholder="输入分类名称搜索..."
                                        searchUrl="/search/categories"
                                        selected={selectedCategories}
                                        onChange={setSelectedCategories}
                                        showAvatar={false}
                                    />
                                </div>
                            )}

                            {showTags && (
                                <div className="flex flex-col gap-2.5">
                                    <label className="text-sm font-semibold text-foreground">
                                        视频标签
                                    </label>
                                    <EntitySuggestInput
                                        placeholder="输入标签名称搜索..."
                                        searchUrl="/search/tags"
                                        selected={selectedTags}
                                        onChange={setSelectedTags}
                                        showAvatar={false}
                                    />
                                </div>
                            )}
                        </div>

                        <DialogFooter className="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 border-t border-border/60 pt-4 mt-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsOpen(false)}
                                disabled={processing}
                                className="w-full sm:w-auto h-11 sm:h-10"
                            >
                                取消
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing || !hasChanges}
                                className="bg-orange-600 hover:bg-orange-700 text-white w-full sm:w-auto h-11 sm:h-10 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {processing ? "提交中..." : "提交建议"}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>

    );
}
