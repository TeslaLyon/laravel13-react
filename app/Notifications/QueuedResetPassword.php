<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;

class QueuedResetPassword extends BaseResetPassword implements ShouldQueue
{
    use Queueable;

    /**
     * Build the mail representation of the notification.
     *
     * @param  string  $url
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    protected function buildMailMessage($url)
    {
        return (new MailMessage)
            ->subject('重置密码通知')
            ->line('您收到这封邮件是因为我们收到了重置您账户密码的申请。')
            ->action('重置密码', $url)
            ->line('该密码重置链接将在 ' . config('auth.passwords.' . config('auth.defaults.passwords') . '.expire') . ' 分钟后失效。')
            ->line('温馨提示：若在收件箱中未找到此邮件，可能被邮箱服务商误判，请前往「垃圾邮件」箱中查找。')
            ->line('如果您未曾申请重置密码，请忽略此邮件。');
    }
}
