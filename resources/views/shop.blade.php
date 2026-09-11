@extends('layouts.customer')

@section('styles')
<style>
  .product-card { transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease; }
  .product-card:hover { transform: translateY(-2px); border-color: #cbded1; box-shadow: 0 14px 36px rgba(18,52,38,.07); }
  .product-card img { transition: transform .45s cubic-bezier(.22,1,.36,1); }
  .product-card:hover img { transform: scale(1.035); }
  /* The round button is the wanted look in-app too, not the full-width bar it
     used to become. Only the offset needs adjusting: the web layout's
     `bottom-20` exists to clear the *web* bottom nav, which is hidden in-app,
     and the WebView is already inset above the native nav bar (the shell
     passes Scaffold's innerPadding) - so keeping 5rem would strand the button
     in dead space. Sit it just above that inset instead.
     Display is deliberately not set: the button carries Tailwind's `flex`
     and the script toggles `hidden` on it to show/hide by cart count. */
  body.in-app #floating-cart {
    bottom: calc(1rem + env(safe-area-inset-bottom));
  }
  .floating-cart-label { display: none; }

  /* In-app: the grid was 1 column below `sm`, which on a phone means one
     product per screenful. Two columns plus a square image and a shorter
     card roughly quadruples how many products fit above the fold. */
  body.in-app #product-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .6rem;
  }
  body.in-app .product-image { aspect-ratio: 1 / 1; }
  body.in-app .product-body { padding: .7rem .8rem .8rem; }
  body.in-app .product-card h3 { font-size: .8125rem; margin-bottom: .25rem; }
  body.in-app .product-card .font-display.text-xl { font-size: 1rem; }
  body.in-app .product-desc,
  body.in-app .product-detail-link { display: none; }
  body.in-app .product-card .btn-sm { min-height: 32px; padding: .4rem .65rem; font-size: .6875rem; }
  /* At two columns a card is ~160px wide, which is not enough for the price
     and the Add button to sit side by side - the button was overflowing and
     being clipped by the card's overflow-hidden. Stack them instead. */
  body.in-app .product-buy-row {
    flex-direction: column;
    align-items: stretch;
    gap: .5rem;
    padding-top: .6rem;
  }
  body.in-app .product-buy-row .btn { width: 100%; }

  /* Category chips wrapped to two rows, eating another ~60px. A horizontal
     scroll row keeps them to one. */
  body.in-app #category-chips {
    flex-wrap: nowrap;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    padding-bottom: .2rem;
    scrollbar-width: none;
  }
  body.in-app #category-chips::-webkit-scrollbar { display: none; }
  body.in-app #category-chips .chip { flex: 0 0 auto; }

  /* The search/sort/max-price card is another ~700px of the first screen, and
     behind a Filters button it was a tap away from being found at all. In-app
     it is dropped entirely in favour of the always-visible search pill below,
     the way a marketplace app does it; the category chips cover the rest of
     the common case. Sort and max price stay web-only. */
  body.in-app #filter-form { display: none; }

  /* Search pill - in-app only, sits directly under the page head. */
  .shop-search-bar { display: none; }
  body.in-app .shop-search-bar { display: block; margin-bottom: .75rem; }
  /* Icon offset and text inset are set here rather than inherited: the layout
     supplies them through `:where(...)` rules, whose zero specificity makes
     them too easy to lose, and the pill's larger radius needs the text pushed
     further in regardless. */
  body.in-app .shop-search-bar .field-icon > svg { left: 1rem; }
  body.in-app .shop-search-bar .field {
    min-height: 44px;
    border-radius: 999px;
    padding-left: 2.75rem;
  }
  /* The bottom bar already says "View cart"; drop the header duplicate. */
  body.in-app .shop-header-cart { display: none; }
</style>
@endsection

@section('content')
@php
  $activeFilters = collect([
    filled($q) ? 'search' : null,
    $category !== 'all' ? 'category' : null,
    filled($maxPrice) ? 'price' : null,
  ])->filter()->count();
