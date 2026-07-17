// CommentItem.tsx
import React, { useState } from 'react';
import { CommentType } from '@/pages/video/mockData';

interface CommentItemProps {
    comment: CommentType;
    depth?: number; // 用于区分顶层和子评论
}

// 辅助组件：专门用于渲染 Reddit 风格的“勺形”弯曲连接线
// 它从左侧垂直线开始，弯曲向下并向右连接到头像。
const NestingLineConnector = () => {
    return (
        // 这个容器用于精确定位连线。宽度 w-8 (32px), 与头像等宽。
        <div className="absolute top-[-10px] left-[-23px] w-8 h-[44px] pointer-events-none z-0">
            {/* 这里是勺形连线核心：
        1. 使用 border-l-2 (左边框) 和 border-b-2 (下边框)
        2. rounded-bl-2xl 创建一个大的左下圆角，实现“弯曲”
        3. 通过精确的偏移 (-mt-[8px])，使其垂直部分看起来从上面的折叠按钮或直线上延伸下来，
           其水平部分刚好精确对齐头像的左上角。
      */}
            <div className="w-full h-full border-l-2 border-b-2 border-gray-200 rounded-bl-2xl"></div>
        </div>
    );
};

export default function CommentItem({ comment, depth = 0 }: CommentItemProps) {
    const [isExpanded, setIsExpanded] = useState(true);
    const hasReplies = comment.replies && comment.replies.length > 0;

    // 如果已折叠，显示简化视图
    if (!isExpanded && depth === 0) {
        return (
            <div className="flex items-center gap-1.5 mt-2 text-xs text-gray-500 font-semibold cursor-pointer border border-gray-100 rounded p-1 hover:bg-gray-50" onClick={() => setIsExpanded(true)}>
                <span>+</span>
                <img
                    src={comment.avatar}
                    alt={comment.author}
                    className="w-5 h-5 rounded-full border border-gray-200 bg-white"
                />
                <span>{comment.author}</span>
                <span>·</span>
                <span>已折叠 {comment.replies?.length || 0} 个回复</span>
            </div>
        );
    }

    // 计算左侧线条区域和内容区域的宽度。头像 w-8 (32px)。
    // 线条区域 w-10 (40px) 可以提供足够的对齐空间。
    const lineAreaWidthClass = depth === 0 ? "w-0" : "w-10"; // 顶层评论不需要左侧空间
    const gapClass = depth === 0 ? "gap-2" : "gap-1";

    return (
        // 增加垂直间距的区分
        <div className={`flex flex-col ${depth === 0 ? 'mt-4' : 'mt-1'}`}>

            {/* --- 1. 评论主体区域 (头像+内容) --- */}
            <div className={`flex items-start ${gapClass}`}>

                {/* 左侧连线区域 (仅在展开且非顶层评论时需要) */}
                {depth > 0 && (
                    <div className={`${lineAreaWidthClass} flex-shrink-0 relative`}>
                        {/* 渲染“勺形”弯曲连线 */}
                        <NestingLineConnector />
                    </div>
                )}

                {/* 右侧内容区域：头像和所有内容 */}
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                        {/* 头像 */}
                        <div className="flex-shrink-0 relative z-10">
                            <img
                                src={comment.avatar}
                                alt={comment.author}
                                className="w-8 h-8 rounded-full border border-gray-200 bg-white"
                            />
                        </div>

                        {/* 用户名和时间 */}
                        <div className="flex-1 flex items-center gap-2 text-xs text-gray-500 mb-0.5">
                            <span className="font-semibold text-gray-900">{comment.author}</span>
                            <span>·</span>
                            <span>{comment.timeAgo}</span>
                        </div>
                    </div>

                    {/* 正文 */}
                    <div className="text-sm text-gray-800 break-words mb-1.5 pl-[40px]"> {/* 头像 w-8 + 间距 */}
                        {comment.content}
                    </div>

                    {/* 操作栏 (点赞、回复、分享) */}
                    <div className="flex items-center gap-4 text-xs font-semibold text-gray-500 pl-[40px]"> {/* 头像 w-8 + 间距 */}
                        <button className="flex items-center gap-1 hover:bg-gray-100 px-1 py-0.5 rounded transition">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" /></svg>
                            <span>{comment.upvotes}</span>
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <button className="flex items-center gap-1 hover:bg-gray-100 px-2 py-0.5 rounded transition">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                            回复
                        </button>
                        <button className="flex items-center gap-1 hover:bg-gray-100 px-2 py-0.5 rounded transition">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" /></svg>
                            分享
                        </button>
                    </div>
                </div>
            </div>

            {/* --- 2. 嵌套回复及贯穿垂直线 & 折叠按钮 --- */}
            {hasReplies && isExpanded && (
                <div className="flex mt-1">
                    {/* 左侧贯穿垂直线 & 折叠按钮容器 */}
                    <div className="w-[40px] flex-shrink-0 flex items-start justify-center pt-2 relative z-10">

                        {/* 折叠按钮 `(-)`。放置在垂直线顶部。仅在子评论区域需要。 */}
                        <button
                            onClick={() => setIsExpanded(false)}
                            className="w-5 h-5 rounded-full border border-gray-300 bg-white flex items-center justify-center text-gray-500 hover:text-gray-900 group hover:border-gray-500 transition-colors z-10"
                            title="折叠分支"
                        >
                            <span className="text-[10px] leading-none mb-px">-</span>
                        </button>

                        {/* 贯穿到底部的直立线条。起始于折叠按钮下方。 */}
                        <div className="absolute top-[32px] left-[19px] w-[2px] h-[calc(100%-32px)] bg-gray-200 transition-colors pointer-events-none"></div>
                    </div>

                    {/* 右侧递归渲染子评论 (传递 depth + 1) */}
                    <div className="flex-1 pb-1 pl-[-2px]">
                        {comment.replies!.map((reply) => (
                            <CommentItem key={reply.id} comment={reply} depth={depth + 1} />
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
