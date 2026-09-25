@php
  /**
   * Payment ledger panel shared by the order and appointment workspaces.
   *
   * Expects: $payable (Order|Appointment), $storeRoute (string), $invoiceRoute (string),
   *          $isAdmin (bool).
   */
  $billed = $payable->totalBilled();
  $paid = $payable->totalPaid();
  $balance = $payable->balanceDue();
  $isOrderPayable = $payable instanceof \App\Models\Order;
  $canApplyDiscount = $isOrderPayable
      ? $payable->status === 'pending' && $payable->payment_status === 'unpaid' && $paid <= 0
      : in_array($payable->status, ['scheduled', 'confirmed'], true) && $payable->payment_status === 'unpaid' && $paid <= 0;
  $activeDiscount = $payable->activeDiscount()->with('verifiedBy')->first();
  $discountRequest = $payable->discountRequest()->with(['requestedBy', 'reviewedBy'])->first();
  $discountRules = app(\App\Services\PhilippineDiscountRules::class);
  $discountsEnabled = $discountRules->schemeEnabled(\App\Services\PhilippineDiscountCalculator::SCHEME_VOLUNTARY_20)
      || ((bool) config('discounts.enabled', false) && $discountRules->businessTaxProfileReady());
  $lineSchemes = $isOrderPayable
      ? ($payable->orderItems->isNotEmpty()
          ? $payable->orderItems->pluck('discount_scheme')
          : collect($payable->items ?? [])->pluck('discount_scheme'))
      : collect([$payable->discount_scheme ?? 'none']);
  $eligibleDiscountSchemes = $canApplyDiscount
      ? $lineSchemes
          ->unique()
          ->filter(fn ($scheme) => $discountRules->schemeEnabled((string) $scheme)
              && ((string) $scheme !== \App\Services\PhilippineDiscountCalculator::SCHEME_BNPC_5 || $isOrderPayable))
          ->values()
          ->all()
      : [];
  if ($canApplyDiscount && $isOrderPayable && $discountRules->schemeEnabled('ferosa_voluntary_20')) {
      $eligibleDiscountSchemes[] = 'ferosa_voluntary_20';
      $eligibleDiscountSchemes = array_values(array_unique($eligibleDiscountSchemes));
  }
  $hasEnabledDiscountScheme = $discountRules->schemeEnabled(\App\Services\PhilippineDiscountCalculator::SCHEME_STATUTORY_20)
      || ($isOrderPayable && $discountRules->schemeEnabled(\App\Services\PhilippineDiscountCalculator::SCHEME_BNPC_5))
      || ($isOrderPayable && $discountRules->schemeEnabled(\App\Services\PhilippineDiscountCalculator::SCHEME_VOLUNTARY_20));
  $ledgerEntries = $payable->paymentHistory()->with(['recordedBy', 'voidedBy'])->get();
  $balanceTone = match (true) {
      $payable->payment_status === 'refunded' => 'border-surface-200 bg-surface-50 text-surface-700',
      $balance <= 0 && $billed > 0 => 'border-brand-200 bg-brand-50 text-brand-800',
      $paid > 0 => 'border-orange-200 bg-orange-50 text-orange-800',
      default => 'border-amber-200 bg-amber-50 text-amber-800',
  };
@endphp

<div class="border-t border-surface-100 p-5">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h4 class="font-semibold">Payment ledger</h4>
      <p class="text-xs text-surface-500">Invoice {{ $payable->invoiceNumber() }} · every payment received against this record.</p>
    </div>
    <a href="{{ $invoiceRoute }}" target="_blank" rel="noopener"
       class="rounded-lg border border-surface-300 px-3 py-1.5 text-sm font-medium text-surface-700 hover:bg-surface-50">
      Open invoice
    </a>
  </div>

  <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
    <div class="rounded-lg border border-surface-200 px-3 py-2">
      <p class="text-[10px] font-bold uppercase tracking-wider text-surface-400">Total billed</p>
      <p class="text-lg font-bold text-surface-900">PHP {{ number_format($billed, 2) }}</p>
    </div>
    <div class="rounded-lg border border-surface-200 px-3 py-2">
      <p class="text-[10px] font-bold uppercase tracking-wider text-surface-400">Total paid</p>
      <p class="text-lg font-bold text-surface-900">PHP {{ number_format($paid, 2) }}</p>
    </div>
    <div class="col-span-2 rounded-lg border px-3 py-2 sm:col-span-1 {{ $balanceTone }}">
      <p class="text-[10px] font-bold uppercase tracking-wider opacity-70">Balance due</p>
      <p class="text-lg font-bold">PHP {{ number_format($balance, 2) }}</p>
    </div>
