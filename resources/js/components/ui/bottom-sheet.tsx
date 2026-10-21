import * as React from "react";
import * as DialogPrimitive from "@radix-ui/react-dialog";
import { cn } from "@/lib/utils";

/**
 * 底部抽屉 (Bottom Sheet) 根组件
 * 模拟 YouTube App 移动端抽屉交互，在移动端从小屏底部平滑滑出
 */
function BottomSheet({
    ...props
}: React.ComponentProps<typeof DialogPrimitive.Root>) {
    return <DialogPrimitive.Root data-slot="bottom-sheet" {...props} />;
}

function BottomSheetTrigger({
    ...props
}: React.ComponentProps<typeof DialogPrimitive.Trigger>) {
    return <DialogPrimitive.Trigger data-slot="bottom-sheet-trigger" {...props} />;
}

function BottomSheetPortal({
    ...props
}: React.ComponentProps<typeof DialogPrimitive.Portal>) {
    return <DialogPrimitive.Portal data-slot="bottom-sheet-portal" {...props} />;
}

function BottomSheetClose({
    ...props
}: React.ComponentProps<typeof DialogPrimitive.Close>) {
    return <DialogPrimitive.Close data-slot="bottom-sheet-close" {...props} />;
}

function BottomSheetOverlay({
    className,
    ...props
}: React.ComponentProps<typeof DialogPrimitive.Overlay>) {
    return (
        <DialogPrimitive.Overlay
            data-slot="bottom-sheet-overlay"
            className={cn(
                "fixed inset-0 z-50 bg-black/60 backdrop-blur-xs",
                "data-[state=open]:animate-in data-[state=closed]:animate-out",
                "data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 duration-200",
                className
            )}
            {...props}
        />
    );
}

interface BottomSheetContentProps
    extends React.ComponentProps<typeof DialogPrimitive.Content> {
    title?: string;
    description?: string;
    hideHandle?: boolean;
}

function BottomSheetContent({
    className,
    children,
    title,
    description,
    hideHandle = false,
    ...props
}: BottomSheetContentProps) {
    const [touchStartY, setTouchStartY] = React.useState<number | null>(null);
    const [translateY, setTranslateY] = React.useState(0);
    const [isDragging, setIsDragging] = React.useState(false);

    // 处理下拉手势滑动关闭交互 (模拟 YouTube 下滑手势)
    const handleTouchStart = (e: React.TouchEvent) => {
        setTouchStartY(e.touches[0].clientY);
        setIsDragging(true);
    };

    const handleTouchMove = (e: React.TouchEvent) => {
        if (touchStartY === null) return;
        const currentY = e.touches[0].clientY;
        const diff = currentY - touchStartY;
        if (diff > 0) {
            // 向下滑动时产生实时跟随位移
            setTranslateY(diff);
        }
    };

    const handleTouchEnd = () => {
        setIsDragging(false);
        // 如果向下滑动距离超过 60px，模拟点击关闭
        if (translateY > 60) {
            const closeBtn = document.querySelector('[data-slot="bottom-sheet-close-trigger"]') as HTMLButtonElement | null;
            if (closeBtn) {
                closeBtn.click();
            }
        }
        setTranslateY(0);
        setTouchStartY(null);
    };

    return (
        <BottomSheetPortal>
            <BottomSheetOverlay />
            <DialogPrimitive.Content
                data-slot="bottom-sheet-content"
                style={{
                    transform: translateY > 0 ? `translateY(${translateY}px)` : undefined,
                    transition: isDragging ? "none" : "transform 0.2s cubic-bezier(0.16, 1, 0.3, 1)",
                }}
                className={cn(
                    "fixed inset-x-0 bottom-0 z-50 flex flex-col rounded-t-[20px] bg-card text-card-foreground shadow-2xl border-t border-border/60 outline-none select-none",
                    "data-[state=open]:animate-in data-[state=closed]:animate-out",
                    "data-[state=closed]:slide-out-to-bottom data-[state=open]:slide-in-from-bottom data-[state=closed]:duration-200 data-[state=open]:duration-300",
                    "max-h-[85vh] overflow-hidden pb-[max(1.25rem,env(safe-area-inset-bottom))]",
                    className
                )}
                {...props}
            >
                {/* 隐藏的辅助无障碍标题，避免 Radix 控制台警告 */}
                <DialogPrimitive.Title className={cn("sr-only", title && "not-sr-only text-base font-semibold px-5 pt-1 text-foreground")}>
                    {title || "菜单选项"}
                </DialogPrimitive.Title>
                {description && (
                    <DialogPrimitive.Description className="text-xs text-muted-foreground px-5">
                        {description}
                    </DialogPrimitive.Description>
                )}

                {/* 顶部手势拖拽把手区 (YouTube Handle Bar) */}
                {!hideHandle && (
                    <div
                        className="flex justify-center pt-3 pb-2.5 shrink-0 touch-none cursor-grab active:cursor-grabbing"
                        onTouchStart={handleTouchStart}
                        onTouchMove={handleTouchMove}
                        onTouchEnd={handleTouchEnd}
                    >
                        <div className="h-1.25 w-10 rounded-full bg-muted-foreground/35 transition-colors" />
                    </div>
                )}

                {/* 内容区域 */}
                <div className="flex-1 overflow-y-auto px-2 py-1">
                    {children}
                </div>

                {/* 内部隐蔽的 Close 按钮用于手势触发 */}
                <DialogPrimitive.Close data-slot="bottom-sheet-close-trigger" className="hidden" tabIndex={-1} />
            </DialogPrimitive.Content>
        </BottomSheetPortal>
    );
}

interface BottomSheetItemProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    icon?: React.ReactNode;
    children: React.ReactNode;
}

/**
 * 底部抽屉的条目选项，拥有 YouTube 风格的大触控目标、平滑点击高亮与图标文字排版
 */
function BottomSheetItem({
    icon,
    children,
    className,
    disabled = false,
    onClick,
    ...props
}: BottomSheetItemProps) {
    return (
        <button
            type="button"
            disabled={disabled}
            onClick={onClick}
            className={cn(
                "flex w-full min-h-[52px] items-center gap-4 px-4 py-3 rounded-xl text-left text-[15px] font-medium text-foreground",
                "transition-colors hover:bg-muted/80 active:bg-muted/90 cursor-pointer disabled:pointer-events-none disabled:opacity-50",
                className
            )}
            {...props}
        >
            {icon && (
                <span className="flex size-5 shrink-0 items-center justify-center text-foreground/90 [&_svg]:size-5">
                    {icon}
                </span>
            )}
            <span className="flex-1 truncate">{children}</span>
        </button>
    );
}

function BottomSheetSeparator({
    className,
    ...props
}: React.ComponentProps<"div">) {
    return (
        <div
            data-slot="bottom-sheet-separator"
            className={cn("h-px bg-border/60 my-1.5 mx-2", className)}
            {...props}
        />
    );
}

export {
    BottomSheet,
    BottomSheetTrigger,
    BottomSheetPortal,
    BottomSheetClose,
    BottomSheetOverlay,
    BottomSheetContent,
    BottomSheetItem,
    BottomSheetSeparator,
};
