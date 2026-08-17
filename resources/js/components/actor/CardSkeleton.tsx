import React from "react";
import { Skeleton } from "@/components/ui/skeleton";

export function ActorCardSkeleton() {
    return (
        <div className="flex flex-col gap-2 w-full mt-2">
            {/* 1. 封面图骨架：精准保持 9/16 比例，防止页面跳动 (CLS) */}
            <Skeleton className="w-full aspect-[9/16] rounded-xl" />

            {/* 2. 底部信息区骨架：用一个整体大块代替繁琐的小组件，极大地减少 DOM 节点数 */}
            <Skeleton className="w-full h-[100px] rounded-xl" />
        </div>
    );
}

export default ActorCardSkeleton;