@endphp
<main class="customer-page">

  {{-- Header --}}
  <x-page-head
    kicker="From the nursery"
    title="Plants and garden essentials"
    sub="Healthy stock, honest pricing, and delivery across Orani, Bataan.">
    <x-slot:icon>
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6"/>
      </svg>
    </x-slot:icon>
    <span class="badge badge-neutral" id="product-count-label">
      {{ $products->count() }} item{{ $products->count() !== 1 ? 's' : '' }}
    </span>
    <a href="{{ route('checkout') }}" class="shop-header-cart btn btn-secondary btn-sm">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
      View cart
    </a>
  </x-page-head>

  {{-- In-app search. Replaces the Filters button: the filter card it used to
       open is hidden in-app, so this carries the other filter state forward as
       hidden inputs rather than silently clearing it on submit. --}}
  <form method="GET" action="{{ route('shop') }}" class="shop-search-bar reveal reveal-1" role="search">
    <input type="hidden" name="category" value="{{ $category }}">
    <input type="hidden" name="sort" value="{{ $sort }}">
    @if (filled($maxPrice))
      <input type="hidden" name="max_price" value="{{ $maxPrice }}">
    @endif
    <label for="shop-search-app" class="sr-only">Search products</label>
    <div class="field-icon">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
      </svg>
      <input type="search" id="shop-search-app" name="q" value="{{ $q }}"
             placeholder="Search plants, soil, tools…"
             enterkeyhint="search" autocomplete="off" class="field">
    </div>
  </form>

  {{-- Category quick filters --}}
  <div id="category-chips" class="mb-4 flex flex-wrap items-center gap-2 reveal reveal-1">
    <a href="{{ route('shop', array_filter(['q' => $q, 'sort' => $sort, 'max_price' => $maxPrice])) }}"
       class="chip {{ $category === 'all' ? 'chip-active' : '' }}">All</a>
    @foreach ($categories as $cat)
      <a href="{{ route('shop', array_filter(['category' => $cat, 'q' => $q, 'sort' => $sort, 'max_price' => $maxPrice])) }}"
         class="chip {{ $category === $cat ? 'chip-active' : '' }}">{{ ucfirst($cat) }}</a>
    @endforeach
  </div>

  {{-- Filters bar --}}
  <form method="GET" action="{{ route('shop') }}" id="filter-form"
        class="toolbar mb-6 reveal reveal-1">
    <input type="hidden" name="category" value="{{ $category }}">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-12 sm:items-end">
      {{-- Search --}}
      <div class="sm:col-span-5">
        <label for="shop-search" class="field-label">Search</label>
        <div class="field-icon">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
          </svg>
          <input type="search" id="shop-search" name="q" value="{{ $q }}" placeholder="Try “fern”, “soil”, “shears”…" class="field">
        </div>
      </div>
      {{-- Sort --}}
      <div class="sm:col-span-3">
        <label for="shop-sort" class="field-label">Sort by</label>
        <select id="shop-sort" name="sort" onchange="this.form.submit()" class="field">
          <option value="name_asc"  {{ $sort === 'name_asc'  ? 'selected' : '' }}>Name A-Z</option>
          <option value="price_asc" {{ $sort === 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
          <option value="price_desc"{{ $sort === 'price_desc'? 'selected' : '' }}>Price: High to Low</option>
          <option value="newest"    {{ $sort === 'newest'    ? 'selected' : '' }}>Newest</option>
        </select>
      </div>
      {{-- Max price --}}
      <div class="sm:col-span-2">
        <label for="shop-max-price" class="field-label">Max price</label>
        <input type="number" id="shop-max-price" name="max_price" value="{{ $maxPrice ?? '' }}" min="0" step="100" placeholder="Any" class="field">
      </div>
      {{-- Actions --}}
      <div class="flex items-center gap-2 sm:col-span-2">
        <button type="submit" data-loading-label="Filtering..." class="btn btn-primary btn-sm flex-1">Apply</button>
        @if($activeFilters)
          <a href="{{ route('shop') }}" class="btn btn-ghost btn-sm" title="Clear all filters">Reset</a>
        @endif
      </div>
    </div>
  </form>

  {{-- Products grid --}}
  @if ($products->isEmpty())
    <div class="customer-empty reveal reveal-2">
      <div class="customer-empty-icon">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
      </div>
      <h2 class="text-base font-bold text-surface-900 mb-1">No products found</h2>
      <p class="text-surface-500 text-sm mb-5">Try a different keyword, category, or price range.</p>
      <a href="{{ route('shop') }}" class="btn btn-primary btn-sm">Reset filters</a>
    </div>
  @else
    <div id="product-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5 reveal reveal-2">
      @foreach ($products as $product)
        @php
          $catBg = match(strtolower($product->category)) {
            'plants'    => 'bg-green-50',
            'tools'     => 'bg-surface-100',
            'materials' => 'bg-amber-50',
            default     => 'bg-surface-50',
          };
          $inStock = $product->stock_qty > 0;
          $lowStock = $product->stock_qty > 0 && $product->stock_qty <= 5;
        @endphp
        <article class="product-card bg-white border border-surface-200 rounded-[1.15rem] overflow-hidden flex flex-col group"
             data-id="{{ $product->id }}"
             data-name="{{ $product->name }}"
             data-price="{{ (float) $product->price }}"
             data-category="{{ $product->category }}"
             data-stock="{{ $product->stock_qty }}">

          {{-- Image / placeholder --}}
          <a href="{{ route('products.show', $product) }}" class="product-image aspect-[4/3] {{ $catBg }} relative flex items-center justify-center overflow-hidden" aria-label="View {{ $product->name }} details">
            @if ($product->image_url)
              {{-- Product images are remote URLs, so the whole grid used to be
                   fetched at once on page load. Lazy loading keeps the shop
                   usable on a phone: only the cards actually on screen fetch. --}}
              <img src="{{ $product->image_url }}" alt="{{ $product->name }}"
                   loading="lazy" decoding="async"
                   class="w-full h-full object-cover">
            @else
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.2">
                <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
                <polyline points="21 15 16 10 5 21"/>
              </svg>
            @endif

            {{-- Category badge --}}
            <span class="absolute top-3 left-3 bg-white/90 backdrop-blur px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider text-surface-600 shadow-sm">
              {{ $product->category }}
            </span>

            @if($product->plantModel)
              <span class="absolute bottom-3 left-3 inline-flex items-center gap-1 bg-brand-800/90 backdrop-blur px-2.5 py-1 rounded-full text-[10px] font-bold text-white shadow-sm">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7V4h3M17 4h3v3M20 17v3h-3M7 20H4v-3M9 9h6v6H9z"/></svg>
                AR preview
              </span>
            @endif

            {{-- Stock badge --}}
            @if (! $inStock)
              <span class="absolute top-3 right-3 rounded-full bg-white/92 backdrop-blur px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-red-600 shadow-sm">
                Out of stock
              </span>
            @elseif ($lowStock)
              <span class="absolute top-3 right-3 rounded-full bg-white/92 backdrop-blur px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-amber-700 shadow-sm">
                {{ $product->stock_qty }} left
              </span>
            @endif

          </a>

          {{-- Info --}}
          <div class="product-body p-5 flex-1 flex flex-col">
            <h3 class="font-bold text-surface-900 text-base leading-snug mb-1.5"><a href="{{ route('products.show', $product) }}" class="hover:text-brand-700 transition-colors">{{ $product->name }}</a></h3>
            @if ($product->description)
              <p class="product-desc text-[13px] text-surface-500 leading-5 mb-3 line-clamp-2">{{ $product->description }}</p>
            @endif
            <a href="{{ route('products.show', $product) }}" class="product-detail-link mb-3 inline-flex items-center gap-1 text-[11px] font-bold text-brand-700 hover:text-brand-900 transition-colors">
              View details &amp; guidance
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
            </a>
            <div class="product-buy-row mt-auto pt-4 flex items-end justify-between gap-3 border-t border-surface-100">
              <div>
                <p class="font-display text-xl font-bold leading-none text-surface-900">&#8369;{{ number_format((float) $product->price, 2) }}</p>
                @if($inStock)
                  <p class="mt-1.5 inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wide {{ $lowStock ? 'text-amber-700' : 'text-brand-600' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $lowStock ? 'bg-amber-500' : 'bg-brand-500' }}"></span>
                    {{ $lowStock ? 'Low stock' : 'In stock' }}
                  </p>
                @endif
              </div>
              @if ($inStock)
                @guest
                  <a href="{{ route('login') }}" class="btn btn-secondary btn-sm">Sign in to buy</a>
                @else
                <button onclick="addToCart({{ $product->id }}, '{{ addslashes($product->name) }}', {{ (float) $product->price }})"
                        aria-label="Add {{ $product->name }} to cart"
                        class="btn btn-primary btn-sm">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                  Add
                </button>
                @endguest
              @else
                <span class="badge badge-danger">Unavailable</span>
              @endif
            </div>
          </div>
        </article>
      @endforeach
    </div>

    {{-- Shown by the live filter when typing hides every card. Distinct from
         the server-rendered empty state above, which only exists when the
         query returned nothing in the first place. --}}
    <div id="live-empty" class="customer-empty hidden">
      <div class="customer-empty-icon">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
      </div>
      <h2 class="text-base font-bold text-surface-900 mb-1">No products found</h2>
      <p class="text-surface-500 text-sm">Nothing here matches &ldquo;<span id="live-empty-term"></span>&rdquo;.</p>
    </div>
  @endif
</main>

{{-- Floating cart button. Guests have no server-side cart, so it and the whole
     cart script below are auth-only; browsing still works without either. --}}
@auth
<a href="{{ route('checkout') }}" id="floating-cart"
   aria-label="Open shopping cart"
   class="fixed bottom-20 lg:bottom-6 right-5 z-50 bg-brand-800 text-white w-12 h-12 rounded-2xl shadow-lg flex items-center justify-center hover:bg-brand-900 transition-colors hidden">
  <div class="relative">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
      <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
      <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
    </svg>
    <span id="floating-cart-count"
          class="absolute -top-2 -right-2 bg-brand-600 text-white text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center">0</span>
  </div>
  <span class="floating-cart-label">View cart</span>
</a>
@endauth

<div id="shop-toast-container" class="fixed top-4 right-4 z-50 flex flex-col gap-2 pointer-events-none" aria-live="polite" aria-atomic="true"></div>

@include('partials.mobile-bottom-customer')
@endsection

@section('scripts')
<script>
  const toastCont = document.getElementById('shop-toast-container');

  // Live search: the whole catalogue is already rendered, so typing narrows it
  // in place - no round trip, no waiting for a submit. Pressing enter still
  // submits the form, which runs the same search server-side (and that one
  // also looks at the description, so it can return more than this does).
  const productGrid = document.getElementById('product-grid');
  if (productGrid) {
    const productCards = Array.from(productGrid.querySelectorAll('.product-card'));
    const countLabel   = document.getElementById('product-count-label');
    const liveEmpty    = document.getElementById('live-empty');
    const liveTerm     = document.getElementById('live-empty-term');

    function applyLiveFilter(rawTerm) {
      const term = rawTerm.trim().toLowerCase();
      let visible = 0;

      productCards.forEach(card => {
        const haystack = (card.dataset.name + ' ' + card.dataset.category).toLowerCase();
        const match = term === '' || haystack.includes(term);
        card.classList.toggle('hidden', !match);
        if (match) visible++;
      });

      if (countLabel) countLabel.textContent = visible + ' item' + (visible === 1 ? '' : 's');
      if (liveTerm) liveTerm.textContent = rawTerm.trim();
      if (liveEmpty) liveEmpty.classList.toggle('hidden', visible > 0);
    }

    document.querySelectorAll('input[name="q"]').forEach(input => {
      input.addEventListener('input', () => applyLiveFilter(input.value));
      // `search` fires when the field's native clear (x) is used, which does
      // not raise an input event in every engine.
      input.addEventListener('search', () => applyLiveFilter(input.value));
    });
  }

  @auth
  const cartIcon  = document.getElementById('floating-cart');
  const cartBadge = document.getElementById('floating-cart-count');

  function getCart() {
    try { return JSON.parse(localStorage.getItem('ferosa_cart')) || []; } catch { return []; }
  }

  function cacheCart(cart) {
    localStorage.setItem('ferosa_cart', JSON.stringify(cart));
  }

  function updateCartCount(cartOverride = null) {
    const cart  = cartOverride || getCart();
    const count = cart.reduce((t, i) => t + i.qty, 0);
    cartBadge.textContent = count;
    cartIcon.classList.toggle('hidden', count === 0);
  }

  function showCartToast(message, success = true) {
    const toast = document.createElement('div');
    toast.className = 'text-white text-xs font-bold px-4 py-3 rounded-xl shadow-lg flex items-center gap-2 transform translate-x-full transition-transform duration-200 '
      + (success ? 'bg-brand-700' : 'bg-red-600');
    toast.textContent = message;
    toastCont.appendChild(toast);
    setTimeout(() => toast.classList.remove('translate-x-full'), 10);
    setTimeout(() => { toast.classList.add('translate-x-full'); setTimeout(() => toast.remove(), 200); }, 2200);
  }

  async function cartRequest(url, options = {}) {
    const response = await fetch(url, {
      ...options,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.headers || {}),
      },
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'Cart update failed.');
    return data;
  }

  window.addToCart = async function(id, name) {
    try {
      const data = await cartRequest('{{ url('/api/cart/items') }}', {
        method: 'POST',
        body: JSON.stringify({ product_id: id, quantity: 1 }),
      });
      cacheCart(data.items);
      updateCartCount(data.items);
      window.dispatchEvent(new CustomEvent('cartUpdated', { detail: data }));
      showCartToast(`Added ${name}`);
    } catch (error) {
      showCartToast(error.message, false);
    }
  };

  async function loadServerCart() {
    try {
      const legacy = getCart();
      const data = legacy.length
        ? await cartRequest('{{ url('/api/cart/sync') }}', { method: 'POST', body: JSON.stringify({ items: legacy }) })
        : await cartRequest('{{ url('/api/cart') }}');
      cacheCart(data.items);
      updateCartCount(data.items);
      window.dispatchEvent(new CustomEvent('cartUpdated', { detail: data }));
    } catch (error) {
      updateCartCount();
    }
  }

  document.addEventListener('DOMContentLoaded', loadServerCart);
  @endauth
</script>
@endsection
