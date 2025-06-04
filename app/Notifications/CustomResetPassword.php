<?php
namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class CustomResetPassword extends ResetPassword
{
    public function __construct($token)
    {
        parent::__construct($token);
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('重設您的密碼')
            ->line('您收到這封郵件是因為我們收到您的密碼重設請求。')
            ->action('點此重設密碼', url(config('app.url') . route('password.reset', $this->token, false)))
            ->line('如果您沒有要求重設密碼，請忽略此郵件。')
            ->salutation('全國外來植物調查資料管理系統 敬上'); 
    }
}
