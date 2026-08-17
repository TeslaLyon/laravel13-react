import React, { useState } from 'react';
import {
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Pencil,
    Copy,
    Plus,
    Sparkles,
    Tag as TagIcon,
    FolderPlus,
    UserPlus
} from "lucide-react";
import { toast } from 'sonner';
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Video } from "@/types/video";
import { Deferred, Link } from "@inertiajs/react";
import { Skeleton } from "@/components/ui/skeleton";
import { LikeAndDislikeButton } from '@/components/video/LikeAndDislikeButton';
import { SubscribeButton } from '@/components/video/SubscribeButton';
import { MoreOption } from '@/components/video/MoreOption';
import { Save } from '@/components/video/Save';
import { ActorSquare } from '@/components/video/ActorSquare';
import { VideoPreviews } from '@/components/video/VideoPreviews';
import { VideoSkeleton } from "@/components/video/VideoSkeleton";
import { VideoCard } from "@/components/video/Card";
import { show } from '@/routes/videos';
import { DownloadDialog } from '@/components/video/DownloadDialog';
import VideoCorrectionDialog from '@/components/video/Correction';
import { Actor } from '@/types/actor';
import { Category } from '@/types/video';
import { Tag } from '@/types/video';
import { SubtitleDialog } from '@/components/video/SubtitleDialog';
import { VideoHeader } from '@/components/video/VideoHeader';

interface VideoDetailPageProps {
    video: Video;
    isSubscribed: boolean;
    liked: boolean;
    disLiked: boolean;
    likeCount: number;
    initialIsCollect: boolean;
    recommendVideos: Video[];
    actors: Actor[];
    categories: Category[];
    tags: Tag[];
}

