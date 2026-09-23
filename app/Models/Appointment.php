<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Larastan reads `$casts` but not the `casts()` method this model uses, so the
 * datetime attributes below look like plain strings to static analysis. Declare
 * the types the cast actually produces.
 *
 * @property Carbon $appointment_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $archived_at
 * @property array<string, mixed>|null $estimate_snapshot
 */
class Appointment extends Model
{
    use Concerns\HasPayments;

    /**
     * Staff can reopen a confirmed appointment when the workflow requires it.
     * Customer rescheduling has its own stricter rule below and stops as soon
     * as the team confirms the booking.
     *
     * @var array<string, list<string>>
     */
    public const STATUS_TRANSITIONS = [
        'scheduled' => ['confirmed', 'cancelled'],
        'confirmed' => ['scheduled', 'completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    /**
     * The visit times a crew can be dispatched to. The booking form renders its
     * buttons from this list and StoreScheduleRequest validates against it, so
     * the two cannot drift: the times used to live only in the Blade template,
     * which meant a posted 3 a.m. appointment was accepted without complaint.
     */
    public const SLOT_TIMES = ['09:00', '10:30', '13:00', '14:30', '16:00'];

    /**
     * Days no crew is dispatched. The shop keeps selling on a Sunday - an
     * order is picked and delivered on a working day - but a visit puts people
     * on the ground, so Sunday is not a bookable day.
     *
     * Enforced in the DispatchSlot rule, which every booking path shares.
     *
     * @var list<int>
     */
    public const CLOSED_WEEKDAYS = [CarbonInterface::SUNDAY];

    public static function isClosedOn(Carbon $date): bool
    {
        return in_array($date->dayOfWeek, self::CLOSED_WEEKDAYS, true);
    }

    /** @return list<string> Human names of the closed days, for messages and the UI. */
    public static function closedDayNames(): array
    {
        return array_map(
            fn (int $day): string => Carbon::now()->startOfWeek(CarbonInterface::SUNDAY)->addDays($day)->format('l'),
            self::CLOSED_WEEKDAYS
        );
    }

    protected $fillable = [
        'user_id',
        'service_type_id',
        'appointment_at',
        'slot_key',
        'status',
        'payment_status',
        'appointment_amount',
        'notes',
        'site_address',
        'scope_notes',
        'estimate_snapshot',
        'cancel_reason',
        'cancelled_at',
        'cancelled_by',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'appointment_at' => 'datetime',
            'appointment_amount' => 'decimal:2',
            'estimate_snapshot' => 'array',
            'cancelled_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ServiceType, $this> */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** @return HasOne<Feedback, $this> */
    public function feedback(): HasOne
    {
        return $this->hasOne(Feedback::class);
    }

    public static function slotKey(int $serviceTypeId, mixed $appointmentAt): string
    {
        return $serviceTypeId.'|'.Carbon::parse($appointmentAt)->format('Y-m-d H:i:00');
    }

    /**
     * A crew is dispatched to a booked visit, so a customer cannot move or
     * cancel one at the last minute. The window is the same 24 hours a booking
     * has to be made in advance (StoreScheduleRequest), so there is one rule to
     * hold in your head rather than two.
     */
    public const CHANGE_NOTICE_HOURS = 24;

    /**
     * Whether the customer may still cancel this visit themselves. Confirmed
     * visits remain cancellable outside the notice window, but rescheduling is
     * locked separately as soon as the team confirms the booking.
     */
    public function isCustomerChangeable(): bool
    {
        return in_array($this->status, ['scheduled', 'confirmed'], true)
            && $this->appointment_at->greaterThanOrEqualTo(
                Carbon::now()->addHours(self::CHANGE_NOTICE_HOURS)
            );
    }

    /** Whether the customer may choose a new time before the team confirms it. */
    public function isCustomerReschedulable(): bool
    {
        return $this->status === 'scheduled'
            && $this->appointment_at->greaterThanOrEqualTo(
                Carbon::now()->addHours(self::CHANGE_NOTICE_HOURS)
            );
    }

    public function canTransitionTo(string $status): bool
    {
        return $status === $this->status
            || in_array($status, self::STATUS_TRANSITIONS[$this->status] ?? [], true);
    }

    protected function invoiceSeriesLetter(): string
    {
        return 'S';
    }
}
