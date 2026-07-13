// import { cn } from "@/lib/utils"

// function Skeleton({ className, ...props }: React.ComponentProps<"div">) {
//   return (
//     <div
//       data-slot="skeleton"
//       className={cn("animate-pulse rounded-md bg-muted", className)}
//       {...props}
//     />
//   )
// }

// export { Skeleton }
import { cn } from "@/lib/utils"
import React from "react"

function Skeleton({ className, ...props }: React.ComponentProps<"div">) {
    return (
        <div
            data-slot="skeleton"
            className={cn(
                // 1. 移除了 animate-pulse
                // 2. 加上了 animate-shimmer
                // 3. 稍微加深了 bg-muted 的基础颜色（去掉 /60 或者保留纯 muted），让光泽滑过时对比更强烈
                "animate-shimmer rounded-md bg-muted",
                className
            )}
            {...props}
        />
    )
}

export { Skeleton }
