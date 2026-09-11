<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @include('partials.favicon')
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Manage Order - Ferosa Landscaping</title>

  <link rel="stylesheet" href="{{ asset('fonts/ferosa-fonts.css') }}">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @include('admin.partials.premium-theme')
</head>
@php
  $statusTone = match($order->status) {
    'pending' => 'bg-amber-100 text-amber-800',
    'confirmed' => 'bg-blue-100 text-blue-800',
    'out_for_delivery' => 'bg-indigo-100 text-indigo-800',
    'delivered', 'completed' => 'bg-brand-100 text-brand-800',
    'cancelled' => 'bg-red-100 text-red-700',
    default => 'bg-surface-100 text-surface-700',
  };
  $items = $order->orderItems->isNotEmpty() ? $order->orderItems : collect($order->items ?? []);
  $isAdmin = (bool) ($isAdmin ?? auth()->user()?->isAdmin());
  $availableStatuses = array_values(array_unique([$order->status, ...($order::STATUS_TRANSITIONS[$order->status] ?? [])]));
  if (! $isAdmin) {
    $availableStatuses = array_values(array_filter(
      $availableStatuses,
      static fn (string $status): bool => in_array($status, ['pending', 'confirmed', 'out_for_delivery', 'delivered'], true)
    ));
  }
  $hasOperationalTransition = count($availableStatuses) > 1;
  $closedStatusLabel = $order->status === 'cancelled' ? 'cancelled' : 'complete';
  $isPickupOrder = ($order->delivery_method ?? 'delivery') === 'pickup';
  $paymentStatusLabel = ucfirst(str_replace('_', ' ', $order->payment_status ?? 'unpaid'));
  $fulfillmentStatusLabel = fn (string $status) => $isPickupOrder
    ? match($status) {
        'out_for_delivery' => 'Ready for Pickup',
        'delivered' => 'Picked Up',
        default => ucfirst(str_replace('_', ' ', $status)),
      }
    : ucfirst(str_replace('_', ' ', $status));
