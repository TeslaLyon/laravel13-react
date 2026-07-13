import React, { useState } from 'react';
import {
    ThumbsUp,
    ThumbsDown,
    Share2,
    Download,
    MoreHorizontal,
    CheckCircle2,
} from "lucide-react";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Video } from "@/types/video";
import { Deferred } from "@inertiajs/react";
import { Skeleton } from "@/components/ui/skeleton";
import { useHttp } from '@inertiajs/react';
import { subscribe } from '@/routes/channels';
import { Spinner } from '@/components/ui/spinner';
import { toast } from 'sonner';
import { LikeAndDislikeButton } from '@/components/video/LikeAndDislikeButton';
import { SubscribeButton } from '@/components/video/SubscribeButton';
import { MoreOption } from '@/components/video/MoreOption';
import { Save } from '@/components/video/Save';
import { Actor } from '@/components/video/Actor';
import { ActorFullBody } from '@/components/video/ActorFullBody';
import { VideoPreviews } from '@/components/video/VideoPreviews';


interface RelatedVideo {
    id: string;
    title: string;
    channelName: string;
    views: string;
    publishedAt: string;
    thumbnail: string;
    duration: string;
}

// ==========================================
// 2. 小模块：侧边栏横向推荐卡片 (RelatedVideoCard)
// ==========================================
const hoverColorPool = [
    "bg-red-500/10 dark:bg-red-500/20",
    "bg-blue-500/10 dark:bg-blue-500/20",
    "bg-green-500/10 dark:bg-green-500/20",
    "bg-yellow-500/10 dark:bg-yellow-500/20",
    "bg-purple-500/10 dark:bg-purple-500/20",
    "bg-pink-500/10 dark:bg-pink-500/20",
    "bg-indigo-500/10 dark:bg-indigo-500/20",
    "bg-orange-500/10 dark:bg-orange-500/20",
];

const RelatedVideoCard = ({ video }: { video: Video }) => {
    const colorIndex = String(video.id).charCodeAt(0) % hoverColorPool.length;
    const hoverBgStyle = hoverColorPool[colorIndex];

    return (
        <div className="group relative flex gap-2 sm:gap-3 cursor-pointer z-0 w-full mb-3">
            {/* 🎯 核心光晕层 */}
            <div className={`absolute -inset-2 rounded-xl border border-transparent opacity-0 transition-opacity duration-200 group-hover:opacity-100 -z-10 ${hoverBgStyle}`}></div>

            {/* 左侧缩略图 */}
            <div className="relative w-[140px] sm:w-[168px] shrink-0 aspect-video rounded-lg overflow-hidden bg-muted">
                <img
                    // src={video.thumbnail}
                    src="https://media-public-ht.project1content.com/m=eaFaWHbWx/1bd/c20/e85/07c/486/5a5/67b/b43/a5f/47f/26/poster/poster_01.jpg"
                    alt={video.name}
                    className="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-300"
                />
                {/* <span className="absolute bottom-1 right-1 bg-black/80 text-white text-[10px] sm:text-xs px-1 sm:px-1.5 py-0.5 rounded font-medium">
                    {video.duration}
                </span> */}
            </div>

            {/* 右侧信息区 */}
            <div className="flex flex-col flex-1 min-w-0 py-0.5">
                <h3 className="text-sm font-semibold leading-tight line-clamp-2 text-foreground group-hover:text-primary transition-colors">
                    {video.name}
                </h3>
                <p className="text-xs text-muted-foreground mt-1 truncate hover:text-foreground transition-colors">
                    {video.channel.name}
                </p>
                <p className="text-xs text-muted-foreground truncate">
                    {/* {video.views} • {video.publishedAt} */}
                    1.2万次观看 • 2小时前
                </p>
            </div>
        </div>
    );
};

// ==========================================
// 3. 主页面：视频详情页 (VideoDetailPage)
// ==========================================

