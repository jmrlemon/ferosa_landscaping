@extends('admin.layouts.workspace')

@section('title', $claim->claim_number . ' - Ferosa Admin')
@section('admin-section', 'returns')
@section('skip-label', 'Skip to return claim')
@section('header-eyebrow', 'Ordering & delivery')
@section('header-title', 'Return claim review')

@section('content')
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex flex-wrap items-center gap-3">
        <h2 class="text-2xl font-bold text-brand-950">{{ $claim->claim_number }}</h2>
        <span class="badge badge-neutral">{{ ucfirst(str_replace('_', ' ', $claim->status)) }}</span>
      </div>
      <a href="{{ route('admin.returns.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-surface-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-surface-50">&larr; Claims queue</a>
    </div>
    @if(session('status'))<div class="mb-5 rounded-lg border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-800">{{ session('status') }}</div>@endif
    @if($errors->any())
      <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><p class="font-bold">Please correct the following:</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="grid gap-6 xl:grid-cols-5">
      <div class="space-y-6 xl:col-span-3">
        <section class="rounded-xl border border-surface-200 bg-white p-5 shadow-sm">
          <h2 class="text-lg font-bold text-brand-950">Customer report</h2>
          <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-surface-500">Customer</dt><dd class="font-semibold">{{ $claim->user->name }}</dd></div>
            <div><dt class="text-surface-500">Order</dt><dd><a class="font-semibold text-brand-700" href="{{ route('admin.orders.show', $claim->order) }}">{{ $claim->order->order_number }}</a></dd></div>
            <div><dt class="text-surface-500">Submitted</dt><dd>{{ optional($claim->submitted_at)->format('M d, Y g:i A') }}</dd></div>
            <div><dt class="text-surface-500">Contact</dt><dd>{{ $claim->user->phone_number ?: 'No phone number' }}</dd></div>
          </dl>
          <p class="mt-5 whitespace-pre-line rounded-lg bg-surface-50 p-4 text-sm leading-6">{{ $claim->customer_summary }}</p>
          @if($claim->customer_contact_notes)<p class="mt-3 whitespace-pre-line rounded-lg border border-brand-100 bg-brand-50 p-4 text-sm"><strong>Additional information:</strong><br>{{ $claim->customer_contact_notes }}</p>@endif
        </section>

        <section class="rounded-xl border border-surface-200 bg-white p-5 shadow-sm">
          <h2 class="text-lg font-bold text-brand-950">Evidence</h2>
          <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach($claim->evidence as $evidence)
              <a href="{{ route('returns.evidence', [$claim, $evidence]) }}" target="_blank" rel="noopener" class="overflow-hidden rounded-xl border border-surface-200">
                <img src="{{ route('returns.evidence', [$claim, $evidence]) }}" alt="Evidence: {{ $evidence->original_name }}" class="aspect-square w-full object-cover">
              </a>
            @endforeach
          </div>
        </section>

        <section class="rounded-xl border border-surface-200 bg-white p-5 shadow-sm">
          <h2 class="text-lg font-bold text-brand-950">Claimed items</h2>
          <div class="mt-4 space-y-4">
            @foreach($claim->items as $item)
              <article class="rounded-xl border border-surface-200 p-4">
                <div class="flex flex-wrap justify-between gap-3">
                  <div>
                    <h3 class="font-bold">{{ $item->orderItem->name ?? 'Order item' }} &times; {{ $item->quantity_claimed }}</h3>
                    <p class="text-xs text-surface-500">{{ ucfirst(str_replace('_', ' ', $item->issue_type)) }} · Prefers {{ ucfirst($item->preferred_resolution) }}</p>
                  </div>
                  <div class="text-right text-sm"><p>Paid value: ₱{{ number_format($item->maximumRefundAmount(), 2) }}</p><p>Stock: {{ $item->orderItem?->product?->stock_qty ?? 'Unavailable' }}</p></div>
                </div>
                <p class="mt-3 text-sm">{{ $item->issue_description }}</p>
                @if($item->resolution)
                  <div class="mt-3 rounded-lg bg-brand-50 p-3 text-sm">
                    <strong>{{ ucfirst(str_replace('_', ' ', $item->resolution)) }}</strong>
                    @if($item->replacement_quantity) · {{ $item->replacement_quantity }} replacement(s)@endif
                    @if((float)$item->refund_amount > 0) · ₱{{ number_format((float)$item->refund_amount, 2) }} approved refund @endif
                    @if($item->decision_note)<p class="mt-1">{{ $item->decision_note }}</p>@endif
                  </div>
                @endif
              </article>
            @endforeach
          </div>
        </section>

        @if($isAdmin && in_array($claim->status, ['submitted', 'under_review'], true))
          <section class="rounded-xl border border-brand-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="border-b border-surface-200 pb-4">
              <h2 class="text-lg font-bold text-brand-950">Admin decision</h2>
              <p class="mt-1 text-sm text-surface-600">Choose an outcome for every item. Replacement stock is reserved immediately.</p>
            </div>
            <form method="POST" action="{{ route('admin.returns.decision', $claim) }}" class="mt-5 space-y-5">
              @csrf @method('PUT')
              @foreach($claim->items as $index => $item)
                @php
                  $selectedResolution = old("items.$index.resolution", 'replacement');
                  $showReplacement = $selectedResolution === 'replacement';
                  $showRefund = in_array($selectedResolution, ['refund', 'partial_refund'], true);
                  $fullRefundAmount = number_format($item->maximumRefundAmount(), 2, '.', '');
                  $refundValue = $selectedResolution === 'refund'
                      ? $fullRefundAmount
                      : old("items.$index.refund_amount");
                @endphp
                <fieldset data-decision-item class="rounded-xl border border-surface-200 bg-surface-50 p-4 sm:p-5">
                  <legend class="rounded-full border border-surface-200 bg-white px-3 py-1 text-sm font-bold text-brand-950">{{ $item->orderItem->name ?? 'Order item' }}</legend>
                  <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                  <div class="mt-2 grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-semibold text-surface-800">Outcome
                      <select name="items[{{ $index }}][resolution]" required data-resolution-select class="mt-1 block w-full rounded-lg border-surface-300">
                        <option value="replacement" @selected(old("items.$index.resolution", 'replacement') === 'replacement')>Replacement</option>
                        <option value="refund" @selected(old("items.$index.resolution") === 'refund')>Full refund</option>
                        <option value="partial_refund" @selected(old("items.$index.resolution") === 'partial_refund')>Partial refund</option>
                        <option value="rejected" @selected(old("items.$index.resolution") === 'rejected')>Reject item</option>
                      </select>
                    </label>
                    <label data-resolution-field="replacement" @if(! $showReplacement) hidden @endif class="block text-sm font-semibold text-surface-800">Replacement quantity
                      <input name="items[{{ $index }}][replacement_quantity]" type="number" min="1" max="{{ $item->quantity_claimed }}" value="{{ old("items.$index.replacement_quantity", $item->quantity_claimed) }}" data-replacement-input @disabled(! $showReplacement) @required($showReplacement) class="mt-1 block w-full rounded-lg border-surface-300">
                    </label>
                    <label data-resolution-field="refund" @if(! $showRefund) hidden @endif class="block text-sm font-semibold text-surface-800">Refund amount
                      <input name="items[{{ $index }}][refund_amount]" type="number" min="0.01" max="{{ $fullRefundAmount }}" step="0.01" value="{{ $refundValue }}" placeholder="0.00" data-refund-input data-full-refund-amount="{{ $fullRefundAmount }}" @disabled(! $showRefund) @readonly($selectedResolution === 'refund') @required($selectedResolution === 'partial_refund') class="mt-1 block w-full rounded-lg border-surface-300">
                    </label>
                  </div>
                  <label class="mt-4 block text-sm font-semibold text-surface-800">Note for this item
                    <input name="items[{{ $index }}][decision_note]" type="text" maxlength="500" value="{{ old("items.$index.decision_note") }}" placeholder="Optional note shown to the customer" class="mt-1 block w-full rounded-lg border-surface-300">
                  </label>
                </fieldset>
              @endforeach
              <label class="block text-sm font-semibold text-surface-800">Decision message to customer
                <textarea name="decision_reason" required minlength="5" maxlength="1000" rows="3" placeholder="Explain why this outcome was chosen" class="mt-1 block w-full rounded-lg border-surface-300">{{ old('decision_reason') }}</textarea>
                <span class="mt-1 block text-xs font-normal text-surface-500">The customer will see this message with the final decision.</span>
              </label>
              <div class="rounded-xl border border-surface-200 bg-surface-50 p-4">
                <label class="flex items-start gap-3 text-sm">
                  <input type="checkbox" name="return_required" value="1" @checked(old('return_required')) class="mt-1 rounded border-surface-300">
                  <span><strong class="text-surface-800">Physical return required</strong><br><span class="text-surface-500">Leave unchecked by default for damaged live plants.</span></span>
                </label>
                <label class="mt-4 block text-sm font-semibold text-surface-800">Return instructions
                  <textarea name="return_instructions" maxlength="1000" rows="2" placeholder="Tell the customer where and when to return the item" class="mt-1 block w-full rounded-lg border-surface-300">{{ old('return_instructions') }}</textarea>
                </label>
              </div>
              <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-brand-700 px-5 py-3 font-bold text-white hover:bg-brand-800 sm:w-auto">Save final decision</button>
            </form>
          </section>
        @endif
      </div>

      <aside class="space-y-6 xl:col-span-2">
        <section class="rounded-xl border border-surface-200 bg-white p-5 shadow-sm">
          <h2 class="font-bold text-brand-950">Billing context</h2>
          <dl class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between"><dt>Paid</dt><dd class="font-semibold">₱{{ number_format($totalPaid, 2) }}</dd></div>
            <div class="flex justify-between"><dt>Refunded</dt><dd class="font-semibold">₱{{ number_format($totalRefunded, 2) }}</dd></div>
            <div class="flex justify-between border-t border-surface-200 pt-2"><dt>Available to refund</dt><dd class="font-bold">₱{{ number_format($refundableAmount, 2) }}</dd></div>
          </dl>
          @if($claim->refunds->isNotEmpty())
            <div class="mt-4 space-y-2">
              @foreach($claim->refunds as $refund)
                <div class="rounded-lg bg-surface-50 p-3 text-xs">
                  <p class="{{ $refund->isVoided() ? 'line-through text-surface-400' : '' }}">₱{{ number_format((float)$refund->amount, 2) }} via {{ $refund->methodLabel() }} · {{ optional($refund->refunded_at)->format('M d, Y') }}</p>
                  @if($refund->isVoided())
                    <p class="mt-1 text-red-700">Voided: {{ $refund->void_reason }}</p>
                  @endif
                </div>
              @endforeach
            </div>
          @endif
        </section>

        @if(in_array($claim->status, ['submitted', 'under_review'], true))
          <section class="rounded-xl border border-amber-200 bg-amber-50 p-5">
            <h2 class="font-bold text-amber-950">Review controls</h2>
            <form method="POST" action="{{ route('admin.returns.review', $claim) }}" class="mt-4 space-y-3">
              @csrf @method('PUT')
              <label class="block text-sm font-semibold">Action<select name="action" class="mt-1 block w-full rounded-lg border-amber-300"><option value="under_review">Mark under review</option><option value="needs_information">Request information</option></select></label>
              <label class="block text-sm font-semibold">Message to customer<textarea name="decision_reason" maxlength="1000" rows="3" class="mt-1 block w-full rounded-lg border-amber-300"></textarea></label>
              <button class="rounded-lg bg-amber-700 px-4 py-2 text-sm font-bold text-white">Update review</button>
            </form>
          </section>
        @endif

        @if($isAdmin && in_array($claim->status, ['approved', 'replacement_dispatched', 'resolved'], true) && $approvedRefundRemaining > 0)
          @if($maximumRefundAmount <= 0)
            <section class="rounded-xl border border-amber-200 bg-amber-50 p-5">
              <h2 class="font-bold text-amber-950">Payment required before refund</h2>
              <p class="mt-3 text-sm font-semibold text-amber-950">A PHP {{ number_format($approvedRefundRemaining, 2) }} refund is approved.</p>
              <p class="mt-2 text-sm leading-6 text-amber-900">No payment has been recorded for this order. Record the customer's payment first so the refund is tied to a real payment and the billing history remains accurate.</p>
              <a href="{{ route('admin.orders.show', $claim->order) }}" class="mt-4 inline-flex min-h-11 items-center justify-center rounded-lg bg-amber-800 px-4 py-2 text-sm font-bold text-white hover:bg-amber-900">Open order billing</a>
            </section>
          @else
            <section class="rounded-xl border border-blue-200 bg-blue-50 p-5">
              <h2 class="font-bold text-blue-950">Record refund</h2>
              <p class="mt-2 text-sm text-blue-900">PHP {{ number_format($approvedRefundRemaining, 2) }} is approved. PHP {{ number_format($maximumRefundAmount, 2) }} is currently available to refund.</p>
              <form method="POST" action="{{ route('admin.returns.refunds.store', $claim) }}" class="mt-4 space-y-3">
                @csrf
                <label class="block text-sm font-semibold">Amount<input name="amount" type="number" min="0.01" max="{{ number_format($maximumRefundAmount, 2, '.', '') }}" step="0.01" value="{{ number_format($maximumRefundAmount, 2, '.', '') }}" required class="mt-1 block w-full rounded-lg border-blue-300"></label>
                <div class="block text-sm font-semibold">
                  <span>Refund method</span>
                  <div class="mt-1 flex min-h-11 items-center rounded-lg border border-blue-300 bg-white px-3 py-2 text-surface-800">Cash</div>
                  <input type="hidden" name="method" value="cash">
                </div>
                <label class="block text-sm font-semibold">Notes<textarea name="notes" maxlength="1000" rows="2" class="mt-1 block w-full rounded-lg border-blue-300"></textarea></label>
                <button class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white">Record refund</button>
              </form>
            </section>
          @endif
        @endif

        @if($claim->status === 'approved' && $claim->items->sum('replacement_quantity') > 0)
          <section class="rounded-xl border border-indigo-200 bg-indigo-50 p-5">
            <h2 class="font-bold text-indigo-950">Dispatch replacement</h2>
            <form method="POST" action="{{ route('admin.returns.dispatch', $claim) }}" class="mt-4 space-y-3">
              @csrf
              <label class="block text-sm font-semibold">Driver or Rider Name
                <select name="replacement_driver_staff_id" required class="mt-1 block w-full rounded-lg border-indigo-300">
                  <option value="">Select a staff member</option>
                  @foreach($staffMembers as $staffMember)
                    <option value="{{ $staffMember->id }}" @selected((string) old('replacement_driver_staff_id') === (string) $staffMember->id)>{{ $staffMember->name }}</option>
                  @endforeach
                </select>
              </label>
              <p class="-mt-1 text-xs text-indigo-700">Only accounts assigned the Staff role are shown.</p>
              @if($staffMembers->isEmpty())
                <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">No staff accounts are available. Add or promote a staff member before dispatching.</p>
              @endif
              <label class="block text-sm font-semibold">Contact<input name="replacement_driver_phone" maxlength="30" class="mt-1 block w-full rounded-lg border-indigo-300"></label>
              <label class="block text-sm font-semibold">Estimated delivery date<input name="replacement_estimated_delivery_date" type="date" min="{{ now()->toDateString() }}" value="{{ old('replacement_estimated_delivery_date') }}" required class="mt-1 block w-full rounded-lg border-indigo-300"></label>
              <label class="block text-sm font-semibold">Notes<textarea name="replacement_dispatch_notes" maxlength="1000" rows="2" class="mt-1 block w-full rounded-lg border-indigo-300"></textarea></label>
              <button class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-bold text-white">Mark out for delivery</button>
            </form>
          </section>
        @endif

        @if($claim->status === 'replacement_dispatched')
          <section class="rounded-xl border border-brand-200 bg-brand-50 p-5">
            <h2 class="font-bold text-brand-950">Complete replacement</h2>
            <form method="POST" action="{{ route('admin.returns.resolve', $claim) }}" class="mt-4">
              @csrf
              <input type="hidden" name="replacement_delivered" value="1">
              <button class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-bold text-white">Confirm delivered and resolve</button>
            </form>
          </section>
        @endif
      </aside>
    </div>
