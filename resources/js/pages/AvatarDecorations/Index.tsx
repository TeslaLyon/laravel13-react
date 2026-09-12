import React, { useState } from 'react';
import { Head, useHttp } from '@inertiajs/react';
import { toast } from 'sonner';
import { Sparkles, Check, Lock, Undo2, Clock, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { AvatarWithDecoration, AvatarDecorationData } from '@/components/avatar-with-decoration';

export interface DecorationItem {
    id: number;
    title: string;
    code: string;
    imageUrl: string;
    description: string;
    isUnlocked: boolean;
    isWorn: boolean;
    expiresAt?: string | null;
}

interface PageProps {
    decorations: DecorationItem[];
    currentDecorationId: number | null;
    user: {
        id: number;
        name: string;
        avatar: string;
    };
}

export default function AvatarDecorationsIndex({
    decorations,
    currentDecorationId,
    user,
}: PageProps) {
    // 🌟 1. 本地状态接管佩戴 ID，确保接收 JSON 后卡片 UI 实时重绘[cite: 9]
    const [wornDecorationId, setWornDecorationId] = useState<number | null>(currentDecorationId);

    // 🌟 2. 初始化 useHttp 统一网络请求[cite: 9]
    const { post, processing, transform } = useHttp({
        decoration_id: null as number | null,
    });

    // 预览台展示数据（默认展示当前已佩戴项）
    const initialWornItem = decorations.find((d) => d.id === currentDecorationId) || null;
    const [previewDecoration, setPreviewDecoration] = useState<AvatarDecorationData | null>(
        initialWornItem
            ? {
                id: initialWornItem.id,
                title: initialWornItem.title,
                imageUrl: initialWornItem.imageUrl,
            }
            : null
    );

    // 🌟 3. 佩戴 / 卸下切换处理（消费后端 JSON 返回值）
    const handleToggleWear = (decorationId: number | null) => {
        transform((currentData) => ({
            ...currentData,
            decoration_id: decorationId,
        }));

        post('/avatar-decorations/wear', {
            onSuccess: (response: any) => {
                // 🌟 从后端标准 JSON 载荷中解析字段
                const message = response?.message || (decorationId ? '头像挂件已佩戴！' : '已卸下挂件！');
                const nextDecorationId = response?.data?.currentDecorationId ?? decorationId;
                const nextDecoration = response?.data?.decoration ?? null;

                // 弹出接口返回的提示信息
                toast.success(message);

                // 更新当前真正佩戴的 ID（触发卡片佩戴中状态变更）
                setWornDecorationId(nextDecorationId);

                // 更新预览舞台形象
                if (!nextDecorationId) {
                    setPreviewDecoration(null);
                } else if (nextDecoration) {
                    setPreviewDecoration(nextDecoration);
                } else {
                    const fallbackItem = decorations.find((d) => d.id === nextDecorationId);
                    if (fallbackItem) {
                        setPreviewDecoration({
                            id: fallbackItem.id,
                            title: fallbackItem.title,
                            imageUrl: fallbackItem.imageUrl,
                        });
                    }
                }
            },
            onError: (errors: any) => {
                const message = errors?.message || '操作失败，请稍后重试。';
                toast.error(message);
            },
            onNetworkError: () => {
                toast.error('网络错误，请检查网络连接并重试');
            },
        });
    };

    return (
        <div className="min-h-screen bg-background py-8 px-4 sm:px-6 lg:px-8">
            <Head title="个性化头像装扮中心" />

            <div className="max-w-6xl mx-auto space-y-8">
                {/* 顶部标题与操作区 */}
                <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-border pb-6">
                    <div>
                        <h1 className="text-3xl font-extrabold tracking-tight flex items-center gap-2">
                            <Sparkles className="w-7 h-7 text-amber-500 fill-amber-500" />
                            <span>头像装扮中心</span>
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1.5">
                            完成特定成就、社区活动或签到任务即可解锁专属头像动效挂件。
                        </p>
                    </div>

                    {/* 快捷卸下按钮：依赖本地 wornDecorationId 响应 */}
                    {wornDecorationId && (
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={processing}
                            onClick={() => handleToggleWear(null)}
                            className="gap-1.5 text-muted-foreground hover:text-foreground"
                        >
                            <Undo2 className="w-4 h-4" />
                            <span>卸下当前挂件</span>
                        </Button>
                    )}
                </div>

                {/* 实时效果预览舞台 */}
                <div className="p-6 rounded-2xl bg-muted/40 border border-border flex flex-col sm:flex-row items-center justify-between gap-6">
                    <div className="flex items-center gap-5">
                        <AvatarWithDecoration
                            avatarSrc={user.avatar}
                            avatarFallback={user.name}
                            decoration={previewDecoration}
                            sizeClassName="w-20 h-20 sm:w-24 sm:h-24"
                        />
                        <div>
                            <div className="text-lg font-bold text-foreground">
                                {previewDecoration ? previewDecoration.title : '默认形象（无挂件）'}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {previewDecoration
                                    ? '当前正处于佩戴或预览状态'
                                    : '点击下方挂件卡片可即时试戴并佩戴到主页'}
                            </p>
                        </div>
                    </div>

                    <div className="text-xs text-muted-foreground/80 flex items-center gap-1">
                        <ShieldCheck className="w-4 h-4 text-emerald-500" />
                        <span>挂件将在个人中心、评论区及导航栏全站生效</span>
                    </div>
                </div>

                {/* 挂件卡片列表 */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    {decorations.map((item) => {
                        // 🌟 核心判断：比对当前响应式状态 wornDecorationId
                        const isCurrentlyWorn = wornDecorationId === item.id;

                        return (
                            <Card
                                key={item.id}
                                className={`flex flex-col justify-between transition-all duration-200 border ${isCurrentlyWorn
                                        ? 'border-primary ring-2 ring-primary/20 shadow-md'
                                        : 'border-border/80 hover:border-border hover:shadow-xs'
                                    } ${!item.isUnlocked ? 'opacity-85' : ''}`}
                            >
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <CardTitle className="text-base font-bold truncate">
                                            {item.title}
                                        </CardTitle>

                                        {/* 状态徽章 */}
                                        {isCurrentlyWorn ? (
                                            <Badge className="bg-primary text-primary-foreground text-xs gap-1">
                                                <Check className="w-3 h-3" />
                                                佩戴中
                                            </Badge>
                                        ) : item.isUnlocked ? (
                                            <Badge variant="secondary" className="text-xs text-emerald-600 dark:text-emerald-400 bg-emerald-500/10">
                                                已拥有
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline" className="text-xs text-muted-foreground gap-1">
                                                <Lock className="w-3 h-3" />
                                                未达成
                                            </Badge>
                                        )}
                                    </div>
                                    <CardDescription className="text-xs line-clamp-2 min-h-8 mt-1">
                                        {item.description || '官方限定活动专属装扮'}
                                    </CardDescription>
                                </CardHeader>

                                {/* 卡片头像预览 */}
                                <CardContent className="flex flex-col items-center justify-center py-5 bg-muted/20 rounded-lg mx-4 my-1 min-h-[140px]">
                                    <div className="relative w-40 h-40 flex items-center justify-center">
                                        <AvatarWithDecoration
                                            avatarSrc={user.avatar}
                                            avatarFallback={user.name}
                                            decoration={{
                                                id: item.id,
                                                title: item.title,
                                                imageUrl: item.imageUrl,
                                            }}
                                            sizeClassName="w-40 h-40"
                                        />
                                    </div>

                                    {item.expiresAt && item.isUnlocked && (
                                        <span className="flex items-center gap-1 text-[11px] text-amber-600 dark:text-amber-400 mt-2 font-mono">
                                            <Clock className="w-3 h-3" />
                                            有效期至: {item.expiresAt}
                                        </span>
                                    )}
                                </CardContent>

                                {/* 操作按钮 */}
                                <CardFooter className="pt-4">
                                    {isCurrentlyWorn ? (
                                        <Button
                                            variant="outline"
                                            className="w-full text-destructive border-destructive/30 hover:bg-destructive/10"
                                            disabled={processing}
                                            onClick={() => handleToggleWear(item.id)}
                                        >
                                            卸下当前挂件
                                        </Button>
                                    ) : item.isUnlocked ? (
                                        <Button
                                            variant="default"
                                            className="w-full"
                                            disabled={processing}
                                            onClick={() => handleToggleWear(item.id)}
                                        >
                                            立即佩戴
                                        </Button>
                                    ) : (
                                        <Button
                                            variant="secondary"
                                            className="w-full cursor-not-allowed opacity-60"
                                            disabled
                                        >
                                            未解锁
                                        </Button>
                                    )}
                                </CardFooter>
                            </Card>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
