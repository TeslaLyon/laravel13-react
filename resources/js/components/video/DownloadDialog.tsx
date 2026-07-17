import React from 'react';
import {
    Download,
    Magnet,
    ShoppingBag,
    Copy,
    ExternalLink,
    Zap
} from "lucide-react";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Link } from "@inertiajs/react";
import { toast } from "sonner";

const mockDownloads = [
    {
        id: 1,
        type: 'magnet',
        costType: 'free',
        title: '1080P 高清完整版',
        description: 'MP4 格式 • 2.4 GB',
        link: 'magnet:?xt=urn:btih:example123'
    },
    {
        id: 2,
        type: 'store',
        costType: 'paid',
        title: '官方无删减蓝光典藏版',
        description: '包含独家幕后花絮与设定集',
        price: '99 金币',
        link: '/videos'
    }
];

export function DownloadDialog() {
    // 复制到剪贴板的逻辑
    const handleCopy = async (text: string) => {
        try {
            await navigator.clipboard.writeText(text);
            toast.success("复制成功！", {
                duration: 4000,
                description: "磁力链接已保存到您的剪贴板，请前往下载工具中使用。",
            });
        } catch (err) {
            toast.error("复制失败", {
                description: "您的浏览器不支持或拒绝了剪贴板访问，请手动复制。",
            });
        }
    };

    // 处理磁力链接唤起软件的逻辑
    const handleDirectDownload = (link: string) => {
        // 使用原生 JS 强制浏览器加载该协议，避免被 React/Inertia 路由拦截而报错 canceled
        window.location.href = link;
    };

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="secondary" className="rounded-full px-4 h-9 shadow-none hover:bg-muted-foreground/10 gap-2 shrink-0">
                    <Download className="w-4 h-4" />
                    <span className="text-sm font-medium">获取资源</span>
                </Button>
            </DialogTrigger>

            {/* 🌟 修改点 1：将 sm:max-w-md 改为 sm:max-w-xl，大幅增加弹窗宽度 */}
            <DialogContent className="sm:max-w-xl rounded-xl">
                <DialogHeader>
                    <DialogTitle className="text-xl font-bold">资源获取</DialogTitle>
                </DialogHeader>

                <div className="flex flex-col gap-4 mt-2">
                    {mockDownloads.map((item) => (
                        <div
                            key={item.id}
                            // 去掉了多余的 gap，优化了内部元素的对齐和边距
                            className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-xl border border-border bg-card hover:bg-muted/50 transition-colors"
                        >
                            <div className="flex items-start gap-4 w-full">
                                {/* 左侧图标 */}
                                <div className={`p-2.5 rounded-xl shrink-0 mt-0.5 ${item.type === 'magnet' ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400' : 'bg-orange-500/10 text-orange-600 dark:text-orange-400'}`}>
                                    {item.type === 'magnet' ? <Magnet className="w-6 h-6" /> : <ShoppingBag className="w-6 h-6" />}
                                </div>

                                {/* 文本信息与标签 */}
                                <div className="flex flex-col min-w-0 flex-1 justify-center">
                                    <div className="flex items-center gap-2 flex-wrap mb-1">
                                        <span className="font-semibold text-foreground text-base truncate">{item.title}</span>
                                        {item.costType === 'free' ? (
                                            <Badge variant="outline" className="text-green-600 bg-green-500/10 border-green-500/20 px-2 py-0 text-[11px] h-5 font-medium">
                                                免费
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline" className="text-orange-600 bg-orange-500/10 border-orange-500/20 px-2 py-0 text-[11px] h-5 font-medium">
                                                付费
                                            </Badge>
                                        )}
                                    </div>
                                    <span className="text-sm text-muted-foreground">{item.description}</span>
                                    {item.type === 'store' && (
                                        <span className="text-sm font-bold text-orange-600 dark:text-orange-400 mt-1">
                                            {item.price}
                                        </span>
                                    )}
                                </div>
                            </div>

                            {/* 操作按钮区域 */}
                            <div className="flex items-center gap-3 shrink-0 w-full sm:w-auto mt-2 sm:mt-0">
                                {item.type === 'magnet' ? (
                                    <>
                                        {/* 🌟 修改点 2：去掉 a 标签，使用 onClick 配合 window.location.href */}
                                        <Button
                                            variant="default"
                                            size="sm"
                                            className="flex-1 sm:flex-none h-9 px-4"
                                            onClick={() => handleDirectDownload(item.link)}
                                        >
                                            <Zap className="w-4 h-4 mr-1.5" />
                                            直接下载
                                        </Button>

                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="flex-1 sm:flex-none h-9 px-4"
                                            onClick={() => handleCopy(item.link)}
                                        >
                                            <Copy className="w-4 h-4 mr-1.5" />
                                            复制
                                        </Button>
                                    </>
                                ) : (
                                    <Button
                                        variant="default"
                                        size="sm"
                                        className="w-full sm:w-auto h-9 px-4"
                                        asChild
                                    >
                                        <Link href={item.link}>
                                            <ExternalLink className="w-4 h-4 mr-1.5" />
                                            前往商城
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}
