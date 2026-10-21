import { Skeleton } from "@/components/ui/skeleton"

export function VideoSkeleton() {
    return (
        <div className="flex flex-col gap-2.5 sm:gap-3 bg-card">
            {/* 1. 封面图占位 (关键：移动端无圆角全宽，桌面端 rounded-xl) */}
            <Skeleton className="w-full aspect-video rounded-none sm:rounded-xl bg-slate-100 dark:bg-muted" />

            {/* 2. 底部信息区占位 (移动端对齐 px-3.5，桌面端 px-1) */}
            <div className="flex gap-3 mt-1 px-3.5 sm:px-1">
                {/* 头像占位 */}
                <Skeleton className="h-9 w-9 rounded-full shrink-0 bg-slate-100" />

                {/* 标题与频道信息占位 */}
                <div className="flex flex-col gap-2 w-full">
                    {/* 第一行标题 (使用具体宽度百分比) */}
                    <Skeleton className="h-4 w-[90%] bg-slate-100" />
                    {/* 第二行标题或频道名 (稍短一些) */}
                    <Skeleton className="h-4 w-[60%] bg-slate-100" />
                </div>
            </div>
        </div>
    )
}
