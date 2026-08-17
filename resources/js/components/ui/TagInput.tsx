import React, { useState, KeyboardEvent } from "react";
import { Badge } from "@/components/ui/badge";
import { X } from "lucide-react";

interface TagInputProps {
    value: string[];
    onChange: (value: string[]) => void;
    placeholder?: string;
}

export function TagInput({ value, onChange, placeholder }: TagInputProps) {
    const [inputValue, setInputValue] = useState("");

    const handleKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === "Enter" || e.key === ",") {
            e.preventDefault();
            const newTag = inputValue.trim();
            if (newTag && !value.includes(newTag)) {
                onChange([...value, newTag]);
            }
            setInputValue("");
        } else if (e.key === "Backspace" && inputValue === "" && value.length > 0) {
            onChange(value.slice(0, -1));
        }
    };

    const removeTag = (tagToRemove: string) => {
        onChange(value.filter((tag) => tag !== tagToRemove));
    };

    return (
        <div className="flex flex-wrap items-center gap-2 rounded-md border border-input bg-transparent px-3 py-2 text-sm ring-offset-background focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2">
            {value.map((tag) => (
                // 1. 调整 Badge 的内边距 (px-2.5 py-1) 和文字大小 (text-sm)，并稍微加大元素间的间距 (gap-1.5)
                <Badge key={tag} variant="secondary" className="flex items-center gap-1.5 px-2.5 py-1 text-sm font-normal">
                    {tag}

                    <button
                        type="button"
                        // 2. 增加 button 的点击区域内边距 (p-1)
                        className="rounded-full outline-none hover:bg-muted-foreground/20 p-1 transition-colors ml-0.5"
                        onMouseDown={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                        }}
                        onClick={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            removeTag(tag);
                        }}
                    >
                        {/* 3. 增大图标尺寸 (h-3.5 w-3.5) */}
                        <X className="h-3.5 w-3.5 text-muted-foreground hover:text-foreground" />
                    </button>
                </Badge>
            ))}
            <input
                type="text"
                value={inputValue}
                onChange={(e) => setInputValue(e.target.value)}
                onKeyDown={handleKeyDown}
                placeholder={value.length === 0 ? placeholder : ""}
                // 这里的文本框字体大小由最外层 div 的 text-sm 控制，保持了视觉一致性
                className="flex-1 bg-transparent outline-none placeholder:text-muted-foreground min-w-[120px]"
            />
        </div>
    );
}
