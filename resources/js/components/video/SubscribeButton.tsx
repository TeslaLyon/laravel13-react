import {
    ChevronDown,
    Bell
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Spinner } from '@/components/ui/spinner';
import { toast } from 'sonner';
import { useHttp } from '@inertiajs/react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from "@/components/ui/tooltip";

interface SubscribeResponse {
    is_subscribed: boolean;
    message: string;
}
interface PostOptions {
    onSuccess?: (response: unknown) => void;
    onError?: (error: unknown) => void;
}

export function SubscribeButton({ subscribed, channelId, setSubscribed, channelSlug }: {
    subscribed: boolean,
    channelId: number,
    setSubscribed: (subscribed: boolean) => void,
    channelSlug: string
}) {

    const { post, processing } = useHttp()

    const handleSubscribeToggle = () => {
        if (processing) return;
        post(`/channels/${channelId}/${channelSlug}/subscribe`, {
            onSuccess: (response: unknown) => {
                const subscribeResponse = response as SubscribeResponse;
                toast.success(subscribeResponse.message);
                setSubscribed(subscribeResponse.is_subscribed);
            },
            onError: () => {
                toast.error('刷新页面后重试');
            }
        });
    };
    return (
        <>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        variant={subscribed ? "secondary" : "default"}
                        className="rounded-full shadow-none ml-2 px-5 h-9"
                        onClick={handleSubscribeToggle}
                        disabled={processing}
                    >
                        {processing && (
                            <>
                                <Spinner data-icon="inline-start" />
                            </>
                        )
                        }
                        {subscribed ? (
                            <>
                                {!processing && (<Bell className="w-4 h-4 " />)}
                                已订阅
                                <ChevronDown className="w-4 h-4 " />
                            </>
                        ) : (
                            "订阅"
                        )}
                    </Button>
                </TooltipTrigger>
                <TooltipContent>
                    <p>订阅片商</p>
                </TooltipContent>
            </Tooltip>

        </>

    );
}
