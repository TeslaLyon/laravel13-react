import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Card, CardContent } from "@/components/ui/card";
import { Bell, CheckCircle2 } from "lucide-react";
import { show } from '@/actions/App/Http/Controllers/VideoController';

// 1. 定义视频数据的类型接口
export interface Video {
    id: number;
    title: string;
    slug: string;
    thumbnail_url: string;
    views: string;
    published_at: string;
}

// 2. 定义片商数据的类型接口
export interface Studio {
    id: number;
    name: string;
    avatar_url: string;
    banner_url: string | null; // 使用联合类型，考虑到可能没有背景图
    followers_count: string;
    handle: string;
    description: string;
}

// 3. 定义页面组件的 Props 接口
interface StudioShowProps {
    channel: Studio;
    videos: Video[];
}

export default function StudioShow({ channel, videos }: StudioShowProps) {
    return (
        <div className="min-h-screen bg-background">
            <Head title={`${channel.name} - 详情页`} />

            {/* 顶部横幅 (Banner) */}
            <div className="w-full h-40 sm:h-52 md:h-64 lg:h-80 relative overflow-hidden">
                {channel.banner_url ? (
                    <img
                        src={channel.banner_url}
                        alt={`${channel.name} banner`}
                        className="w-full h-full object-cover"
                    />
                ) : (
                    <div className="w-full h-full bg-muted"></div>
                )}
            </div>

            {/* 页面主体内容容器 */}
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                {/* 头部信息区 (Header Info) */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center py-6 gap-6">
                    <Avatar className="w-24 h-24 sm:w-32 sm:h-32 border-4 border-background shadow-sm">
                        <AvatarImage src={channel.avatar_url} alt={channel.name} />
                        <AvatarFallback className="text-3xl font-bold">{channel.name.charAt(0)}</AvatarFallback>
                    </Avatar>

                    <div className="flex-1">
                        <h1 className="text-2xl sm:text-3xl font-bold flex items-center gap-2">
                            {channel.name}
                            <CheckCircle2 className="w-5 h-5 text-muted-foreground" />
                        </h1>
                        <div className="flex items-center text-sm text-muted-foreground mt-1 gap-2">
                            <span>{channel.handle}</span>
                            <span>•</span>
                            <span>{channel.followers_count} 位订阅者</span>
                        </div>
                        <p className="text-sm text-muted-foreground mt-2 line-clamp-1 max-w-2xl">
                            {channel.description}
                        </p>
                    </div>

                    <div className="mt-4 sm:mt-0 flex items-center gap-2">
                        <Button className="rounded-full px-6 font-bold">
                            订阅
                        </Button>
                        <Button variant="outline" size="icon" className="rounded-full">
                            <Bell className="w-5 h-5" />
                        </Button>
                    </div>
                </div>

                {/* 导航标签 (Tabs) */}
                <Tabs defaultValue="videos" className="w-full mt-2">
                    <div className="border-b">
                        <TabsList className="bg-transparent h-12 p-0 flex justify-start gap-6">
                            <TabsTrigger
                                value="home"
                                className="data-[state=active]:bg-transparent data-[state=active]:shadow-none data-[state=active]:border-b-2 data-[state=active]:border-primary rounded-none text-base px-0 py-3"
                            >
                                首页
                            </TabsTrigger>
                            <TabsTrigger
                                value="videos"
                                className="data-[state=active]:bg-transparent data-[state=active]:shadow-none data-[state=active]:border-b-2 data-[state=active]:border-primary rounded-none text-base px-0 py-3"
                            >
                                视频
                            </TabsTrigger>
                            <TabsTrigger
                                value="about"
                                className="data-[state=active]:bg-transparent data-[state=active]:shadow-none data-[state=active]:border-b-2 data-[state=active]:border-primary rounded-none text-base px-0 py-3"
                            >
                                关于
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    {/* 视频列表展示区 */}
                    <TabsContent value="videos" className="pt-6 pb-12">
                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                            {videos.map((video) => (
                                <Link href={show({ video: video.id, slug: video.slug })} key={video.id} className="group">
                                    <Card className="border-0 shadow-none bg-transparent">
                                        <CardContent className="p-0">
                                            <div className="aspect-video rounded-xl overflow-hidden bg-muted">
                                                <img
                                                    src={video.thumbnail_url}
                                                    alt={video.title}
                                                    className="w-full h-full object-cover transition-transform duration-200 group-hover:scale-105"
                                                />
                                            </div>
                                            <div className="mt-3">
                                                <h3 className="font-semibold text-base line-clamp-2 leading-tight group-hover:text-primary transition-colors">
                                                    {video.title}
                                                </h3>
                                                <div className="text-sm text-muted-foreground mt-1">
                                                    {video.views} 次观看 • {video.published_at}
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </Link>
                            ))}
                        </div>
                    </TabsContent>

                    <TabsContent value="home">
                        <div className="py-8 text-center text-muted-foreground">首页内容推荐区</div>
                    </TabsContent>
                    <TabsContent value="about">
                        <div className="py-8 text-muted-foreground">{channel.description}</div>
                    </TabsContent>
                </Tabs>
            </div>
        </div>
    );
}
