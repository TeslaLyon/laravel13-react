<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;

class QueuedVerifyEmail extends BaseVerifyEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Get the verify email notification mail message for the given URL.
     *
     * @param  string  $url
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    protected function buildMailMessage($url)
    {
        return (new MailMessage)
            ->subject('验证您的电子邮箱地址')
            ->line('请点击下方按钮以验证您的电子邮箱地址：')
            ->action('验证电子邮箱', $url)
            ->line('温馨提示：若在收件箱中未找到此邮件，可能被邮箱服务商误判，请前往「垃圾邮件」箱中查找。')
            ->line('如果您未创建过账号，无需进行任何进一步操作。');
    }
}
