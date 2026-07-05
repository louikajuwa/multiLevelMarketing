<?php

namespace App\Notifications;

use App\Models\Partner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewPartnerNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Partner $partner)
    {
        $this->afterCommit = true;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New partner registered')
            ->line("{$this->partner->user->name} joined at level {$this->partner->level?->name}.")
            ->action('View partners', url('/admin/partners'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'partner',
            'title' => 'New partner',
            'message' => "{$this->partner->user->name} registered.",
            'url' => '/admin/partners',
        ];
    }
}