</div>

  @include('admin.partials.discount-request-review', [
    'discountRequest' => $discountRequest,
    'discountsEnabled' => $discountsEnabled,
    'canApplyDiscount' => $canApplyDiscount,
    'eligibleDiscountSchemes' => $eligibleDiscountSchemes,
    'isAdmin' => $isAdmin,
  ])

  @if($activeDiscount)
    <div class="mt-4 rounded-lg border border-brand-200 bg-brand-50 p-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-brand-800">Discount and VAT adjustment</p>
          <p class="mt-1 text-sm font-semibold text-brand-950">{{ $activeDiscount->beneficiaryLabel() }} · {{ $activeDiscount->schemeLabel() }}</p>
          @if($activeDiscount->verifiedBy)
            <p class="mt-1 text-[11px] text-brand-700">Verified by {{ $activeDiscount->verifiedBy->name }} on {{ optional($activeDiscount->verified_at)->format('M d, Y h:i A') }}</p>
          @endif
        </div>
        <span class="rounded-full bg-white px-2.5 py-1 text-xs font-bold text-brand-800">-PHP {{ number_format((float) $activeDiscount->discount_amount, 2) }}</span>
      </div>
      <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-xs sm:grid-cols-4">
        <div><dt class="text-surface-500">Gross</dt><dd class="font-semibold">PHP {{ number_format((float) $activeDiscount->gross_total, 2) }}</dd></div>
        @if($activeDiscount->scheme === \App\Services\PhilippineDiscountCalculator::SCHEME_STATUTORY_20)
          <div><dt class="text-surface-500">VAT removed from eligible items</dt><dd class="font-semibold">PHP {{ number_format((float) $activeDiscount->vat_removed, 2) }}</dd></div>
          <div><dt class="text-surface-500">20% eligible discount base</dt><dd class="font-semibold">PHP {{ number_format((float) $activeDiscount->discount_base, 2) }}</dd></div>
        @elseif($activeDiscount->scheme === 'bnpc_5')
          <div><dt class="text-surface-500">BNPC eligible base</dt><dd class="font-semibold">PHP {{ number_format((float) $activeDiscount->discount_base, 2) }}</dd></div>
        @else
          <div><dt class="text-surface-500">Promotional discount base</dt><dd class="font-semibold">PHP {{ number_format((float) $activeDiscount->discount_base, 2) }}</dd></div>
        @endif
        <div><dt class="text-surface-500">New total</dt><dd class="font-semibold">PHP {{ number_format((float) $activeDiscount->net_total, 2) }}</dd></div>
      </dl>
    </div>
  @elseif($isAdmin && $isOrderPayable && ! $discountRequest)
    @if(! $discountsEnabled || ! $hasEnabledDiscountScheme)
      <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-800">
        <strong>Discount tools are disabled pending compliance approval.</strong>
        Confirm VAT registration and eligible products, then enable the Philippine discount settings.
      </p>
    @elseif(count($eligibleDiscountSchemes) === 0)
      <p class="mt-4 rounded-lg border border-surface-200 bg-surface-50 px-3 py-2.5 text-sm text-surface-700">
        No order lines are currently marked eligible for an enabled discount scheme.
      </p>
    @elseif($payable->status === 'pending' && $payable->payment_status === 'unpaid' && $paid <= 0)
      <form method="POST" action="{{ route('admin.orders.discounts.store', $payable) }}" class="mt-4 rounded-lg border border-amber-200 bg-amber-50/60 p-4">
        @csrf
        <p class="text-xs font-bold uppercase tracking-wider text-amber-800">Apply Senior/PWD discount</p>
        <p class="mt-1 text-xs leading-5 text-amber-700">Verify the customer before applying a benefit. For a qualifying statutory sale, VAT is removed before the 20% discount. The Ferosa-funded promotion covers ordinary products without a VAT exemption.</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
          <label class="block text-xs font-semibold text-surface-700">Benefit *
            <select name="scheme" required class="mt-1.5 w-full rounded-lg border border-surface-200 px-3 py-2 text-sm font-normal outline-none focus:border-brand-500">
              @if(in_array(\App\Services\PhilippineDiscountCalculator::SCHEME_STATUTORY_20, $eligibleDiscountSchemes, true))
                <option value="statutory_20_vat_exempt">20% + VAT exemption</option>
              @endif
              @if(in_array(\App\Services\PhilippineDiscountCalculator::SCHEME_BNPC_5, $eligibleDiscountSchemes, true))
                <option value="bnpc_5">5% BNPC discount</option>
              @endif
              @if(in_array('ferosa_voluntary_20', $eligibleDiscountSchemes, true))
                <option value="ferosa_voluntary_20">Ferosa-funded 20% promotion (no VAT exemption)</option>
              @endif
            </select>
          </label>
          <label class="block text-xs font-semibold text-surface-700">Beneficiary *
            <select name="beneficiary_type" required class="mt-1.5 w-full rounded-lg border border-surface-200 px-3 py-2 text-sm font-normal outline-none focus:border-brand-500">
              <option value="senior">Senior Citizen</option>
              <option value="pwd">PWD</option>
            </select>
          </label>
        </div>
        <label class="mt-3 flex items-start gap-2 text-xs leading-5 text-surface-700">
          <input type="checkbox" name="eligibility_confirmed" value="1" required class="mt-0.5 h-4 w-4 rounded border-surface-300 text-brand-600 focus:ring-brand-500">
          I checked the customer's valid ID and confirmed that the selected benefit is applicable. Senior and PWD benefits are not stacked.
        </label>
        <button type="submit" class="mt-3 rounded-lg bg-amber-700 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-800">Verify and apply discount</button>
      </form>
    @endif
  @endif

  @if($ledgerEntries->isEmpty())
    <p class="mt-4 rounded-lg border border-dashed border-surface-200 px-3 py-4 text-center text-sm text-surface-400">
      No payments recorded yet.
    </p>
  @else
    <div class="mt-4 overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead class="border-b border-surface-100 text-[10px] uppercase tracking-wider text-surface-400">
          <tr>
            <th class="py-2 pr-3 font-semibold">Date</th>
            <th class="py-2 pr-3 font-semibold">Method</th>
            <th class="py-2 pr-3 font-semibold">Reference</th>
            <th class="py-2 pr-3 text-right font-semibold">Amount</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-surface-50">
          @foreach($ledgerEntries as $payment)
            <tr @class(['bg-red-50/40' => $payment->isVoided()])>
              <td class="whitespace-nowrap py-2.5 pr-3 text-surface-500">{{ optional($payment->paid_at)->format('M d, Y') }}</td>
              <td class="py-2.5 pr-3">
                <div class="flex flex-wrap items-center gap-1.5">
                  <span @class(['font-medium', 'text-surface-800' => ! $payment->isVoided(), 'text-surface-500 line-through' => $payment->isVoided()])>{{ $payment->methodLabel() }}</span>
                  @if($payment->isVoided())
                    <span class="rounded-full border border-red-200 bg-red-50 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-red-700">Voided</span>
                  @endif
                </div>
                @if($payment->recordedBy)<div class="text-[11px] text-surface-400">by {{ $payment->recordedBy->name }}</div>@endif
                @if($payment->notes)<div class="max-w-xs text-[11px] text-surface-400">{{ $payment->notes }}</div>@endif
                @if($payment->isVoided())
                  <div class="mt-1 max-w-sm text-[11px] text-red-700">
                    <span class="font-semibold">Reason:</span> {{ $payment->void_reason }}
                    <span class="block text-red-600/80">
                      Voided {{ optional($payment->voided_at)->format('M d, Y h:i A') }}
                      @if($payment->voidedBy) by {{ $payment->voidedBy->name }}@endif
                    </span>
                  </div>
                @endif
              </td>
              <td class="py-2.5 pr-3 font-mono text-[11px] text-surface-500">{{ $payment->reference ?: '—' }}</td>
              <td @class(['whitespace-nowrap py-2.5 pr-3 text-right font-bold', 'text-surface-900' => ! $payment->isVoided(), 'text-surface-400 line-through' => $payment->isVoided()])>PHP {{ number_format((float) $payment->amount, 2) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  @if($isAdmin && $balance > 0)
    @php($selectedPaymentMethod = old('method', 'cash'))
    <form method="POST" action="{{ $storeRoute }}" data-payment-record-form class="mt-4 rounded-lg border border-surface-200 bg-surface-50/60 p-4">
      @csrf
      <p class="text-xs font-bold uppercase tracking-wider text-surface-500">Record a payment</p>
      <div data-payment-fields class="mt-3 grid gap-3 sm:grid-cols-2 {{ $selectedPaymentMethod === 'cash' ? 'lg:grid-cols-3' : 'lg:grid-cols-4' }}">
        <label class="block text-xs font-semibold text-surface-700">Amount *
          <input type="number" name="amount" step="0.01" min="0.01" max="{{ $balance }}" value="{{ old('amount', $balance) }}" required
                 class="mt-1.5 w-full rounded-lg border border-surface-200 px-3 py-2 text-sm font-normal outline-none focus:border-brand-500">
          <span class="mt-1 block text-[11px] font-normal text-surface-400">Up to PHP {{ number_format($balance, 2) }}</span>
        </label>
        <label class="block text-xs font-semibold text-surface-700">Method *
          <select name="method" data-payment-method required class="mt-1.5 w-full rounded-lg border border-surface-200 px-3 py-2 text-sm font-normal outline-none focus:border-brand-500">
            <option value="cash" @selected($selectedPaymentMethod === 'cash')>Cash</option>
            <option value="gcash" @selected($selectedPaymentMethod === 'gcash')>GCash</option>
            <option value="bank_transfer" @selected($selectedPaymentMethod === 'bank_transfer')>Bank transfer</option>
            <option value="other" @selected($selectedPaymentMethod === 'other')>Other</option>
          </select>
        </label>
        <label data-payment-reference{{ $selectedPaymentMethod === 'cash' ? ' hidden' : '' }} class="{{ $selectedPaymentMethod === 'cash' ? 'hidden' : 'block' }} text-xs font-semibold text-surface-700">Reference
          <input type="text" name="reference" maxlength="255" value="{{ old('reference') }}"{{ $selectedPaymentMethod === 'cash' ? ' disabled' : '' }}
                 class="mt-1.5 w-full rounded-lg border border-surface-200 px-3 py-2 text-sm font-normal outline-none focus:border-brand-500">
        </label>
        <label class="block text-xs font-semibold text-surface-700">Received on
          <input type="date" name="paid_at" max="{{ now()->format('Y-m-d') }}" value="{{ old('paid_at', now()->format('Y-m-d')) }}"
                 class="mt-1.5 w-full rounded-lg border border-surface-200 px-3 py-2 text-sm font-normal outline-none focus:border-brand-500">
        </label>
      </div>
      <label class="mt-3 block text-xs font-semibold text-surface-700">Notes
        <input type="text" name="notes" maxlength="1000" value="{{ old('notes') }}"
               class="mt-1.5 w-full rounded-lg border border-surface-200 px-3 py-2 text-sm font-normal outline-none focus:border-brand-500">
      </label>
      <button type="submit" class="mt-3 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
        Record payment
      </button>
    </form>
    <script>
      document.querySelectorAll('[data-payment-record-form]').forEach(form => {
        const method = form.querySelector('[data-payment-method]');
        const fields = form.querySelector('[data-payment-fields]');
        const referenceField = form.querySelector('[data-payment-reference]');
        const referenceInput = referenceField?.querySelector('input[name="reference"]');
        if (!method || !fields || !referenceField || !referenceInput) return;

        function syncPaymentReference() {
          const isCash = method.value === 'cash';
          referenceField.hidden = isCash;
          referenceField.classList.toggle('hidden', isCash);
          referenceField.classList.toggle('block', !isCash);
          referenceInput.disabled = isCash;
          fields.classList.toggle('lg:grid-cols-3', isCash);
          fields.classList.toggle('lg:grid-cols-4', !isCash);
        }

        method.addEventListener('change', syncPaymentReference);
        syncPaymentReference();
      });
    </script>
  @elseif($isAdmin && $billed > 0)
    <p class="mt-4 rounded-lg border border-brand-100 bg-brand-50 px-3 py-2.5 text-sm font-semibold text-brand-800">
      This record is fully settled.
    </p>
  @endif
</div>
