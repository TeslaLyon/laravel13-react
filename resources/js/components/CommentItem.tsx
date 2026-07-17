// components/CommentItem.tsx
import React, { useState } from 'react';
import { Link } from '@inertiajs/react';
import CommentInput from './CommentInput';

export interface User {
    id: number;
    name: string;
    avatar: string;
}

export interface CommentType {
    id: number;
    user: User;
    content: string;
    createdAt: string;
    likes: number;
    isLikedByMe?: boolean;
    replies?: CommentType[]; // 嵌套的回复列表
}

interface Props {
    comment: CommentType;
    currentUser: User;
    videoId: string;
    // 增加一个层级深度，用来判断是否是第一层回复
    depth?: number;
}

export default function CommentItem({ comment, currentUser, videoId, depth = 0 }: Props) {
    const [showReplies, setShowReplies] = useState(false);

    return (
        // 外层容器：关键在于 relative，为绝对定位的弧线提供锚点
        <div className={`relative ${depth > 0 ? 'mt-4' : 'mb-6'}`}>

            {/* 弧线连接器 (仅当是子评论时显示) */}
            {depth > 0 && (
                <div
                    className="absolute -left-6 top-0 w-6 h-6 border-l-2 border-b-2 border-gray-300 rounded-bl-xl"
                />
            )}

            <div className="flex gap-4 items-start">
                {/* 头像区域：添加 z-10 保证它盖住线条 */}
                <img
                    src={comment.user.avatar}
                    className="w-10 h-10 rounded-full flex-shrink-0 z-10 bg-white"
                />

                <div className="flex-1">
                    {/* 内容区域 */}
                    <div className="font-semibold text-sm">{comment.user.name}</div>
                    <div className="text-gray-800 text-sm mt-1">{comment.content}</div>

                    {/* 这里放置操作按钮 (点赞等) */}
                    <div className="mt-2 text-xs text-gray-500">...操作区域...</div>

                    {/* 嵌套回复区域 */}
                    {comment.replies && comment.replies.length > 0 && (
                        <div className="mt-2">
                            <button
                                onClick={() => setShowReplies(!showReplies)}
                                className="text-blue-600 text-sm font-medium flex items-center hover:bg-blue-50 px-2 py-1 rounded-full"
                            >
                                {showReplies ? '收起回复' : `${comment.replies.length} 条回复`}
                            </button>

                            {/* 关键点：回复列表容器 (垂直线) */}
                            {showReplies && (
                                <div className="relative mt-4 pl-8"> {/* pl-8 给弧线留空间 */}
                                    {/* 这条边框线就是连接父子评论的垂直主线 */}
                                    <div className="absolute left-0 top-0 bottom-4 w-px bg-gray-300" />

                                    {comment.replies.map(reply => (
                                        <CommentItem
                                            key={reply.id}
                                            comment={reply}
                                            currentUser={currentUser}
                                            videoId={videoId}
                                            depth={depth + 1} // 增加深度
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
