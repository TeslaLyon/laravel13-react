import { SubscribeButton as BaseSubscribeButton, type NotificationType } from '@/components/subscribe-button';

export function SubscribeButton({
    subscribed,
    channelId,
    setSubscribed,
    channelSlug,
    onCountChange,
    channelName,
    notificationType = 'personalized',
    className = 'h-9 ml-2 shrink-0',
}: {
    subscribed: boolean;
    channelId: number;
    setSubscribed: (subscribed: boolean) => void;
    channelSlug?: string;
    onCountChange?: (count: number) => void;
    channelName?: string;
    notificationType?: NotificationType;
    className?: string;
}) {
    return (
        <BaseSubscribeButton
            type="channel"
            id={channelId}
            name={channelName}
            initialIsSubscribed={subscribed}
            initialNotificationType={notificationType}
            onSubscriptionChange={(isSub, delta) => {
                setSubscribed(isSub);
                onCountChange?.(delta);
            }}
            className={className}
        />
    );
}

export { BaseSubscribeButton };

