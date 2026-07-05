<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewOrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order)
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
            ->subject("New order {$this->order->order_number}")
            ->line("Order {$this->order->order_number} for \${$this->order->grand_total} was placed.")
            ->action('View orders', url('/orders'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order',
            'title' => 'New order',
            'message' => "Order {$this->order->order_number} · \${$this->order->grand_total}",
            'url' => '/orders',
        ];
    }
}
