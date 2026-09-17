@extends('layouts.customer')

@section('title', 'Claim '.$claim->claim_number.' - Ferosa Landscaping')

@section('content')
@php
  $statusLabel = ucfirst(str_replace('_', ' ', $claim->status));
  $statusTone = match($claim->status) {
    'resolved' => 'badge-success',
    'rejected', 'cancelled' => 'badge-danger',
    'needs_information' => 'badge-warning',
    default => 'badge-neutral',
  };
@endphp
<main class="customer-page is-narrow">
  <div class="mb-5 flex items-center justify-between gap-3">
    <a href="{{ route('orders') }}" class="text-sm font-semibold text-brand-700 hover:text-brand-900">&larr; Back to orders</a>
    <span class="badge {{ $statusTone }}">{{ $statusLabel }}</span>
  </div>

  @if(session('status'))<x-alert type="success" class="mb-5">{{ session('status') }}</x-alert>@endif

  <section class="customer-card overflow-hidden reveal">
    <header class="border-b border-surface-100 p-5 sm:p-7">
      <p class="page-kicker">Damage claim</p>
      <h1 class="page-title">{{ $claim->claim_number }}</h1>
      <p class="page-sub">Order {{ $claim->order->order_number }} · Submitted {{ optional($claim->submitted_at)->format('M d, Y h:i A') }}</p>
    </header>

    <div class="space-y-6 p-5 sm:p-7">
      <div>
        <h2 class="text-sm font-bold text-surface-900">Your summary</h2>
        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-surface-700">{{ $claim->customer_summary }}</p>
      </div>

      <div class="border-t border-surface-100 pt-6">
        <h2 class="text-sm font-bold text-surface-900">Affected items</h2>
        <div class="mt-3 space-y-3">
          @foreach($claim->items as $item)
            <article class="rounded-xl border border-surface-200 p-4">
              <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <h3 class="font-semibold text-surface-900">{{ $item->orderItem->name ?? 'Order item' }} &times; {{ $item->quantity_claimed }}</h3>
                  <p class="mt-1 text-xs text-surface-500">{{ ucfirst(str_replace('_', ' ', $item->issue_type)) }} · Preferred: {{ ucfirst($item->preferred_resolution) }}</p>
                </div>
                @if($item->resolution)<span class="badge badge-neutral">{{ ucfirst(str_replace('_', ' ', $item->resolution)) }}</span>@endif
              </div>
              <p class="mt-3 text-sm leading-6 text-surface-700">{{ $item->issue_description }}</p>
              @if($item->decision_note)<p class="mt-3 rounded-lg bg-brand-50 p-3 text-xs text-brand-900"><strong>Ferosa:</strong> {{ $item->decision_note }}</p>@endif
            </article>
          @endforeach
        </div>
      </div>

      <div class="border-t border-surface-100 pt-6">
        <h2 class="text-sm font-bold text-surface-900">Evidence</h2>
        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
          @foreach($claim->evidence as $evidence)
            <a href="{{ route('returns.evidence', [$claim, $evidence]) }}" target="_blank" rel="noopener" class="overflow-hidden rounded-xl border border-surface-200 bg-surface-50">
              <img src="{{ route('returns.evidence', [$claim, $evidence]) }}" alt="Damage evidence: {{ $evidence->original_name }}" class="aspect-square w-full object-cover">
            </a>
          @endforeach
        </div>
      </div>

      @if($claim->decision_reason)
        <div class="rounded-xl border border-brand-100 bg-brand-50 p-4">
          <h2 class="text-sm font-bold text-brand-950">Ferosa decision</h2>
          <p class="mt-2 text-sm leading-6 text-brand-900">{{ $claim->decision_reason }}</p>
        </div>
      @endif

      @if($claim->customer_contact_notes)
        <div class="rounded-xl border border-surface-200 bg-surface-50 p-4">
          <h2 class="text-sm font-bold text-surface-900">Your additional information</h2>
          <p class="mt-2 whitespace-pre-line text-sm text-surface-700">{{ $claim->customer_contact_notes }}</p>
        </div>
      @endif

      @if($claim->status === 'needs_information')
        <form method="POST" action="{{ route('returns.information', $claim) }}" class="rounded-xl border border-amber-200 bg-amber-50 p-4">
          @csrf
          <label class="block text-sm font-bold text-amber-950">Reply with the requested details
            <textarea name="customer_contact_notes" required minlength="10" maxlength="1000" rows="4" class="mt-2 block w-full rounded-lg border-amber-300 bg-white p-3 text-sm">{{ old('customer_contact_notes') }}</textarea>
          </label>
          @error('customer_contact_notes')<p class="mt-2 text-xs text-red-700">{{ $message }}</p>@enderror
          <button class="mt-3 rounded-lg bg-amber-700 px-4 py-2 text-sm font-bold text-white">Send information</button>
        </form>
      @endif

      @if($claim->refunds->whereNull('voided_at')->isNotEmpty())
        <div class="rounded-xl border border-blue-100 bg-blue-50 p-4">
          <h2 class="text-sm font-bold text-blue-950">Refunds</h2>
          @foreach($claim->refunds->whereNull('voided_at') as $refund)
            <p class="mt-2 text-sm text-blue-900">₱{{ number_format((float)$refund->amount, 2) }} via {{ $refund->methodLabel() }} on {{ optional($refund->refunded_at)->format('M d, Y') }}@if($refund->reference) · Ref. {{ $refund->reference }}@endif</p>
          @endforeach
        </div>
      @endif

      @if($claim->replacement_dispatched_at)
        <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-4">
          <h2 class="text-sm font-bold text-indigo-950">Replacement delivery</h2>
          <p class="mt-2 text-sm text-indigo-900">Dispatched {{ $claim->replacement_dispatched_at->format('M d, Y g:i A') }} with {{ $claim->replacement_driver_name }}@if($claim->replacement_driver_phone) · {{ $claim->replacement_driver_phone }}@endif.</p>
          @if($claim->replacement_dispatch_notes)<p class="mt-1 text-xs text-indigo-800">{{ $claim->replacement_dispatch_notes }}</p>@endif
        </div>
      @endif

      @if(in_array($claim->status, ['submitted', 'needs_information'], true))
        <form method="POST" action="{{ route('returns.cancel', $claim) }}" data-confirm-title="Cancel this claim?" data-confirm="Ferosa will stop reviewing this damage claim." data-confirm-action="Cancel claim">
          @csrf
          <button class="text-sm font-semibold text-red-700 hover:text-red-900">Cancel this claim</button>
        </form>
      @endif

      <div class="rounded-xl border border-surface-200 bg-surface-50 p-4 text-xs leading-5 text-surface-600">
        We will notify you here when the claim needs information, is approved, is rejected, or has been resolved.
      </div>
    </div>
  </section>
</main>

@include('partials.mobile-bottom-customer')
@endsection
