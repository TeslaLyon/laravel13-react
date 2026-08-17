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

// 假设这是你现有的数据源，你可以将其替换为从 API 获取的实际数组
const INITIAL_STUDIOS = ["Brazzers", "Tushy", "Vixen", "Blacked"]

interface StudioComboboxProps {
    data: {
        studio: string;
        [key: string]: any; // 允许其他属性
    };
    setData: (field: string, value: any) => void;
    errors: {
        studio?: string;
        [key: string]: any; // 允许其他错误字段
    };
}

export default function StudioCombobox({ data, setData, errors }: StudioComboboxProps) {
    // 控制下拉菜单的打开/关闭状态
    const [open, setOpen] = useState(false)
    // 维护可选项列表（允许我们将新增的项临时加进列表以便显示）
    const [studios, setStudios] = useState<string[]>(INITIAL_STUDIOS)
    // 追踪用户当前的搜索输入
    const [searchQuery, setSearchQuery] = useState("")

    // 判断当前输入是否已经完全等于某个已有片商
    const hasExactMatch = studios.some(
        (studio) => studio.toLowerCase() === searchQuery.toLowerCase()
    )

    const handleSelect = (value: string) => {
        // 写入你的表单状态
        setData("studio", value)
        setOpen(false)
        setSearchQuery("")
    }

    const handleCreate = () => {
        const newValue = searchQuery.trim()
        if (!newValue) return

        // 如果想要新增后立刻在列表中看到它，可以更新本地选项状态
        if (!hasExactMatch) {
            setStudios([...studios, newValue])
        }

        handleSelect(newValue)
    }

    return (
        <div className="space-y-2">
            <Label htmlFor="studio" className="flex items-center gap-1">
                所属片商 <span className="text-destructive">*</span>
            </Label>

            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    {/* 将按钮的样式伪装成 Input，并在有错误时显示红色边框 */}
                    <Button
                        id="studio"
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        className={cn(
                            "w-full justify-between font-normal",
                            !data.studio && "text-muted-foreground",
                            errors.studio && "border-destructive focus-visible:ring-destructive"
                        )}
                    >
                        {data.studio ? data.studio : "请选择或搜索片商..."}
                        <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>

                <PopoverContent className="w-[--radix-popover-trigger-width] p-0" align="start">
                    {/* Command 组件内部会自动处理列表的按键导航和搜索过滤 */}
                    <Command shouldFilter={false}>
                        {/* shouldFilter={false} 是因为我们要自定义过滤和显示逻辑，以支持"新增" */}
                        <CommandInput
                            placeholder="输入片商名称搜索或新建..."
                            value={searchQuery}
                            onValueChange={setSearchQuery}
                        />

                        <CommandList>
                            {/* 根据输入过滤现有的列表项 */}
                            <CommandGroup>
                                {studios
                                    .filter((studio) =>
                                        studio.toLowerCase().includes(searchQuery.toLowerCase())
                                    )
                                    .map((studio) => (
                                        <CommandItem
                                            key={studio}
                                            value={studio}
                                            onSelect={() => handleSelect(studio)}
                                        >
                                            <Check
                                                className={cn(
                                                    "mr-2 h-4 w-4",
                                                    data.studio === studio ? "opacity-100" : "opacity-0"
                                                )}
                                            />
                                            {studio}
                                        </CommandItem>
                                    ))}
                            </CommandGroup>

                            {/* 如果搜索框有内容，且完全匹配不到现有选项，则显示“新增”按钮 */}
                            {searchQuery.trim() !== "" && !hasExactMatch && (
                                <CommandGroup>
                                    <CommandItem
                                        value={searchQuery}
                                        onSelect={handleCreate}
                                        className="cursor-pointer text-primary font-medium"
                                    >
                                        <PlusCircle className="mr-2 h-4 w-4" />
                                        新增片商: "{searchQuery}"
                                    </CommandItem>
                                </CommandGroup>
                            )}

                            {/* 当没有任何匹配，且输入为空时的默认空状态 */}
                            {studios.length === 0 && searchQuery === "" && (
                                <CommandEmpty>暂无片商数据。</CommandEmpty>
                            )}
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>

            {/* 错误信息展示保持不变 */}
            {errors.studio && (
                <p className="text-xs text-destructive flex items-center gap-1">
                    <AlertCircle className="h-3 w-3" />
                    {errors.studio}
                </p>
            )}
        </div>
    )
}