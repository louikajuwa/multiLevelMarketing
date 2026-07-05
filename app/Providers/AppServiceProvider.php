<?php

namespace App\Providers;

use App\Enums\RoleName;
use App\Models\User;
use App\Services\MailConfigService;
use App\Services\RealtimeService;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
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

        // Push a live "notification" event to the recipient's socket room when a
        // database notification is stored (fires once per notification).
        Event::listen(
            NotificationSent::class,
            function (NotificationSent $e): void {
                if ($e->channel !== 'database' || ! $e->notifiable instanceof User) {
                    return;
                }
                $data = method_exists($e->notification, 'toArray') ? $e->notification->toArray($e->notifiable) : [];
                app(RealtimeService::class)->publish(
                    ['user.'.$e->notifiable->getKey()],
                    'notification',
                    ['title' => $data['title'] ?? 'Notification', 'message' => $data['message'] ?? ''],
                );
            }
        );
    }
}
