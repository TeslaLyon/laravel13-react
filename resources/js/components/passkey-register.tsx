import { usePasskeyRegister } from '@laravel/passkeys/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    onSuccess: () => void;
};

export default function PasskeyRegistration({ onSuccess }: Props) {
    const [name, setName] = useState(() => {
        const ua = navigator.userAgent;

        const browser = ['Chrome', 'Firefox', 'Safari', 'Edge', 'Opera'].find(
            (browser) => new RegExp(browser).test(ua),
        );

        const os = ['iPhone', 'iPad', 'Android', 'Mac', 'Windows'].find((os) =>
            new RegExp(os).test(ua),
        );

        return [browser, os].filter(Boolean).join(' on ') || '';
    });

    const [showForm, setShowForm] = useState(false);
    const { register, isLoading, error, isSupported } = usePasskeyRegister({
        onSuccess: () => {
            setName('');
            setShowForm(false);
            onSuccess();
        },
    });

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!name.trim()) {
            return;
        }

        await register(name);
    };

    const handleCancel = () => {
        setShowForm(false);
        setName('');
    };

    if (!isSupported) {
        return (
            <div className="text-sm text-muted-foreground">
                此浏览器不支持通行密钥。
            </div>
        );
    }

    if (!showForm) {
        return (
            <Button variant="outline" onClick={() => setShowForm(true)}>
                添加通行密钥
            </Button>
        );
    }

    return (
        <form
            onSubmit={handleSubmit}
            className="space-y-4 rounded-lg border border-border bg-muted/50 p-4"
        >
            <div className="grid gap-2">
                <Label htmlFor="passkey-name">通行密钥名称</Label>
                <Input
                    id="passkey-name"
                    type="text"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="e.g., MacBook Pro, iPhone"
                    className="mt-1 block w-full border-foreground/20"
                    autoFocus
                />
                <p className="text-xs text-muted-foreground">
                    设置一个名称，以便日后识别此通行密钥。
                </p>
            </div>

            {error && <InputError message={error} />}

            <div className="flex gap-2">
                <Button type="submit" disabled={isLoading || !name.trim()}>
                    {isLoading ? '注册中...' : '注册通行密钥'}
                </Button>
                <Button type="button" variant="ghost" onClick={handleCancel}>
                    取消
                </Button>
            </div>
        </form>
    );
}
