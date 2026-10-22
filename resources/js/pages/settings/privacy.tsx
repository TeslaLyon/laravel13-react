import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Globe, Users, UserCheck, Lock, Check, ShieldCheck } from 'lucide-react';
import { toast } from 'sonner';
import { cn } from '@/lib/utils';
import { Separator } from '@/components/ui/separator';

interface PrivacySettingsProps {
    settings: {
        profile_visibility: 'public' | 'followers' | 'mutual' | 'private';
        video_favorites?: 'public' | 'followers' | 'mutual' | 'private';
        liked_videos?: 'public' | 'followers' | 'mutual' | 'private';
    };
    status?: string;
}

const VISIBILITY_OPTIONS = [
    {
        value: 'public',
        title: '公开（所有人可见）',
        description: '全站任何注册会员及访客均可自由浏览您的主页与公开动态。',
        icon: Globe,
    },
    {
        value: 'followers',
        title: '仅我的粉丝可见',
        description: '只有关注了您的粉丝才能查看您的主页详细内容。',
        icon: Users,
    },
    {
        value: 'mutual',
        title: '互关好友可见',
        description: '仅限彼此互相关注的好友才能查看您的主页内容，隐私度更高。',
        icon: UserCheck,
    },
    {
        value: 'private',
        title: '仅自己可见（私密）',
        description: '除您本人外，任何访客或其他用户均无法查看您的主页内容。',
        icon: Lock,
    },
] as const;

export default function PrivacySettings({ settings }: PrivacySettingsProps) {
    const { data, setData, patch, processing } = useForm({
        profile_visibility: settings.profile_visibility || 'public',
        video_favorites: settings.video_favorites || 'public',
        liked_videos: settings.liked_videos || 'followers',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        patch('/settings/privacy', {
            preserveScroll: true,
            onError: () => {
                toast.error('保存设置失败，请检查输入或重试');
            },
        });
    };

    return (
        <>
            <Head title="隐私设置" />

            <div className="w-full max-w-2xl space-y-8">
                <Heading
                    variant="small"
                    title="隐私与访问权限"
                    description="自主管理您的个人主页与社交互动信息的对外公开范围"
                />

                <form onSubmit={handleSubmit} className="space-y-8">
                    {/* 1. 主页整体访问权限 */}
                    <div className="space-y-4">
                        <div className="space-y-1">
                            <h3 className="text-sm font-semibold text-foreground flex items-center gap-2">
                                <ShieldCheck className="w-4 h-4 text-primary" />
                                个人主页查看权限
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                设置谁可以访问您的个人主页空间（包括动态、作品与个人概况）。
                            </p>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            {VISIBILITY_OPTIONS.map((option) => {
                                const isSelected = data.profile_visibility === option.value;
                                const Icon = option.icon;

                                return (
                                    <div
                                        key={option.value}
                                        onClick={() => setData('profile_visibility', option.value)}
                                        className={cn(
                                            "relative flex flex-col justify-between p-4 rounded-xl border transition-all cursor-pointer select-none text-left",
                                            isSelected
                                                ? "border-primary bg-primary/5 ring-1 ring-primary shadow-xs"
                                                : "border-border/80 bg-card hover:border-border hover:bg-muted/40"
                                        )}
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="flex items-center gap-2.5">
                                                <div className={cn(
                                                    "w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-colors",
                                                    isSelected ? "bg-primary text-primary-foreground" : "bg-muted text-muted-foreground"
                                                )}>
                                                    <Icon className="w-4 h-4" />
                                                </div>
                                                <span className="font-semibold text-sm text-foreground">
                                                    {option.title}
                                                </span>
                                            </div>

                                            <div className={cn(
                                                "w-4 h-4 rounded-full border flex items-center justify-center shrink-0 mt-0.5 transition-all",
                                                isSelected
                                                    ? "border-primary bg-primary text-primary-foreground"
                                                    : "border-muted-foreground/40"
                                            )}>
                                                {isSelected && <Check className="w-2.5 h-2.5 stroke-[3]" />}
                                            </div>
                                        </div>

                                        <p className="text-xs text-muted-foreground mt-3 leading-relaxed">
                                            {option.description}
                                        </p>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <Separator />

                    {/* 2. 细项隐私设置 */}
                    <div className="space-y-4">
                        <div className="space-y-1">
                            <h3 className="text-sm font-semibold text-foreground">
                                模块公开细项
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                当主页公开时，针对特定模块单独设置可见范围。
                            </p>
                        </div>

                        <div className="space-y-3">
                            <div className="flex items-center justify-between p-3.5 rounded-xl border border-border/80 bg-card">
                                <div>
                                    <div className="text-sm font-medium text-foreground">视频收藏夹</div>
                                    <div className="text-xs text-muted-foreground">谁可以在主页查看您收藏的视频</div>
                                </div>
                                <select
                                    value={data.video_favorites}
                                    onChange={(e) => setData('video_favorites', e.target.value as any)}
                                    className="text-xs rounded-lg border border-border bg-background px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-primary"
                                >
                                    <option value="public">所有人公开</option>
                                    <option value="followers">仅我的粉丝</option>
                                    <option value="mutual">互关好友</option>
                                    <option value="private">仅自己可见</option>
                                </select>
                            </div>

                            <div className="flex items-center justify-between p-3.5 rounded-xl border border-border/80 bg-card">
                                <div>
                                    <div className="text-sm font-medium text-foreground">点赞视频列表</div>
                                    <div className="text-xs text-muted-foreground">谁可以在主页查看您点赞过的视频</div>
                                </div>
                                <select
                                    value={data.liked_videos}
                                    onChange={(e) => setData('liked_videos', e.target.value as any)}
                                    className="text-xs rounded-lg border border-border bg-background px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-primary"
                                >
                                    <option value="public">所有人公开</option>
                                    <option value="followers">仅我的粉丝</option>
                                    <option value="mutual">互关好友</option>
                                    <option value="private">仅自己可见</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-4 pt-2">
                        <Button type="submit" disabled={processing} className="px-6">
                            {processing ? '保存中...' : '保存更改'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

PrivacySettings.layout = {
    breadcrumbs: [
        {
            title: '隐私设置',
            href: '/settings/privacy',
        },
    ],
};

