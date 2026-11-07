<?php

namespace App\Providers;

use App\Support\Cms\HitungMenu;
use App\Support\Cms\NotifikasiAdmin;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Lapisan luar login MUK: batas per IP (mencegah spray ke banyak email).
        // Batas per email+IP atas percobaan GAGAL ada di MukAuthService.
        RateLimiter::for('muk-login', function (Request $request) {
            return Limit::perMinute(config('muk.login_max_attempts_per_ip'))->by('muk-login-ip|'.$request->ip());
        });

        // Autosave dokumen (debounce di FE), per user.
        RateLimiter::for('muk-tulis', fn (Request $request) => Limit::perMinute(120)
            ->by('muk-tulis|'.$request->user()?->getMorphClass().'|'.$request->user()?->getKey()));

        // Tanda tangan & submit, per user.
        RateLimiter::for('muk-aksi', fn (Request $request) => Limit::perMinute(20)
            ->by('muk-aksi|'.$request->user()?->getMorphClass().'|'.$request->user()?->getKey()));

        // CMS: lonceng notifikasi (milik admin yang login) & badge jumlah pekerjaan di sidebar.
        View::composer('layouts.partials.topbar', function ($view) {
            $admin = auth('web')->user();
            $view->with([
                'notifBelumDibaca' => $admin ? $admin->unreadNotifications()->count() : 0,
                'notifTerbaru' => $admin
                    ? $admin->notifications()->latest()->limit(8)->get()->map(fn ($n) => NotifikasiAdmin::tampil($n))
                    : collect(),
            ]);
        });
        View::composer('layouts.partials.sidebar', fn ($view) => $view->with('hitungMenu', HitungMenu::semua()));
    }
}
