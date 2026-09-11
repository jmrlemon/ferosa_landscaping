<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'before',
        'after',
        'ip',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function getDescriptionAttribute(): string
    {
        $target = $this->target_label;

        return match ($this->action) {
            'created' => "Created {$target}.",
            'updated' => $this->describeChangedFields("Updated {$target}"),
            'product.create' => "Created {$target}.",
            'product.update' => $this->describeChangedFields("Updated {$target}"),
            'product.archive' => "Archived {$target}.",
            'product.restore' => "Restored {$target}.",
            'service.create' => "Created {$target}.",
            'service.update' => $this->describeChangedFields("Updated {$target}"),
            'service.archive' => "Archived {$target}.",
            'service.restore' => "Restored {$target}.",
            'order.status.update' => $this->describeWorkflowChange($target),
            'order.status.bulk-update' => $this->describeWorkflowChange($target, true),
            'order.archive' => "Archived {$target}.",
            'order.restore' => "Restored {$target}.",
            'order.receipt.confirmed' => "Confirmed receipt of {$target}.",
            'order.cancel' => "Cancelled {$target}.",
            'appointment.status.update' => $this->describeWorkflowChange($target),
            'appointment.cancel' => "Cancelled {$target}.",
            'appointment.cancel.customer' => "Cancelled their {$target}.",
            'appointment.archive' => "Archived {$target}.",
            'appointment.restore' => "Restored {$target}.",
            default => 'Performed '.Str::headline(str_replace('.', ' ', (string) $this->action))." on {$target}.",
        };
    }

    public function getActionLabelAttribute(): string
    {
        return Str::headline(str_replace('.', ' ', (string) $this->action));
    }

    public function getTargetLabelAttribute(): string
    {
        $type = match (class_basename((string) $this->auditable_type)) {
            'Product' => 'product',
            'ServiceType' => 'service',
            'Order' => 'order',
            'Appointment' => 'appointment',
            default => Str::lower(Str::headline(class_basename((string) $this->auditable_type) ?: 'record')),
        };

        $snapshot = $this->after ?: $this->before ?: [];
        $name = $snapshot['name'] ?? $snapshot['title'] ?? $snapshot['order_number'] ?? null;

        if (is_scalar($name) && trim((string) $name) !== '') {
            return $type.' “'.trim((string) $name).'”';
        }

        return $type.' #'.$this->auditable_id;
    }

    private function describeWorkflowChange(string $target, bool $bulk = false): string
    {
        $changes = [];

        foreach (['status' => 'status', 'payment_status' => 'payment status'] as $field => $label) {
            $before = $this->before[$field] ?? null;
            $after = $this->after[$field] ?? null;

            if ($this->valuesMatch($before, $after)) {
                continue;
            }

            $changes[] = "{$label} from {$this->formatValue($before)} to {$this->formatValue($after)}";
        }

        $prefix = $bulk ? "Bulk-updated {$target}" : "Changed {$target}";
        if ($changes !== []) {
            return $prefix.' '.$this->joinWords($changes).'.';
        }

        return $this->describeChangedFields($prefix);
    }

    private function describeChangedFields(string $prefix): string
    {
        $ignored = [
            'archived_at',
            'cancelled_at',
            'cancelled_by',
            'payment_verified_at',
            'payment_verified_by',
            'customer_confirmed_at',
            'slot_key',
        ];

        $labels = [
            'name' => 'name',
            'description' => 'description',
            'image_url' => 'image',
            'price' => 'price',
            'stock_qty' => 'stock quantity',
            'category' => 'category',
            'is_active' => 'availability',
            'default_fee' => 'default fee',
            'status' => 'status',
            'payment_status' => 'payment status',
            'payment_review_notes' => 'payment review notes',
            'dispatch_proof_url' => 'dispatch proof',
            'delivery_proof_url' => 'delivery proof',
            'driver_name' => 'driver name',
            'driver_phone' => 'driver phone',
            'dispatch_notes' => 'dispatch notes',
            'delivery_recipient_name' => 'delivery recipient',
            'cancel_reason' => 'cancellation reason',
            'appointment_at' => 'appointment date',
            'total_amount' => 'total amount',
        ];

        $fields = [];
        foreach (array_unique(array_merge(array_keys($this->before ?? []), array_keys($this->after ?? []))) as $field) {
            if (in_array($field, $ignored, true)) {
                continue;
            }

            if ($this->valuesMatch($this->before[$field] ?? null, $this->after[$field] ?? null)) {
                continue;
            }

            $fields[] = $labels[$field] ?? Str::lower(Str::headline($field));
        }

        if ($fields === []) {
            return $prefix.'.';
        }

        if (count($fields) > 4) {
            $fields = array_merge(array_slice($fields, 0, 4), ['other details']);
        }

        return $prefix.' (changed '.$this->joinWords($fields).').';
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Not set';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return Str::headline(str_replace('_', ' ', (string) $value));
    }

    private function valuesMatch(mixed $before, mixed $after): bool
    {
        return json_encode($before, JSON_PRESERVE_ZERO_FRACTION) === json_encode($after, JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * @param  array<int, string>  $items
     */
    private function joinWords(array $items): string
    {
        if (count($items) < 2) {
            return $items[0] ?? '';
        }

        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
    }
}
