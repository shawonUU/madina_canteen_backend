<?php

namespace App\Providers;

use App\Observers\RecordLogObserver;
use App\Services\AccessService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Admin\Models\User;

class RecordServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AccessService::class);
        $this->app->scoped(RecordLogObserver::class);
    }

    public function boot(): void
    {
        foreach (config('audit.resources', []) as $resource) {
            $resource[0]::observe(RecordLogObserver::class);
        }
        Gate::before(function (User $user, string $ability) {
            return str_contains($ability, '.') ? app(AccessService::class)->can($user, $ability) : null;
        });
        ResetPassword::createUrlUsing(fn ($user, $token) => rtrim(config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
            'token' => $token, 'email' => $user->getEmailForPasswordReset(),
        ]));
    }
}