@endphp
<body class="min-h-screen bg-surface-100 font-sans text-surface-900 antialiased">
  <a href="#admin-main" class="skip-link">Skip to admin content</a>
  <header class="flex h-14 items-center justify-between border-b border-surface-200 bg-white px-5">
    <h1 class="text-sm font-semibold text-surface-600">Ordering & Delivery</h1>
    <div class="flex items-center gap-2">
      <span class="rounded-md bg-brand-600 px-2.5 py-1 text-xs font-bold text-white">Ferosa Landscaping</span>
    </div>
  </header>

  <main id="admin-main" tabindex="-1" class="p-5">
    @if (session('status'))
      <div class="mb-4 rounded-lg border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
      <div class="mb-4 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
        <p class="font-semibold">Please check the details and try again.</p>
        <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
      </div>
    @endif

    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div class="flex items-center gap-4">
        <a href="{{ route('admin.ordering-delivery') }}" class="inline-flex h-9 w-9 items-center justify-center rounded border border-surface-400 text-surface-600 hover:bg-white" aria-label="Back to ordering and delivery">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        </a>
        <div>
          <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-2xl font-bold text-brand-950">{{ $order->order_number }}</h2>
            <span class="rounded-md px-3 py-1 text-sm font-semibold {{ $statusTone }}">{{ $fulfillmentStatusLabel($order->status) }}</span>
          </div>
          <p class="mt-1 text-sm text-surface-500">{{ $isAdmin ? 'Manage the customer order, billing status, delivery details, and fulfillment proof.' : 'Confirm orders, coordinate delivery, and record fulfilment proof.' }}</p>
        </div>
      </div>

      @if(auth()->user()?->isStaffOrAdmin())
        <div class="flex flex-wrap gap-2">
          @if($order->status === 'pending')
            <form method="POST" action="{{ route('admin.orders.status', $order) }}">
              @csrf @method('PUT')
              <input type="hidden" name="redirect_to" value="show">
              <input type="hidden" name="status" value="confirmed">
              @if($isAdmin)<input type="hidden" name="payment_status" value="{{ $order->payment_status ?? 'unpaid' }}">@endif
              <button class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Confirm</button>
            </form>
          @endif
          @if($isAdmin && $order->canTransitionTo('cancelled'))
            <form method="POST" action="{{ route('admin.orders.status', $order) }}"
                  data-confirm-title="Cancel this order?"
                  data-confirm="Order {{ $order->order_number }} will be cancelled and any reserved stock returned. The customer is notified."
                  data-confirm-action="Cancel order">
              @csrf @method('PUT')
              <input type="hidden" name="redirect_to" value="show">
              <input type="hidden" name="status" value="cancelled">
              <input type="hidden" name="payment_status" value="{{ $order->payment_status ?? 'unpaid' }}">
              <button class="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Cancel</button>
            </form>
          @endif
        </div>
      @endif
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
      <div class="space-y-6 xl:col-span-3">
        <section class="overflow-hidden rounded-xl border border-surface-100 bg-white shadow-sm">
          <div class="border-b border-surface-200 px-5 py-4"><h3 class="font-semibold">Order Items</h3></div>
          <div class="divide-y divide-surface-100">
            @forelse($items as $item)
              @php
                $name = is_array($item) ? ($item['name'] ?? 'Item') : $item->name;
                $qty = is_array($item) ? ($item['qty'] ?? 1) : $item->qty;
                $price = is_array($item) ? ($item['price'] ?? 0) : $item->price;
                $image = is_array($item) ? null : ($item->product->image_url ?? null);
              @endphp
              <div class="flex items-center gap-4 px-5 py-4">
                <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-lg bg-brand-50">
                  @if($image)
                    <img src="{{ $image }}" alt="{{ $name }}" class="h-full w-full object-cover">
                  @else
                    <svg class="h-8 w-8 text-brand-200" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5 12 3 3.75 7.5m16.5 0L12 12m8.25-4.5v9L12 21m0-9L3.75 7.5m8.25 4.5v9m0-9-8.25 4.5"/></svg>
                  @endif
                </div>
                <div class="min-w-0 flex-1">
                  <p class="font-semibold text-surface-900">{{ $name }}</p>
                  <p class="text-sm text-surface-500">Quantity: {{ $qty }}</p>
                </div>
                <p class="text-right font-bold text-brand-800">PHP {{ number_format((float) $price * (int) $qty, 2) }}</p>
              </div>
            @empty
              <div class="px-5 py-8 text-center text-sm text-surface-400">No order items found.</div>
            @endforelse
          </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-surface-100 bg-white shadow-sm">
          <div class="border-b border-surface-200 px-5 py-4"><h3 class="font-semibold">Billing & Fulfillment</h3></div>
          <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2">
            <div>
              <p class="text-xs text-surface-400">Total Amount</p>
              <p class="text-2xl font-bold text-brand-800">PHP {{ number_format((float) $order->total_amount, 2) }}</p>
            </div>
            <div>
              <p class="text-xs text-surface-400">Payment</p>
              <p class="text-lg font-semibold">{{ strtoupper($order->payment_method ?? 'COD') }} · {{ $paymentStatusLabel }}</p>
              @if($order->payment_reference)<p class="text-sm text-surface-500">Reference: {{ $order->payment_reference }}</p>@endif
              @if($order->payment_verified_at)
                <p class="text-xs text-brand-700">Verified {{ $order->payment_verified_at->format('M d, Y h:i A') }}{{ $order->paymentVerifiedBy ? ' by '.$order->paymentVerifiedBy->name : '' }}</p>
              @endif
            </div>
            <div>
              <p class="text-xs text-surface-400">Delivery Method</p>
              <p class="text-lg font-semibold">{{ ucfirst($order->delivery_method ?? 'delivery') }}</p>
            </div>
            <div>
              <p class="text-xs text-surface-400">Created</p>
              <p class="text-lg font-semibold">{{ optional($order->created_at)->format('M d, Y h:i A') }}</p>
            </div>
          </div>

          @include('admin.partials.payment-ledger', [
            'payable' => $order,
            'storeRoute' => route('admin.orders.payments.store', $order),
            'invoiceRoute' => route('orders.invoice', $order),
            'isAdmin' => $isAdmin,
          ])

          @if($order->payment_proof_path)
            <div class="border-t border-surface-100 p-5">
              <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p class="font-semibold">Customer Payment Receipt</p>
                  <p class="text-xs text-surface-500">Private evidence submitted for GCash verification.</p>
                </div>
                <a href="{{ route('orders.payment-proof', $order) }}" target="_blank" rel="noopener" class="rounded-lg border border-sky-300 px-3 py-1.5 text-sm font-medium text-sky-700 hover:bg-sky-50">Open Receipt</a>
              </div>
              <img src="{{ route('orders.payment-proof', $order) }}" alt="Customer payment receipt" class="mt-3 max-h-96 rounded-lg border border-surface-200 object-contain">
            </div>
          @endif
          @if($order->payment_review_notes)
            <div class="border-t border-surface-100 bg-amber-50/60 p-5">
              <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Payment review note</p>
              <p class="mt-1 text-sm text-amber-950">{{ $order->payment_review_notes }}</p>
            </div>
          @endif
          @if($order->dispatch_proof_url)
            <div class="border-t border-surface-100 p-5">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <p class="font-semibold">Dispatch Proof</p>
                  <p class="text-xs text-surface-500">Sent {{ optional($order->dispatched_at)->format('M d, Y h:i A') ?? 'without a recorded time' }} · Driver: {{ $order->driver_name ?: 'Not recorded' }}</p>
                  @if($order->driver_phone)<p class="text-xs text-surface-500">Contact: {{ $order->driver_phone }}</p>@endif
                  @if($order->dispatch_notes)<p class="mt-1 text-xs text-surface-600">{{ $order->dispatch_notes }}</p>@endif
                </div>
                <a href="{{ $order->dispatch_proof_url }}" target="_blank" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-sm font-medium text-indigo-700 hover:bg-indigo-50">View Full</a>
              </div>
              <img src="{{ $order->dispatch_proof_url }}" alt="Dispatch proof" class="mt-3 max-h-72 rounded-lg border border-surface-200 object-contain">
            </div>
          @endif
          @if($order->delivery_proof_url)
            <div class="border-t border-surface-100 p-5">
              <div class="flex items-center justify-between gap-3">
                <p class="font-semibold">Delivery Proof</p>
                @if($order->delivery_recipient_name)<p class="text-xs text-surface-500">Received by {{ $order->delivery_recipient_name }}</p>@endif
              </div>
              <button type="button"
                data-proof-src="{{ route('orders.delivery-proof', $order) }}"
                data-proof-alt="Delivery proof for order {{ $order->order_number }}"
                onclick="openDeliveryProof(this.dataset.proofSrc, this.dataset.proofAlt)"
                class="mt-3 block max-w-full cursor-zoom-in overflow-hidden rounded-lg border border-surface-200"
                aria-label="View full delivery proof for order {{ $order->order_number }}">
                <img src="{{ route('orders.delivery-proof', $order) }}" alt="Delivery proof for order {{ $order->order_number }}" class="max-h-72 max-w-full object-contain">
              </button>
            </div>
          @endif
        </section>

        @if($isAdmin && $history->isNotEmpty())
          <section class="overflow-hidden rounded-xl border border-surface-100 bg-white shadow-sm">
            <div class="border-b border-surface-200 px-5 py-4"><h3 class="font-semibold">Activity History</h3></div>
            <div class="divide-y divide-surface-100">
              @foreach($history as $entry)
                <div class="px-5 py-3">
                  <p class="text-sm font-medium text-surface-800">{{ $entry->description }}</p>
                  <p class="mt-1 text-xs text-surface-400">{{ $entry->actor->name ?? 'System' }} · {{ optional($entry->created_at)->format('M d, Y h:i A') }}</p>
                </div>
              @endforeach
            </div>
          </section>
        @endif
      </div>

      <aside class="space-y-6 xl:col-span-2">
        <section class="rounded-xl border border-surface-100 bg-white shadow-sm">
          <div class="border-b border-surface-200 px-5 py-4"><h3 class="font-semibold">Customer Information</h3></div>
          <div class="p-5">
            <div class="flex items-center gap-3">
              <div class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-800 text-lg font-bold text-white">{{ strtoupper(substr($order->user->name ?? 'C', 0, 1)) }}</div>
              <div>
                <p class="font-semibold">{{ $order->user->name ?? $order->delivery_name ?? 'Customer' }}</p>
                <p class="text-sm text-surface-500">{{ $order->user->email ?? '' }}</p>
              </div>
            </div>
            <div class="mt-4 space-y-2 text-sm text-surface-700">
              <p>{{ $order->delivery_phone ?: ($order->user->phone_number ?? 'No phone number') }}</p>
              <p>{{ $order->delivery_address ?: 'No delivery address' }}{{ $order->delivery_city ? ', '.$order->delivery_city : '' }}</p>
            </div>
          </div>
        </section>

        <section class="rounded-xl border border-surface-100 bg-white shadow-sm">
          <div class="border-b border-surface-200 px-5 py-4"><h3 class="font-semibold">Update Order</h3></div>
          <form method="POST" action="{{ route('admin.orders.status', $order) }}" enctype="multipart/form-data" class="space-y-4 p-5">
            @csrf @method('PUT')
            <input type="hidden" name="redirect_to" value="show">
            <label class="block text-sm font-medium">Status
              <select name="status" class="mt-2 h-10 w-full rounded-lg border border-surface-200 px-3 outline-none focus:border-brand-600" {{ $isAdmin || $hasOperationalTransition ? '' : 'disabled' }}>
                @foreach($availableStatuses as $status)
                  <option value="{{ $status }}" {{ $order->status === $status ? 'selected' : '' }}>{{ $fulfillmentStatusLabel($status) }}</option>
                @endforeach
              </select>
            </label>
            @if($isAdmin)
              <label class="block text-sm font-medium">Payment Status
                <select name="payment_status" class="mt-2 h-10 w-full rounded-lg border border-surface-200 px-3 outline-none focus:border-brand-600">
                  <option value="unpaid" {{ ($order->payment_status ?? 'unpaid') === 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                  <option value="pending_verification" {{ $order->payment_status === 'pending_verification' ? 'selected' : '' }}>Pending verification</option>
                  <option value="paid" {{ ($order->payment_status ?? 'unpaid') === 'paid' ? 'selected' : '' }}>Paid</option>
                  <option value="rejected" {{ $order->payment_status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                  <option value="refunded" {{ $order->payment_status === 'refunded' ? 'selected' : '' }}>Refunded</option>
                </select>
              </label>
              <label class="block text-sm font-medium">Payment Review Notes
                <textarea name="payment_review_notes" rows="3" maxlength="1000" placeholder="Required when rejecting a payment; visible to the customer." class="mt-2 w-full rounded-lg border border-surface-200 px-3 py-2 outline-none focus:border-brand-600">{{ old('payment_review_notes', $order->payment_review_notes) }}</textarea>
              </label>
            @else
              <p class="rounded-lg border border-surface-200 bg-surface-50 px-3 py-2 text-xs text-surface-500">Payment verification is handled by an administrator.</p>
            @endif
            @unless($isPickupOrder)
            <div class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 space-y-3">
              <div><p class="text-sm font-semibold text-indigo-900">Out for Delivery</p><p class="text-xs text-indigo-700">Required when dispatching the order.</p></div>
              <label class="block text-sm font-medium">Driver or Rider Name
                <input name="driver_name" value="{{ old('driver_name', $order->driver_name) }}" maxlength="255" class="mt-2 h-10 w-full rounded-lg border border-surface-200 bg-white px-3 outline-none focus:border-indigo-500">
              </label>
              <label class="block text-sm font-medium">Driver Contact
                <input name="driver_phone" type="tel" inputmode="numeric" pattern="[0-9]{11}" minlength="11" maxlength="11" title="Enter exactly 11 digits." value="{{ old('driver_phone', $order->driver_phone) }}" class="mt-2 h-10 w-full rounded-lg border border-surface-200 bg-white px-3 outline-none focus:border-indigo-500">
              </label>
              <label class="block text-sm font-medium">Dispatch Notes
                <textarea name="dispatch_notes" rows="2" maxlength="1000" class="mt-2 w-full rounded-lg border border-surface-200 bg-white px-3 py-2 outline-none focus:border-indigo-500">{{ old('dispatch_notes', $order->dispatch_notes) }}</textarea>
              </label>
            </div>
            <div class="rounded-xl border border-brand-100 bg-brand-50/60 p-4 space-y-3">
              <div><p class="text-sm font-semibold text-brand-900">Delivered</p><p class="text-xs text-brand-700">Required after the customer receives the order.</p></div>
              <label class="block text-sm font-medium">Received By
                <input name="delivery_recipient_name" value="{{ old('delivery_recipient_name', $order->delivery_recipient_name) }}" maxlength="255" class="mt-2 h-10 w-full rounded-lg border border-surface-200 bg-white px-3 outline-none focus:border-brand-500">
              </label>
              <label class="block text-sm font-medium">Delivery Proof
                <input name="delivery_proof" type="file" accept="image/*" class="mt-2 h-10 w-full rounded-lg border border-surface-200 bg-white text-sm file:mr-3 file:h-full file:border-0 file:bg-brand-100 file:px-3">
              </label>
            </div>
            @endunless
            @if($isAdmin || $hasOperationalTransition)
              <button class="w-full rounded-lg bg-brand-700 py-2.5 font-semibold text-white hover:bg-brand-800">Save Changes</button>
            @else
              <p class="rounded-lg border border-surface-200 bg-surface-50 px-3 py-2 text-xs text-surface-500">This order is {{ $closedStatusLabel }}. No further staff action is needed.</p>
            @endif
          </form>
        </section>
      </aside>
    </div>
  </main>

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

  <script>
    const deliveryProofModal = document.getElementById('delivery-proof-modal');
    const deliveryProofImage = document.getElementById('delivery-proof-modal-image');

    function openDeliveryProof(src, alt) {
      if (!src) return;
      deliveryProofImage.src = src;
      deliveryProofImage.alt = alt || 'Delivery proof';
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

    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') closeDeliveryProof();
    });
  </script>
  @include('partials.confirm-dialog')
</body>
</html>
