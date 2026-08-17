import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Home, ArrowLeft, Search, Compass } from 'lucide-react';

interface ErrorPageProps {
    status?: number;
}

export default function ErrorPage({ status = 404 }: ErrorPageProps) {
    const titleMap: Record<number, string> = {
        404: '页面不存在或已移除',
        500: '服务器内部错误',
        403: '暂无权限访问',
    };

    const descMap: Record<number, string> = {
        404: '抱歉，您访问的片商、演员或页面可能已被删除、更名或链接输入有误。',
        500: '服务器遇到了一点小麻烦，请稍后刷新重试。',
        403: '您没有权限访问此页面，请尝试登录或返回首页。',
    };

    const title = titleMap[status] || '页面出错了';
    const description = descMap[status] || '遇到了未知的错误，请重试。';

    return (
        <div className="min-h-screen bg-background flex items-center justify-center p-4 relative overflow-hidden">
            <Head title={`${status} - ${title}`} />

            {/* 背景高光发光气泡，呼应页面卡片的高光氛围 */}
            <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[400px] sm:w-[600px] h-[400px] sm:h-[600px] bg-primary/10 rounded-full blur-3xl pointer-events-none" />

            <div className="relative z-10 max-w-lg w-full text-center space-y-6">
                {/* 巨型 404 标志 + 悬浮指南针图标 */}
                <div className="relative flex flex-col items-center justify-center">
                    <span className="text-8xl sm:text-9xl font-black text-muted-foreground/15 tracking-widest select-none font-mono">
                        {status}
                    </span>
                    <div className="absolute inset-0 flex items-center justify-center">
                        <div className="p-4 bg-muted/80 backdrop-blur-md rounded-2xl border border-border shadow-sm">
                            <Compass className="w-10 h-10 text-primary animate-spin [animation-duration:12s]" />
                        </div>
                    </div>
                </div>

                {/* 文字说明 */}
                <div className="space-y-2">
                    <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-foreground">
                        {title}
                    </h1>
                    <p className="text-sm text-muted-foreground/90 max-w-md mx-auto leading-relaxed">
                        {description}
                    </p>
                </div>

                {/* 胶囊形快捷按钮组，保持项目一致样式 */}
                <div className="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => window.history.back()}
                        className="w-full sm:w-auto rounded-full gap-2 border-border hover:bg-muted h-10 px-6 text-sm"
                    >
                        <ArrowLeft className="w-4 h-4" />
                        返回上一页
                    </Button>

                    <Button asChild className="w-full sm:w-auto rounded-full gap-2 shadow-sm h-10 px-6 text-sm">
                        <Link href="/">
                            <Home className="w-4 h-4" />
                            返回首页
                        </Link>
                    </Button>

                    <Button asChild variant="secondary" className="w-full sm:w-auto rounded-full gap-2 h-10 px-6 text-sm">
                        <Link href="/channels">
                            <Search className="w-4 h-4" />
                            探索片商
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    );
}
