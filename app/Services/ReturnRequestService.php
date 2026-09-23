<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReturnRequestService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $evidenceFiles
     */
    public function submit(Order $order, User $customer, array $data, array $evidenceFiles): ReturnRequest
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($order, $customer, $data, $evidenceFiles, &$storedPaths): ReturnRequest {
                /** @var Order $lockedOrder */
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

                if ((int) $lockedOrder->user_id !== (int) $customer->id) {
                    abort(403);
                }

                if (! $lockedOrder->canOpenReturnRequest()) {
                    throw ValidationException::withMessages([
                        'order' => 'The 24-hour claim window for this order has closed.',
                    ]);
                }

                $requestedItems = collect($data['items']);
                $orderItems = $lockedOrder->orderItems()
                    ->whereIn('id', $requestedItems->pluck('order_item_id')->all())
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($orderItems->count() !== $requestedItems->count()) {
                    throw ValidationException::withMessages([
                        'items' => 'One or more selected items do not belong to this order.',
                    ]);
                }

                foreach ($requestedItems as $index => $requested) {
                    /** @var OrderItem $orderItem */
                    $orderItem = $orderItems->get((int) $requested['order_item_id']);
                    $alreadyClaimed = (int) ReturnRequestItem::query()
                        ->where('order_item_id', $orderItem->id)
                        ->whereHas('returnRequest', fn ($query) => $query->whereNotIn('status', ['rejected', 'cancelled']))
                        ->sum('quantity_claimed');
                    $remaining = max(0, (int) $orderItem->qty - $alreadyClaimed);

                    if ((int) $requested['quantity'] > $remaining) {
                        throw ValidationException::withMessages([
                            "items.{$index}.quantity" => "Only {$remaining} unclaimed unit(s) remain for {$orderItem->name}.",
                        ]);
                    }
                }

                $customerSummary = $requestedItems
                    ->map(function (array $requested) use ($orderItems): string {
                        /** @var OrderItem $orderItem */
                        $orderItem = $orderItems->get((int) $requested['order_item_id']);

                        return $orderItem->name.': '.trim((string) $requested['issue_description']);
                    })
                    ->implode("\n");

                $claim = ReturnRequest::query()->create([
                    'claim_number' => 'RET-PENDING-'.Str::upper(Str::random(12)),
                    'order_id' => $lockedOrder->id,
                    'user_id' => $customer->id,
                    'status' => 'submitted',
                    'customer_summary' => Str::limit($customerSummary, 1000, ''),
                    'customer_contact_notes' => $data['customer_contact_notes'] ?? null,
                    'submitted_at' => now(),
                ]);
                $claim->update([
                    'claim_number' => 'RET-'.now()->format('Ymd').'-'.str_pad((string) $claim->id, 6, '0', STR_PAD_LEFT),
                ]);

                foreach ($requestedItems as $requested) {
                    $claim->items()->create([
                        'order_item_id' => $requested['order_item_id'],
                        'quantity_claimed' => $requested['quantity'],
                        'issue_type' => $requested['issue_type'],
                        'issue_description' => $requested['issue_description'],
                        'preferred_resolution' => $requested['preferred_resolution'],
                        'disposition' => 'not_required',
                    ]);
                }

                foreach ($evidenceFiles as $file) {
                    $filename = Str::uuid().'.'.$file->extension();
                    $path = $file->storeAs("return-evidence/{$claim->id}", $filename, 'local');
                    if ($path === false) {
                        throw new \RuntimeException('The evidence photo could not be stored.');
                    }
                    $storedPaths[] = $path;
                    $claim->evidence()->create([
                        'uploaded_by' => $customer->id,
                        'path' => $path,
                        'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                        'mime_type' => (string) $file->getMimeType(),
                        'size_bytes' => $file->getSize(),
                    ]);
                }

                return $claim->load(['items.orderItem', 'evidence']);
            }, 3);
        } catch (\Throwable $e) {
            if ($storedPaths !== []) {
                Storage::disk('local')->delete($storedPaths);
            }

            throw $e;
        }
    }

    /** @return array<int, int> */
    public function remainingQuantities(Order $order): array
    {
        return $order->orderItems->mapWithKeys(function (OrderItem $item): array {
            $claimed = (int) ReturnRequestItem::query()
                ->where('order_item_id', $item->id)
                ->whereHas('returnRequest', fn ($query) => $query->whereNotIn('status', ['rejected', 'cancelled']))
                ->sum('quantity_claimed');

            return [$item->id => max(0, (int) $item->qty - $claimed)];
        })->all();
    }
}
