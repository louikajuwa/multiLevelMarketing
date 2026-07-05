<?php

namespace App\Notifications;

use App\Models\Commission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CommissionEarnedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Commission $commission)
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
            ->subject('Commission earned')
            ->line("You earned \${$this->commission->amount} ({$this->commission->type->value}).")
            ->action('View commissions', url('/commissions'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'commission',
            'title' => 'Commission earned',
            'message' => "\${$this->commission->amount} · {$this->commission->type->value}",
            'url' => '/commissions',
        ];
    }
}
