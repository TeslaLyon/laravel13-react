import React, { useState } from "react";
import { Check, ChevronsUpDown, Plus } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import { Badge } from "@/components/ui/badge";

interface Option {
    label: string;
    // 【核心修改】：将 value 改为 string 类型
    value: string;
}

interface CreatableMultiSelectProps {
    options: Option[];
    selected: string[];
    onChange: (selected: string[]) => void;
    placeholder?: string;
}

export function CreatableMultiSelect({ options, selected, onChange, placeholder }: CreatableMultiSelectProps) {
    const [open, setOpen] = useState(false);
    const [inputValue, setInputValue] = useState("");

    // 【核心修改】：参数改为 string，移除所有 .toString() 的转换逻辑
    const toggleSelection = (value: string) => {
        const newSelected = selected.includes(value)
            ? selected.filter((item) => item !== value)
            : [...selected, value];
        onChange(newSelected);
    };

    const handleCreate = () => {
        const newVal = inputValue.trim();
        if (newVal && !selected.includes(newVal)) {
            onChange([...selected, newVal]);
        }
        setInputValue("");
        setOpen(false);
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    className="w-full justify-between h-auto min-h-[40px] px-3 py-2"
                >
                    <div className="flex flex-wrap gap-1">
                        {selected.length > 0 ? (
                            selected.map((item) => (
                                <Badge variant="secondary" key={item} className="mr-1">
                                    {item}
                                </Badge>
                            ))
                        ) : (
                            <span className="text-muted-foreground font-normal">{placeholder}</span>
                        )}
                    </div>
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-full p-0">
                <Command>
                    <CommandInput
                        placeholder="搜索或输入新内容..."
                        value={inputValue}
                        onValueChange={setInputValue}
                    />
                    <CommandList>
                        <CommandEmpty>
                            {inputValue ? (
                                <div
                                    className="flex items-center gap-2 cursor-pointer p-2 text-sm hover:bg-accent hover:text-accent-foreground rounded-sm"
                                    onClick={handleCreate}
                                >
                                    <Plus className="h-4 w-4" />
                                    创建 "{inputValue}"
                                </div>
                            ) : (
                                "未找到结果"
                            )}
                        </CommandEmpty>
                        <CommandGroup>
                            {options.map((option) => (
                                <CommandItem
                                    key={option.value}
                                    value={option.label}
                                    onSelect={() => toggleSelection(option.value)}
                                >
                                    <Check
                                        className={cn(
                                            "mr-2 h-4 w-4",
                                            // 【核心修改】：直接使用字符串比对，不再需要 .toString()
                                            selected.includes(option.value) ? "opacity-100" : "opacity-0"
                                        )}
                                    />
                                    {option.label}
                                </CommandItem>
                            ))}
                            {inputValue && !options.some(opt => opt.label === inputValue) && (
                                <CommandItem value={inputValue} onSelect={handleCreate} className="text-blue-600">
                                    <Plus className="mr-2 h-4 w-4" />
                                    创建 "{inputValue}"
                                </CommandItem>
                            )}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
