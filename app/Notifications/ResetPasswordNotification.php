<?php

namespace App\Notifications;

use App\Models\SiteSetting;
use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResetPasswordNotification extends BaseResetPassword implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 1800];

    public function __construct(string $token, public readonly ?string $origin = null)
    {
        parent::__construct($token);
        $this->afterCommit();
    }

    public function toMail($notifiable): MailMessage
    {
        $siteName = SiteSetting::current()->site_name;
        $path = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], absolute: false);
        $url = rtrim($this->origin ?: (string) config('app.url'), '/').$path;

        return (new MailMessage)
            ->subject('Đặt lại mật khẩu '.$siteName)
            ->greeting('Xin chào '.$notifiable->name.'!')
            ->line('Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.')
            ->action('Đặt lại mật khẩu', $url)
            ->line('Liên kết này sẽ hết hạn sau 60 phút.')
            ->line('Nếu bạn không yêu cầu thay đổi, hãy bỏ qua email này; mật khẩu hiện tại vẫn an toàn.');
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('mail')->error('Password reset email exhausted retries', ['exception' => $exception::class]);
    }
}
