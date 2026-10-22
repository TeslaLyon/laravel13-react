import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import Turnstile from '@/components/turnstile';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    return (
        <>
            <Head title="注册" />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-1.5">
                                <div className="flex items-center justify-between">
                                    <Label htmlFor="name">用户名 <span className="text-destructive">*</span></Label>
                                    <span className="text-[11px] text-muted-foreground">用于登录与账号标识</span>
                                </div>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="username"
                                    name="name"
                                    placeholder="例如：alex_99"
                                />
                                <p className="text-[11px] text-muted-foreground leading-normal">
                                    3-20 位字符，仅支持字母、数字、下划线 (_) 与连字符 (-)，须以字母或数字开头，不支持特殊符号与表情。
                                </p>
                                <InputError
                                    message={errors.name}
                                    className="mt-1"
                                />
                            </div>

                            <div className="grid gap-1.5">
                                <div className="flex items-center justify-between">
                                    <Label htmlFor="nickname">昵称 <span className="text-destructive">*</span></Label>
                                    <span className="text-[11px] text-muted-foreground">用于社区展示</span>
                                </div>
                                <Input
                                    id="nickname"
                                    type="text"
                                    required
                                    tabIndex={2}
                                    autoComplete="nickname"
                                    name="nickname"
                                    placeholder="例如：Alex 🚀"
                                />
                                <p className="text-[11px] text-muted-foreground leading-normal">
                                    2-30 位字符，支持中文、字母、数字、常规符号及 Emoji 表情。
                                </p>
                                <InputError
                                    message={errors.nickname}
                                    className="mt-1"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">电子邮箱地址 <span className="text-destructive">*</span></Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={3}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">密码 <span className="text-destructive">*</span></Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Password"
                                    passwordrules={passwordRules}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    确认密码 <span className="text-destructive">*</span>
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    tabIndex={5}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Confirm password"
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div className="flex flex-col items-center">
                                <Turnstile resetTrigger={errors} />
                                <InputError message={(errors as Record<string, string>)['cf-turnstile-response']} />
                            </div>

                            <div className="rounded-xl border border-amber-500/20 bg-amber-50/80 p-3 text-xs leading-relaxed text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                                <strong>提示：</strong>注册后将发送验证邮件，如收件箱未收到，可能被系统误判，请检查<strong>「垃圾邮件」</strong>箱。
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                tabIndex={6}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                创建账户
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            已有账户？{' '}
                            <TextLink href={login()} tabIndex={7}>
                                登录
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: '创建账户',
    description: '请输入以下信息来创建账号',
};