@endsection

@push('head')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      document.querySelectorAll('[data-decision-item]').forEach(function (item) {
        const select = item.querySelector('[data-resolution-select]');
        const replacementField = item.querySelector('[data-resolution-field="replacement"]');
        const refundField = item.querySelector('[data-resolution-field="refund"]');
        const replacementInput = item.querySelector('[data-replacement-input]');
        const refundInput = item.querySelector('[data-refund-input]');

        if (!select || !replacementField || !refundField || !replacementInput || !refundInput) return;

        function updateOutcomeFields() {
          const resolution = select.value;
          const isReplacement = resolution === 'replacement';
          const isRefund = resolution === 'refund' || resolution === 'partial_refund';
          const isPartialRefund = resolution === 'partial_refund';

          replacementField.hidden = !isReplacement;
          replacementInput.disabled = !isReplacement;
          replacementInput.required = isReplacement;

          refundField.hidden = !isRefund;
          refundInput.disabled = !isRefund;
          refundInput.required = isPartialRefund;
          refundInput.readOnly = resolution === 'refund';

          if (resolution === 'refund') {
            refundInput.value = refundInput.dataset.fullRefundAmount;
          } else if (isPartialRefund && select.dataset.previousResolution === 'refund') {
            refundInput.value = '';
          }

          select.dataset.previousResolution = resolution;
        }

        select.addEventListener('change', updateOutcomeFields);
        updateOutcomeFields();
      });
    });
  </script>
@endpush
