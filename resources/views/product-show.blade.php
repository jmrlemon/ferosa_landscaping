@extends('layouts.customer')

@section('title', $product->name.' - Ferosa Landscaping')

@section('styles')
<style>
  .detail-photo { min-height: 360px; }
  .proof-step { position: relative; }
  .proof-step:not(:last-child)::after { content: ''; position: absolute; left: 17px; top: 38px; bottom: -14px; width: 1px; background: #dce9e0; }

  /* In-app: quantity + Add to cart is a normal row ~360px down the page,
     below the photo. Pinning it to the bottom means it's reachable the
     instant the page opens, without scrolling past the photo first. */
  .product-buy-price { display: none; }
  body.in-app #product-buy-bar {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 40;
    display: grid;
    grid-template-columns: auto 88px 1fr;
    align-items: end;
    gap: .6rem;
    margin-top: 0;
    background: #fff;
    border-top: 1px solid #eae7df;
    padding: .65rem 1rem calc(.65rem + env(safe-area-inset-bottom));
    box-shadow: 0 -8px 24px rgba(18,52,38,.08);
  }
  body.in-app .product-buy-price { display: block; }
  body.in-app #product-buy-bar .field { height: 44px; }
</style>
@endsection

@section('content')
<main class="customer-page">
  <nav class="mb-5 flex items-center gap-2 text-xs text-surface-400" aria-label="Breadcrumb">
    <a href="{{ route('shop') }}" class="font-semibold text-brand-700 hover:text-brand-900">Shop</a>
    <span aria-hidden="true">/</span>
    <span class="truncate">{{ $product->name }}</span>
  </nav>

  <section class="grid gap-7 lg:grid-cols-[1.08fr_.92fr] lg:items-start">
    <div class="detail-photo overflow-hidden rounded-[1.5rem] border border-surface-200 bg-brand-50">
      @if($product->image_url)
        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="h-full min-h-[360px] w-full object-cover">
      @else
        <div class="flex min-h-[420px] items-center justify-center text-brand-300">
          <svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.15"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21V11m0 0C12 6 7 4 4 7m8 4c0-5 5-7 8-4M7 21h10"/></svg>
        </div>
      @endif
    </div>

    <div class="lg:py-4">
      <div class="flex flex-wrap items-center gap-2">
        <span class="rounded-full bg-brand-50 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-brand-700">{{ $product->category ?: 'Garden' }}</span>
        @if($product->plantModel)
          <span class="rounded-full bg-surface-900 px-3 py-1 text-[10px] font-bold text-white">AR preview available</span>
        @endif
      </div>
      <h1 class="mt-4 font-display text-3xl font-bold tracking-[-.025em] text-surface-950 sm:text-4xl">{{ $product->name }}</h1>
      <p class="mt-4 font-display text-3xl font-bold text-brand-800">&#8369;{{ number_format((float) $product->price, 2) }}</p>

      <div class="mt-5 flex items-center gap-2 text-sm font-semibold {{ $product->inStock() ? 'text-brand-700' : 'text-red-600' }}">
        <span class="h-2 w-2 rounded-full {{ $product->inStock() ? 'bg-brand-500' : 'bg-red-500' }}"></span>
        {{ $product->inStock() ? $product->stock_qty.' available' : 'Currently unavailable' }}
      </div>

      @if($product->description)
        <p class="mt-6 whitespace-pre-line text-[15px] leading-7 text-surface-600">{{ $product->description }}</p>
      @else
        <p class="mt-6 text-[15px] leading-7 text-surface-500">Ask the Ferosa team about sizing, care, placement, and current availability before checkout.</p>
      @endif

      {{-- Guests see the price and stock but buy nothing: the cart lives on the
           server behind `auth`, so the whole buy bar becomes a sign-in prompt
           rather than a control that would fail on submit. --}}
      @guest
        <div class="mt-7 rounded-2xl border border-brand-100 bg-brand-50 p-5">
          <p class="text-sm font-bold text-surface-900">
            {{ $product->inStock() ? 'Sign in to order this item' : 'Sign in to ask about availability' }}
          </p>
          <p class="mt-1 text-[13px] leading-6 text-surface-600">Create a free account to add items to your cart, book a service visit, and track delivery.</p>
          <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('login') }}" class="btn btn-primary btn-sm">Sign in</a>
            <a href="{{ route('register') }}" class="btn btn-secondary btn-sm">Create account</a>
          </div>
        </div>
      @else
        @if($product->inStock())
          <div id="product-buy-bar" class="mt-7 grid items-end gap-3 sm:grid-cols-[120px_1fr]">
            <div class="product-buy-price">
              <p class="text-[10px] font-bold uppercase tracking-wide text-surface-400">Price</p>
              <p class="font-display text-lg font-bold text-brand-800 whitespace-nowrap">&#8369;{{ number_format((float) $product->price, 2) }}</p>
            </div>
            <div>
              <label for="product-quantity" class="field-label">Quantity</label>
              <input id="product-quantity" type="number" min="1" max="{{ $product->stock_qty }}" value="1" class="field h-[50px] text-base">
            </div>
            <button id="product-add-button" type="button" class="btn btn-primary btn-lg disabled:cursor-wait disabled:opacity-70">Add to cart</button>
          </div>
          <a href="{{ route('checkout') }}" class="btn btn-soft btn-lg btn-block mt-3">Review cart and checkout</a>
        @else
          <a href="{{ route('messages') }}" class="btn btn-primary btn-lg mt-7">Ask about availability</a>
        @endif
      @endguest

      <div class="mt-7 grid gap-3 sm:grid-cols-2">
        <div class="rounded-2xl border border-surface-200 bg-white p-4">
          <p class="text-[10px] font-bold uppercase tracking-wider text-surface-400">Service area</p>
          <p class="mt-1 text-sm font-bold text-surface-800">{{ $businessProfile['service_area'] ?: 'Contact Ferosa to confirm' }}</p>
        </div>
        <div class="rounded-2xl border border-surface-200 bg-white p-4">
          <p class="text-[10px] font-bold uppercase tracking-wider text-surface-400">Need advice?</p>
          <a href="{{ route('messages') }}" class="mt-1 inline-block text-sm font-bold text-brand-700">Message the team &rarr;</a>
        </div>
      </div>
    </div>
  </section>

  <section class="mt-12 grid gap-6 lg:grid-cols-2">
    <div class="customer-card p-6 sm:p-7">
      <p class="text-[11px] font-bold uppercase tracking-[.12em] text-brand-600">Buy with clarity</p>
      <h2 class="mt-2 font-display text-2xl font-bold text-surface-950">What happens after checkout</h2>
      <div class="mt-6 space-y-5">
        @foreach(['Your order and payment details are recorded securely.', 'Ferosa reviews stock and prepares the order for the selected method.', 'Track every status change from Orders and Notifications.'] as $step)
          <div class="proof-step flex gap-4">
            <span class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">{{ $loop->iteration }}</span>
            <p class="pt-2 text-sm leading-6 text-surface-600">{{ $step }}</p>
          </div>
        @endforeach
      </div>
    </div>

    <div class="customer-card p-6 sm:p-7">
      <p class="text-[11px] font-bold uppercase tracking-[.12em] text-brand-600">AR-ready products</p>
      <h2 class="mt-2 font-display text-2xl font-bold text-surface-950">Preview scale before deciding</h2>
      @if($product->plantModel)
        <p class="mt-3 text-sm leading-6 text-surface-600">Open Ferosa on a compatible Android device, choose this product in AR, scan a well-lit flat surface, then tap to place it. Move around the preview to check the fit.</p>
        <div class="mt-5 rounded-xl border border-brand-100 bg-brand-50 p-4 text-xs leading-5 text-brand-800">AR is a planning aid. Actual plant shape, color, and dimensions can vary.</div>
        <a href="ferosa://ar?type=product&amp;size=100&amp;productId={{ $product->id }}" class="app-only btn btn-primary btn-sm mt-4">Preview this product in AR</a>
      @else
        <p class="mt-3 text-sm leading-6 text-surface-600">This item does not yet have an AR model. You can still message Ferosa for placement guidance or use the estimator to plan the wider project.</p>
      @endif
      <a href="{{ route('estimator') }}" class="mt-5 inline-flex text-xs font-bold text-brand-700">Open cost estimator &rarr;</a>
    </div>
  </section>

  @if($relatedProducts->isNotEmpty())
    <section class="mt-12">
      <div class="mb-5 flex items-end justify-between gap-3">
        <div>
          <p class="text-[11px] font-bold uppercase tracking-[.12em] text-brand-600">You may also like</p>
          <h2 class="mt-1 font-display text-2xl font-bold text-surface-950">Related products</h2>
        </div>
        <a href="{{ route('shop') }}" class="text-xs font-bold text-brand-700">Browse all &rarr;</a>
      </div>
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach($relatedProducts as $related)
          <a href="{{ route('products.show', $related) }}" class="overflow-hidden rounded-2xl border border-surface-200 bg-white transition hover:-translate-y-0.5 hover:shadow-sm">
            <div class="aspect-[4/3] bg-brand-50">
              @if($related->image_url)<img src="{{ $related->image_url }}" alt="{{ $related->name }}" class="h-full w-full object-cover" loading="lazy">@endif
            </div>
            <div class="p-4"><h3 class="line-clamp-1 text-sm font-bold text-surface-900">{{ $related->name }}</h3><p class="mt-2 text-xs font-bold text-brand-700">&#8369;{{ number_format((float) $related->price, 2) }}</p></div>
          </a>
        @endforeach
      </div>
    </section>
  @endif
