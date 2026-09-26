<?php

namespace App\Notifications;

use App\Models\SiteSetting;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

class VerifyEmailNotification extends BaseVerifyEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 1800];

    public function __construct(public readonly ?string $origin = null)
    {
        $this->afterCommit();
    }

    public function toMail($notifiable): MailMessage
    {
        $siteName = SiteSetting::current()->site_name;
        $verificationPath = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
            absolute: false,
        );
        $verificationUrl = rtrim($this->origin ?: (string) config('app.url'), '/').$verificationPath;

        return (new MailMessage)
            ->subject('Xác thực tài khoản '.$siteName)
            ->greeting('Xin chào '.$notifiable->name.'!')
            ->line('Địa chỉ email này đang được thiết lập cho tài khoản của bạn tại '.$siteName.'.')
            ->line('Vui lòng bấm nút bên dưới để xác nhận bạn sở hữu địa chỉ email này.')
            ->action('Xác thực email', $verificationUrl)
            ->line('Liên kết này sẽ hết hạn sau 60 phút.')
            ->line('Nếu bạn không đăng ký tài khoản, bạn có thể bỏ qua email này.');
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('mail')->error('Verification email exhausted retries', ['exception' => $exception::class]);
    }
}
