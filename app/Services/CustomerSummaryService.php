<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\User;

/**
 * Builds the compact account snapshot the Android app needs.
 *
 * The native Home screen, the bottom-navigation badges, and the background
 * poll that raises local notifications all want the same handful of facts, so
 * they share one query pass rather than three round trips.
 *
 * Everything here is scoped to the passed user. Nothing in this class takes an
 * id from a request.
 */
class CustomerSummaryService
{
    /** Orders that are still in flight from the customer's point of view. */
    private const OPEN_ORDER_STATUSES = ['pending', 'confirmed', 'out_for_delivery', 'delivered'];

    /** Appointments that have not happened yet. */
    private const UPCOMING_APPOINTMENT_STATUSES = ['scheduled', 'confirmed'];

    public function __construct(private readonly CartService $cart) {}

    /**
     * @return array{
     *     cart_count: int,
     *     unread_notifications: int,
     *     unread_messages: int,
     *     active_order: array{id: int, order_number: string, status: string, status_label: string, total_amount: float, placed_at: string}|null,
     *     next_appointment: array{id: int, service: string, status: string, status_label: string, appointment_at: string}|null,
     * }
     */
    public function forUser(User $user): array
    {
        return [
            'cart_count' => (int) $this->cart->summary($user)['cart_count'],
            'unread_notifications' => $user->unreadNotifications()->count(),
            'unread_messages' => $this->unreadMessages($user),
            'active_order' => $this->activeOrder($user),
            'next_appointment' => $this->nextAppointment($user),
        ];
    }

    /**
     * Unread staff replies waiting for this customer.
     *
     * Staff accounts read their inbox in the admin workspace and have no
     * customer conversation of their own, so they always see zero here.
     */
    private function unreadMessages(User $user): int
    {
        $conversation = Conversation::query()
            ->where('customer_id', $user->id)
            ->first();

        return $conversation?->unreadFor($user->id) ?? 0;
    }

    /**
     * The most recent order that has not reached a terminal state.
     *
     * `delivered` is deliberately included: the customer still has to confirm
     * receipt, so it is not finished from their side.
     */
    private function activeOrder(User $user): ?array
    {
        $order = Order::query()
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->whereIn('status', self::OPEN_ORDER_STATUSES)
            ->latest()
            ->first();

        if (! $order) {
            return null;
        }

        return [
            'id' => (int) $order->id,
            'order_number' => (string) $order->order_number,
            'status' => (string) $order->status,
            'status_label' => $this->humanise((string) $order->status),
            'total_amount' => (float) $order->total_amount,
            'placed_at' => $order->created_at->toIso8601String(),
        ];
    }

    private function nextAppointment(User $user): ?array
    {
        $appointment = Appointment::query()
            ->with('serviceType')
            ->where('user_id', $user->id)
            ->whereNull('archived_at')
            ->whereIn('status', self::UPCOMING_APPOINTMENT_STATUSES)
            ->where('appointment_at', '>=', now())
            ->orderBy('appointment_at')
            ->first();

        if (! $appointment) {
            return null;
        }

        return [
            'id' => (int) $appointment->id,
            // `??` over the whole chain is the pattern used everywhere else for
            // this relation (SendAppointmentReminders, PageController); it covers
            // an appointment whose service type row has since been removed.
            'service' => (string) ($appointment->serviceType->name ?? 'Service visit'),
            'status' => (string) $appointment->status,
            'status_label' => $this->humanise((string) $appointment->status),
            'appointment_at' => $appointment->appointment_at->toIso8601String(),
        ];
    }

    /** `out_for_delivery` reads as "Out for delivery" on a phone screen. */
    private function humanise(string $status): string
    {
        return ucfirst(str_replace('_', ' ', $status));
    }
}
