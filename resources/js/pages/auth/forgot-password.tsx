// Components
import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes';
import { email } from '@/routes/password';
import AlertError from '@/components/alert-error';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="忘记密码" />

            {status && (
                <div className="mb-4 rounded-xl border border-green-500/20 bg-green-50/80 p-3 text-center text-sm font-medium text-green-700 dark:bg-green-950/40 dark:text-green-300">
                    {status}
                </div>
            )}

            <div className="rounded-xl border border-amber-500/20 bg-amber-50/80 p-3.5 text-sm leading-relaxed text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                <strong>温馨提示：</strong>密码重置邮件发送后，若未在收件箱中收到，可能被系统误判，请前往<strong>「垃圾邮件 / 垃圾箱」</strong>中查找；若找到，建议点击<strong>「这不是垃圾邮件」</strong>以确保后续正常接收。
            </div>

            <div className="space-y-6">
                <Form {...email.form()}>
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">邮箱地址</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    autoComplete="off"
                                    autoFocus
                                    placeholder="email@example.com"
                                />

                                <InputError message={errors.email} />
                            </div>

                            <div className="my-6 flex items-center justify-start">
                                <Button
                                    className="w-full"
                                    disabled={processing}
                                    data-test="email-password-reset-link-button"
                                >
                                    {processing && (
                                        <LoaderCircle className="h-4 w-4 animate-spin" />
                                    )}
                                    发送密码重置链接
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <div className="space-x-1 text-center text-sm text-muted-foreground">
                    <span>或者，返回到</span>
                    <TextLink href={login()}>登录</TextLink>
                </div>
            </div>
        </>
    );
}

ForgotPassword.layout = {
    title: '忘记密码',
    description: '输入您的邮箱地址以接收密码重置链接',
};
