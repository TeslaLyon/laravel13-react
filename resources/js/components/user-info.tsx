import { AvatarWithDecoration, type AvatarDecorationData } from '@/components/avatar-with-decoration';
import { useInitials } from '@/hooks/use-initials';
import type { User } from '@/types';

// 定义后端返回的原始装饰品数据结构
interface RawAvatarDecorationData {
    id: string | number;
    title: string;
    imageUrl?: string;
    image_url?: string;
}


export function UserInfo({
    user,
    showEmail = false,
}: {
    user: User;
    showEmail?: boolean;
}) {

    const getInitials = useInitials();

    // 🌟 1. 提取后端注入的 avatar_decoration 对象
    const rawDecoration = user.avatar_decoration as RawAvatarDecorationData | null | undefined;

    // 🌟 2. 做数据清洗与兼容，确保 AvatarWithDecoration 能够正确读取 imageUrl
    const activeDecoration: AvatarDecorationData | null = rawDecoration
        ? {
            id: typeof rawDecoration.id === 'number' ? rawDecoration.id : Number(rawDecoration.id),
            title: rawDecoration.title,
            imageUrl: rawDecoration.imageUrl || rawDecoration.image_url || '',
        }
        : null;

    return (
        <div className="flex items-center gap-2 group-data-[collapsible=icon]:gap-0">
            {/* 头像与挂件 */}
            <AvatarWithDecoration
                avatarSrc={user.avatar}
                avatarFallback={getInitials(user.name)}
                decoration={activeDecoration}
                sizeClassName="w-8 h-8"
            />

            {/* 🌟 核心点：在折叠状态下自动隐藏文字区块，避免文字溢出 */}
            <div className="grid flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden">
                <span className="truncate font-medium">{user.nickname || user.name}</span>
                {showEmail && (
                    <span className="truncate text-xs text-muted-foreground">
                        @{user.name}
                    </span>
                )}
            </div>
        </div>
    );
}

