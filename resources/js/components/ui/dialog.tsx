// resources/js/components/ui/dialog.tsx

import * as React from "react"
import * as DialogPrimitive from "@radix-ui/react-dialog"
import { X } from "lucide-react" // 优化：使用标准的 X 图标组件

import { cn } from "@/lib/utils"
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip"

function Dialog({
    ...props
}: React.ComponentProps<typeof DialogPrimitive.Root>) {
    return <DialogPrimitive.Root data-slot="dialog" {...props} />
}
Dialog.displayName = "Dialog"

function DialogTrigger({
    ...props
}: React.ComponentProps<typeof DialogPrimitive.Trigger>) {
    return <DialogPrimitive.Trigger data-slot="dialog-trigger" {...props} />
}
DialogTrigger.displayName = "DialogTrigger"

function DialogPortal({
    ...props
}: React.ComponentProps<typeof DialogPrimitive.Portal>) {
    return <DialogPrimitive.Portal data-slot="dialog-portal" {...props} />
}
DialogPortal.displayName = "DialogPortal"

function DialogClose({
    ...props
}: React.ComponentProps<typeof DialogPrimitive.Close>) {
    return <DialogPrimitive.Close data-slot="dialog-close" {...props} />
}
DialogClose.displayName = "DialogClose"

function DialogOverlay({
    className,
    ...props
}: React.ComponentProps<typeof DialogPrimitive.Overlay>) {
    return (
        <DialogPrimitive.Overlay
            data-slot="dialog-overlay"
            className={cn(
                "data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-black/80",
                className
            )}
            {...props}
        />
    )
}
DialogOverlay.displayName = "DialogOverlay"

interface DialogContentProps
    extends React.ComponentProps<typeof DialogPrimitive.Content> {
    showCloseButton?: boolean
    closeClassName?: string
    closeTooltip?: string
}

function DialogContent({
    className,
    children,
    showCloseButton = true,
    closeClassName,
    closeTooltip = "关闭",
    ...props
}: DialogContentProps) {
    return (
        <DialogPortal data-slot="dialog-portal">
            <DialogOverlay />
            <DialogPrimitive.Content
                data-slot="dialog-content"
                className={cn(
                    "bg-background data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 fixed top-[50%] left-[50%] z-50 grid w-full max-w-[calc(100%-2rem)] translate-x-[-50%] translate-y-[-50%] gap-4 rounded-lg border p-6 shadow-lg duration-200 sm:max-w-lg",
                    className
                )}
                {...props}
            >
                {children}

                {/* 🌟 增大尺寸、饱满触控区域、带 Tooltip 提示的高对比微交互关闭按钮（适配亮色与暗色模式） */}
                {showCloseButton && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <DialogPrimitive.Close
                                className={cn(
                                    "absolute top-3.5 right-3.5 sm:top-4 sm:right-4 size-9 sm:size-10 rounded-full flex items-center justify-center cursor-pointer transition-all duration-200",
                                    "text-slate-500 hover:text-slate-900 hover:bg-slate-200/80 active:bg-slate-300/80",
                                    "dark:text-slate-400 dark:hover:text-slate-100 dark:hover:bg-slate-800 dark:active:bg-slate-700",
                                    "active:scale-90 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none",
                                    "[&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-5",
                                    closeClassName
                                )}
                            >
                                <X />
                                <span className="sr-only">Close</span>
                            </DialogPrimitive.Close>
                        </TooltipTrigger>
                        <TooltipContent side="bottom" align="end" className="text-xs font-medium">
                            {closeTooltip}
                        </TooltipContent>
                    </Tooltip>
                )}
            </DialogPrimitive.Content>
        </DialogPortal>
    )
}
DialogContent.displayName = "DialogContent"

function DialogHeader({ className, ...props }: React.ComponentProps<"div">) {
    return (
        <div
            data-slot="dialog-header"
            className={cn("flex flex-col gap-2 text-center sm:text-left", className)}
            {...props}
        />
    )
}
DialogHeader.displayName = "DialogHeader"

function DialogFooter({ className, ...props }: React.ComponentProps<"div">) {
    return (
        <div
            data-slot="dialog-footer"
            className={cn(
                "flex flex-col-reverse gap-2 sm:flex-row sm:justify-end",
                className
            )}
            {...props}
        />
    )
}
DialogFooter.displayName = "DialogFooter"

function DialogTitle({
    className,
    ...props
}: React.ComponentProps<typeof DialogPrimitive.Title>) {
    return (
        <DialogPrimitive.Title
            data-slot="dialog-title"
            className={cn("text-lg leading-none font-semibold", className)}
            {...props}
        />
    )
}
DialogTitle.displayName = "DialogTitle"

function DialogDescription({
    className,
    ...props
}: React.ComponentProps<typeof DialogPrimitive.Description>) {
    return (
        <DialogPrimitive.Description
            data-slot="dialog-description"
            className={cn("text-muted-foreground text-sm", className)}
            {...props}
        />
    )
}
DialogDescription.displayName = "DialogDescription"

export {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogOverlay,
    DialogPortal,
    DialogTitle,
    DialogTrigger,
}
