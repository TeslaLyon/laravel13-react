import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Video } from "@/types/video";
import { VideoMenu } from "@/components/video/Menu";
import { getCardHoverColor } from "@/lib/utils";
import dayjs from 'dayjs'
import relativeTime from 'dayjs/plugin/relativeTime'
import 'dayjs/locale/zh-cn'

dayjs.extend(relativeTime)

dayjs.locale('zh-cn')

export function VideoCard({ video }: { video: Video }) {
    const hoverBgStyle = getCardHoverColor(video.id);
    return (
        // 🎯 核心悬停特效区：
        // p-2 -m-2: 让容器向外扩张一点但用负边距抵消，防止挤压旁边的卡片
        // hover:bg-secondary: 悬停时使用 shadcn 的次要背景色（完美支持暗黑模式）
        // hover:scale-[1.02]: 悬停时轻微放大 2%，带来“浮起”的立体感
        <div className="group relative flex flex-col gap-1 cursor-pointer z-0">

            {/* 🎯 核心层：完美复刻截图的背景框 */}
            {/* 1. 使用 -inset-3 向外扩张吃掉一半间距 */}
            {/* 2. 移除 scale 缩放，仅仅使用 opacity 渐变显现 */}
            {/* 3. 加上了 border 和 border-transparent 来防闪动 */}
            <div className={`absolute -inset-3 rounded-2xl border border-transparent opacity-0 transition-opacity duration-200 group-hover:opacity-100 -z-10 ${hoverBgStyle}`}></div>

            {/* 视频封面区域 */}
            <div className="relative w-full aspect-video">
                <img
                    // src={video.list_img}
                    src="https://static0.srcdn.com/wordpress/wp-content/uploads/2024/12/gta-6-keyart-logo.jpg?w=1600&h=900&fit=crop"
                    alt={video.name}
                    className="w-full h-full object-cover rounded-xl transition-all duration-200"
                />
                {/* <span className="absolute bottom-1 right-1 bg-black/80 text-white text-xs px-1.5 py-0.5 rounded font-medium">
                    {video.duration}
                </span> */}

                {/* 🎯 细节复刻：底部模拟进度条（仅悬停时显示） */}
                {/* <div className="absolute bottom-0 left-0 h-[2px] bg-red-500 w-1/3 opacity-0 group-hover:opacity-100 transition-opacity"></div> */}
            </div>

            {/* 底部信息区域 (不变) */}
            <div className="flex gap-3 px-1 mt-2">

                {/* 头像 */}
                <Avatar className="h-9 w-9 shrink-0 mt-0.5">
                    <AvatarImage src={video.channel.avatar} alt={video.channel.name} />
                    <AvatarFallback>{video.channel.name.substring(0, 2)}</AvatarFallback>
                </Avatar>
                {/* <Avatar className="h-9 w-9 shrink-0 mt-0.5">
                    <AvatarImage src={video.channelAvatar} alt={video.channelName} />
                    <AvatarFallback>{video.channelName.substring(0, 2)}</AvatarFallback>
                </Avatar> */}

                {/* 🎯 右侧内容区：加上 flex-1 占满空间，加上 min-w-0 防止多行截断失效 */}
                <div className="flex flex-col flex-1 min-w-0">

                    {/* 🎯 第一行：标题与按钮的并排容器 */}
                    <div className="flex items-start justify-between gap-2">

                        <h3 className="text-[15px] font-semibold leading-tight line-clamp-2 text-primary group-hover:text-blue-500 transition-colors pt-1.5">
                            {video.name}
                        </h3>

                        {/* 🎯 菜单按钮容器 */}
                        {/* shrink-0: 保证按钮不会被标题挤压变小 */}
                        {/* -mt-1 -mr-2: 向上向右微调一点负边距，让图标的中心与第一行文字的中心完美对齐 */}
                        {/* 响应式逻辑：默认(移动端) opacity-100 永远可见；md(PC端) 恢复为悬停可见 */}
                        <div className="shrink-0 -mt-1 -mr-2 transition-opacity p-1 hover:bg-muted-foreground/20 rounded-full">
                            <VideoMenu videoId={video.id} slug={video.slug} />
                        </div>
                    </div>

                    {/* 频道与播放数据 */}
                    {/* 加入 truncate 让频道名过长时单行省略，保持布局整洁 */}
                    {/* <p className="text-sm text-muted-foreground mt-1 hover:text-primary transition-colors truncate">
                        {video.channelName}
                    </p>
                    <p className="text-sm text-muted-foreground truncate">
                        {video.views} • {video.uploadedAt}
                    </p> */}
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
