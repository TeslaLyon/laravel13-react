// components/CommentInput.tsx
import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';

export interface User {
  id: number;
  name: string;
  avatar: string;
}

interface Props {
    currentUser: User;
    videoId: string;
    parentId?: number; // 如果是回复某条评论，传入父评论ID
    onCancel?: () => void;
}

export default function CommentInput({ currentUser, videoId, parentId, onCancel }: Props) {
    const [isFocused, setIsFocused] = useState(false);

    // Inertia 的表单状态管理
    const { data, setData, post, processing, reset } = useForm({
        content: '',
        video_id: videoId,
        parent_id: parentId || null,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!data.content.trim()) return;

        post('/comments', {
            preserveScroll: true,
            onSuccess: () => {
                reset('content');
                setIsFocused(false);
            },
        });
    };

    const handleCancel = () => {
        reset('content');
        setIsFocused(false);
        if (onCancel) onCancel();
    };

    return (
        <div className="flex gap-4 items-start w-full my-4">
            <img
                src={currentUser.avatar}
                alt="avatar"
                className="w-10 h-10 rounded-full object-cover mt-1"
            />
            <form onSubmit={handleSubmit} className="flex-1">
                <input
                    type="text"
                    value={data.content}
                    onChange={(e) => setData('content', e.target.value)}
                    onFocus={() => setIsFocused(true)}
                    placeholder="添加评论..."
                    className="w-full bg-transparent border-b border-gray-300 focus:border-black outline-none py-1 text-sm transition-colors"
                />

                {isFocused && (
                    <div className="flex justify-end gap-2 mt-3">
                        <button
                            type="button"
                            onClick={handleCancel}
                            className="px-4 py-2 rounded-full hover:bg-gray-100 text-sm font-medium transition-colors"
                        >
                            取消
                        </button>
                        <button
                            type="submit"
                            disabled={processing || !data.content.trim()}
                            className={`px-4 py-2 rounded-full text-sm font-medium transition-colors ${data.content.trim()
                                    ? 'bg-blue-600 text-white hover:bg-blue-700'
                                    : 'bg-gray-100 text-gray-500 cursor-not-allowed'
                                }`}
                        >
                            评论
                        </button>
                    </div>
                )}
            </form>
        </div>
    );
}
