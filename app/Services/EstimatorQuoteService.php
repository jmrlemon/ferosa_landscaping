<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class EstimatorQuoteService
{
    public const SESSION_KEY = 'estimator_booking';

    public const VERSION = 1;

    public const VALID_HOURS = 24;

    /**
     * Build an authoritative estimate from validated selection keys.
     *
     * @param  array{project_type:string,size:int,tier:string,addons?:list<string>,products?:list<array{id:int,qty:int}>}  $data
     * @return array{version:int,prepared_at:string,service_type_id:int,snapshot:array<string,mixed>}
     */
    public function prepare(array $data): array
    {
        $projectTypes = (array) config('estimator.project_types', []);
        $tiers = (array) config('estimator.tiers', []);
        $addonCatalog = (array) config('estimator.addons', []);
        $project = (array) ($projectTypes[$data['project_type']] ?? []);
        $tier = (array) ($tiers[$data['tier']] ?? []);

        $serviceName = (string) ($project['booking_service_name'] ?? '');
        $service = ServiceType::query()
            ->where('name', $serviceName)
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->first();

        if (! $service) {
            throw ValidationException::withMessages([
                'project_type' => 'That consultation service is unavailable right now. Please choose another project type or contact Ferosa.',
            ]);
        }

        $baseAmount = round((float) ($project['rate'] ?? 0) * $data['size'] * (float) ($tier['multiplier'] ?? 1), 2);
        $addons = [];
        $addonsAmount = 0.0;

        foreach ($data['addons'] ?? [] as $addonKey) {
            $addon = (array) ($addonCatalog[$addonKey] ?? []);
            $amount = round((float) ($addon['amount'] ?? 0), 2);
            $addonsAmount += $amount;
            $addons[] = [
                'key' => $addonKey,
                'label' => (string) ($addon['label'] ?? $addonKey),
                'amount' => $amount,
            ];
        }

        $requestedProducts = collect($data['products'] ?? [])->keyBy('id');
        $products = [];
        $productsAmount = 0.0;

        if ($requestedProducts->isNotEmpty()) {
            $availableProducts = Product::query()
                ->whereKey($requestedProducts->keys()->all())
                ->where('is_active', true)
                ->whereNull('archived_at')
                ->where('stock_qty', '>', 0)
                ->get()
                ->keyBy('id');

            if ($availableProducts->count() !== $requestedProducts->count()) {
                throw ValidationException::withMessages([
                    'products' => 'One of the selected products is no longer available. Please update your estimate.',
                ]);
            }

            foreach ($requestedProducts as $productId => $requested) {
                $product = $availableProducts->get((int) $productId);
                $quantity = (int) $requested['qty'];

                if (! $product || $quantity > $product->stock_qty) {
                    throw ValidationException::withMessages([
                        'products' => 'A selected product does not have enough stock. Please lower its quantity and try again.',
                    ]);
                }

                $price = round((float) $product->price, 2);
                $amount = round($price * $quantity, 2);
                $productsAmount += $amount;
                $products[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $price,
                    'quantity' => $quantity,
                    'amount' => $amount,
                ];
            }
        }

        $total = round($baseAmount + $addonsAmount + $productsAmount, 2);
        $range = (array) config('estimator.range', []);

        return [
            'version' => self::VERSION,
            'prepared_at' => now()->toIso8601String(),
            'service_type_id' => $service->id,
            'snapshot' => [
                'project_type' => $data['project_type'],
                'project_type_label' => (string) ($project['label'] ?? $data['project_type']),
                'size' => $data['size'],
                'tier' => $data['tier'],
                'tier_label' => (string) ($tier['label'] ?? $data['tier']),
                'addons' => $addons,
                'products' => $products,
                'base_amount' => $baseAmount,
                'addons_amount' => round($addonsAmount, 2),
                'products_amount' => round($productsAmount, 2),
                'total' => $total,
                'range_low' => round($total * (float) ($range['low'] ?? 0.8), 2),
                'range_high' => round($total * (float) ($range['high'] ?? 1.25), 2),
            ],
        ];
    }

    /** @return array{version:int,prepared_at:string,service_type_id:int,snapshot:array<string,mixed>}|null */
    public function current(Request $request): ?array
    {
        $draft = $request->session()->get(self::SESSION_KEY);

        if (! is_array($draft)
            || ($draft['version'] ?? null) !== self::VERSION
            || ! is_string($draft['prepared_at'] ?? null)
            || ! is_numeric($draft['service_type_id'] ?? null)
            || ! is_array($draft['snapshot'] ?? null)) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        try {
            $preparedAt = Carbon::parse($draft['prepared_at']);
        } catch (\Throwable) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        if ($preparedAt->isFuture() || $preparedAt->lt(now()->subHours(self::VALID_HOURS))) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        /** @var array{version:int,prepared_at:string,service_type_id:int,snapshot:array<string,mixed>} $draft */
        return $draft;
    }

    public function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
