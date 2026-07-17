import React, { useState } from 'react';
import {
    CheckCircle2,
} from "lucide-react";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Video } from "@/types/video";
import { Deferred, Link } from "@inertiajs/react";
import { Skeleton } from "@/components/ui/skeleton";
import { LikeAndDislikeButton } from '@/components/video/LikeAndDislikeButton';
import { SubscribeButton } from '@/components/video/SubscribeButton';
import { MoreOption } from '@/components/video/MoreOption';
import { Save } from '@/components/video/Save';
import { ActorFullBody } from '@/components/video/ActorFullBody';
import { VideoPreviews } from '@/components/video/VideoPreviews';
import { VideoSkeleton } from "@/components/video/VideoSkeleton";
import { VideoCard } from "@/components/video/Card";
import { show } from '@/routes/videos';
import { DownloadDialog } from '@/components/video/DownloadDialog';

// TODO:修正功能，提示：采纳后会给予贡献奖励

interface VideoDetailPageProps {
    video: Video;
    isSubscribed: boolean;
    liked: boolean;
    disLiked: boolean;
    likeCount: number;
    initialIsCollect: boolean;
    recommendVideos: Video[]
}

export default function VideoDetailPage({ video, isSubscribed, liked, disLiked, likeCount, initialIsCollect, recommendVideos }: VideoDetailPageProps) {
    const [isDescriptionExpanded, setIsDescriptionExpanded] = useState(false);
    const [subscribed, setSubscribed] = useState(isSubscribed);
    const mockActors = [
        {
            id: 1,
            name: "约翰·多伊",
            description: "资深前端架构师",
            avatar: "https://i.pravatar.cc/150?img=33",
            photo9x16: "http://localhost/storage/images/actors/licensed-image.jpeg",
            url: "/actors/2"
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

    const mockDownloads = [
        {
            id: 1,
            type: 'magnet',
            title: '1080P 高清完整版',
            description: 'MP4 格式 • 2.4 GB',
            link: 'magnet:?xt=urn:btih:example123'
        },
        {
            id: 2,
            type: 'store',
            title: '官方无删减蓝光典藏版',
            description: '包含独家幕后花絮与设定集',
            price: '99 金币',
            link: '/store/items/1'
        }
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
        // 优化：手机端取消外层 padding 允许播放器贴边，大屏幕限制最大宽度
        <div className="w-full max-w-[1800px] mx-auto pb-8 sm:py-6 xl:py-8 bg-background min-h-screen">

            {/* 核心布局：在 xl 时才切分为左右两列，xl 之下为上下结构 */}
            <div className="flex flex-col xl:flex-row gap-0 sm:gap-6 xl:gap-8 px-0 sm:px-4 md:px-6 xl:px-8">

                {/* ================================== */}
                {/* 左侧：主内容区 (占据大部分空间) */}
                {/* ================================== */}
                <div className="flex-1 min-w-0">

                    {/* 1. 播放器容器 (手机端无圆角贴边，平板及以上加圆角) */}
                    <div className="w-full aspect-video bg-black sm:rounded-2xl overflow-hidden shadow-sm flex items-center justify-center">
                        <span className="text-white/50 font-medium">Video Player Placeholder</span>
                    </div>

                    {/* 手机端下方内容容器：为了弥补外层去掉的 px-0，在此处将边距加回来 */}
                    <div className="px-4 sm:px-0">

                        {/* 2. 视频标题 */}
                        <Deferred data="video" fallback={
                            <>
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
                                                <Skeleton className="h-3 w-32" />
                                                <CheckCircle2 className="w-3.5 h-3.5 text-muted-foreground" />
                                            </div>
                                            <Skeleton className="h-4 w-24 mt-1" />
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

                            {/* 互动按钮区：使用 overflow-x-auto 支持手机端横向滑动 */}
                            <div className="flex items-center gap-2 overflow-x-auto pb-2 sm:pb-0 scrollbar-none w-full sm:w-auto">
                                <LikeAndDislikeButton
                                    videoId={video?.id}
                                    slug={video?.slug}
                                    initialLiked={liked}
                                    initialDisliked={disLiked}
                                    initialLikeCount={likeCount}
                                />

                                <div className="shrink-0">
                                    <DownloadDialog />
                                </div>

                                <div className="shrink-0">
                                    <Save videoId={video?.id}
                                        slug={video?.slug}
                                        initialIsCollect={initialIsCollect}
                                    />
                                </div>

                                <div className="shrink-0">
                                    <MoreOption />
                                </div>
                            </div>
                        </div>

                        <div className="mt-2">
                            <ActorFullBody actors={mockActors} />
                        </div>

                        <VideoPreviews images={mockPreviewImages} />

                        {/* 4. 视频描述区 */}
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

                        {/* 5. 评论区 (完整保留) */}
                        {/* TODO:// 待完善,不知道采用什么样式的 */}
                        {/* <div className="mt-6 mb-10">
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
                        </div> */}

                    </div>
                </div>

                {/* ================================== */}
                {/* 右侧：侧边推荐栏 (固定宽度) */}
                {/* ================================== */}
                {/* xl 级别及以上才固定为 350px/400px，下方加了补齐的 px-4 以适应手机端 */}
                <div className="w-full xl:w-[350px] 2xl:w-[400px] shrink-0 flex flex-col gap-2 px-4 sm:px-0 mt-6 xl:mt-0">

                    {/* YouTube 侧边栏的分类胶囊 */}
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

                    {/* 循环渲染推荐卡片：加入 Grid，在平板上呈现并排的多列，xl 时恢复 flex 垂直单列 */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:flex xl:flex-col gap-y-4 gap-x-4">
                        <Deferred data="recommendVideos" fallback={
                            Array.from({ length: 10 }).map((_, index) => (
                                <VideoSkeleton key={index} />
                            ))
                        }>
                            {recommendVideos?.map((video: any) => (
                                <Link href={show({ video: video.id, slug: video.slug })} key={video.id}>
                                    <VideoCard key={video.id} video={video} />
                                </Link>
                            ))}
                        </Deferred>
                    </div>

                </div>

            </div>
        </div>
    );
}
