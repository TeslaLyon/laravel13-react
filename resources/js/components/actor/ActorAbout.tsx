// components/actor/ActorAbout.tsx
import React from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { XIcon, FacebookIcon, YoutubeIcon, InstagramIcon, WebsiteIcon } from '@/components/BrandIcons';

// 引入我们刚刚创建的智能翻译函数
import { translateActorKey } from '@/utils/translations';

export function ActorAbout({ actor }: { actor: any }) {

    // 💡 核心优化：改为遍历实际的 data，而不是写死的 map
    // 这样才能确保用户新增的“眼睛颜色”等自定义属性被渲染出来
    const renderInfoBlock = (data: any) => {
        if (!data || Object.keys(data).length === 0) {
            return <div className="text-sm text-muted-foreground">暂无数据</div>;
        }

        return (
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-y-4 gap-x-6">
                {Object.entries(data).map(([key, value]) => {
                    // 忽略空值
                    if (value === null || value === '') return null;

                    return (
                        <div key={key} className="flex flex-col border-b border-border/50 pb-2">
                            {/* 调用智能翻译函数 */}
                            <span className="text-xs text-muted-foreground mb-1">
                                {translateActorKey(key)}
                            </span>
                            <span className="text-sm font-medium text-foreground">
                                {String(value)}
                            </span>
                        </div>
                    );
                })}
            </div>
        );
    };

    const getSocialConfig = (platform: string) => {
        switch (platform.toLowerCase()) {
            case 'x':
            case 'twitter':
                return { icon: <XIcon className="w-7 h-7" />, color: 'hover:bg-foreground hover:text-background border-border', label: 'X' };
            case 'instagram':
                return { icon: <InstagramIcon className="w-7 h-7" />, color: 'hover:bg-pink-50 hover:border-pink-200 border-border dark:hover:bg-pink-950', label: 'Instagram' };
            case 'youtube':
                return { icon: <YoutubeIcon className="w-7 h-7" />, color: 'hover:bg-red-50 hover:border-red-200 border-border dark:hover:bg-red-950', label: 'YouTube' };
            case 'facebook':
                return { icon: <FacebookIcon className="w-7 h-7" />, color: 'hover:bg-blue-50 hover:border-blue-200 border-border dark:hover:bg-blue-950', label: 'Facebook' };
            case 'website':
                return { icon: <WebsiteIcon className="w-7 h-7 text-muted-foreground" />, color: 'hover:bg-muted hover:border-muted-foreground border-border', label: 'Website' };
            default:
                // 默认平台（支持用户自定义社交媒体名称展示）
                return { icon: <WebsiteIcon className="w-7 h-7 text-muted-foreground" />, color: 'hover:bg-muted border-border', label: translateActorKey(platform) };
        }
    };

    return (
        <div className="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
            <Card className="bg-muted/10 border-border/50 shadow-sm">
                <CardHeader className="pb-3">
                    <CardTitle className="text-lg font-bold">基本信息 (Basic Info)</CardTitle>
                </CardHeader>
                <CardContent>
                    {/* 直接传入数据即可 */}
                    {renderInfoBlock(actor?.basic_info)}
                </CardContent>
            </Card>

            <Card className="bg-muted/10 border-border/50 shadow-sm">
                <CardHeader className="pb-3">
                    <CardTitle className="text-lg font-bold">外形数据 (Physical Attributes)</CardTitle>
                </CardHeader>
                <CardContent>
                    {renderInfoBlock(actor?.physical_info)}
                </CardContent>
            </Card>

            <Card className="bg-muted/10 border-border/50 shadow-sm">
                <CardHeader className="pb-3">
                    <CardTitle className="text-lg font-bold">社交媒体 (Social Media)</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="flex flex-wrap gap-4">
                        {actor?.socials && Object.entries(actor.socials).map(([platform, url]) => {
                            if (!url) return null;
                            const config = getSocialConfig(platform);
                            return (
                                <a
                                    key={platform}
                                    href={url as string}
                                    target="_blank"
                                    rel="noreferrer"
                                    className={`
                                        flex items-center gap-3 px-5 py-2.5 rounded-full border
                                        bg-background text-sm font-semibold text-foreground
                                        transition-all duration-300 shadow-sm hover:shadow-md
                                        ${config.color}
                                    `}
                                >
                                    {config.icon}
                                    <span>{config.label}</span>
                                </a>
                            );
                        })}

                        {(!actor?.socials || Object.keys(actor.socials).length === 0) && (
                            <span className="text-sm text-muted-foreground">暂无社交媒体信息</span>
                        )}
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