const mockRelatedVideos: RelatedVideo[] = [
    { id: "v1", title: "2026年最好用的10款前端开发工具推荐，效率翻倍！", channelName: "极客风向标", views: "14万次观看", publishedAt: "2天前", duration: "12:45", thumbnail: "https://images.pexels.com/photos/1181671/pexels-photo-1181671.jpeg?auto=compress&cs=tinysrgb&w=600" },
    { id: "v2", title: "React 19 核心新特性深度解析，告别繁琐的 Hooks？", channelName: "Web dev simplified", views: "32万次观看", publishedAt: "1周前", duration: "24:10", thumbnail: "https://images.pexels.com/photos/11035471/pexels-photo-11035471.jpeg?auto=compress&cs=tinysrgb&w=600" },
    { id: "v3", title: "如何打造令人惊叹的高级感 UI 交互？", channelName: "DesignCourse", views: "8.9万次观看", publishedAt: "3天前", duration: "08:15", thumbnail: "https://images.pexels.com/photos/196645/pexels-photo-196645.jpeg?auto=compress&cs=tinysrgb&w=600" },
    { id: "v4", title: "沉浸式桌面搭建 Vlog：打造完美的编码环境", channelName: "生活与代码", views: "56万次观看", publishedAt: "1个月前", duration: "15:20", thumbnail: "https://images.pexels.com/photos/1779487/pexels-photo-1779487.jpeg?auto=compress&cs=tinysrgb&w=600" },
];

interface VideoDetailPageProps {
    video: Video;
    isSubscribed: boolean;
    liked: boolean;
    disLiked: boolean;
    likeCount: number;
    initialIsCollect: boolean;
}


