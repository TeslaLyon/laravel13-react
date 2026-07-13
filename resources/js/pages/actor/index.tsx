import React from "react";
import ActorCard from "@/components/actor/Card";
import { PaginatedResponse, Actor } from "@/types/actor";
import { Link } from '@inertiajs/react';
import { show } from '@/routes/actors'



export default function ActorListPage({ actors }: { actors: PaginatedResponse<Actor> }) {
    return (
        // 外层容器：居中对齐，限制最大宽度，模仿 YouTube 的页面边距
        <div className="w-full p-4 md:p-8 bg-background min-h-screen">

            {/* 页面标题 */}
            <h1 className="text-2xl font-bold mb-6">推荐演员</h1>

            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-5  gap-x-3 gap-y-8">
                {actors.data.map((actor) => (
                    <Link href={show({ actor: actor.id, slug: actor.slug })} key={actor.id}>
                        <ActorCard key={actor.id} actor={actor} />
                    </Link>
                ))}
            </div>
        </div>
    );
}
