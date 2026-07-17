// components/CommentSection.tsx
import React from 'react';
import CommentInput from './CommentInput';
import CommentItem from './CommentItem';

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
    comments: CommentType[];
    totalComments: number;
    currentUser: User;
    videoId: string;
}

export default function CommentSection({ comments, totalComments, currentUser, videoId }: Props) {
    return (
        <div className="max-w-4xl mx-auto py-6">
            {/* 头部统计与排序 */}
            <div className="flex items-center gap-6 mb-6">
                <h2 className="text-xl font-bold">{totalComments} 条评论</h2>
                <button className="flex items-center gap-2 font-medium text-sm text-gray-700 hover:text-black">
                    <svg viewBox="0 0 24 24" className="w-6 h-6 fill-current"><path d="M3 18h6v-2H3v2zM3 6v2h18V6H3zm0 7h12v-2H3v2z"></path></svg>
                    排序方式
                </button>
            </div>

            {/* 顶部主输入框 */}
            <CommentInput currentUser={currentUser} videoId={videoId} />

            {/* 评论列表渲染 */}
            <div className="mt-8">
                {comments.map(comment => (
                    <CommentItem
                        key={comment.id}
                        comment={comment}
                        currentUser={currentUser}
                        videoId={videoId}
                    />
                ))}
            </div>
        </div>
    );
}
