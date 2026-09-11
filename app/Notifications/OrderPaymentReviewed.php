<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OrderPaymentReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $status = $this->order->payment_status;
        $message = match ($status) {
            'paid' => "Payment for order #{$this->order->order_number} was verified.",
            'rejected' => "Payment for order #{$this->order->order_number} needs your attention.",
            'refunded' => "Payment for order #{$this->order->order_number} was marked as refunded.",
            default => "Payment for order #{$this->order->order_number} is under review.",
        };

        return [
            'type' => 'order_payment',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'payment_status' => $status,
            'message' => $message,
            'url' => route('orders', absolute: false),
        ];
    }
}
