<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class CustomVerifyEmail extends BaseVerifyEmail
{
    /**
     * 自訂 Email 驗證信內容
     */
    public function toMail($notifiable)
    {
        $verifyUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('請驗證您的 Email 帳號')
            ->greeting('您好！')
            ->line('請點擊下方按鈕完成 Email 驗證。')
            ->action('點我驗證 Email', $verifyUrl)
            ->line('若您沒有註冊本站帳號，請忽略這封信。')
            ->salutation('感謝您使用本系統！');
    }
}
