<?php

namespace App\Providers;

use App\Support\FamilyContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(FamilyContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Brute-force protection for login, registration and invitation links.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);

        // Reset links open the SPA page, and the email is in Russian.
        ResetPassword::createUrlUsing(fn ($user, string $token) => url('/reset-password/'.$token.'?email='.urlencode($user->email)));
        ResetPassword::toMailUsing(fn ($user, string $token) => (new MailMessage)
            ->subject(__('Сброс пароля — :app', ['app' => config('app.name')]))
            ->greeting(__('Здравствуйте, :name!', ['name' => $user->name]))
            ->line(__('Кто-то (надеемся, вы) попросил сбросить пароль для входа в семейное приложение.'))
            ->action(__('Задать новый пароль'), url('/reset-password/'.$token.'?email='.urlencode($user->email)))
            ->line(__('Ссылка действует :hours ч.', ['hours' => intdiv(config('auth.passwords.users.expire'), 60)]))
            ->line(__('Если вы ничего не запрашивали, просто удалите это письмо — пароль останется прежним.'))
            ->salutation(__('Ваша семья')));

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
    }
}
