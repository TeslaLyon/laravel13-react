import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Video } from "@/types/video";
import { VideoMenu } from "@/components/video/Menu";
import { getCardHoverColor, formatChineseUnit, formatVideoPublishTime } from "@/lib/utils";
import { ResponsiveVideoImage } from '@/components/video/ResponsiveVideoImage';
import { CountryFlag } from "@/components/CountryFlag";
import { Download } from "lucide-react";
import { Link, router } from '@inertiajs/react';
import { show as videoShow } from '@/actions/App/Http/Controllers/VideoController';
import { show as channelShow } from '@/actions/App/Http/Controllers/ChannelController';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

export function VideoCard({ video }: { video: Video }) {
    const hoverBgStyle = getCardHoverColor(video.id);

    // 🎯 计算视频详情页 URL
    const videoId = video?.id ?? 0;
    const videoSlug = video?.slug || String(videoId || 'video');
    const videoUrl = (video as any).url || videoShow({
        video: videoId,
        slug: videoSlug,
    });

    // 🎯 计算片商详情页 URL
    const channelId = video.channel?.id || video.channel_id;
    const channelSlug = video.channel?.slug || String(channelId || '');
    const channelUrl = channelId ? channelShow.url({
        channel: channelId,
        slug: channelSlug,
    }) : '';

    // 🎯 卡片空白区域点击兜底跳转视频详情
    const handleCardClick = (e: React.MouseEvent) => {
        const target = e.target as HTMLElement;
        if (target.closest('a') || target.closest('button')) {
            return;
        }
        if (e.ctrlKey || e.metaKey) {
            window.open(videoUrl, '_blank');
        } else {
            router.visit(videoUrl);
        }
    };

    // 🎯 1. 提取并校验 country 是否为有效的非空字符串
    const hasValidCountry = Boolean(video.country && video.country.trim());

    // 🎯 2. 提取并校验 name_zh 是否为有效的非空字符串
    const hasValidNameZh = Boolean(video.name_zh && video.name_zh.trim());

    // 🎯 3. 校验是否拥有下载资源 (优先数据库字段，兜底关联模型)
    const hasDownloads = Boolean(
        video.has_downloads ||
        (video.active_downloads && video.active_downloads.length > 0) ||
        (video.downloads && video.downloads.length > 0)
    );

    return (
        <div
            className="group relative flex flex-col gap-1 cursor-pointer z-0"
            onClick={handleCardClick}
        >
            {/* 核心悬停背景框 (移动端禁用，避免出现横向滚动条) */}
            <div className={`hidden sm:block absolute -inset-3 rounded-2xl border border-transparent opacity-0 transition-opacity duration-200 group-hover:opacity-100 -z-10 ${hoverBgStyle}`}></div>

            {/* 视频封面区域：独立链接至视频详情页 */}
            <Link
                href={videoUrl}
                className="relative block w-full aspect-video overflow-hidden rounded-none sm:rounded-xl bg-background"
            >
                <ResponsiveVideoImage
                    listImg={video.list_img}
                    preview={video.preview}
                    alt={video.name}
                    dataCrawlType={video.channel?.data_crawl_type}
                    className="w-full h-full object-cover rounded-none sm:rounded-xl transition-all duration-200"
                />

                {/* 封面左上角状态徽章 */}
                <div className="absolute top-2 left-2 z-10 flex items-center gap-1.5 pointer-events-none">
                    {hasDownloads && (
                        <span className="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-950/80 text-emerald-300 border border-emerald-500/40 backdrop-blur-md shadow-sm">
                            <Download className="w-2.5 h-2.5" />
                            <span>下载</span>
                        </span>
                    )}
                    {video.has_zh_subtitles && (
                        <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-orange-950/80 text-orange-300 border border-orange-500/40 backdrop-blur-md shadow-sm">
                            中字
                        </span>
                    )}
                    {video.is_4k && (
                        <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-950/80 text-purple-300 border border-purple-500/40 backdrop-blur-md shadow-sm">
                            4K
                        </span>
                    )}
                </div>

                {hasValidCountry && (
                    <div className="absolute top-2.5 right-2.5 z-10 pointer-events-none bg-transparent">
                        <CountryFlag code={video.country} showLabel={false} />
                    </div>
                )}
            </Link>

            {/* 底部信息区域 */}
            <div className="flex gap-3 px-3.5 sm:px-1 mt-2.5 sm:mt-2">
                {/* 片商头像：独立链接至片商详情页 */}
                {video.channel && channelUrl ? (
                    <Link
                        href={channelUrl}
                        className="shrink-0 mt-0.5 group/avatar"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <Avatar className="h-9 w-9 transition-opacity group-hover/avatar:opacity-80">
                            <AvatarImage src={video.channel.avatar} alt={video.channel.name} />
                            <AvatarFallback>{video.channel.name?.substring(0, 2) || '视频'}</AvatarFallback>
                        </Avatar>
                    </Link>
                ) : (
                    <Avatar className="h-9 w-9 shrink-0 mt-0.5">
                        <AvatarImage src={video.channel?.avatar} alt={video.channel?.name} />
                        <AvatarFallback>{video.channel?.name?.substring(0, 2) || '视频'}</AvatarFallback>
                    </Avatar>
                )}

                <div className="flex flex-col flex-1 min-w-0">
                    <div className="flex items-start justify-between gap-2">
                        <Link
                            href={videoUrl}
                            className="flex-1 min-w-0 group/title pt-1.5"
                        >
                            <h3
                                className="text-base font-semibold leading-tight line-clamp-2 text-primary group-hover/title:text-red-500 transition-colors"
                                title={hasValidNameZh ? video.name_zh : video.name}
                            >
                                {hasValidNameZh ? video.name_zh : video.name}
                            </h3>
                        </Link>

                        <div
                            className="shrink-0 -mt-1 -mr-2 transition-opacity p-1 hover:bg-muted-foreground/20 rounded-full"
                            onClick={(e) => e.stopPropagation()}
                        >
                            <VideoMenu videoId={video.id} slug={video.slug} />
                        </div>
                    </div>

                    {hasValidNameZh && (
                        <Link
                            href={videoUrl}
                            className="block group/sub mt-1"
                        >
                            <p
                                className="text-xs font-mono text-muted-foreground/80 line-clamp-1 group-hover/sub:text-muted-foreground transition-colors truncate"
                                title={video.name}
                            >
                                {video.name}
                            </p>
                        </Link>
                    )}

                    {/* 片商名字：带 Tooltip 且独立链接至片商详情页 */}
                    {video.channel && channelUrl && (
                        <div className="mt-1 truncate flex items-center">
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Link
                                        href={channelUrl}
                                        onClick={(e) => e.stopPropagation()}
                                        className="text-sm text-muted-foreground hover:text-primary transition-colors truncate inline-block max-w-full font-medium"
                                    >
                                        {video.channel.name}
                                    </Link>
                                </TooltipTrigger>
                                <TooltipContent side="top">
                                    <p>{video.channel.name}</p>
                                </TooltipContent>
                            </Tooltip>
                        </div>
                    )}

                    <p className="text-sm text-muted-foreground truncate">
                        {formatChineseUnit(video.views_count)}次观看 • {formatVideoPublishTime(video.release_at || video.created_at)}
                    </p>
                </div>
            </div>
        </div>
    );
}
