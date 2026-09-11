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
  $ledgerEntries = $payable->activePayments()->with('recordedBy')->get();
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
            @if($isAdmin)<th class="py-2 text-right font-semibold">Action</th>@endif
          </tr>
        </thead>
        <tbody class="divide-y divide-surface-50">
          @foreach($ledgerEntries as $payment)
            <tr>
              <td class="whitespace-nowrap py-2.5 pr-3 text-surface-500">{{ optional($payment->paid_at)->format('M d, Y') }}</td>
              <td class="py-2.5 pr-3">
                <span class="font-medium text-surface-800">{{ $payment->methodLabel() }}</span>
                @if($payment->recordedBy)<div class="text-[11px] text-surface-400">by {{ $payment->recordedBy->name }}</div>@endif
                @if($payment->notes)<div class="max-w-xs text-[11px] text-surface-400">{{ $payment->notes }}</div>@endif
              </td>
              <td class="py-2.5 pr-3 font-mono text-[11px] text-surface-500">{{ $payment->reference ?: '—' }}</td>
              <td class="whitespace-nowrap py-2.5 pr-3 text-right font-bold text-surface-900">PHP {{ number_format((float) $payment->amount, 2) }}</td>
              @if($isAdmin)
                <td class="py-2.5 text-right">
                  <details class="inline-block text-left">
                    <summary class="cursor-pointer list-none rounded border border-red-200 px-2 py-1 text-[11px] font-semibold text-red-600 hover:bg-red-50">Void</summary>
                    <form method="POST" action="{{ route('admin.payments.void', $payment) }}" class="mt-2 w-56 rounded-lg border border-surface-200 bg-white p-2 shadow-sm">
                      @csrf @method('PUT')
                      <input type="text" name="void_reason" required maxlength="255" placeholder="Reason for voiding"
                             class="w-full rounded border border-surface-200 px-2 py-1 text-[11px] outline-none focus:border-red-400">
                      <button type="submit" class="mt-1.5 w-full rounded bg-red-600 px-2 py-1 text-[11px] font-semibold text-white hover:bg-red-700">
                        Void this payment
                      </button>
                    </form>
                  </details>
                </td>
              @endif
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  @if($isAdmin && $balance > 0)
    <form method="POST" action="{{ $storeRoute }}" class="mt-4 rounded-lg border border-surface-200 bg-surface-50/60 p-4">
      @csrf
      <p class="text-xs font-bold uppercase tracking-wider text-surface-500">Record a payment</p>
      <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <label class="block text-xs font-semibold text-surface-700">Amount *
          <input type="number" name="amount" step="0.01" min="0.01" max="{{ $balance }}" value="{{ old('amount', $balance) }}" required
                 class="mt-1.5 w-full rounded-lg border border-surface-200 px-3 py-2 text-sm font-normal outline-none focus:border-brand-500">
          <span class="mt-1 block text-[11px] font-normal text-surface-400">Up to PHP {{ number_format($balance, 2) }}</span>
        </label>
        <label class="block text-xs font-semibold text-surface-700">Method *
          <select name="method" required class="mt-1.5 w-full rounded-lg border border-surface-200 px-3 py-2 text-sm font-normal outline-none focus:border-brand-500">
            <option value="cash">Cash</option>
            <option value="gcash">GCash</option>
            <option value="bank_transfer">Bank transfer</option>
            <option value="other">Other</option>
          </select>
        </label>
        <label class="block text-xs font-semibold text-surface-700">Reference
          <input type="text" name="reference" maxlength="255" value="{{ old('reference') }}"
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
  @elseif($isAdmin && $billed > 0)
    <p class="mt-4 rounded-lg border border-brand-100 bg-brand-50 px-3 py-2.5 text-sm font-semibold text-brand-800">
      This record is fully settled.
    </p>
  @endif
</div>
