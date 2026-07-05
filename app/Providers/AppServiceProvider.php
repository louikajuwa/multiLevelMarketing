<?php

namespace App\Providers;

use App\Enums\RoleName;
use App\Services\MailConfigService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Super Admin implicitly has every permission/ability.
        Gate::before(function ($user, string $ability) {
            return $user->hasRole(RoleName::SuperAdmin->value) ? true : null;
        });

        // Apply admin-managed mail settings over .env (runs for web + queue workers).
        app(MailConfigService::class)->apply();
    }
}
