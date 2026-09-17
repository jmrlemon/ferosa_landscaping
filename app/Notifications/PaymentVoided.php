<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentVoided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $payable = $this->payment->payable;
        $amount = number_format((float) $this->payment->amount, 2);
        $reason = $this->payment->void_reason ?: 'No reason provided.';

        if ($payable instanceof Order) {
            return [
                'type' => 'payment_voided',
                'payment_id' => $this->payment->id,
                'order_id' => $payable->id,
                'message' => "A payment of PHP {$amount} for order #{$payable->order_number} was voided. Reason: {$reason} Your balance has been updated.",
                'url' => route('orders.invoice', $payable, absolute: false),
            ];
        }

        if ($payable instanceof Appointment) {
            $service = $payable->serviceType->name;

            return [
                'type' => 'payment_voided',
                'payment_id' => $this->payment->id,
                'appointment_id' => $payable->id,
                'message' => "A payment of PHP {$amount} for your {$service} was voided. Reason: {$reason} Your balance has been updated.",
                'url' => route('appointments.invoice', $payable, absolute: false),
            ];
        }

        return [
            'type' => 'payment_voided',
            'payment_id' => $this->payment->id,
            'message' => "A payment of PHP {$amount} was voided. Reason: {$reason} Your balance has been updated.",
            'url' => route('home', absolute: false),
        ];
    }
}
