import React from 'react';
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";

interface ActorProps {
    actor: {
        name: string;
        avatar: string;
        description?: string;
    }
}

export default function ActorProfileCircle({ actor }: ActorProps) {
    return (
        <div className="flex items-center justify-between p-2">
            {/* 左侧：头像与个人信息 */}
            <div className="flex items-center gap-3">
                <Avatar className="h-10 w-10">
                    <AvatarImage src={actor.avatar} alt={actor.name} className="object-cover" />
                    {/* 如果图片加载失败，显示名字的第一个字 */}
                    <AvatarFallback>{actor.name.charAt(0)}</AvatarFallback>
                </Avatar>
                <div className="flex flex-col">
                    <h3 className="text-base font-semibold leading-none tracking-tight text-foreground">
                        {actor.name}
                    </h3>
                    {actor.description && (
                        <p className="text-sm text-muted-foreground mt-1.5">
                            {actor.description}
                        </p>
                    )}
                </div>
            </div>

            {/* 右侧：操作按钮（模仿 YouTube 的关注按钮） */}
            <Button variant="default" className="rounded-full px-5 font-semibold">
                关注
            </Button>
        </div>
    );
}
