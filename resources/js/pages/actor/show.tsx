import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Actor } from '@/types/actor';
import { Search, X } from 'lucide-react';
import { FollowBtn } from '@/components/actor/FollowBtn';

interface ActorShowProps {
    actor: Actor;
    initisFollowed: boolean;
}

const nicknames = ["老张", "表演艺术家", "喜剧之王"];

export default function ActorShow({ actor, initisFollowed }: ActorShowProps) {
    const [isSearchOpen, setIsSearchOpen] = useState(false);

    return (
        <div className="min-h-screen bg-background">
            <Head title={`${actor?.name} - 详情`} />

            <main className="container mx-auto pb-12">
                {actor && actor.banner && (
                    <div className="w-full px-4 pt-4 sm:px-6 lg:px-8">
                        <div
                            className="w-full aspect-video md:aspect-[21/9] lg:aspect-[3/1] rounded-2xl bg-muted bg-cover bg-center shadow-inner"
                            style={{ backgroundImage: `url(${actor?.banner})` }}
                        />
                    </div>
                )
                }

                {/* 个人信息头部 */}
                <div className="px-4 sm:px-6 lg:px-8 mt-4 md:mt-6 flex flex-col md:flex-row items-start md:items-center gap-6">
                    <Avatar className="w-24 h-24 md:w-40 md:h-40 border-4 border-background shadow-md">
                        <AvatarImage src={actor?.avatar} alt={actor?.name} />
                        <AvatarFallback className="text-3xl font-bold">{actor?.name?.charAt(0)}</AvatarFallback>
                    </Avatar>

                    <div className="flex-1 w-full">
                        <h1 className="text-2xl md:text-4xl font-extrabold text-foreground tracking-tight">{actor?.name}</h1>
                        <div className="flex flex-wrap items-center gap-2 mt-3 text-sm text-muted-foreground">
                            <span className="font-semibold text-foreground bg-muted px-2 py-0.5 rounded-md">{actor?.follow_num} 位关注者</span>
                            <div className="flex flex-wrap gap-1.5 ml-2">
                                {nicknames.map((nickname, index) => (
                                    <Badge key={index} variant="outline" className="font-normal text-muted-foreground bg-background hover:bg-muted transition-colors">
                                        {nickname}
                                    </Badge>
                                ))}
                            </div>
                        </div>
                        <p className="mt-3 text-sm text-muted-foreground/90 max-w-2xl leading-relaxed">
                            这里是演员的个人简介。热爱表演，用心感受每一个角色。
                        </p>

                        {/* 修改说明：关注按钮被移动到了这里。添加了 mt-6 保证美观的垂直间距 */}
                        {actor && actor.id && <FollowBtn actorId={actor.id} slug={actor.slug} initisFollowed={initisFollowed} />}
                    </div>
                </div>

                {/* 底部 Tab 切换区 - iOS 分段控制器风格 */}
                <div className="px-4 sm:px-6 lg:px-8 mt-4">
                    <Tabs defaultValue="home" className="w-full">
                        <div className="flex flex-wrap sm:flex-nowrap items-center justify-between gap-4 mb-8">

                            {/* 设计变更：整体变成一个带背景色的紧凑型卡槽 */}
                            <TabsList className="inline-flex h-11 items-center justify-center rounded-xl bg-muted/80 p-1 text-muted-foreground w-full sm:w-[400px]">
                                {[
                                    { value: 'home', label: '首页' },
                                    { value: 'videos', label: '作品' },
                                    { value: 'about', label: '简介' },
                                ].map((tab) => (
                                    <TabsTrigger
                                        key={tab.value}
                                        value={tab.value}
                                        // 设计变更：激活时变成卡槽内白色的浮动滑块
                                        className="inline-flex flex-1 items-center justify-center whitespace-nowrap rounded-lg px-3 py-1.5 text-sm font-medium transition-all data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm hover:text-foreground"
                                    >
                                        {tab.label}
                                    </TabsTrigger>
                                ))}
                            </TabsList>

                            {/* 交互核心：搜索区域 */}
                            <div className="flex items-center justify-end w-full sm:w-auto ml-auto">
                                <div
                                    className={`flex items-center transition-all duration-300 ease-in-out overflow-hidden ${isSearchOpen ? 'w-full sm:w-64 opacity-100 mr-2' : 'w-0 opacity-0 mr-0'
                                        }`}
                                >
                                    <input
                                        type="text"
                                        placeholder="搜索作品..."
                                        // 【核心优化】：
                                        // 1. 移除 focus:ring-2 及其相关的颜色设置。
                                        // 2. 添加 focus:border-foreground，使其在聚焦时边框变成高对比度的实心色。
                                        // 3. 改用 bg-background 或 bg-transparent，让输入框质感更好。
                                        className="w-full h-10 px-4 rounded-full border border-border bg-muted/30 text-sm text-foreground transition-shadow"
                                    />
                                </div>

                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => setIsSearchOpen(!isSearchOpen)}
                                    className="rounded-full text-muted-foreground hover:text-foreground hover:bg-muted shrink-0"
                                >
                                    {isSearchOpen ? <X className="h-5 w-5" /> : <Search className="h-5 w-5" />}
                                </Button>
                            </div>
                        </div>

                        {/* Tabs 内容区 */}
                        <div className="mt-2">
                            <TabsContent value="home" className="animate-in fade-in slide-in-from-bottom-4 duration-500">
                                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                                    <div className="aspect-video bg-muted/50 rounded-2xl border border-border flex items-center justify-center text-sm text-muted-foreground hover:bg-muted transition-colors cursor-pointer">推荐作品 1</div>
                                </div>
                            </TabsContent>
                            <TabsContent value="videos">...</TabsContent>
                            <TabsContent value="about">...</TabsContent>
                        </div>
                    </Tabs>
                </div>

                <div className="px-4 sm:px-6 lg:px-8 mt-10">
                    <Tabs defaultValue="home" className="w-full">
                        <div className="flex flex-wrap sm:flex-nowrap items-center justify-between gap-4 mb-6 border-b border-border pb-4">

                            <TabsList className="flex flex-wrap h-auto items-center justify-start gap-3 bg-transparent p-0">
                                {[
                                    { value: 'home', label: '首页' },
                                    { value: 'videos', label: '作品' },
                                    { value: 'about', label: '简介' },
                                ].map((tab) => (
                                    <TabsTrigger
                                        key={tab.value}
                                        value={tab.value}
                                        className="rounded-full border border-border px-6 py-2.5 text-sm font-semibold text-muted-foreground transition-all duration-200 hover:border-foreground/50 hover:text-foreground data-[state=active]:border-foreground data-[state=active]:bg-foreground data-[state=active]:text-background data-[state=active]:shadow-md"
                                    >
                                        {tab.label}
                                    </TabsTrigger>
                                ))}
                            </TabsList>

                            {/* 交互核心：搜索区域 */}
                            <div className="flex items-center justify-end w-full sm:w-auto ml-auto">
                                <div
                                    className={`flex items-center overflow-hidden transition-all duration-300 ease-in-out ${isSearchOpen ? 'w-full sm:w-64 opacity-100 mr-2' : 'w-0 opacity-0 mr-0'
                                        }`}
                                >
                                    <input
                                        type="text"
                                        placeholder="搜索作品..."
                                        className="w-full h-10 px-4 rounded-full border border-border bg-muted/30 text-sm text-foreground transition-shadow"
                                    />
                                </div>

                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => setIsSearchOpen(!isSearchOpen)}
                                    className="rounded-full text-muted-foreground hover:text-foreground hover:bg-muted shrink-0"
                                >
                                    {/* 根据状态切换图标，展开时显示 X 以便关闭，收起时显示放大镜 */}
                                    {isSearchOpen ? <X className="h-5 w-5" /> : <Search className="h-5 w-5" />}
                                </Button>
                            </div>
                        </div>

                        {/* Tabs 内容区 (省略了重复的代码，与之前保持一致) */}
                        <div className="mt-6">
                            <TabsContent value="home" className="animate-in fade-in slide-in-from-bottom-4 duration-500">
                                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                                    <div className="aspect-video bg-muted/50 rounded-xl border border-border flex items-center justify-center text-sm text-muted-foreground hover:bg-muted transition-colors cursor-pointer">推荐作品 1</div>
                                    <div className="aspect-video bg-muted/50 rounded-xl border border-border flex items-center justify-center text-sm text-muted-foreground hover:bg-muted transition-colors cursor-pointer">推荐作品 2</div>
                                </div>
                            </TabsContent>
                            <TabsContent value="videos">...</TabsContent>
                            <TabsContent value="about">...</TabsContent>
                        </div>
                    </Tabs>
                </div>
            </main>
        </div>
    );
}