export default function VideoDetailPage({ video, isSubscribed, liked, disLiked, likeCount, initialIsCollect }: VideoDetailPageProps) {
    const [isDescriptionExpanded, setIsDescriptionExpanded] = useState(false);
    const [subscribed, setSubscribed] = useState(isSubscribed);
    const mockActors = [
        {
            id: 1,
            name: "约翰·多伊",
            description: "资深前端架构师",
            avatar: "https://i.pravatar.cc/150?img=33",
            photo9x16: "http://localhost/storage/images/actors/licensed-image.jpeg",
            url: "/actors/2" // Laravel Inertia 常用的路由写法，或者直接写 "/actors/1"
        },
        {
            id: 2,
            name: "简·史密斯",
            description: "UI/UX 设计专家",
            avatar: "https://i.pravatar.cc/150?img=47",
            photo9x16: "https://images.pexels.com/photos/415829/pexels-photo-415829.jpeg?auto=compress&cs=tinysrgb&w=600&h=1066",
            url: "/actors/2"
        },
        {
            id: 3,
            name: "简·史密斯",
            description: "UI/UX 设计专家",
            avatar: "https://i.pravatar.cc/150?img=47",
            photo9x16: "https://images.pexels.com/photos/415829/pexels-photo-415829.jpeg?auto=compress&cs=tinysrgb&w=600&h=1066",
            url: "/actors/2"
        },
        {
            id: 4,
            name: "简·史密斯",
            description: "UI/UX 设计专家",
            avatar: "https://i.pravatar.cc/150?img=47",
            photo9x16: "https://images.pexels.com/photos/415829/pexels-photo-415829.jpeg?auto=compress&cs=tinysrgb&w=600&h=1066",
            url: "/actors/2"
        },
        {
            id: 5,
            name: "简·史密斯",
            description: "UI/UX 设计专家",
            avatar: "https://i.pravatar.cc/150?img=47",
            photo9x16: "https://images.pexels.com/photos/415829/pexels-photo-415829.jpeg?auto=compress&cs=tinysrgb&w=600&h=1066",
            url: "/actors/2"
        },
    ];

    const mockPreviewImages = [
        "https://images.pexels.com/photos/3183150/pexels-photo-3183150.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1",
        "https://images.pexels.com/photos/3182773/pexels-photo-3182773.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1",
        "https://images.pexels.com/photos/3183197/pexels-photo-3183197.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1",
        "https://images.pexels.com/photos/1181467/pexels-photo-1181467.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1",
        "https://images.pexels.com/photos/3182773/pexels-photo-3182773.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1",
        "https://images.pexels.com/photos/3183197/pexels-photo-3183197.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1",
        "https://images.pexels.com/photos/1181467/pexels-photo-1181467.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1",
    ];

    return (
        // 外层容器：限定最大宽度，水平居中
        <div className="w-full mx-auto p-4 md:p-6 lg:p-8 bg-background min-h-screen">

            {/* 核心布局：大屏幕左右分栏，小屏幕单列 */}
            <div className="flex flex-col lg:flex-row gap-6 lg:gap-8">

                {/* ================================== */}
                {/* 左侧：主内容区 (占据大部分空间) */}
                {/* ================================== */}
                <div className="flex-1 min-w-0">

                    {/* 1. 播放器容器 (16:9 占位) */}
                    <div className="w-full aspect-video bg-black rounded-xl sm:rounded-2xl overflow-hidden shadow-sm flex items-center justify-center">
                        {/* 真实项目中这里会放 <video> 或 iframe */}
                        <span className="text-white/50 font-medium">Video Player Placeholder</span>
                    </div>
                    {/* 2. 视频标题 */}
                    <Deferred data="video" fallback={
                        <>
                            {/* <div className="w-full flex items-center justify-start">
                                <Skeleton className="h-4 w-[90%] bg-slate-100" />
                            </div> */}
                            <h1 className="text-xl sm:text-2xl font-bold text-foreground mt-4 line-clamp-2">
                                <Skeleton className="h-8 w-[90%]" />
                            </h1>
                        </>
                    }>
                        <h1 className="text-xl sm:text-2xl font-bold text-foreground mt-4 line-clamp-2">
                            {video?.name}
                        </h1>
                    </Deferred>



                    {/* 3. 操作与信息栏 (响应式 Flex) */}
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mt-3">

                        {/* 频道信息区 */}
                        <div className="flex items-center gap-3">
                            <Deferred data="video" fallback={
                                <>
                                    <Skeleton className="w-10 h-10 rounded-full" />

                                    <div className="flex flex-col mr-2">
                                        <div className="flex items-center gap-1 cursor-pointer">
                                            {/* <span className="font-semibold text-foreground text-sm sm:text-base">编码助手实验室</span> */}
                                            <Skeleton className="h-3 w-30" />
                                            <CheckCircle2 className="w-3.5 h-3.5 text-muted-foreground" />
                                        </div>
                                        {/* <span className="text-xs text-muted-foreground">125万 位订阅者</span> */}
                                        <Skeleton className="h-4 w-30" />
                                    </div>
                                </>
                            }>
                                <Avatar className="w-10 h-10 cursor-pointer">
                                    <AvatarImage src={video?.channel.avatar} alt={video?.channel.name} />
                                    <AvatarFallback>{video?.channel.name}</AvatarFallback>
                                </Avatar>

                                <div className="flex flex-col mr-2">
                                    <div className="flex items-center gap-1 cursor-pointer">
                                        <span className="font-semibold text-foreground text-sm sm:text-base">{video?.channel.name}</span>
                                        <CheckCircle2 className="w-3.5 h-3.5 text-muted-foreground" />
                                    </div>
                                    <span className="text-xs text-muted-foreground">125万 位订阅者</span>
                                </div>
                            </Deferred>
                            {/* 订阅按钮 */}
                            <SubscribeButton
                                subscribed={subscribed}
                                channelId={video?.channel.id}
                                setSubscribed={setSubscribed}
                                channelSlug={video?.channel.slug}

                            />
                        </div>

                        {/* 互动按钮区：YouTube 风格药丸按钮组 */}
                        <div className="flex items-center gap-2 pb-1 sm:pb-0">

                            {/* 点赞/踩 组合按钮 */}
                            <LikeAndDislikeButton
                                videoId={video?.id}
                                slug={video?.slug}
                                initialLiked={liked}
                                initialDisliked={disLiked}
                                initialLikeCount={likeCount}
                            />

                            {/* 分享按钮 */}
                            {/* <Button variant="secondary" className="rounded-full px-4 h-9 shadow-none hover:bg-muted-foreground/10 gap-2">
                                <Share2 className="w-4 h-4" />
                                <span className="text-sm font-medium hidden sm:inline">分享</span>
                            </Button> */}

                            {/* 下载按钮 */}
                            <Button variant="secondary" className="rounded-full px-4 h-9 shadow-none hover:bg-muted-foreground/10 gap-2 hidden md:flex">
                                <Download className="w-4 h-4" />
                                <span className="text-sm font-medium">下载</span>
                            </Button>

                            {/* 保存到书签 */}
                            <Save videoId={video?.id}
                                slug={video?.slug}
                                initialIsCollect={initialIsCollect}
                            />

                            {/* TODO:如何实现根据屏幕宽度自动隐藏按钮到 moreOption 中 */}
                            {/* 更多按钮 */}
                            <MoreOption />
                        </div>
                    </div>

                    <div className="mt-2">
                        <ActorFullBody actors={mockActors} />
                    </div>

                    <VideoPreviews images={mockPreviewImages} />

                    {/* 4. 视频描述区 (可展开折叠的灰色色块) */}
                    <div
                        className={`mt-4 bg-muted hover:bg-muted/80 transition-colors p-3 sm:p-4 rounded-xl cursor-pointer text-sm text-foreground`}
                        onClick={() => setIsDescriptionExpanded(!isDescriptionExpanded)}
                    >
                        <div className="font-semibold mb-1">
                            85万次观看 • 2026年6月29日
                        </div>
                        <p className={`whitespace-pre-line ${isDescriptionExpanded ? '' : 'line-clamp-2'}`}>
                            欢迎来到本期教程！今天我们将利用现代前端技术栈（React, Tailwind CSS, shadcn/ui）从零开始复刻大厂级别的 UI 设计。
                            {"\n\n"}
                            在这个视频中，你将学到：
                            1. 如何完美实现无阴影扁平化设计
                            2. 高级彩色光晕悬浮动画的制作技巧
                            3. 复杂的响应式左右分栏布局
                            {"\n\n"}
                            如果你喜欢这个视频，请不要忘记点赞和订阅！
                        </p>
                        <Button variant="link" className="p-0 h-auto mt-2 text-foreground font-semibold">
                            {isDescriptionExpanded ? "收起" : "展开"}
                        </Button>
                    </div>

                    {/* 5. 评论区 (简易占位) */}
                    <div className="mt-6 mb-10">
                        <h3 className="text-xl font-bold text-foreground mb-4">1,452 条评论</h3>
                        <div className="flex gap-3 mb-6">
                            <Avatar className="w-10 h-10">
                                <AvatarImage src="https://i.pravatar.cc/150?img=11" />
                                <AvatarFallback>Me</AvatarFallback>
                            </Avatar>
                            <div className="flex-1">
                                <input
                                    type="text"
                                    placeholder="添加评论..."
                                    className="w-full bg-transparent border-b border-border pb-1 focus:outline-none focus:border-foreground text-sm transition-colors"
                                />
                            </div>
                        </div>
                        {/* 这里可以继续循环渲染具体的评论条目 */}
                    </div>

                </div>


                {/* ================================== */}
                {/* 右侧：侧边推荐栏 (固定宽度) */}
                {/* ================================== */}
                {/* lg:w-[350px] xl:w-[400px] 保证大屏幕下比例协调，shrink-0 防止被左侧挤压 */}
                <div className="w-full lg:w-[350px] xl:w-[400px] shrink-0 flex flex-col gap-2">

                    {/* YouTube 侧边栏也有分类胶囊 */}
                    <div className="flex items-center gap-2 overflow-x-auto pb-3 scrollbar-none snap-x mb-2">
                        {["全部", "来自 编码助手实验室", "为您推荐", "近期上传"].map((cat, i) => (
                            <button
                                key={cat}
                                className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-all shrink-0 snap-center ${i === 0
                                    ? 'bg-foreground text-background shadow-sm'
                                    : 'bg-muted hover:bg-muted/80 text-foreground'
                                    }`}
                            >
                                {cat}
                            </button>
                        ))}
                    </div>

                    {/* 循环渲染横向推荐卡片 */}
                    <div className="flex flex-col pr-1">
                        {/* {mockRelatedVideos.map(video => (
                            <RelatedVideoCard key={video.id} video={video} />
                        ))} */}
                        {/* 凑数的额外视频数据 */}
                        {/* {mockRelatedVideos.map(video => (
                            <RelatedVideoCard key={video.id + "copy"} video={{ ...video, id: video.id + "copy" }} />
                        ))} */}
                    </div>

                </div>

            </div>
        </div>
    );
}
