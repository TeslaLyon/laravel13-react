import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Video } from "@/types/video";
import { VideoMenu } from "@/components/video/Menu";
import { getCardHoverColor } from "@/lib/utils";
import dayjs from 'dayjs'
import relativeTime from 'dayjs/plugin/relativeTime'
import 'dayjs/locale/zh-cn'

import { ResponsiveVideoImage } from '@/components/video/ResponsiveVideoImage';
import { CountryFlag } from "@/components/CountryFlag";

dayjs.extend(relativeTime)
dayjs.locale('zh-cn')

export function VideoCard({ video }: { video: Video }) {
    const hoverBgStyle = getCardHoverColor(video.id);

    // 🎯 1. 提取并校验 country 是否为有效的非空字符串
    const hasValidCountry = Boolean(video.country && video.country.trim());

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

                {hasValidCountry && (
                    <div className="absolute top-2.5 right-2.5 z-10 pointer-events-none bg-transparent">
                        <CountryFlag code={video.country} showLabel={false} />
                    </div>
                )}
            </div>

            {/* 底部信息区域 */}
            <div className="flex gap-3 px-1 mt-2">
                <Avatar className="h-9 w-9 shrink-0 mt-0.5">
                    <AvatarImage src={video.channel.avatar} alt={video.channel.name} />
                    <AvatarFallback>{video.channel.name.substring(0, 2)}</AvatarFallback>
                </Avatar>

                <div className="flex flex-col flex-1 min-w-0">
                    <div className="flex items-start justify-between gap-2">
                        <h3 className="text-[15px] font-semibold leading-tight line-clamp-2 text-primary group-hover:text-blue-500 transition-colors pt-1.5">
                            {video.name}
                        </h3>

                        <div className="shrink-0 -mt-1 -mr-2 transition-opacity p-1 hover:bg-muted-foreground/20 rounded-full">
                            <VideoMenu videoId={video.id} slug={video.slug} />
                        </div>
                    </div>

                    <p className="text-sm text-muted-foreground mt-1 hover:text-primary transition-colors truncate">
                        {video.channel.name}
                    </p>
                    <p className="text-sm text-muted-foreground truncate">
                        1.2万次观看 • {dayjs(video.created_at).fromNow()}
                    </p>
                </div>
            </div>
        </div>
    );
}