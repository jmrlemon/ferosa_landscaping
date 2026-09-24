<?php

namespace App\Services;

use App\Models\ServiceType;
use Illuminate\Validation\ValidationException;

class PhilippineDiscountRules
{
    public function businessTaxProfileReady(): bool
    {
        $vatRegistered = config('discounts.vat_registered');

        if (! is_bool($vatRegistered)) {
            return false;
        }

        return $vatRegistered === false || (bool) config('discounts.prices_include_vat', true);
    }

    public function taxExclusivePricingUnsupported(): bool
    {
        return config('discounts.vat_registered') === true
            && ! (bool) config('discounts.prices_include_vat', true);
    }

    public function schemeEnabled(string $scheme): bool
    {
        if (! (bool) config('discounts.enabled', false) || ! $this->businessTaxProfileReady()) {
            return false;
        }

        return match ($scheme) {
            PhilippineDiscountCalculator::SCHEME_STATUTORY_20 => (bool) config('discounts.statutory_20_enabled', false),
            PhilippineDiscountCalculator::SCHEME_BNPC_5 => (bool) config('discounts.bnpc_5_enabled', false)
                && (bool) config('discounts.bnpc_5_workflow_complete', false),
            default => false,
        };
    }

    /**
     * @param  list<array{price?: float|int|string|null, discount_scheme?: string|null}>  $lines
     */
    public function orderHasAvailableScheme(array $lines): bool
    {
        foreach ($lines as $line) {
            $scheme = (string) ($line['discount_scheme'] ?? PhilippineDiscountCalculator::SCHEME_NONE);
            if ((float) ($line['price'] ?? 0) > 0 && $this->schemeEnabled($scheme)) {
                return true;
            }
        }

        return false;
    }

    public function serviceHasAvailableScheme(ServiceType $serviceType): bool
    {
        return (float) $serviceType->default_fee > 0
            && $this->schemeEnabled((string) $serviceType->discount_scheme);
    }

    public function assertSchemeEnabled(string $scheme): void
    {
        if (! (bool) config('discounts.enabled', false)) {
            throw ValidationException::withMessages([
                'discount' => 'Discounts are disabled pending compliance approval.',
            ]);
        }

        if (! is_bool(config('discounts.vat_registered'))) {
            throw ValidationException::withMessages([
                'discount' => 'Confirm whether Ferosa is VAT-registered before approving a discount.',
            ]);
        }

        if ($this->taxExclusivePricingUnsupported()) {
            throw ValidationException::withMessages([
                'discount' => 'VAT-exclusive pricing is not supported by checkout. Configure VAT-inclusive prices before approving discounts.',
            ]);
        }

        if (! $this->schemeEnabled($scheme)) {
            throw ValidationException::withMessages([
                'discount' => 'This discount scheme is disabled pending compliance approval.',
            ]);
        }
    }
}
