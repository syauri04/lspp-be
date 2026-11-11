<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email reset password asesi -> halaman lspp-fe /{lang}/reset-password?token=&email=.
 * Token asli hanya ada di email ini; di database tersimpan sebagai hash (broker 'asesis').
 */
class AsesiResetPasswordNotifikasi extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token, public string $lang = 'id')
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/')."/{$this->lang}/reset-password?".http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
        $menit = config('auth.passwords.asesis.expire');

        if ($this->lang === 'en') {
            return (new MailMessage)
                ->subject('Reset Your Password - LSPP 306')
                ->greeting("Hello, {$notifiable->name}!")
                ->line('We received a request to reset the password of your LSPP 306 account.')
                ->action('Reset Password', $url)
                ->line("This link is valid for {$menit} minutes and can only be used once.")
                ->line('If you did not request a password reset, ignore this email. Your password will not change.')
                ->salutation('Regards, lspp306.com');
        }

        return (new MailMessage)
            ->subject('Atur Ulang Password Anda - LSPP 306')
            ->greeting("Halo, {$notifiable->name}!")
            ->line('Kami menerima permintaan untuk mengatur ulang password akun LSPP 306 Anda.')
            ->action('Atur Ulang Password', $url)
            ->line("Link ini berlaku {$menit} menit dan hanya bisa dipakai sekali.")
            ->line('Jika Anda tidak meminta reset password, abaikan email ini. Password Anda tidak akan berubah.')
            ->salutation('Salam, lspp306.com');
    }
}
