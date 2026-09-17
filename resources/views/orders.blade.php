@extends('layouts.customer')

@section('title', 'Orders & Delivery - Ferosa Landscaping')

@section('content')
<main class="customer-page">
  <x-page-head
    kicker="Purchases"
    {{-- Literal "&": the component prints the title with {{ }}, so an HTML
         entity here would be escaped a second time and render as "&amp;". --}}
    title="Orders & delivery"
    sub="See what needs attention now and follow every update from the Ferosa team.">
    <x-slot:icon>
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5v-9Z"/><path d="m3 7.5 9 4.5m0 0 9-4.5M12 12v9"/>
      </svg>
    </x-slot:icon>
    <a href="{{ route('shop') }}" class="btn btn-secondary btn-sm">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 0 0-8 0v4M5 9h14l1 12H4L5 9Z"/></svg>
      Continue shopping
    </a>
  </x-page-head>

  @if (session('status'))
    <x-alert type="success" class="mb-6 reveal">{{ session('status') }}</x-alert>
  @endif
  @if (session('error'))
    <x-alert type="error" class="mb-6 reveal">{{ session('error') }}</x-alert>
  @endif

  <div class="customer-card mb-8 overflow-hidden reveal reveal-1">
    <div class="px-5 py-4 flex items-center justify-between gap-4 text-sm font-bold text-surface-800">
      <span>
        <span class="block">Track by order reference</span>
        <span class="block mt-0.5 text-xs font-normal text-surface-400">Use this if you have an order number such as FRS-98243.</span>
      </span>
      <span class="w-8 h-8 rounded-lg bg-brand-50 text-brand-700 flex items-center justify-center" aria-hidden="true">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      </span>
    </div>
    <div class="border-t border-surface-100 p-4 sm:p-5">
  <!-- Search -->
  <div class="mb-6 flex max-w-xl gap-2">
    <div class="field-icon flex-1">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="text" id="order-id" placeholder="e.g. FRS-98243" aria-label="Order reference" class="field font-mono uppercase">
    </div>
    <button onclick="trackOrder()" id="track-btn" class="btn btn-primary">
      Track
    </button>
  </div>

  <!-- Tracking Result -->
  <div id="tracking-result" class="hidden max-w-2xl rounded-xl border border-surface-200 bg-white p-5 sm:p-6" aria-live="polite">
    <div class="flex justify-between items-start mb-5 pb-4 border-b border-surface-100">
      <div>
        <h3 class="text-sm font-semibold text-surface-900 mb-0.5">Order <span id="display-id" class="text-brand-600 font-mono"></span></h3>
        <p class="text-xs text-surface-400" id="track-placed-at"></p>
      </div>
      <span class="badge badge-neutral" id="track-status-badge"></span>
    </div>

    <div class="relative ml-3">
      <div class="absolute left-[7px] top-1 bottom-4 w-px bg-surface-200"></div>
      <div class="absolute left-[7px] top-1 w-px bg-brand-500" id="track-progress-bar" style="height:0"></div>

      <div class="relative flex gap-4 mb-6">
        <div class="relative z-10 w-4 h-4 rounded-full bg-brand-500 flex items-center justify-center ring-2 ring-white shrink-0">
          <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div>
          <p class="text-sm font-medium text-surface-900">Order Placed</p>
          <p class="text-xs text-surface-400" id="track-date-placed"></p>
        </div>
      </div>

      <div class="relative flex gap-4 mb-6" id="track-step-confirmed">
        <div class="relative z-10 w-4 h-4 rounded-full ring-2 ring-white shrink-0 flex items-center justify-center" id="track-dot-confirmed"></div>
        <div>
          <p class="text-sm font-medium" id="track-label-confirmed">Confirmed</p>
          <p class="text-xs text-surface-400" id="track-desc-confirmed">Preparing your order</p>
        </div>
      </div>

      <div class="relative flex gap-4 mb-6" id="track-step-ofd">
        <div class="relative z-10 w-4 h-4 rounded-full ring-2 ring-white shrink-0 flex items-center justify-center" id="track-dot-ofd"></div>
        <div>
          <p class="text-sm font-medium" id="track-label-ofd">Out for Delivery</p>
          <p class="text-xs text-surface-400" id="track-desc-ofd">Pending</p>
        </div>
      </div>

      <div class="relative flex gap-4">
        <div class="relative z-10 w-4 h-4 rounded-full ring-2 ring-white shrink-0" id="track-dot-delivered"></div>
        <div>
          <p class="text-sm font-medium" id="track-label-delivered">Delivered</p>
          <p class="text-xs text-surface-400" id="track-desc-delivered">Pending</p>
        </div>
      </div>
    </div>

    <div id="track-proof-card" class="hidden mt-5 pt-4 border-t border-surface-100">
      <p class="text-xs font-semibold text-surface-900 mb-2">Delivery Proof</p>
      <button id="track-proof-link" type="button" onclick="openDeliveryProof(this.dataset.proofSrc, this.dataset.proofAlt)" class="inline-block overflow-hidden rounded-lg border border-surface-100 bg-surface-50 text-left cursor-zoom-in">
        {{-- No empty src: the tracking script sets it. An `src=""` resolves to
             the current page, so every visit silently re-requested the whole
             orders document and tried to decode it as an image. --}}
        <img id="track-proof-img" alt="Delivery proof" class="w-full max-h-44 object-cover">
      </button>
      <p class="text-xs text-surface-500 mt-2" id="track-proof-meta"></p>
    </div>
  </div>

  <!-- Empty State -->
  <div id="empty-state" class="hidden customer-empty shadow-none mt-5" role="status" aria-live="polite">
    <div class="customer-empty-icon">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    </div>
    <h2 class="text-sm font-semibold text-surface-900 mb-1">Order not found</h2>
    <p class="text-surface-400 text-sm">Check the order ID and try again.</p>
  </div>
    </div>
  </div>

  <!-- Order History -->
  <div>
    <div class="flex items-end justify-between gap-4 mb-6">
      <div>
        <p class="text-[11px] font-bold uppercase tracking-[.13em] text-brand-600">Your activity</p>
        <h2 class="mt-1 text-xl font-display font-bold text-surface-900">Order history</h2>
        <p class="mt-1 text-xs text-surface-400">Your purchases, current status, and delivery details.</p>
      </div>
    </div>

    <!-- Status quick filters -->
    <div class="mb-4 flex flex-wrap items-center gap-2">
      <a href="{{ route('orders', array_filter(['from' => request('from'), 'to' => request('to')])) }}"
         class="chip {{ request('status') ? '' : 'chip-active' }}">All</a>
      @foreach (['pending','confirmed','out_for_delivery','delivered','completed','cancelled'] as $st)
        <a href="{{ route('orders', array_filter(['status' => $st, 'from' => request('from'), 'to' => request('to')])) }}"
           class="chip {{ request('status') === $st ? 'chip-active' : '' }}">{{ ucfirst(str_replace('_',' ', $st)) }}</a>
      @endforeach
    </div>

    <!-- Date range -->
    <form method="GET" action="{{ route('orders') }}" class="toolbar mb-5" data-loading-label="Filtering...">
      <input type="hidden" name="status" value="{{ request('status') }}">
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-12 sm:items-end">
        <div class="sm:col-span-4">
          <label for="order-from" class="field-label">From date</label>
          <input type="date" id="order-from" name="from" value="{{ request('from') }}" class="field">
        </div>
        <div class="sm:col-span-4">
          <label for="order-to" class="field-label">To date</label>
          <input type="date" id="order-to" name="to" value="{{ request('to') }}" class="field">
        </div>
        <div class="col-span-2 flex gap-2 sm:col-span-4">
          <button class="btn btn-primary btn-sm flex-1" data-loading-label="Filtering...">Apply dates</button>
          @if(request('from') || request('to'))
            <a href="{{ route('orders', array_filter(['status' => request('status')])) }}" class="btn btn-ghost btn-sm">Reset</a>
          @endif
        </div>
      </div>
      @if ($errors->any())
        <div class="mt-3">
          <x-alert type="error">
            <ul class="list-disc space-y-0.5 pl-4">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </x-alert>
        </div>
      @endif
    </form>

    <!-- Orders List -->
    <div class="space-y-4">
      @forelse ($orders as $order)
        @php
          $status = $order->status ?? 'pending';
          $isPickupOrder = ($order->delivery_method ?? 'delivery') === 'pickup';
          $badge = match ($status) {
            'pending' => 'badge-warning',
            'confirmed', 'out_for_delivery' => 'badge-info',
            'delivered', 'completed' => 'badge-success',
            default => 'badge-danger',
          };
          $statusLabel = match (true) {
            $isPickupOrder && $status === 'out_for_delivery' => 'Ready for Pickup',
            $isPickupOrder && $status === 'delivered' && ! $order->customer_confirmed_at => 'Picked Up - Pending Confirmation',
            $status === 'delivered' && ! $order->customer_confirmed_at => 'Delivered - Pending Confirmation',
            default => ucfirst(str_replace('_',' ', $status)),
          };
          $balanceDue = $order->balanceDue();
          $amountPaid = $order->totalPaid();
          $amountRefunded = $order->totalRefunded();
          $netPaid = round(max(0, $amountPaid - $amountRefunded), 2);
          $needsTeamUpdate = $order->needsStatusFollowUp();
          $latestClaim = $order->returnRequests->sortByDesc('created_at')->first();
        @endphp

        <div class="customer-card lift overflow-hidden">
          <div class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 border-b border-surface-100">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-[15px] font-bold text-surface-900">
                  Order <span class="font-mono text-brand-600">{{ $order->order_number }}</span>
                </h3>
                <span class="badge {{ $badge }}">
                  {{ $statusLabel }}
                </span>
                @if ($needsTeamUpdate)
                  <span class="badge badge-danger">Needs team update</span>
                @endif
              </div>
              <p class="mt-1 text-xs font-medium text-surface-500">
                Placed {{ optional($order->created_at)->format('M d, Y h:i A') }}
              </p>
              @if ($needsTeamUpdate)
                <p class="mt-2 max-w-xl text-xs leading-5 text-amber-700">
                  This order has had no status change for over {{ \App\Models\Order::FOLLOW_UP_AFTER_DAYS }} days.
                  <a href="{{ route('messages') }}" class="font-bold underline underline-offset-2">Message the team</a>
                  for an update.
                </p>
              @endif
            </div>
            <div class="sm:text-right flex flex-row sm:flex-col items-center sm:items-end justify-between sm:justify-start gap-2">
              <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-surface-400">Total</p>
                <p class="font-display text-xl font-bold text-surface-900">&#8369;{{ number_format((float) $order->total_amount, 2) }}</p>
                @if ($balanceDue > 0 && $amountPaid > 0)
                  <p class="text-[11px] font-semibold text-orange-600">
                    &#8369;{{ number_format($amountPaid, 2) }} paid · &#8369;{{ number_format($balanceDue, 2) }} due
                  </p>
                @elseif ($balanceDue <= 0 && (float) $order->total_amount > 0)
                  <p class="text-[11px] font-semibold text-brand-700">Fully paid</p>
                @endif
                @if($amountRefunded > 0)
                  <p class="text-[11px] font-semibold text-blue-700">
                    &#8369;{{ number_format($amountRefunded, 2) }} refunded · &#8369;{{ number_format($netPaid, 2) }} net paid
                  </p>
                @endif
              </div>
              <div class="flex items-center flex-wrap gap-1.5 justify-end">
                @if($order->canOpenReturnRequest())
                  <a href="{{ route('returns.create', $order) }}" class="btn btn-sm border-red-200 bg-red-50 text-red-700 hover:border-red-300 hover:bg-red-100">
                    Report damaged plant
                  </a>
                @endif
                @if($latestClaim)
                  <a href="{{ route('returns.show', $latestClaim) }}" class="btn btn-soft btn-sm">
                    {{ $latestClaim->claim_number }} · {{ ucfirst(str_replace('_', ' ', $latestClaim->status)) }}
                  </a>
                @endif
                @if ($status === 'delivered' && ($isPickupOrder || $order->delivery_proof_url))
                  <form method="POST" action="{{ route('orders.confirm-received', $order) }}">
                    @csrf
                    <button type="submit" data-loading-label="Confirming..." class="btn btn-soft btn-sm">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8"><polyline points="20 6 9 17 4 12"/></svg>
                      {{ $isPickupOrder ? 'Confirm pickup' : 'Confirm received' }}
                    </button>
                  </form>
                @endif
                @if ($status === 'completed' && !$order->feedback)
                  <button onclick="openFeedbackModal({{ $order->id }}, '{{ $order->order_number }}')"
                    class="btn btn-sm border-amber-200 bg-amber-50 text-amber-700 hover:border-amber-300 hover:bg-amber-100">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    Leave feedback
                  </button>
                @elseif ($status === 'completed' && $order->feedback)
                  <span class="badge badge-success">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8"><polyline points="20 6 9 17 4 12"/></svg>
                    Reviewed
                  </span>
                @endif
                <a href="{{ route('orders.invoice', $order) }}" class="btn btn-secondary btn-sm">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/><line x1="12" y1="17" x2="8" y2="17"/>
                  </svg>
                  Invoice
                </a>
                @if ($order->hasFinalReceipt())
                  <a href="{{ route('orders.receipt', $order) }}" class="btn btn-secondary btn-sm">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                      <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                    </svg>
                    Receipt
                  </a>
                @endif
                @if ($order->isCustomerCancellable())
                  <button onclick="openCancelModal({{ $order->id }}, '{{ $order->order_number }}')"
                    class="inline-flex items-center gap-1 text-[11px] font-medium text-red-600 hover:text-red-800 border border-red-200 hover:border-red-400 bg-red-50 hover:bg-red-100 px-2.5 py-1.5 rounded-lg transition-colors touch-manipulation min-h-[34px]">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                    Cancel
                  </button>
                @endif
              </div>
            </div>
          </div>

          @if($order->dispatch_proof_url || $order->delivery_proof_url || $status === 'cancelled')
            <div class="p-4 sm:p-5 grid grid-cols-1 sm:grid-cols-2 gap-4 border-b border-surface-100">
              @if($order->dispatch_proof_url)
                <div class="rounded-lg border border-indigo-100 bg-indigo-50/50 p-3 text-xs">
                  <p class="font-semibold text-indigo-700 mb-1.5">Dispatch Information</p>
                  <button type="button"
                    data-proof-src="{{ route('orders.dispatch-proof', $order) }}"
                    data-proof-alt="Dispatch proof for order {{ $order->order_number }}"
                    data-proof-title="Dispatch Proof"
                    onclick="openDeliveryProof(this.dataset.proofSrc, this.dataset.proofAlt, this.dataset.proofTitle)"
                    class="block w-full overflow-hidden rounded-lg border border-indigo-100 bg-white mb-2 text-left cursor-zoom-in"
                    aria-label="View dispatch proof for order {{ $order->order_number }}">
                    <img src="{{ route('orders.dispatch-proof', $order) }}" alt="Dispatch proof for order {{ $order->order_number }}" loading="lazy" decoding="async" class="w-full h-32 object-cover">
                  </button>
                  <p class="text-surface-500">Dispatched: {{ optional($order->dispatched_at)->format('M d, Y h:i A') ?? 'Pending timestamp' }}</p>
                  <p class="text-surface-500">Driver: {{ $order->driver_name ?: 'Not recorded' }}{{ $order->driver_phone ? ' · '.$order->driver_phone : '' }}</p>
                </div>
              @endif
              @if($order->delivery_proof_url)
                <div class="rounded-lg border border-brand-100 bg-brand-50/50 p-3 text-xs">
                  <p class="font-semibold text-brand-700 mb-1.5">Delivery Proof</p>
                  <button type="button"
                    data-proof-src="{{ route('orders.delivery-proof', $order) }}"
                    data-proof-alt="Delivery proof for order {{ $order->order_number }}"
                    onclick="openDeliveryProof(this.dataset.proofSrc, this.dataset.proofAlt)"
                    class="block w-full overflow-hidden rounded-lg border border-brand-100 bg-white mb-2 text-left cursor-zoom-in"
                    aria-label="View delivery proof for order {{ $order->order_number }}">
                    <img src="{{ route('orders.delivery-proof', $order) }}" alt="Delivery proof for order {{ $order->order_number }}" loading="lazy" decoding="async" class="w-full h-32 object-cover">
                  </button>
                  <p class="text-surface-500">Delivered: {{ optional($order->delivered_at)->format('M d, Y h:i A') ?? 'Pending timestamp' }}</p>
                  @if($order->delivery_recipient_name)<p class="text-surface-500">Received by: {{ $order->delivery_recipient_name }}</p>@endif
                  @if($order->customer_confirmed_at)
                    <p class="text-brand-700 font-medium">Confirmed received: {{ optional($order->customer_confirmed_at)->format('M d, Y h:i A') }}</p>
                  @else
                    <p class="text-amber-700 font-medium">Waiting for your confirmation.</p>
                  @endif
                </div>
              @endif
              @if($status === 'cancelled')
                <div class="rounded-lg border border-red-100 bg-red-50 p-3 text-xs">
                  <p class="font-semibold text-red-700 mb-1">Cancellation Record</p>
                  <p class="text-surface-500">
                    Cancelled: {{ optional($order->cancelled_at)->format('M d, Y h:i A') ?? 'Recorded' }}
                  </p>
                  @if($order->cancel_reason)
                    <p class="text-surface-500 mt-1">Reason: {{ $order->cancel_reason }}</p>
                  @endif
                </div>
              @endif
            </div>
          @endif

          <div class="p-4 sm:p-5 grid grid-cols-1 lg:grid-cols-3 gap-5">
            <!-- Items -->
            <div>
              <h4 class="text-xs font-semibold text-surface-900 mb-2">Items</h4>
              @if (is_array($order->items) && count($order->items))
                <ul class="divide-y divide-surface-100 rounded-lg border border-surface-100">
                  @foreach ($order->items as $line)
                    @php
                      $qty = (int) ($line['qty'] ?? $line['quantity'] ?? 1);
                      $price = (float) ($line['price'] ?? 0);
                      $name = (string) ($line['name'] ?? 'Item');
                    @endphp
                    <li class="flex items-center justify-between gap-3 px-3 py-2.5 text-xs">
                      <div class="min-w-0">
                        <p class="font-medium text-surface-900 truncate">{{ $name }}</p>
                        <p class="text-surface-400">Qty: {{ $qty }}</p>
                      </div>
                      <div class="text-right shrink-0">
                        <p class="font-medium text-surface-900">&#8369;{{ number_format($price * $qty, 2) }}</p>
                        <p class="text-surface-400">&#8369;{{ number_format($price, 2) }} each</p>
                      </div>
                    </li>
                  @endforeach
                </ul>
              @else
                <p class="text-xs text-surface-400">No line items stored for this order.</p>
              @endif
            </div>

            <!-- Delivery Info -->
            <div>
              <h4 class="text-xs font-semibold text-surface-900 mb-2">Delivery Info</h4>
              <div class="rounded-lg border border-surface-100 p-3 space-y-2 text-xs">
                @php
                  $dMethod = $order->delivery_method ?? 'delivery';
                  $pMethod = $order->payment_method ?? 'cod';
                @endphp
                <div class="flex items-center gap-2">
                  <span class="text-surface-400">Method:</span>
                  @if($dMethod === 'pickup')
                    <span class="bg-purple-50 text-purple-700 border border-purple-100 px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide">Pick-up</span>
                  @else
                    <span class="bg-blue-50 text-blue-700 border border-blue-100 px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide">Delivery</span>
                  @endif
                </div>
                @if($dMethod === 'delivery' && $order->delivery_address)
                  <div>
                    <p class="text-surface-400 mb-0.5">Address:</p>
                    <p class="text-surface-700 leading-relaxed">
                      {{ $order->delivery_name }}<br>
                      {{ $order->delivery_address }}, {{ $order->delivery_city }}<br>
                      {{ $order->delivery_phone }}
                    </p>
                  </div>
                @elseif($dMethod === 'pickup')
                  <p class="text-surface-400 text-xs">A. Arellano Ave. Mulawin, Orani, Philippines 2112</p>
                @endif
                <div class="flex items-center gap-2 pt-1 border-t border-surface-100">
                  <span class="text-surface-400">Payment:</span>
                  @if($pMethod === 'gcash')
                    <span class="bg-sky-50 text-sky-700 border border-sky-100 px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide">GCash</span>
                  @else
                    <span class="bg-amber-50 text-amber-700 border border-amber-100 px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide">COD</span>
                  @endif
                </div>
                <div class="flex items-center justify-between gap-2 border-t border-surface-100 pt-2">
                  <span class="text-surface-400">Payment status:</span>
                  <span class="font-semibold {{ $order->payment_status === 'paid' ? 'text-brand-700' : ($order->payment_status === 'rejected' ? 'text-red-700' : 'text-amber-700') }}">
                    {{ ucfirst(str_replace('_', ' ', $order->payment_status ?? 'unpaid')) }}
                  </span>
                </div>
                @if($order->payment_review_notes)
                  <p class="rounded-md bg-amber-50 px-2 py-1.5 text-amber-900">{{ $order->payment_review_notes }}</p>
                @endif
                @if($order->payment_proof_path)
                  <a href="{{ route('orders.payment-proof', $order) }}" target="_blank" rel="noopener" class="inline-flex text-xs font-semibold text-sky-700 hover:text-sky-900">View submitted receipt</a>
                @endif
              </div>
            </div>

            <!-- Timeline -->
            <div>
              <h4 class="text-xs font-semibold text-surface-900 mb-2">Delivery Timeline</h4>
              @php
                $step = $status === 'pending' ? 1 : ($status === 'confirmed' ? 2 : ($status === 'out_for_delivery' ? 3 : (in_array($status, ['delivered','completed']) ? 4 : 0)));
                $steps = [
                  ['label' => 'Order Placed', 'desc' => optional($order->created_at)->format('M d, Y h:i A')],
                  ['label' => 'Confirmed', 'desc' => $status === 'pending' ? 'Pending' : 'Preparing your items'],
                  ['label' => 'Out for Delivery', 'desc' => $status === 'out_for_delivery' ? 'Driver is on the way' : 'Pending'],
                  ['label' => 'Delivered', 'desc' => $status === 'completed' ? 'Confirmed received' : ($status === 'delivered' ? 'Waiting for your confirmation' : 'Pending')],
                ];
              @endphp

              <div class="relative ml-1.5">
                <div class="absolute left-[5px] top-1 bottom-1 w-px bg-surface-200"></div>
                <div class="space-y-4">
                  @foreach ($steps as $i => $s)
                    @php
                      $idx = $i + 1;
                      $done = $step > 0 && $idx < $step;
                      $active = $step === $idx;
                      $circle =
                        $done ? 'bg-brand-600 ring-brand-50' :
                        ($active ? 'bg-brand-600 ring-brand-50' : 'bg-white border-surface-300 ring-white');
                      $text =
                        $done ? 'text-surface-900' :
                        ($active ? 'text-brand-700' : 'text-surface-350');
                    @endphp
                    <div class="relative flex gap-3">
                      <div class="relative z-10 w-3 h-3 mt-0.5 rounded-full ring-2 {{ $circle }} flex items-center justify-center border {{ $done || $active ? 'border-brand-600' : 'border-surface-300' }}">
                        @if ($done)
                          <svg width="7" height="7" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                        @elseif ($active)
                          <div class="w-1 h-1 bg-white rounded-full"></div>
                        @endif
                      </div>
                      <div class="min-w-0">
                        <p class="text-xs font-medium {{ $text }}">{{ $s['label'] }}</p>
                        <p class="text-[10px] {{ $active ? 'text-surface-600' : 'text-surface-400' }}">{{ $s['desc'] }}</p>
                        @if ($status === 'cancelled' && $i === 0)
                          <p class="text-[10px] text-red-600 font-semibold mt-0.5">Cancelled</p>
                        @endif
                      </div>
                    </div>
                  @endforeach
                </div>
              </div>
            </div>
          </div>
        </div>
      @empty
        <div class="customer-empty">
          <div class="customer-empty-icon">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
          </div>
          <h2 class="text-base font-bold text-surface-900 mb-1">No orders yet</h2>
          <p class="text-surface-500 text-sm mb-5">Your purchases and delivery updates will appear here.</p>
          <a href="{{ route('shop') }}" class="btn btn-primary btn-sm">Browse products</a>
        </div>
      @endforelse
    </div>

    @if (method_exists($orders, 'links'))
      <div class="mt-6">
        {{ $orders->links() }}
      </div>
    @endif
  </div>
