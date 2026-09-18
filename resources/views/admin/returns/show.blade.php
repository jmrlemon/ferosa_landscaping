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
                @if($isAdmin && $claim->return_required && $item->disposition !== 'restocked')
                  <form method="POST" action="{{ route('admin.returns.items.restock', [$claim, $item]) }}" class="mt-3 flex flex-wrap items-end gap-2">
                    @csrf
                    <label class="text-xs font-semibold">Inspected saleable quantity<input name="quantity" type="number" min="1" max="{{ $item->quantity_claimed }}" value="1" class="mt-1 block w-28 rounded-lg border-surface-300"></label>
                    <button class="rounded-lg border border-brand-300 px-3 py-2 text-xs font-bold text-brand-800">Return to stock</button>
                  </form>
                @endif
              </article>
            @endforeach
          </div>
        </section>

        @if($isAdmin && in_array($claim->status, ['submitted', 'under_review'], true))
          <section class="rounded-xl border border-brand-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-brand-950">Admin decision</h2>
            <p class="mt-1 text-sm text-surface-600">Choose an outcome for every line. Replacement stock is reserved immediately.</p>
            <form method="POST" action="{{ route('admin.returns.decision', $claim) }}" class="mt-5 space-y-5">
              @csrf @method('PUT')
              @foreach($claim->items as $index => $item)
                <fieldset class="rounded-xl border border-surface-200 p-4">
                  <legend class="px-1 font-bold">{{ $item->orderItem->name ?? 'Order item' }}</legend>
                  <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                  <div class="grid gap-3 sm:grid-cols-3">
                    <label class="text-sm font-semibold">Outcome
                      <select name="items[{{ $index }}][resolution]" required class="mt-1 block w-full rounded-lg border-surface-300">
                        <option value="replacement">Replacement</option>
                        <option value="refund">Full refund</option>
                        <option value="partial_refund">Partial refund</option>
                        <option value="rejected">Reject line</option>
                      </select>
                    </label>
                    <label class="text-sm font-semibold">Replacement qty<input name="items[{{ $index }}][replacement_quantity]" type="number" min="1" max="{{ $item->quantity_claimed }}" value="{{ $item->quantity_claimed }}" class="mt-1 block w-full rounded-lg border-surface-300"></label>
                    <label class="text-sm font-semibold">Refund amount<input name="items[{{ $index }}][refund_amount]" type="number" min="0.01" max="{{ $item->maximumRefundAmount() }}" step="0.01" class="mt-1 block w-full rounded-lg border-surface-300"></label>
                  </div>
                  <label class="mt-3 block text-sm font-semibold">Customer-visible line note<input name="items[{{ $index }}][decision_note]" maxlength="500" class="mt-1 block w-full rounded-lg border-surface-300"></label>
                </fieldset>
              @endforeach
              <label class="block text-sm font-semibold">Customer-visible decision reason<textarea name="decision_reason" required minlength="5" maxlength="1000" rows="3" class="mt-1 block w-full rounded-lg border-surface-300">{{ old('decision_reason') }}</textarea></label>
              <label class="block text-sm font-semibold">Private admin notes<textarea name="admin_notes" maxlength="2000" rows="2" class="mt-1 block w-full rounded-lg border-surface-300">{{ old('admin_notes') }}</textarea></label>
              <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="return_required" value="1" class="mt-1 rounded border-surface-300"><span><strong>Physical return required</strong><br><span class="text-surface-500">Leave clear by default for damaged live plants.</span></span></label>
              <label class="block text-sm font-semibold">Return instructions<textarea name="return_instructions" maxlength="1000" rows="2" class="mt-1 block w-full rounded-lg border-surface-300"></textarea></label>
              <button class="rounded-lg bg-brand-700 px-5 py-3 font-bold text-white hover:bg-brand-800">Save final decision</button>
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
                  @elseif($isAdmin)
                    <form method="POST" action="{{ route('admin.returns.refunds.void', [$claim, $refund]) }}" class="mt-2 flex flex-wrap gap-2">
                      @csrf @method('PUT')
                      <input name="void_reason" required minlength="10" maxlength="500" placeholder="Reason for correction" class="min-w-0 flex-1 rounded-lg border-surface-300 text-xs">
                      <button class="rounded-lg border border-red-200 px-2 py-1 font-bold text-red-700">Void</button>
                    </form>
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
                <label class="block text-sm font-semibold">Method<select name="method" class="mt-1 block w-full rounded-lg border-blue-300"><option value="cash">Cash</option><option value="gcash">GCash</option><option value="bank_transfer">Bank transfer</option><option value="other">Other</option></select></label>
                <label class="block text-sm font-semibold">Reference<input name="reference" maxlength="120" class="mt-1 block w-full rounded-lg border-blue-300"></label>
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
              <label class="block text-sm font-semibold">Driver or rider<input name="replacement_driver_name" required maxlength="120" class="mt-1 block w-full rounded-lg border-indigo-300"></label>
              <label class="block text-sm font-semibold">Contact<input name="replacement_driver_phone" maxlength="30" class="mt-1 block w-full rounded-lg border-indigo-300"></label>
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
