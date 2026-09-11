<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = match ($this->order->status) {
            'confirmed' => 'confirmed',
            'out_for_delivery' => 'out for delivery',
            'delivered' => 'delivered and is waiting for your confirmation',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            default => $this->order->status,
        };

        return [
            'type' => 'order',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'message' => "Your order #{$this->order->order_number} has been {$label}.",
            'url' => route('orders', absolute: false),
        ];
    }
}
