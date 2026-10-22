import { Form, Head, usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="个人资料设置" />

            <h1 className="sr-only">个人资料设置</h1>

            {/* 🎯 重点修改：添加 w-full max-w-xl 限制表单最大宽度 */}
            <div className="w-full max-w-xl space-y-6">
                <Heading
                    variant="small"
                    title="个人资料"
                    description="更新您的姓名和邮箱地址"
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    用户名 <span className="text-muted-foreground text-xs">(系统唯一标识，暂不支持修改)</span>
                                </Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full bg-muted/50 cursor-not-allowed select-none"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="username"
                                    placeholder="用户名"
                                    readOnly
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="nickname">昵称</Label>

                                <Input
                                    id="nickname"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.nickname}
                                    name="nickname"
                                    required
                                    autoComplete="nickname"
                                    placeholder="例如：Alex 🚀"
                                />

                                <p className="text-xs text-muted-foreground">
                                    2-30 位字符，支持中文、字母、数字、常规符号及 Emoji 表情。
                                </p>

                                <InputError
                                    className="mt-1"
                                    message={errors.nickname}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">电子邮箱地址</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder="电子邮箱地址"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div>
                                        <p className="-mt-4 text-sm text-muted-foreground">
                                            您的电子邮箱未经验证。{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                            >
                                                点击这里重新发送验证邮件。
                                            </Link>
                                        </p>

                                        <div className="mt-3 rounded-xl border border-amber-500/20 bg-amber-50/80 p-3 text-sm leading-relaxed text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                                            <strong>温馨提示：</strong>若未在收件箱中收到邮件，可能被系统误判，请前往<strong>「垃圾邮件 / 垃圾箱」</strong>中查找；若找到，建议点击<strong>「这不是垃圾邮件」</strong>以确保后续正常接收。
                                        </div>

                                        {status ===
                                            'verification-link-sent' && (
                                                <div className="mt-2 text-sm font-medium text-green-600">
                                                    新的验证链接已发送！若数分钟内收件箱未收到，可能被误判，请检查「垃圾邮件」箱。
                                                </div>
                                            )}
                                    </div>
                                )}

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    保存
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            {/* <DeleteUser /> */}
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: '个人资料设置',
            href: edit(),
        },
    ],
};