</main>

<div id="product-toast" class="pointer-events-none fixed right-5 top-5 z-[80] translate-y-[-8px] rounded-xl bg-surface-950 px-4 py-3 text-sm font-semibold text-white opacity-0 shadow-xl transition" role="status" aria-live="polite"></div>
@include('partials.mobile-bottom-customer')
@endsection

@section('scripts')
<script>
  (() => {
    const button = document.getElementById('product-add-button');
    const quantityInput = document.getElementById('product-quantity');
    const toast = document.getElementById('product-toast');
    const product = {
      id: {{ (int) $product->id }},
      name: @json($product->name),
      stock: {{ (int) $product->stock_qty }},
    };
    if (!button) return;
    let requestInProgress = false;
    let toastTimer;

    button.addEventListener('click', async () => {
      if (requestInProgress) return;

      const requested = Math.max(1, Math.min(product.stock, Number(quantityInput.value) || 1));
      quantityInput.value = requested;
      requestInProgress = true;
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      button.textContent = 'Adding…';
      quantityInput.disabled = true;

      try {
        const response = await fetch('{{ url('/api/cart/items') }}', {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ product_id: product.id, quantity: requested }),
        });
        const data = (await response.json().catch(() => ({}))) || {};

        if (!response.ok) {
          const validationMessage = Object.values(data.errors || {})[0]?.[0];
          throw new Error(data.message || validationMessage || 'Could not add this item to your cart.');
        }

        if (Array.isArray(data.items)) {
          localStorage.setItem('ferosa_cart', JSON.stringify(data.items));
        }
        window.dispatchEvent(new CustomEvent('cartUpdated', { detail: data }));
        showToast(requested + ' item' + (requested === 1 ? '' : 's') + ' added to cart.');
      } catch (error) {
        showToast(error instanceof Error ? error.message : 'Could not add this item to your cart.', false);
      } finally {
        requestInProgress = false;
        button.disabled = false;
        button.removeAttribute('aria-busy');
        button.textContent = 'Add to cart';
        quantityInput.disabled = false;
      }
    });

    function showToast(message, success = true) {
      window.clearTimeout(toastTimer);
      toast.textContent = message;
      toast.classList.toggle('bg-surface-950', success);
      toast.classList.toggle('bg-red-700', !success);
      toast.classList.remove('opacity-0', 'translate-y-[-8px]');
      toastTimer = window.setTimeout(() => toast.classList.add('opacity-0', 'translate-y-[-8px]'), 3200);
    }
  })();
</script>
@endsection