</main>

{{-- Delivery proof stays on this page in an image viewer. --}}
<div id="delivery-proof-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="delivery-proof-title">
  <button type="button" class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="closeDeliveryProof()" aria-label="Close delivery proof"></button>
  <div class="relative flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
    <div class="flex items-center justify-between border-b border-surface-100 px-4 py-3 sm:px-5">
      <h3 id="delivery-proof-title" class="text-sm font-semibold text-surface-900">Delivery Proof</h3>
      <button type="button" onclick="closeDeliveryProof()" class="rounded-lg p-2 text-surface-500 hover:bg-surface-100 hover:text-surface-800" aria-label="Close delivery proof">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="flex h-[min(78vh,720px)] min-h-[240px] w-full items-center justify-center bg-surface-950 p-3 sm:p-5">
      <img id="delivery-proof-modal-image" alt="Delivery proof" class="h-full w-full object-contain">
    </div>
  </div>
</div>

{{-- ── Cancel Order Modal ──────────────────────────────────────────── --}}
<div id="cancel-modal" class="fixed inset-0 z-50 hidden flex items-end sm:items-center justify-center sm:p-4">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeCancelModal()"></div>
  <div class="relative bg-white w-full sm:max-w-sm rounded-t-2xl sm:rounded-2xl shadow-2xl animate-modal-up sm:animate-fade-in">
    <div class="flex justify-center pt-3 pb-1 sm:hidden">
      <div class="w-10 h-1 bg-surface-200 rounded-full"></div>
    </div>
    <div class="px-4 sm:px-6 pt-4 sm:pt-5 pb-5 sm:pb-6">
      <div class="flex items-start gap-3 mb-3">
        <div class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center shrink-0">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-red-500"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div>
          <h3 class="text-base font-semibold text-surface-900">Cancel Order?</h3>
          <p class="text-xs text-surface-400 mt-0.5">Order <span id="cancel-order-number" class="font-mono text-brand-600 font-semibold"></span></p>
        </div>
      </div>
      <p class="text-sm text-surface-500 mb-5">Are you sure you want to cancel this order? This action cannot be undone.</p>
      <form id="cancel-order-form" method="POST">
        @csrf
        @method('DELETE')
        <label for="order-cancel-reason" class="block text-xs font-medium text-surface-600 mb-1">Reason <span class="text-surface-400">(optional)</span></label>
        <textarea id="order-cancel-reason" name="cancel_reason" rows="3" maxlength="500"
          class="w-full border border-surface-200 rounded-xl px-3 py-2.5 text-sm text-surface-700 outline-none focus:border-red-400 focus:ring-1 focus:ring-red-100 resize-none transition-all mb-4"
          placeholder="Tell us why you want to cancel this order."></textarea>
        <div class="flex flex-col-reverse sm:flex-row gap-2 sm:gap-3">
          <button type="button" onclick="closeCancelModal()"
            class="py-3 sm:py-2.5 sm:px-5 rounded-xl border border-surface-200 text-sm font-medium text-surface-500 hover:bg-surface-50 transition-colors touch-manipulation">
            Keep Order
          </button>
          <button type="submit" data-loading-label="Cancelling..."
            class="flex-1 bg-red-600 hover:bg-red-700 active:bg-red-800 text-white text-sm font-medium py-3 sm:py-2.5 rounded-xl transition-colors touch-manipulation">
            Yes, Cancel Order
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- ── Feedback Modal ─────────────────────────────────────────────── --}}
<div id="feedback-modal" class="fixed inset-0 z-50 hidden flex items-end sm:items-center justify-center sm:p-4">
  {{-- backdrop --}}
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeFeedbackModal()"></div>

  <div class="relative bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl shadow-2xl animate-modal-up sm:animate-fade-in">
    {{-- drag handle - mobile only --}}
    <div class="flex justify-center pt-3 pb-1 sm:hidden">
      <div class="w-10 h-1 bg-surface-200 rounded-full"></div>
    </div>

    <div class="px-4 sm:px-6 pt-2 sm:pt-5 pb-5 sm:pb-6">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-base font-semibold text-surface-900">Rate Your Order</h3>
          <p class="text-xs text-surface-400 mt-0.5">Order <span id="modal-order-number" class="font-mono text-brand-600 font-semibold"></span></p>
        </div>
        <button type="button" aria-label="Close dialog" onclick="closeFeedbackModal()" class="p-1.5 text-surface-400 hover:text-surface-600 transition-colors rounded-lg">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <form method="POST" action="{{ route('feedback.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="order_id" id="modal-order-id">

        {{-- Star rating --}}
        <div>
          <label class="block text-xs font-medium text-surface-600 mb-3">Rating <span class="text-red-500">*</span></label>
          <div class="flex justify-between sm:justify-start sm:gap-3" id="modal-star-group">
            @for ($i = 1; $i <= 5; $i++)
              <button type="button" data-value="{{ $i }}"
                class="modal-star text-5xl sm:text-4xl text-surface-350 hover:text-amber-400 active:scale-90 transition-all focus:outline-none leading-none touch-manipulation"
                aria-label="{{ $i }} star">&#9733;</button>
            @endfor
          </div>
          <input type="hidden" name="rating" id="modal-rating-input" value="">
        </div>

        {{-- Comment --}}
        <div>
          <label for="order-feedback-comment" class="block text-xs font-medium text-surface-600 mb-1">Comment <span class="text-surface-400">(optional)</span></label>
          <textarea id="order-feedback-comment" name="comment" rows="3"
            class="w-full border border-surface-200 rounded-xl px-3 py-2.5 text-sm text-surface-700 outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-100 resize-none transition-all"
            placeholder="Tell us about your experience..."></textarea>
        </div>

        <div class="flex flex-col-reverse sm:flex-row gap-2 sm:gap-3 pt-1">
          <button type="button" onclick="closeFeedbackModal()"
            class="py-3 sm:py-2.5 sm:px-4 rounded-xl border border-surface-200 text-sm font-medium text-surface-500 hover:bg-surface-50 transition-colors touch-manipulation">
            Cancel
          </button>
          <button type="submit" id="modal-submit-btn" data-loading-label="Submitting..."
            class="btn btn-primary flex-1 touch-manipulation">
            Submit feedback
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
@keyframes fade-in   { from { opacity:0; transform:scale(.95); }    to { opacity:1; transform:scale(1); } }
@keyframes modal-up  { from { transform:translateY(100%); opacity:0; } to { transform:translateY(0); opacity:1; } }
.animate-fade-in  { animation: fade-in  .18s ease-out; }
.animate-modal-up { animation: modal-up .25s cubic-bezier(.32,1,.5,1); }
</style>

