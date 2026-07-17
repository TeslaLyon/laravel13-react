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
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    一个新的验证链接已经发送至您在注册时提供的电子邮件地址。
                </div>
            )}

            <div className="rounded-md bg-amber-50 p-3 text-sm text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                <strong>温馨提示：</strong> 邮件发送后，请先到<strong>垃圾邮件</strong>中查看是否收到来自“什么值得看”的邮件。如果被误判为垃圾邮件，请在邮件详情页中点击<strong>“这不是垃圾邮件”</strong>按钮，以确保后续能正常接收通知。
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
