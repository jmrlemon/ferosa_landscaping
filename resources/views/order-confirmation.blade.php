@extends('layouts.customer')

@section('content')
<main class="customer-page is-narrow">
  <div class="customer-card p-6 sm:p-8 reveal">
    <div class="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-600">
      <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
    </div>
    <p class="page-kicker">Order received</p>
    <h1 class="page-title">Thank you — we've got it.</h1>
    <p class="page-sub mb-6">Your order is recorded. Check <strong class="font-bold text-surface-700">{{ $order->user->email }}</strong> and Notifications for updates.</p>

    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs border-t border-surface-100 pt-5">
      <div>
        <dt class="text-surface-400 mb-0.5">Order number</dt>
        <dd class="font-mono font-semibold text-surface-900">{{ $order->order_number }}</dd>
      </div>
      <div>
        <dt class="text-surface-400 mb-0.5">Total</dt>
        <dd class="font-semibold text-surface-900">&#8369;{{ number_format((float) $order->total_amount, 2) }}</dd>
      </div>
      <div class="sm:col-span-2">
        <dt class="text-surface-400 mb-0.5">Payment status</dt>
        <dd class="font-semibold text-surface-900">{{ ucfirst(str_replace('_', ' ', $order->payment_status ?? 'unpaid')) }}</dd>
        @if($order->payment_method === 'gcash')
          <p class="mt-1 text-surface-500">Your receipt is private and is waiting for administrator verification.</p>
        @endif
      </div>
      <div class="sm:col-span-2">
        <dt class="text-surface-400 mb-2">Items</dt>
        <dd>
          @if ($order->orderItems->count())
            <ul class="divide-y divide-surface-100 rounded-lg border border-surface-100">
              @foreach ($order->orderItems as $line)
                <li class="flex justify-between gap-3 px-3 py-2.5 text-xs">
                  <span class="text-surface-800">{{ $line->name }} &times; {{ $line->qty }}</span>
                  <span class="text-surface-500">&#8369;{{ number_format((float) $line->price * $line->qty, 2) }}</span>
                </li>
              @endforeach
            </ul>
          @elseif (is_array($order->items) && count($order->items))
            <ul class="divide-y divide-surface-100 rounded-lg border border-surface-100">
              @foreach ($order->items as $line)
                <li class="flex justify-between gap-3 px-3 py-2.5 text-xs">
                  <span class="text-surface-800">{{ $line['name'] ?? 'Item' }} &times; {{ (int) ($line['qty'] ?? 1) }}</span>
                  <span class="text-surface-500">&#8369;{{ number_format((float) ($line['price'] ?? 0) * (int) ($line['qty'] ?? 1), 2) }}</span>
                </li>
              @endforeach
            </ul>
          @else
            <span class="text-surface-400">Line items not stored for this order.</span>
          @endif
        </dd>
      </div>
    </dl>

    <div class="mt-6 rounded-xl border border-brand-100 bg-brand-50 p-4">
      <p class="text-[10px] font-bold uppercase tracking-wider text-brand-600">What happens next</p>
      <ol class="mt-3 grid gap-3 text-xs leading-5 text-brand-900 sm:grid-cols-3">
        <li><strong class="block">1. Review</strong>Ferosa checks the order and payment details.</li>
        <li><strong class="block">2. Prepare</strong>Your items move through the selected delivery or pickup flow.</li>
        <li><strong class="block">3. Track</strong>Every status change appears in Orders and Notifications.</li>
      </ol>
    </div>

    <div class="mt-6 flex flex-wrap gap-2">
      <a href="{{ route('orders') }}" class="btn btn-primary">View my orders</a>
      <a href="{{ route('shop') }}" class="btn btn-secondary">Continue shopping</a>
    </div>
  </div>
</main>

@include('partials.mobile-bottom-customer')

<script>
  @if(session('clear_cart'))
  try { localStorage.removeItem('ferosa_cart'); } catch (e) {}
  @endif
</script>
@endsection
