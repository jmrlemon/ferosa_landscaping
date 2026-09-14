<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\MessageAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TidyDemoData extends Command
{
    protected $signature = 'demo:tidy
                            {--email=user@cblandscaping.com : Demo customer account to tidy}
                            {--apply : Apply the cleanup; without this option the command is read-only}';

    protected $description = 'Preview or clean known diagnostic clutter from the demo customer account';

    /** @var list<string> */
    private const DIAGNOSTIC_MESSAGES = [
        'Test message from diagnostics',
        'ang pogi ni uge',
        'hsllo',
    ];

    private const AGAVE_DESCRIPTION = 'Architectural succulent with sculptural leaves, suited to sunny, well-drained spaces.';

    public function handle(): int
    {
        $email = trim((string) $this->option('email'));
        $customer = User::query()->where('email', $email)->first();

        if (! $customer) {
            $this->components->warn("No customer account found for {$email}.");

            return self::SUCCESS;
        }

        $messages = Message::query()
            ->whereHas('conversation', fn ($query) => $query->where('customer_id', $customer->id))
            ->get();

        $messageClutter = $messages->filter(
            fn (Message $message): bool => in_array(trim($message->body), self::DIAGNOSTIC_MESSAGES, true)
                || ($message->hasAttachment() && ! $message->attachmentAvailable())
        );

        $pastOpenAppointments = Appointment::query()
            ->where('user_id', $customer->id)
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->where('appointment_at', '<', now())
            ->get();

        $badAgaveDescriptions = Product::query()
            ->where('name', 'Agave')
            ->where('description', '25meters')
            ->count();

        $legacyOrders = Order::query()
            ->where('user_id', $customer->id)
            ->whereIn('order_number', ['ORD-123456', 'ORD-789012'])
            ->get();

        $this->table(
            ['Demo cleanup item', 'Found'],
            [
                ['Diagnostic or missing-attachment messages', $messageClutter->count()],
                ['Past appointments still open', $pastOpenAppointments->count()],
                ['Agave placeholder descriptions', $badAgaveDescriptions],
                ['Legacy demo order references', $legacyOrders->count()],
            ]
        );

        if (! $this->option('apply')) {
            $this->components->info('Preview only. Run again with --apply after reviewing these counts.');

            return self::SUCCESS;
        }

        $attachmentPaths = $messageClutter
            ->pluck('attachment_path')
            ->filter()
            ->values();

        DB::transaction(function () use ($messageClutter, $pastOpenAppointments, $legacyOrders): void {
            $conversationIds = $messageClutter->pluck('conversation_id')->unique();

            Message::query()->whereKey($messageClutter->modelKeys())->delete();

            foreach ($conversationIds as $conversationId) {
                $latestAt = Message::query()
                    ->where('conversation_id', $conversationId)
                    ->latest('created_at')
                    ->value('created_at');

                DB::table('conversations')->where('id', $conversationId)->update([
                    'last_message_at' => $latestAt,
                    'updated_at' => now(),
                ]);
            }

            foreach ($pastOpenAppointments as $appointment) {
                if (! $appointment->canTransitionTo('cancelled')) {
                    continue;
                }

                $appointment->update([
                    'status' => 'cancelled',
                    'slot_key' => null,
                    'cancel_reason' => 'Closed during demo-data cleanup because the visit date had passed.',
                    'cancelled_at' => now(),
                ]);
            }

            $this->normalizeLegacyOrders($legacyOrders);

            Product::query()
                ->where('name', 'Agave')
                ->where('description', '25meters')
                ->update(['description' => self::AGAVE_DESCRIPTION]);
        });

        $attachmentPaths->each(fn (string $path) => MessageAttachment::delete($path));

        $this->components->info('Demo data cleanup applied.');

        return self::SUCCESS;
    }

    /** @param Collection<int, Order> $legacyOrders */
    private function normalizeLegacyOrders(Collection $legacyOrders): void
    {
        $mapping = [
            'ORD-123456' => 'FRS-DEMO-001',
            'ORD-789012' => 'FRS-DEMO-002',
        ];

        foreach ($legacyOrders as $order) {
            $replacement = $mapping[$order->order_number] ?? null;

            if (! $replacement || Order::query()->where('order_number', $replacement)->exists()) {
                continue;
            }

            $order->update(['order_number' => $replacement]);
        }
    }
}
