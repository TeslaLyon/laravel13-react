export type User = {
    id: number;
    name: string;
    nickname: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type UnreadNotification = {
    id: string;
    data: {
        category?: string;
        result?: 'approved' | 'rejected' | string;
        title?: string;
        message?: string;
        order_no?: string;
        amount_cents?: number;
        tissues?: string;
        payment_method?: string;
        remark?: string | null;
        action_url?: string;
    };
    created_at?: string;
};

export type Auth = {
    user: User;
    unreadNotifications?: UnreadNotification[];
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
