import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Carousel, CarouselContent, CarouselItem, CarouselNext, CarouselPrevious } from '@/components/ui/carousel';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { PlayCircle, Crown, ShoppingBag, Clapperboard, Star, Bell } from 'lucide-react';

// --- 新的实用工具：生成高质量的随机真实图片 ---
// 使用 seed 参数确保每次刷新页面时，同一张图保持不变
const getImageUrl = (width: number, height: number, seed: string) => {
    return `https://picsum.photos/seed/${seed}/${width}/${height}`;
};

// --- 新的实用工具：生成带有名字首字母的纯色头像 ---
const getAvatarUrl = (name: string) => {
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=random&color=fff&size=200`;
};

// --- 类型定义 ---
interface MediaItem {
    id: number;
    title: string;
    cover: string;
    views?: string;
    is_vip: boolean;
}

interface Actor {
    id: number;
    name: string;
    avatar: string;
}

interface CarouselItemData {
    id: number;
    title: string;
    cover: string;
    is_vip: boolean;
    type: string;
}

interface PageProps {
    carouselItems: CarouselItemData[];
    announcements: { id: number; text: string; date: string }[];
    videos: MediaItem[];
    images: MediaItem[];
    actors: Actor[];
    studios: Actor[];
    articles: { id: number; title: string; excerpt: string }[];
    products: { id: number; name: string; price: string; image: string }[];
}

export default function HomeIndex({
    // 使用 getImageUrl 替换轮播图
    carouselItems = [
        { id: 1, title: '探索年度最受瞩目视界', cover: getImageUrl(800, 480, 'visual'), is_vip: true, type: '视频' },
        { id: 2, title: 'VIP 独享：幕后独家制作特辑', cover: getImageUrl(800, 480, 'behind'), is_vip: true, type: '特辑' },
        { id: 3, title: '本周热门推荐：光影背后的故事', cover: getImageUrl(800, 480, 'story'), is_vip: false, type: '推荐' }
    ],
    announcements = [
        { id: 1, text: '新功能上线：交互式播放器', date: '06-25' },
        { id: 2, text: '本月VIP专属特权更新，立享独家内容', date: '06-20' },
        { id: 3, text: '周边商城新品上架，首单9折优惠', date: '06-18' },
        { id: 4, text: '系统例行维护，将在本周末进行', date: '06-15' }
    ],
    // 使用 getImageUrl 替换视频封面
    videos = [
        { id: 1, title: '视频标题：模仿 YouTube 的两行截断样式', cover: getImageUrl(640, 360, 'v1'), views: '12万次观看', is_vip: false },
        { id: 2, title: '另一个精彩视频：独家幕后视角', cover: getImageUrl(640, 360, 'v2'), views: '8.5万次观看', is_vip: true },
        { id: 3, title: '技术前沿：未来影像的诞生', cover: getImageUrl(640, 360, 'v3'), views: '1.2万次观看', is_vip: false },
        { id: 4, title: '艺术空间：创意摄影大赏', cover: getImageUrl(640, 360, 'v4'), views: '500次观看', is_vip: true },
        { id: 5, title: '生活家：记录每个精彩瞬间', cover: getImageUrl(640, 360, 'v5'), views: '12万次观看', is_vip: false },
        { id: 6, title: '城市记忆：霓虹下的故事', cover: getImageUrl(640, 360, 'v6'), views: '8.5万次观看', is_vip: true },
    ],
    // 使用 getAvatarUrl 替换演员头像
    actors = [
        { id: 1, name: '三上', avatar: getAvatarUrl('三上') },
        { id: 2, name: '桥本', avatar: getAvatarUrl('桥本') },
        { id: 3, name: '深田', avatar: getAvatarUrl('深田') },
        { id: 4, name: '新垣', avatar: getAvatarUrl('新垣') },
        { id: 5, name: '石原', avatar: getAvatarUrl('石原') },
        { id: 6, name: '长泽', avatar: getAvatarUrl('长泽') },
    ],
    // 使用 getImageUrl 替换商品图片
    products = [
        { id: 1, name: '官方T恤', price: '¥ 299', image: getImageUrl(300, 300, 'p1') },
        { id: 2, name: '限定马克杯', price: '¥ 88', image: getImageUrl(300, 300, 'p2') },
        { id: 3, name: '时尚手机壳', price: '¥ 129', image: getImageUrl(300, 300, 'p3') },
        { id: 4, name: '周边棒球帽', price: '¥ 188', image: getImageUrl(300, 300, 'p4') }
    ],
    articles = [
        { id: 1, title: '深度解读：光影艺术的现代应用', excerpt: '探索现代影像技术如何与经典艺术风格完美结合...' },
        { id: 2, title: 'VIP专栏：制作幕后的那些事儿', excerpt: '带你走进不为人知的拍摄现场，揭秘那些光影背后的故事...' },
        { id: 3, title: '技术沙龙：未来十年的视界前瞻', excerpt: '技术大咖与你一起畅谈影像技术的未来趋势...' }
    ]
}: PageProps) {

    return (
        <div className="min-h-screen bg-background text-foreground font-sans selection:bg-primary selection:text-primary-foreground">
            <Head title="首页 - 探索无限视界" />

            <main className="container mx-auto px-4 md:px-6 py-10 md:py-16 space-y-16">

                {/* 英雄区域：左侧轮播，右侧公告 */}
                <section className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-[2fr,1fr] gap-8 items-start">
                    {/* 左侧：缩小的轮播图 */}
                    <div className="relative group overflow-hidden rounded-2xl bg-muted aspect-[5/3]">
                        <Carousel opts={{ loop: true }}>
                            <CarouselContent>
                                {carouselItems.map((item, idx) => (
                                    <CarouselItem key={idx}>
                                        <div className="relative w-full h-full cursor-pointer">
                                            <img src={item.cover} alt={item.title} className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" />
                                            <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent flex flex-col justify-end p-6 md:p-8">
                                                {item.is_vip && <Badge className="w-fit mb-3 bg-yellow-500 text-black hover:bg-yellow-400">VIP 独享</Badge>}
                                                <h1 className="text-xl md:text-3xl font-bold text-white tracking-tight max-w-xl mb-3">{item.title}</h1>
                                                <div className="flex gap-4 mt-1">
                                                    <Button size="default" className="rounded-full bg-white text-black hover:bg-white/90">
                                                        <PlayCircle className="mr-2 h-5 w-5" /> 立即播放
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </CarouselItem>
                                ))}
                            </CarouselContent>
                            <CarouselPrevious />
                            <CarouselNext />
                        </Carousel>
                    </div>

                    {/* 右侧：最新公告版块 */}
                    <Card className="rounded-2xl border bg-muted/20">
                        <CardHeader className="flex flex-row items-center justify-between pb-3">
                            <CardTitle className="text-xl font-bold tracking-tight flex items-center gap-2"><Bell className="h-6 w-6 text-primary"/> 最新公告</CardTitle>
                            <Link href="/announcements" className="text-xs text-muted-foreground hover:text-primary hover:underline">查看全部</Link>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {announcements.map((announcement, idx) => (
                                <div key={idx} className="flex items-center justify-between gap-4 p-3 border-b border-muted group cursor-pointer hover:bg-muted/50 rounded-lg transition">
                                    <p className="text-sm line-clamp-1 group-hover:text-primary">{announcement.text}</p>
                                    <span className="text-xs text-muted-foreground">{announcement.date}</span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </section>

                {/* 视频网格 */}
                <section>
                    <div className="flex items-center justify-between mb-6">
                        <h2 className="text-2xl font-bold tracking-tight flex items-center gap-2"><Clapperboard className="h-6 w-6"/> 最新上架</h2>
                        <Link href="/videos" className="text-sm font-medium text-primary hover:underline">查看全部</Link>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-10">
                        {videos.map((video, idx) => (
                            <div key={idx} className="group cursor-pointer flex flex-col gap-3">
                                <div className="relative overflow-hidden rounded-xl bg-muted aspect-video">
                                    <img src={video.cover} alt="Thumbnail" className="object-cover w-full h-full transition-transform duration-300 group-hover:scale-105" />
                                    {video.is_vip && <Badge className="absolute top-2 right-2 bg-yellow-500/90 text-black border-none text-xs">VIP</Badge>}
                                </div>
                                <div>
                                    <h3 className="font-semibold leading-tight line-clamp-2 group-hover:text-primary transition-colors">{video.title}</h3>
                                    <p className="text-sm text-muted-foreground mt-1.5">{video.views} · 刚刚</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                {/* 热门演员横向滚动 */}
                <section>
                    <h2 className="text-2xl font-bold tracking-tight mb-6 flex items-center gap-2"><Star className="h-6 w-6"/> 热门演员与片商</h2>
                    <ScrollArea className="w-full pb-4">
                        <div className="flex w-max space-x-8 px-2">
                            {actors.map((actor, idx) => (
                                <div key={idx} className="flex flex-col items-center gap-3 cursor-pointer group">
                                    <Avatar className="h-24 w-24 border-2 border-transparent group-hover:border-primary transition-all duration-300 shadow-sm">
                                        <AvatarImage src={actor.avatar} />
                                        <AvatarFallback>{actor.name[0]}</AvatarFallback>
                                    </Avatar>
                                    <span className="text-sm font-medium text-muted-foreground group-hover:text-foreground transition-colors">{actor.name}</span>
                                </div>
                            ))}
                        </div>
                        <ScrollBar orientation="horizontal" />
                    </ScrollArea>
                </section>

                {/* 文章与商城部分 */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-12">
                    {/* 文章区 */}
                    <section className="lg:col-span-2">
                        <h2 className="text-2xl font-bold tracking-tight mb-6">深度阅读</h2>
                        <div className="space-y-6">
                            {articles.map((article, idx) => (
                                <Card key={idx} className="border-none shadow-none bg-muted/30 hover:bg-muted/50 transition-colors cursor-pointer rounded-2xl">
                                    <CardHeader>
                                        <CardTitle className="text-lg">{article.title}</CardTitle>
                                        <p className="text-sm text-muted-foreground line-clamp-2 mt-2">{article.excerpt}</p>
                                    </CardHeader>
                                </Card>
                            ))}
                        </div>
                    </section>

                    {/* 商城推荐区 */}
                    <section>
                        <div className="flex items-center justify-between mb-6">
                            <h2 className="text-2xl font-bold tracking-tight flex items-center gap-2"><ShoppingBag className="h-6 w-6"/> 周边商城</h2>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            {products.map((product, idx) => (
                                <div key={idx} className="group cursor-pointer">
                                    <div className="relative overflow-hidden rounded-xl bg-muted aspect-square mb-3">
                                        <img src={product.image} alt={product.name} className="object-cover w-full h-full group-hover:scale-105 transition-transform" />
                                    </div>
                                    <h4 className="text-sm font-medium line-clamp-1">{product.name}</h4>
                                    <p className="text-sm font-bold text-primary mt-1">{product.price}</p>
                                </div>
                            ))}
                        </div>
                    </section>
                </div>
            </main>
        </div>
    );
}
