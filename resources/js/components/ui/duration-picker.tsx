import * as React from "react"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"

interface DurationPickerProps {
    value?: number
    onChange?: (totalSeconds: number) => void
    label?: string
}

export function DurationPicker({
    value = 0,
    onChange,
    label = "视频时长"
}: DurationPickerProps) {

    const hours = Math.floor(value / 3600)
    const minutes = Math.floor((value % 3600) / 60)
    const seconds = value % 60

    const handleTimeChange = (type: "hours" | "minutes" | "seconds", inputValue: string) => {
        let numericValue = inputValue.replace(/\D/g, "")
        let num = numericValue === "" ? 0 : parseInt(numericValue, 10)

        if ((type === "minutes" || type === "seconds") && num > 59) {
            num = 59
        }

        let newTotalSeconds = 0
        if (type === "hours") {
            newTotalSeconds = num * 3600 + minutes * 60 + seconds
        } else if (type === "minutes") {
            newTotalSeconds = hours * 3600 + num * 60 + seconds
        } else if (type === "seconds") {
            newTotalSeconds = hours * 3600 + minutes * 60 + num
        }

        if (onChange) {
            onChange(newTotalSeconds)
        }
    }

    const formatDisplay = (val: number) => (val === 0 ? "" : val.toString())

    return (
        <div className="flex flex-col gap-2">
            {/* {label && <Label className="text-sm font-medium text-foreground">{label}</Label>} */}

            {/* 最外层容器保持水平排列 */}
            <div className="flex items-center gap-2">

                {/* 小时组：移除了 flex-col，现在 Input 和 span 是水平排列的 */}
                <div className="flex items-center gap-1.5">
                    <Input
                        type="text"
                        inputMode="numeric"
                        placeholder="00"
                        value={formatDisplay(hours)}
                        onChange={(e) => handleTimeChange("hours", e.target.value)}
                        className="w-10 px-1 text-center font-mono"
                        maxLength={2}
                    />
                    <span className="text-sm text-muted-foreground whitespace-nowrap">时</span>
                </div>

                {/* 移除了 pb-5，因为现在元素都在同一水平居中线上 */}
                <span className="font-bold text-muted-foreground">:</span>

                {/* 分钟组 */}
                <div className="flex items-center gap-1.5">
                    <Input
                        type="text"
                        inputMode="numeric"
                        placeholder="00"
                        value={formatDisplay(minutes)}
                        onChange={(e) => handleTimeChange("minutes", e.target.value)}
                        className="w-10 px-1 text-center font-mono"
                        maxLength={2}
                    />
                    <span className="text-sm text-muted-foreground whitespace-nowrap">分</span>
                </div>

                <span className="font-bold text-muted-foreground">:</span>

                {/* 秒数组 */}
                <div className="flex items-center gap-1.5">
                    <Input
                        type="text"
                        inputMode="numeric"
                        placeholder="00"
                        value={formatDisplay(seconds)}
                        onChange={(e) => handleTimeChange("seconds", e.target.value)}
                        className="w-10 px-1 text-center font-mono"
                        maxLength={2}
                    />
                    <span className="text-sm text-muted-foreground whitespace-nowrap">秒</span>
                </div>

            </div>
        </div>
    )
}