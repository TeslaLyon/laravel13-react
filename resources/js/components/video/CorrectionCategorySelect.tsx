import React, { useState } from "react"
import { Check, ChevronsUpDown, PlusCircle, AlertCircle } from "lucide-react"
import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { Label } from "@/components/ui/label"
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command"
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover"

// 1. 定义初始的分类数据（您可以根据实际业务需求从接口获取）
const INITIAL_CATEGORIES = ["无码", "有码", "欧美", "国产", "二次元"]

interface CategorySelectProps {
    data: {
        category: string;
        [key: string]: any; // 允许表单对象中存在其他属性
    };
    setData: (field: string, value: any) => void;
    errors: {
        category?: string;
        [key: string]: any; // 允许存在其他错误提示
    };
}

export default function CorrectionCategorySelect({ data, setData, errors }: CategorySelectProps) {
    // 控制下拉弹窗的开关
    const [open, setOpen] = useState(false)
    // 维护当前的分类列表，允许用户新增分类并渲染
    const [categories, setCategories] = useState<string[]>(INITIAL_CATEGORIES)
    // 追踪搜索框中的输入内容
    const [searchQuery, setSearchQuery] = useState("")

    // 检查用户的输入是否与现有分类完全匹配（不区分大小写）
    const hasExactMatch = categories.some(
        (category) => category.toLowerCase() === searchQuery.trim().toLowerCase()
    )

    // 处理选中某个分类的逻辑
    const handleSelect = (value: string) => {
        // 更新表单数据中的 category 字段
        setData("category", value)
        setOpen(false)
        setSearchQuery("")
    }

    // 处理新增分类的逻辑
    const handleCreate = () => {
        const newValue = searchQuery.trim()
        if (!newValue) return

        // 如果列表中没有这个分类，就把它临时加进列表里
        if (!hasExactMatch) {
            setCategories([...categories, newValue])
        }

        handleSelect(newValue)
    }

    return (
        <div className="space-y-2">
            <Label htmlFor="category" className="flex items-center gap-1">
                所属分类 <span className="text-destructive">*</span>
            </Label>

            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    {/* 下拉触发按钮，根据是否有错误信息展示不同的边框颜色 */}
                    <Button
                        id="category"
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        className={cn(
                            "w-full justify-between font-normal",
                            !data.category && "text-muted-foreground",
                            errors.category && "border-destructive focus-visible:ring-destructive"
                        )}
                    >
                        {data.category ? data.category : "请选择或搜索分类..."}
                        <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>

                <PopoverContent className="w-[--radix-popover-trigger-width] p-0" align="start">
                    <Command shouldFilter={false}>
                        <CommandInput
                            placeholder="输入分类名称搜索或新建..."
                            value={searchQuery}
                            onValueChange={setSearchQuery}
                        />

                        <CommandList>
                            <CommandGroup>
                                {/* 根据搜索框内容过滤并渲染分类选项 */}
                                {categories
                                    .filter((category) =>
                                        category.toLowerCase().includes(searchQuery.trim().toLowerCase())
                                    )
                                    .map((category) => (
                                        <CommandItem
                                            key={category}
                                            value={category}
                                            onSelect={() => handleSelect(category)}
                                        >
                                            <Check
                                                className={cn(
                                                    "mr-2 h-4 w-4",
                                                    data.category === category ? "opacity-100" : "opacity-0"
                                                )}
                                            />
                                            {category}
                                        </CommandItem>
                                    ))}
                            </CommandGroup>

                            {/* 如果搜索框有内容，且完全匹配不到现有选项，则显示“新增分类”按钮 */}
                            {searchQuery.trim() !== "" && !hasExactMatch && (
                                <CommandGroup>
                                    <CommandItem
                                        value={searchQuery}
                                        onSelect={handleCreate}
                                        className="cursor-pointer text-primary font-medium"
                                    >
                                        <PlusCircle className="mr-2 h-4 w-4" />
                                        新增分类: "{searchQuery}"
                                    </CommandItem>
                                </CommandGroup>
                            )}

                            {/* 如果什么都没搜到，且输入框为空时的兜底提示 */}
                            {categories.length === 0 && searchQuery === "" && (
                                <CommandEmpty>暂无分类数据。</CommandEmpty>
                            )}
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>

            {/* 表单校验的错误信息展示区 */}
            {errors.category && (
                <p className="text-xs text-destructive flex items-center gap-1">
                    <AlertCircle className="h-3 w-3" />
                    {errors.category}
                </p>
            )}
        </div>
    )
}
