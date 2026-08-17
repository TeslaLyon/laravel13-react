import React, { useState, useMemo } from 'react';
import { Search, Plus, Check, RotateCcw, Filter, Loader2 } from 'lucide-react';
import { Button } from "@/components/ui/button";

export interface FilterOption {
    id: string | number;
    name: string;
}

export interface FilterGroupData {
    actors: FilterOption[];
    tags: FilterOption[];
    channels: FilterOption[];
}

export interface SelectedFilterState {
    actors: (string | number)[];
    tags: (string | number)[];
    channels: (string | number)[];
}

interface AdvancedFilterPanelProps {
    data: FilterGroupData;
    initialSelected?: SelectedFilterState;
    isLoading?: boolean;
    onSelectionChange?: (selected: SelectedFilterState) => void;
    onApply: (selected: SelectedFilterState) => void;
    onReset: () => void;
}

export function AdvancedFilterPanel({
    data,
    initialSelected = { actors: [], tags: [], channels: [] },
    isLoading = false,
    onSelectionChange,
    onApply,
    onReset,
}: AdvancedFilterPanelProps) {
    const [selected, setSelected] = useState<SelectedFilterState>(initialSelected);

    const [searchQueries, setSearchQueries] = useState({
        actors: '',
        tags: '',
        channels: '',
    });

    const toggleSelect = (category: keyof SelectedFilterState, id: string | number) => {
        if (isLoading) return;

        const currentList = selected[category] || [];
        const isExists = currentList.includes(id);

        const newSelected = {
            ...selected,
            [category]: isExists
                ? currentList.filter((item) => item !== id)
                : [...currentList, id],
        };

        setSelected(newSelected);

        if (onSelectionChange) {
            onSelectionChange(newSelected);
        }
    };

    const handleSearchChange = (category: keyof SelectedFilterState, query: string) => {
        setSearchQueries((prev) => ({ ...prev, [category]: query }));
    };

    // 🎯 核心防错优化 1：确保 filterItem 处理的一定是数组
    const filteredData = useMemo(() => {
        const filterItem = (list: FilterOption[], query: string): FilterOption[] => {
            // 安全防护：如果 list 不是有效数组，返回空数组
            if (!Array.isArray(list)) return [];
            if (!query.trim()) return list;

            return list.filter((item) =>
                item && typeof item.name === 'string' && item.name.toLowerCase().includes(query.toLowerCase())
            );
        };

        return {
            actors: filterItem(data?.actors, searchQueries.actors),
            tags: filterItem(data?.tags, searchQueries.tags),
            channels: filterItem(data?.channels, searchQueries.channels),
        };
    }, [data, searchQueries]);

    const handleReset = () => {
        const emptyState = { actors: [], tags: [], channels: [] };
        setSelected(emptyState);
        setSearchQueries({ actors: '', tags: '', channels: '' });
        onReset();
    };

    const handleApply = () => {
        onApply(selected);
    };

    // 🎯 核心防错优化 2：给 options 参数添加默认空数组 = []
    const renderColumn = (
        title: string,
        category: keyof SelectedFilterState,
        options: FilterOption[] = []
    ) => {
        const currentSearch = searchQueries[category] || '';
        const currentSelected = selected[category] || [];
        // 🎯 核心防错优化 3：确保 safeOptions 必须是个数组
        const safeOptions = Array.isArray(options) ? options : [];

        return (
            <div className="flex flex-col flex-1 bg-muted/40 rounded-2xl p-4 md:p-5 border border-border/80 shadow-sm">
                <h3 className="text-base font-semibold text-foreground mb-3">{title}</h3>

                <div className="relative mb-3.5">
                    <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                    <input
                        type="text"
                        placeholder="搜索名称..."
                        disabled={isLoading}
                        value={currentSearch}
                        onChange={(e) => handleSearchChange(category, e.target.value)}
                        className="w-full pl-10 pr-3.5 py-2 bg-background text-foreground text-sm rounded-xl border border-input focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary placeholder:text-muted-foreground/70 transition-all shadow-sm disabled:opacity-50"
                    />
                </div>

                <div className="flex-1 max-h-[260px] overflow-y-auto overscroll-y-contain pr-1 space-y-2 scrollbar-thin scrollbar-thumb-muted-foreground/20 scrollbar-track-transparent">
                    <div className="flex flex-wrap gap-2.5">
                        {/* 🎯 使用 safeOptions 进行安全的 .map() 操作 */}
                        {safeOptions.map((item) => {
                            if (!item) return null;
                            const isSelected = currentSelected.includes(item.id);
                            return (
                                <button
                                    key={item.id}
                                    type="button"
                                    disabled={isLoading}
                                    onClick={() => toggleSelect(category, item.id)}
                                    className={`flex items-center gap-2 px-3.5 py-1.5 rounded-full text-sm font-medium border transition-all select-none shadow-xs disabled:cursor-not-allowed ${isSelected
                                        ? 'bg-primary text-primary-foreground border-primary shadow-sm scale-[1.02]'
                                        : 'bg-background hover:bg-accent hover:text-accent-foreground border-input text-foreground'
                                        }`}
                                >
                                    <span>{item.name}</span>
                                    {isSelected ? (
                                        <Check className="w-3.5 h-3.5 shrink-0" />
                                    ) : (
                                        <Plus className="w-3.5 h-3.5 text-muted-foreground shrink-0" />
                                    )}
                                </button>
                            );
                        })}

                        {safeOptions.length === 0 && (
                            <p className="text-sm text-muted-foreground py-6 w-full text-center">
                                暂无匹配数据
                            </p>
                        )}
                    </div>
                </div>
            </div>
        );
    };

    return (
        <div className="w-full bg-card text-card-foreground border border-border rounded-3xl p-5 md:p-6 shadow-xl backdrop-blur-md transition-colors duration-200">
            <div className="relative">
                {isLoading && (
                    <div className="absolute inset-0 z-30 flex flex-col items-center justify-center bg-card/75 backdrop-blur-[2px] rounded-2xl transition-all duration-300 animate-in fade-in">
                        <div className="flex flex-col items-center gap-3 p-4 rounded-2xl bg-background/90 border border-border shadow-lg">
                            <Loader2 className="w-8 h-8 animate-spin text-primary" />
                            <span className="text-sm font-medium text-foreground">
                                正在更新关联筛选数据...
                            </span>
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 mb-6">
                    {renderColumn('模特 / 演员', 'actors', filteredData.actors)}
                    {renderColumn('分类 / 标签', 'tags', filteredData.tags)}
                    {renderColumn('频道 / 来源', 'channels', filteredData.channels)}
                </div>
            </div>

            <div className="flex items-center justify-end gap-3.5 pt-4 border-t border-border">
                <Button
                    type="button"
                    variant="outline"
                    disabled={isLoading}
                    onClick={handleReset}
                    className="rounded-xl px-5 py-2.5 text-sm gap-2 hover:bg-muted font-medium transition-all"
                >
                    <RotateCcw className="w-4 h-4" />
                    <span>重置</span>
                </Button>

                <Button
                    type="button"
                    disabled={isLoading}
                    onClick={handleApply}
                    className="bg-primary text-primary-foreground hover:bg-primary/90 font-medium rounded-xl px-7 py-2.5 text-sm gap-2 shadow-md transition-all"
                >
                    <Filter className="w-4 h-4" />
                    <span>应用筛选</span>
                </Button>
            </div>
        </div>
    );
}
