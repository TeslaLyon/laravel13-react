// Components
import { Form, Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="邮箱验证" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 rounded-xl border border-green-500/20 bg-green-50/80 p-3 text-center text-sm font-medium text-green-700 dark:bg-green-950/40 dark:text-green-300">
                    新的验证链接已发送！若数分钟内收件箱未收到，可能被邮箱过滤，请检查「垃圾邮件」箱。
                </div>
            )}

            <div className="rounded-xl border border-amber-500/20 bg-amber-50/80 p-3.5 text-sm leading-relaxed text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                <strong>温馨提示：</strong>若未在收件箱中收到邮件，可能被系统误判，请前往<strong>「垃圾邮件 / 垃圾箱」</strong>中查找；若找到，建议点击<strong>「这不是垃圾邮件」</strong>以确保后续正常接收。
            </div>

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button disabled={processing} variant="secondary">
                            {processing && <Spinner />}
                            重新发送验证邮件
                        </Button>

                        <TextLink
                            href={logout()}
                            className="mx-auto block text-sm"
                        >
                            退出登录
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: '邮箱验证',
    description:
        '请点击我们刚刚发送给您的邮件中的链接以验证电子邮箱。',
};
