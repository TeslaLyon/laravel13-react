import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Video } from "@/types/video";
import { VideoMenu } from "@/components/video/Menu";
import { getCardHoverColor, formatChineseUnit } from "@/lib/utils";
import dayjs from 'dayjs'
import relativeTime from 'dayjs/plugin/relativeTime'
import 'dayjs/locale/zh-cn'

import { ResponsiveVideoImage } from '@/components/video/ResponsiveVideoImage';
import { CountryFlag } from "@/components/CountryFlag";
import { Download } from "lucide-react";

dayjs.extend(relativeTime)
dayjs.locale('zh-cn')

export function VideoCard({ video }: { video: Video }) {
    const hoverBgStyle = getCardHoverColor(video.id);

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
        <div className="group relative flex flex-col gap-1 cursor-pointer z-0">
            {/* 核心悬停背景框 */}
            <div className={`absolute -inset-3 rounded-2xl border border-transparent opacity-0 transition-opacity duration-200 group-hover:opacity-100 -z-10 ${hoverBgStyle}`}></div>

            {/* 视频封面区域 */}
            <div className="relative w-full aspect-video">
                <ResponsiveVideoImage
                    listImg={video.list_img}
                    preview={video.preview}
                    alt={video.name}
                    dataCrawlType={video.channel?.data_crawl_type}
                    className="w-full h-full object-cover rounded-xl transition-all duration-200"
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
            </div>

            {/* 底部信息区域 */}
            <div className="flex gap-3 px-1 mt-2">
                <Avatar className="h-9 w-9 shrink-0 mt-0.5">
                    <AvatarImage src={video.channel?.avatar} alt={video.channel?.name} />
                    <AvatarFallback>{video.channel?.name?.substring(0, 2) || '视频'}</AvatarFallback>
                </Avatar>

                <div className="flex flex-col flex-1 min-w-0">
                    <div className="flex items-start justify-between gap-2">
                        <h3 className="text-base font-semibold leading-tight line-clamp-2 text-primary hover:text-red-500 transition-colors pt-1.5">
                            {video.name}
                        </h3>

                        <div className="shrink-0 -mt-1 -mr-2 transition-opacity p-1 hover:bg-muted-foreground/20 rounded-full">
                            <VideoMenu videoId={video.id} slug={video.slug} />
                        </div>
                    </div>

                    {hasValidNameZh && (
                        <p className="text-sm font-semibold text-muted-foreground/85 line-clamp-1 mt-1 hover:text-red-500 transition-colors truncate" title={video.name_zh}>
                            {video.name_zh}
                        </p>
                    )}

                    {video.channel && (
                        <p className="text-sm text-muted-foreground mt-1 hover:text-primary transition-colors truncate">
                            {video.channel.name}
                        </p>
                    )}
                    <p className="text-sm text-muted-foreground truncate">
                        {formatChineseUnit(video.views_count)}次观看 • {dayjs(video.created_at).fromNow()}
                    </p>
                </div>
            </div>
        </div>
    );
}
