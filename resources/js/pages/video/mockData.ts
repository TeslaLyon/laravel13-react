// 1. 模拟当前正在浏览视频的用户（也就是发评论的人）
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

export const currentUserMock: User = {
    id: 999,
    name: "前端练习生",
    avatar: "https://i.pravatar.cc/150?u=999",
};

export const mockComments: CommentType[] = [
    {
        id: 1,
        user: {
            id: 101,
            name: "React狂热粉",
            avatar: "https://i.pravatar.cc/150?u=101",
        },
        content: "这个视频做得太棒了！特别是后半部分讲解代码逻辑的地方，非常清晰易懂。👍",
        createdAt: "2小时前",
        likes: 128,
    },
    {
        id: 2,
        user: {
            id: 102,
            name: "全栈架构师",
            avatar: "https://i.pravatar.cc/150?u=102",
        },
        content: "我觉得UI还可以再优化一下，不过功能已经很完善了。请问会出第二期讲后端的视频吗？",
        createdAt: "5小时前",
        likes: 45,
        replies: [
            {
                id: 3,
                user: {
                    id: 103,
                    name: "UP主本人",
                    avatar: "https://i.pravatar.cc/150?u=103",
                },
                content: "谢谢支持！第二期已经在筹备中了，主要会讲配合 Inertia 处理后端数据。下周见！",
                createdAt: "4小时前",
                likes: 89,
                // 这里增加了一个嵌套的回复，测试多层级线条
                replies: [
                    {
                        id: 6,
                        user: {
                            id: 106,
                            name: "小白同学",
                            avatar: "https://i.pravatar.cc/150?u=106",
                        },
                        content: "期待期待！！！",
                        createdAt: "3小时前",
                        likes: 5,
                    }
                ]
            },
            {
                id: 4,
                user: {
                    id: 104,
                    name: "吃瓜群众",
                    avatar: "https://i.pravatar.cc/150?u=104",
                },
                content: "前排蹲一个更新！！！",
                createdAt: "1小时前",
                likes: 2,
            }
        ]
    },
    {
        id: 5,
        user: {
            id: 105,
            name: "潜水员",
            avatar: "https://i.pravatar.cc/150?u=105",
        },
        content: "第一！",
        createdAt: "1天前",
        likes: 0,
    }
];
