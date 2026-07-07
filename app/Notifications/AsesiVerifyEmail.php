<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class AsesiVerifyEmail extends BaseVerifyEmail
{

    protected function verificationUrl($notifiable)
    {
        $signedApiUrl = URL::temporarySignedRoute(
            'api.asesi.verify-email',
            Carbon::now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        // $query sudah berisi: id, hash, expires, signature — lengkap
        $query = parse_url($signedApiUrl, PHP_URL_QUERY);

        $frontendUrl = rtrim(config('app.frontend_url'), '/') . '/verify-email';

        return $frontendUrl . '?' . $query;
    }

    /**
     * Kustomisasi isi email.
     */
    public function toMail($notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verifikasi Alamat Email Anda')
            ->greeting('Halo, ' . $notifiable->name . '!')
            ->line('Terima kasih telah mendaftar. Klik tombol di bawah untuk memverifikasi alamat email Anda.')
            ->action('Verifikasi Email', $url)
            ->line('Link ini berlaku selama 60 menit.')
            ->line('Jika Anda tidak merasa mendaftar, abaikan email ini.')
            ->salutation('Salam, lspp306.com');
    }
}
