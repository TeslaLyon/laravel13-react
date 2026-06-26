import React from "react";
import { Card, CardContent } from "@/components/ui/card";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Play } from "lucide-react";

// 1. 定义模拟的视频数据
const MOCK_VIDEOS = [
  {
    id: "1",
    title: "2026年 React 进阶开发指南",
    thumbnail: "https://images.unsplash.com/photo-1633356122544-f134324a6cee?w=800&q=80",
    duration: "12:34",
    views: "1.2万",
    postedAt: "2小时前",
    author: {
      name: "前端大师兄",
      avatar: "https://api.dicebear.com/7.x/avataaars/svg?seed=Felix",
    },
  },
  {
    id: "2",
    title: "掌握 shadcn/ui 的核心架构设计",
    thumbnail: "https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=800&q=80",
    duration: "45:10",
    views: "8500",
    postedAt: "昨天",
    author: {
      name: "UI实验室",
      avatar: "https://api.dicebear.com/7.x/avataaars/svg?seed=Aneka",
    },
  },
  {
    id: "3",
    title: "从零开始的 Laravel 13 完整教程",
    thumbnail: "https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=800&q=80",
    duration: "1:20:05",
    views: "3.4万",
    postedAt: "3天前",
    author: {
      name: "全栈开发者",
      avatar: "https://api.dicebear.com/7.x/avataaars/svg?seed=Jack",
    },
  }
];

export default function VideoList() {
  return (
    // 2. 响应式网格容器
    <div className="p-6">
      <h2 className="mb-6 text-2xl font-bold tracking-tight">最新推荐</h2>

      <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        {MOCK_VIDEOS.map((video) => (
          // 3. 视频卡片主体
          <Card key={video.id} className="overflow-hidden transition-all hover:shadow-lg border-none bg-transparent shadow-none group cursor-pointer">

            {/* 封面图区域 */}
            <div className="relative aspect-video w-full overflow-hidden rounded-xl">
              <img
                src={video.thumbnail}
                alt={video.title}
                className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
              />
              {/* 悬浮播放图标 */}
              <div className="absolute inset-0 flex items-center justify-center bg-black/20 opacity-0 transition-opacity group-hover:opacity-100">
                <div className="rounded-full bg-white/30 p-3 backdrop-blur-sm">
                  <Play className="h-6 w-6 text-white fill-white" />
                </div>
              </div>
              {/* 时长角标 */}
              <Badge variant="secondary" className="absolute bottom-2 right-2 bg-black/80 text-white hover:bg-black/80">
                {video.duration}
              </Badge>
            </div>

            {/* 信息区域 */}
            <CardContent className="p-0 pt-4 flex gap-3">
              <Avatar className="h-9 w-9 mt-1">
                <AvatarImage src={video.author.avatar} alt={video.author.name} />
                <AvatarFallback>{video.author.name.substring(0, 2)}</AvatarFallback>
              </Avatar>

              <div className="flex flex-col">
                <h3 className="line-clamp-2 text-sm font-semibold leading-tight text-foreground group-hover:text-primary transition-colors">
                  {video.title}
                </h3>
                <p className="mt-1 text-sm text-muted-foreground">
                  {video.author.name}
                </p>
                <div className="flex items-center text-xs text-muted-foreground">
                  <span>{video.views} 次观看</span>
                  <span className="mx-1.5 inline-block h-1 w-1 rounded-full bg-muted-foreground/50" />
                  <span>{video.postedAt}</span>
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}
