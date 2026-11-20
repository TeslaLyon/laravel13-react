<?php

namespace App\Notifications;

use App\Models\WalletOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WalletRechargeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param WalletOrder $order 充值订单
     * @param string $result 'approved' (核实到账) | 'rejected' (核验驳回)
     * @param int|null $actualAmountCents 实际核实到账金额（分）
     * @param string|null $remark 管理员备注或驳回原因
     */
    public function __construct(
        public WalletOrder $order,
        public string $result = 'approved',
        public ?int $actualAmountCents = null,
        public ?string $remark = null
    ) {
    }

    /**
     * 通知发送通道：站内数据库通知 + 队列邮件（若用户存在邮箱）
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (!empty($notifiable->email) && config('mail.default') !== 'log') {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * 构建站内数据库通知结构（供 Inertia 全局共享及站内 Toast 弹窗展示）
     */
    public function toDatabase(object $notifiable): array
    {
        $cents = $this->actualAmountCents ?? ($this->order->really_amount ?: $this->order->amount);
        $tissues = number_format($cents / 100, 2, '.', '');
        $methodLabel = $this->order->paymentMethodLabel();

        if ($this->result === 'approved') {
            return [
                'category'       => 'wallet_recharge',
                'result'         => 'approved',
                'title'          => '充值到账通知',
                'message'        => "您通过【{$methodLabel}】提交的充值已核实到账，账户已成功增加 {$tissues} 纸巾！",
                'order_no'       => $this->order->order_no,
                'amount_cents'   => (int) $cents,
                'tissues'        => $tissues,
                'payment_method' => $methodLabel,
                'remark'         => $this->remark,
                'action_url'     => route('wallet.index'),
            ];
        }

        $reason = $this->remark ?: '口令未核实到账或已失效';
        return [
            'category'       => 'wallet_recharge',
            'result'         => 'rejected',
            'title'          => '充值审核未通过',
            'message'        => "您通过【{$methodLabel}】提交的订单 {$this->order->order_no} 未通过核实（{$reason}）。",
            'order_no'       => $this->order->order_no,
            'amount_cents'   => (int) $this->order->amount,
            'tissues'        => number_format($this->order->amount / 100, 2, '.', ''),
            'payment_method' => $methodLabel,
            'remark'         => $reason,
            'action_url'     => route('wallet.index'),
        ];
    }

    /**
     * 构建队列邮件消息（触达已关闭网页的离站用户）
     */
    public function toMail(object $notifiable): MailMessage
    {
        $cents = $this->actualAmountCents ?? ($this->order->really_amount ?: $this->order->amount);
        $tissues = number_format($cents / 100, 2, '.', '');
        $methodLabel = $this->order->paymentMethodLabel();

        if ($this->result === 'approved') {
            $mail = (new MailMessage)
                ->subject("充值到账通知：已成功发放 {$tissues} 纸巾")
                ->greeting('您好，' . ($notifiable->nickname ?: $notifiable->name) . '：')
                ->line("您通过【{$methodLabel}】提交的充值订单已由管理员核实确认。")
                ->line("订单编号：{$this->order->order_no}")
                ->line("实付金额：¥{$tissues}（已入账 {$tissues} 纸巾）");

            if (!empty($this->remark)) {
                $mail->line("审核备注：{$this->remark}");
            }

            return $mail
                ->action('查看我的资产钱包', route('wallet.index'))
                ->line('感谢您的支持与信任！');
        }

        $reason = $this->remark ?: '口令无效或未查收到对应款项';
        return (new MailMessage)
            ->subject("充值审核未通过通知：订单 {$this->order->order_no}")
            ->greeting('您好，' . ($notifiable->nickname ?: $notifiable->name) . '：')
            ->line("抱歉，您通过【{$methodLabel}】提交的充值订单（{$this->order->order_no}）未通过核实。")
            ->line("未通过原因：{$reason}")
            ->action('重新前往钱包充值', route('wallet.index'))
            ->line('如有疑问，欢迎随时联系站点管理员。');
    }
}