export default function VideoDetailPage({ video, isSubscribed, liked, disLiked, likeCount, initialIsCollect, recommendVideos }: VideoDetailPageProps) {
    const [isDescriptionExpanded, setIsDescriptionExpanded] = useState(false);
    const [subscribed, setSubscribed] = useState(isSubscribed);
    const [isTagsExpanded, setIsTagsExpanded] = useState(false);

    const VISIBLE_TAGS_COUNT = 5;
    const mockPreviewImages = [
        "https://images.pexels.com/photos/3183150/pexels-photo-3183150.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1",
        "https://images.pexels.com/photos/3182773/pexels-photo-3182773.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=1"
    ];

    const getResolutionColor = (res: string) => {
        const resLower = res.toLowerCase();
        if (resLower.includes('2160') || resLower.includes('4k') || resLower.includes('8k')) {
            return 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300 border-purple-200 dark:border-purple-800';
        }
        if (resLower.includes('1080')) {
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 border-blue-200 dark:border-blue-800';
        }
        if (resLower.includes('720')) {
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
        }
        return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700';
    };

    const handleCopyCode = () => {
        const codeToCopy = video?.video_code || "BrazzersExxtra.19.05.21.Demi.Sutra.A.Family.Affair.The.Reunion";
        navigator.clipboard.writeText(codeToCopy).then(() => {
            toast.success("视频编号已复制到剪贴板！");
        }).catch(() => {
            toast.error("复制失败，请尝试手动复制。");
        });
    };


    return (
        <div className="w-full mx-auto pb-8 sm:py-6 xl:py-8 bg-background min-h-screen">
            <div className="flex flex-col xl:flex-row gap-0 sm:gap-6 xl:gap-8 px-0 sm:px-4 md:px-6 xl:px-8">

                {/* 左侧：主内容区 */}
                <div className="flex-1 min-w-0">
                    <Deferred
                        data="video"
                        fallback={
                            <div className="w-full aspect-video sm:rounded-2xl overflow-hidden mb-4">
                                {/* 16:9 比例的封面视频大图骨架屏 */}
                                <Skeleton className="w-full h-full" />
                            </div>
                        }
                    >
                        {/* 2. 数据加载就绪后渲染组件 */}
                        <VideoHeader
                            imgMetaList={video?.video_detail?.list_img_large_meta}
                            videoUrl={video?.video_detail?.video_urls}
                            title={video?.name}
                        />
                    </Deferred>

                    <div className="px-4 sm:px-0">
                        {/* 视频标题 */}
                        <div className="mt-4">
                            <Deferred data="video" fallback={
                                <div className="flex flex-col gap-2">
                                    <Skeleton className="h-8 w-[90%]" />
                                    <Skeleton className="h-5 w-[30%]" />
                                </div>
                            }>
                                <div className="flex flex-col gap-1.5">
                                    {/* 主标题 */}
                                    <h1 className="text-xl sm:text-2xl font-bold text-foreground leading-snug sm:leading-snug line-clamp-2">
                                        {(video?.max_quality || video?.is_vr) && (
                                            <span className="inline-flex items-center gap-1.5 align-middle mr-2 -mt-1">
                                                {video?.has_zh_subtitles && (
                                                    <span className="px-1.5 py-0.5 text-[10px] sm:text-xs font-bold rounded border shadow-sm tracking-wider bg-gradient-to-r from-orange-500 to-amber-500 text-white border-orange-600/50">
                                                        中文字幕
                                                    </span>
                                                )}
                                                {video?.max_quality && (
                                                    <span className={`px-1.5 py-0.5 text-[10px] sm:text-xs font-bold rounded border shadow-sm tracking-wider ${getResolutionColor(video.max_quality)}`}>
                                                        {video.max_quality.toUpperCase()}
                                                    </span>
                                                )}
                                                {video?.is_vr && (
                                                    <span className="px-1.5 py-0.5 text-[10px] sm:text-xs font-bold rounded border shadow-sm tracking-wider bg-teal-100 text-teal-700 border-teal-200 dark:bg-teal-900/40 dark:text-teal-300 dark:border-teal-800">
                                                        VR
                                                    </span>
                                                )}
                                            </span>
                                        )}
                                        {video?.name}
                                    </h1>

                                    {/* 中文名及修正按钮布局 */}
                                    <div className="flex items-center gap-2">
                                        {video?.name_zh ? (
                                            <span className="text-sm sm:text-base text-muted-foreground font-medium">
                                                {video.name_zh}
                                            </span>
                                        ) : (
                                            <span className="text-sm text-muted-foreground/60 italic">
                                                暂无中文译名
                                            </span>
                                        )}

                                        {/* 修正按钮 - 始终展示 */}
                                        <VideoCorrectionDialog
                                            video={video}
                                            type="name_zh"
                                            trigger={
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-6 w-6 text-muted-foreground hover:text-primary transition-colors shrink-0"
                                                    title="补充或修正中文名"
                                                >
                                                    <Pencil className="w-3.5 h-3.5" />
                                                </Button>
                                            }
                                        />
                                    </div>
                                </div>
                            </Deferred>
                        </div>

                        {/* 频道信息与交互操作组 */}
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mt-3">
                            <div className="flex items-center gap-3">
                                <Deferred data="video" fallback={
                                    <>
                                        <Skeleton className="w-10 h-10 rounded-full" />
                                        <div className="flex flex-col mr-2">
                                            <Skeleton className="h-3 w-32" />
                                            <Skeleton className="h-4 w-24 mt-1" />
                                        </div>
                                    </>
                                }>
                                    <Link href={`/channels/${video?.channel.id}`} className="shrink-0">
                                        <Avatar className="w-10 h-10 cursor-pointer">
                                            <AvatarImage src={video?.channel.avatar} alt={video?.channel.name} />
                                            <AvatarFallback>{video?.channel.name}</AvatarFallback>
                                        </Avatar>
                                    </Link>

                                    <div className="flex flex-col mr-2">
                                        <Link href={`/channels/${video?.channel.id}`} className="flex items-center gap-1 cursor-pointer group">
                                            <span className="font-semibold text-foreground text-sm sm:text-base group-hover:text-primary transition-colors">{video?.channel.name}</span>
                                            <CheckCircle2 className="w-3.5 h-3.5 text-muted-foreground" />
                                        </Link>
                                        <span className="text-xs text-muted-foreground">125万 位订阅者</span>
                                    </div>
                                </Deferred>

                                <SubscribeButton
                                    subscribed={subscribed}
                                    channelId={video?.channel.id}
                                    setSubscribed={setSubscribed}
                                    channelSlug={video?.channel.slug}
                                />
                            </div>

                            <div className="flex items-center gap-2 overflow-x-auto flex-nowrap pb-2 sm:pb-0 scrollbar-none w-full sm:w-auto -mx-4 px-4 sm:mx-0 sm:px-0">
                                <div className="shrink-0">
                                    <LikeAndDislikeButton videoId={video?.id} slug={video?.slug} initialLiked={liked} initialDisliked={disLiked} initialLikeCount={likeCount} />
                                </div>
                                <div className="shrink-0"><SubtitleDialog video={video} /></div>
                                <div className="shrink-0">
                                    <Deferred data="video" fallback={
                                        <>
                                            <Skeleton className="h-9 w-[88px] rounded-full shrink-0" />
                                        </>
                                    }>
                                        <DownloadDialog video={video} />
                                    </Deferred>
                                </div>
                                <div className="shrink-0"><Save videoId={video?.id} slug={video?.slug} initialIsCollect={initialIsCollect} /></div>
                                <div className="shrink-0"><MoreOption video={video} /></div>
                            </div>
                        </div>

                        {/* 简介区域 */}
                        <div className="mt-4 bg-muted hover:bg-muted/80 transition-colors p-3 sm:p-4 rounded-xl text-sm text-foreground">
                            <div className="flex flex-col gap-2 mb-3">
                                <div className="text-sm font-medium text-muted-foreground">
                                    85万次观看 • {video?.release_at || "2026年6月29日"}
                                </div>
                                <div className="flex items-center gap-2 text-sm text-muted-foreground font-medium">
                                    <span className="shrink-0">视频编号：</span>
                                    <span className="bg-background/80 px-2 py-0.5 rounded border border-border/50 text-foreground select-all break-all">
                                        {video?.video_code || "BrazzersExxtra.19.05.21.Demi.Sutra.A.Family.Affair.The.Reunion"}
                                    </span>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        onClick={handleCopyCode}
                                        className="h-7 w-7 shrink-0 text-muted-foreground hover:text-foreground"
                                        title="复制编号"
                                    >
                                        <Copy className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>

                            <p className={`whitespace-pre-line mt-1 ${isDescriptionExpanded ? '' : 'line-clamp-2'}`}>
                                欢迎来到本期教程！今天我们将利用现代前端技术栈（React, Tailwind CSS, shadcn/ui）从零开始复刻大厂级别的 UI 设计。
                                {"\n\n"}
                                如果你喜欢这个视频，请不要忘记点赞和订阅！
                            </p>
                            <Button
                                variant="link"
                                className="p-0 h-auto mt-2 text-foreground font-semibold"
                                onClick={() => setIsDescriptionExpanded(!isDescriptionExpanded)}
                            >
                                {isDescriptionExpanded ? "收起" : "展开"}
                            </Button>
                        </div>

                        {/* ===================== 1. 演员模块 ===================== */}
                        <div className="mt-6">
                            <div className="flex items-center gap-2 mb-3">
                                <h3 className="text-base sm:text-lg font-bold text-foreground">演出人员</h3>
                                <VideoCorrectionDialog
                                    video={video}
                                    type="actors"
                                    trigger={
                                        <Button variant="ghost" size="icon" className="h-6 w-6 text-muted-foreground hover:text-primary transition-colors" title="修正演员资料">
                                            <Pencil className="w-3.5 h-3.5" />
                                        </Button>
                                    }
                                />
                            </div>
                            <Deferred data="video" fallback={
                                <div className='w-full'>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 sm:gap-4">
                                        {Array.from({ length: 3 }).map((_, index) => (
                                            <div key={index} className="flex items-center gap-4 p-2.5 rounded-2xl w-full">
                                                <Skeleton className="shrink-0 w-16 h-16 sm:w-[72px] sm:h-[72px] rounded-full" />
                                                <div className="flex-1 min-w-0 flex flex-col justify-center gap-2.5 py-1">
                                                    <Skeleton className="h-4 sm:h-5 w-24 sm:w-32" />
                                                    <Skeleton className="h-3 w-16 sm:w-20" />
                                                </div>
                                                <Skeleton className="shrink-0 w-9 h-9 rounded-full" />
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            }>
                                {/* 【空状态判断与引导】 */}
                                {video?.actors && video.actors.length > 0 ? (
                                    <ActorSquare actors={video.actors} />
                                ) : (
                                    <div className="flex flex-col sm:flex-row items-end justify-start gap-4 p-4 rounded-2xl border border-dashed border-border/80 bg-muted/20 hover:bg-muted/40 transition-colors">
                                        <div className="flex items-center gap-3">
                                            <div className="w-10 h-10 rounded-full bg-orange-500/10 text-orange-600 dark:text-orange-400 flex items-center justify-center shrink-0">
                                                <UserPlus className="w-5 h-5" />
                                            </div>
                                            <div className="flex flex-col">
                                                <span className="text-sm font-semibold text-foreground">暂无演职人员信息</span>
                                                <span className="text-xs text-muted-foreground">知道谁出演了这部作品？参与补充可获得贡献积分！</span>
                                            </div>
                                        </div>
                                        <VideoCorrectionDialog
                                            video={video}
                                            type="actors"
                                            trigger={
                                                <Button size="sm" variant="outline" className="gap-1.5 text-xs h-8 border-orange-500/30 hover:border-orange-500/60 hover:bg-orange-500/10 text-orange-600 dark:text-orange-400 shrink-0">
                                                    <Sparkles className="w-3.5 h-3.5" />
                                                    补充演员
                                                </Button>
                                            }
                                        />
                                    </div>
                                )}
                            </Deferred>
                        </div>

                        {/* 预览图 */}
                        <VideoPreviews images={mockPreviewImages} />

                        {/* ===================== 2. 分类模块 ===================== */}
                        <div className="mt-6">
                            <div className="flex items-center gap-2 mb-3">
                                <h3 className="text-base sm:text-lg font-bold text-foreground">视频分类</h3>
                                <VideoCorrectionDialog
                                    video={video}
                                    type="categories"
                                    trigger={
                                        <Button variant="ghost" size="icon" className="h-6 w-6 text-muted-foreground hover:text-primary transition-colors" title="修正分类">
                                            <Pencil className="w-3.5 h-3.5" />
                                        </Button>
                                    }
                                />
                            </div>
                            <Deferred data="video" fallback={
                                <div className="flex items-center gap-2">
                                    <Skeleton className="h-8 w-16 rounded-xl" />
                                    <Skeleton className="h-8 w-20 rounded-xl" />
                                </div>
                            }>
                                {/* 【空状态判断与引导】 */}
                                {video?.categories && video.categories.length > 0 ? (
                                    <div className="flex items-center gap-2 flex-wrap">
                                        {video.categories.map(category => (
                                            <Link
                                                key={category.id}
                                                href={`/categories/${category.id}`}
                                                className="px-4 py-1.5 bg-primary/5 hover:bg-primary/10 text-primary border border-primary/20 transition-colors rounded-xl text-sm font-semibold shadow-sm"
                                            >
                                                {category.name}
                                            </Link>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="flex items-center justify-start gap-4 p-3.5 rounded-xl border border-dashed border-border/80 bg-muted/20 hover:bg-muted/40 transition-colors">
                                        <div className="flex items-center gap-2.5">
                                            <FolderPlus className="w-4 h-4 text-muted-foreground shrink-0" />
                                            <span className="text-xs sm:text-sm text-muted-foreground">暂无视频分类，协助添加获得社区贡献奖励</span>
                                        </div>
                                        <VideoCorrectionDialog
                                            video={video}
                                            type="categories"
                                            trigger={
                                                <Button size="sm" variant="ghost" className="h-7 text-xs text-primary hover:text-primary/80 gap-1 px-2">
                                                    <Plus className="w-3.5 h-3.5" />
                                                    添加分类
                                                </Button>
                                            }
                                        />
                                    </div>
                                )}
                            </Deferred>
                        </div>

                        {/* ===================== 3. 标签模块 ===================== */}
                        <div className="mt-6">
                            <div className="flex items-center gap-2 mb-3">
                                <h3 className="text-base sm:text-lg font-bold text-foreground">热门标签</h3>
                                <VideoCorrectionDialog
                                    video={video}
                                    type="tags"
                                    trigger={
                                        <Button variant="ghost" size="icon" className="h-6 w-6 text-muted-foreground hover:text-primary transition-colors" title="修正标签">
                                            <Pencil className="w-3.5 h-3.5" />
                                        </Button>
                                    }
                                />
                            </div>
                            <Deferred data="video" fallback={
                                <div className="flex items-center gap-2">
                                    <Skeleton className="h-7 w-14 rounded-md" />
                                    <Skeleton className="h-7 w-20 rounded-md" />
                                </div>
                            }>
                                {/* 【空状态判断与引导】 */}
                                {video?.tags && video.tags.length > 0 ? (
                                    <div className="flex flex-wrap gap-2 items-center">
                                        {(isTagsExpanded ? video.tags : video.tags.slice(0, VISIBLE_TAGS_COUNT)).map(tag => (
                                            <Link
                                                key={tag.id}
                                                href={`/tags/${tag.id}`}
                                                className="px-3 py-1 bg-secondary/60 hover:bg-secondary text-secondary-foreground transition-colors cursor-pointer text-xs font-medium rounded-lg"
                                            >
                                                #{tag.name}
                                            </Link>
                                        ))}

                                        {video.tags.length > VISIBLE_TAGS_COUNT && (
                                            <button
                                                onClick={() => setIsTagsExpanded(!isTagsExpanded)}
                                                className="flex items-center gap-1 px-3 py-1 bg-transparent hover:bg-muted text-xs text-muted-foreground hover:text-foreground font-semibold rounded-lg transition-colors border border-transparent hover:border-border"
                                            >
                                                {isTagsExpanded ? (
                                                    <>收起 <ChevronUp className="w-3 h-3" /></>
                                                ) : (
                                                    <>展开全部 <ChevronDown className="w-3 h-3" /></>
                                                )}
                                            </button>
                                        )}
                                    </div>
                                ) : (
                                    <div className="flex items-center justify-start gap-4 p-3.5 rounded-xl border border-dashed border-border/80 bg-muted/20 hover:bg-muted/40 transition-colors">
                                        <div className="flex items-center gap-2.5">
                                            <TagIcon className="w-4 h-4 text-muted-foreground shrink-0" />
                                            <span className="text-xs sm:text-sm text-muted-foreground">还没有关联标签，点击推荐合适的热门标签</span>
                                        </div>
                                        <VideoCorrectionDialog
                                            video={video}
                                            type="tags"
                                            trigger={
                                                <Button size="sm" variant="ghost" className="h-7 text-xs text-primary hover:text-primary/80 gap-1 px-2">
                                                    <Plus className="w-3.5 h-3.5" />
                                                    添加标签
                                                </Button>
                                            }
                                        />
                                    </div>
                                )}
                            </Deferred>
                        </div>
                    </div>
                </div>

                {/* 右侧：侧边推荐栏 */}
                <div className="w-full xl:w-[350px] 2xl:w-[400px] shrink-0 flex flex-col gap-2 px-4 sm:px-0 mt-6 xl:mt-0">
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