@include('partials.mobile-bottom-customer')
@endsection

@section('scripts')
<script>
  const CSRF = document.querySelector('meta[name="csrf-token"]')?.content
            || '{{ csrf_token() }}';

  const deliveryProofModal = document.getElementById('delivery-proof-modal');
  const deliveryProofImage = document.getElementById('delivery-proof-modal-image');
  const deliveryProofTitle = document.getElementById('delivery-proof-title');

  function openDeliveryProof(src, alt, title = 'Delivery Proof') {
    if (!src) return;
    deliveryProofImage.src = src;
    deliveryProofImage.alt = alt || 'Delivery proof';
    deliveryProofTitle.textContent = title;
    deliveryProofModal.classList.remove('hidden');
    deliveryProofModal.classList.add('flex');
    document.body.style.overflow = 'hidden';
  }

  function closeDeliveryProof() {
    deliveryProofModal.classList.add('hidden');
    deliveryProofModal.classList.remove('flex');
    deliveryProofImage.removeAttribute('src');
    document.body.style.overflow = '';
  }

  // ── Status helpers ─────────────────────────────────────────────────────
  const STATUS_BADGE = {
    pending:          'badge-warning',
    confirmed:        'badge-info',
    out_for_delivery: 'badge-info',
    delivered:        'badge-success',
    completed:        'badge-success',
    cancelled:        'badge-danger',
  };

  // step index: pending=1, confirmed=2, out_for_delivery=3, delivered/completed=4
  function stepOf(status) {
    return { pending:1, confirmed:2, out_for_delivery:3, delivered:4, completed:4 }[status] ?? 0;
  }

  function applyDot(dotEl, labelEl, descEl, done, active, label, desc) {
    dotEl.className = 'relative z-10 w-4 h-4 rounded-full ring-2 ring-white shrink-0 flex items-center justify-center border ';
    if (done) {
      dotEl.className += 'bg-brand-500 border-brand-500';
      dotEl.innerHTML = '<svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>';
      labelEl.className = 'text-sm font-medium text-surface-900';
    } else if (active) {
      dotEl.className += 'bg-brand-500 border-brand-500';
      dotEl.innerHTML = '<div class="w-1.5 h-1.5 bg-white rounded-full"></div>';
      labelEl.className = 'text-sm font-medium text-brand-600';
    } else {
      dotEl.className += 'bg-white border-surface-300';
      dotEl.innerHTML = '';
      labelEl.className = 'text-sm font-medium text-surface-350';
    }
    if (label) labelEl.textContent = label;
    if (desc)  { descEl.textContent = desc; descEl.className = (done || active) ? 'text-xs text-surface-600' : 'text-xs text-surface-350'; }
  }

  async function trackOrder() {
    const val    = document.getElementById('order-id').value.trim().toUpperCase();
    const result = document.getElementById('tracking-result');
    const empty  = document.getElementById('empty-state');
    const button = document.getElementById('track-btn');

    if (!val) { result.classList.add('hidden'); empty.classList.add('hidden'); return; }

    try {
      button.disabled = true;
      button.dataset.loading = 'true';
      button.innerHTML = '<span class="inline-block w-3.5 h-3.5 border-2 border-current border-r-transparent rounded-full animate-spin"></span><span>Checking...</span>';
      const res  = await fetch('{{ route('orders.track') }}', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body:    JSON.stringify({ order_number: val }),
      });
      const data = await res.json();

      if (!data.found) {
        result.classList.add('hidden');
        empty.classList.remove('hidden');
        return;
      }

      // ── Populate header ────────────────────────────
      document.getElementById('display-id').textContent    = data.order_number;
      document.getElementById('track-placed-at').textContent = 'Placed on ' + (data.created_at || '');

      const badgeCls = STATUS_BADGE[data.status] ?? 'badge-neutral';
      const badge    = document.getElementById('track-status-badge');
      badge.className = 'badge ' + badgeCls;
      badge.textContent = data.status === 'delivered' && !data.customer_confirmed_at
        ? 'delivered - pending confirmation'
        : data.status.replace(/_/g,' ');

      // ── Timeline dots ──────────────────────────────
      const step = stepOf(data.status);
      const placed = document.getElementById('track-date-placed');
      placed.textContent = data.created_at || '';

      applyDot(
        document.getElementById('track-dot-confirmed'),
        document.getElementById('track-label-confirmed'),
        document.getElementById('track-desc-confirmed'),
        step > 2, step === 2, 'Confirmed', step >= 2 ? 'Preparing your items' : 'Pending'
      );
      applyDot(
        document.getElementById('track-dot-ofd'),
        document.getElementById('track-label-ofd'),
        document.getElementById('track-desc-ofd'),
        step > 3, step === 3, 'Out for Delivery', step >= 3 ? 'Driver is on the way' : 'Pending'
      );
      applyDot(
        document.getElementById('track-dot-delivered'),
        document.getElementById('track-label-delivered'),
        document.getElementById('track-desc-delivered'),
        step >= 4, false, 'Delivered', data.status === 'completed' ? 'Confirmed received' : (step >= 4 ? 'Waiting for customer confirmation' : 'Pending')
      );

      const proofCard = document.getElementById('track-proof-card');
      if (data.delivery_proof_url) {
        document.getElementById('track-proof-link').dataset.proofSrc = data.delivery_proof_url;
        document.getElementById('track-proof-link').dataset.proofAlt = 'Delivery proof for order ' + data.order_number;
        document.getElementById('track-proof-img').src = data.delivery_proof_url;
        document.getElementById('track-proof-meta').textContent = data.customer_confirmed_at
          ? 'Confirmed received on ' + data.customer_confirmed_at
          : 'Delivered on ' + (data.delivered_at || 'pending timestamp') + '. Waiting for your confirmation.';
        proofCard.classList.remove('hidden');
      } else {
        proofCard.classList.add('hidden');
      }

      // Progress bar height (rough %)
      const pct = { 0:'0%', 1:'8%', 2:'38%', 3:'65%', 4:'100%' }[step] ?? '0%';
      document.getElementById('track-progress-bar').style.height = pct;

      empty.classList.add('hidden');
      result.classList.remove('hidden');

    } catch (err) {
      console.error('Track error', err);
      result.classList.add('hidden');
      empty.classList.remove('hidden');
    } finally {
      button.disabled = false;
      button.dataset.loading = 'false';
      button.innerHTML = 'Track';
    }
  }

  // allow Enter key in the input
  document.getElementById('order-id')?.addEventListener('keydown', e => { if (e.key === 'Enter') trackOrder(); });

  // ── Feedback Modal ──────────────────────────────────────────────────────
  const feedbackModal   = document.getElementById('feedback-modal');
  const modalOrderId    = document.getElementById('modal-order-id');
  const modalOrderNum   = document.getElementById('modal-order-number');
  const modalRatingInput = document.getElementById('modal-rating-input');
  const modalStars      = document.querySelectorAll('.modal-star');
  let modalSelected = 0;

  function paintModal(upTo) {
    modalStars.forEach((s, i) => {
      s.classList.toggle('text-amber-400', i < upTo);
      s.classList.toggle('text-surface-350', i >= upTo);
    });
  }

  modalStars.forEach((btn, idx) => {
    btn.addEventListener('mouseenter', () => paintModal(idx + 1));
    btn.addEventListener('mouseleave', () => paintModal(modalSelected));
    btn.addEventListener('click', () => {
      modalSelected = idx + 1;
      modalRatingInput.value = modalSelected;
      paintModal(modalSelected);
    });
  });

  function openFeedbackModal(orderId, orderNum) {
    modalOrderId.value   = orderId;
    modalOrderNum.textContent = orderNum;
    modalSelected = 0;
    modalRatingInput.value = '';
    paintModal(0);
    feedbackModal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }

  function closeFeedbackModal() {
    feedbackModal.classList.add('hidden');
    document.body.style.overflow = '';
  }

  document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeDeliveryProof(); closeFeedbackModal(); closeCancelModal(); } });

  // ── Cancel Order Modal ──────────────────────────────────────────────────
  const cancelModal    = document.getElementById('cancel-modal');
  const cancelForm     = document.getElementById('cancel-order-form');
  const cancelOrderNum = document.getElementById('cancel-order-number');

  function openCancelModal(orderId, orderNum) {
    cancelForm.action = '{{ url('/orders') }}/' + orderId + '/cancel';
    cancelOrderNum.textContent = orderNum;
    cancelModal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }

  function closeCancelModal() {
    cancelModal.classList.add('hidden');
    document.body.style.overflow = '';
  }
</script>
@endsection
