@extends('layouts.customer')

@section('styles')
<style>
  /* ── Project Type cards ─────────────────────────────────────── */
  .card-body { transition: border-color .15s, background-color .15s, box-shadow .15s; }

  .step-card input[type="radio"]:checked + .card-body {
    border-color: var(--color-brand-600);
    border-width: 2px;
    background-color: var(--color-brand-50);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-brand-600) 10%, transparent);
  }
  .step-card input[type="radio"]:checked + .card-body .card-icon {
    background-color: var(--color-brand-600);
    color: white;
  }
  .step-card input[type="radio"]:checked + .card-body .card-title {
    color: var(--color-brand-700);
  }
  /* Checkmark badge: hidden by default, shown when checked */
  .card-check {
    opacity: 0;
    transform: scale(0.6);
    transition: opacity .15s, transform .15s;
  }
  .step-card input[type="radio"]:checked + .card-body .card-check {
    opacity: 1;
    transform: scale(1);
  }
  /* "Selected" label under the rate badge */
  .card-selected-label {
    display: none;
  }
  .step-card input[type="radio"]:checked + .card-body .card-selected-label {
    display: inline-flex;
  }
  .step-card input[type="radio"]:checked + .card-body .card-rate-label {
    display: none;
  }

  /* ── Quality Tier cards ─────────────────────────────────────── */
  .tier-body { transition: border-color .15s, background .15s; }

  .tier-card input[type="radio"]:checked + .tier-body {
    border-color: var(--color-brand-600);
    border-width: 2px;
    background: var(--color-brand-50);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-brand-600) 10%, transparent);
  }
  .tier-check {
    opacity: 0;
    transform: scale(0.6);
    transition: opacity .15s, transform .15s;
  }
  .tier-card input[type="radio"]:checked + .tier-body .tier-check {
    opacity: 1;
    transform: scale(1);
  }
  /* hide plain dot when checked, show checkmark */
  .tier-dot { transition: opacity .1s; }
  .tier-card input[type="radio"]:checked + .tier-body .tier-dot {
    display: none;
  }

  /* ── Add-on checkboxes ──────────────────────────────────────── */
  .addon-box { transition: border-color .15s, background .15s, box-shadow .15s; }
  .addon-item input[type="checkbox"]:checked ~ .addon-box {
    border-color: var(--color-brand-500);
    border-width: 2px;
    background: var(--color-brand-50);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-brand-600) 8%, transparent);
  }
  .addon-item input[type="checkbox"]:checked ~ .addon-box .check-box {
    background: var(--color-brand-600);
    border-color: var(--color-brand-600);
  }
  .addon-item input[type="checkbox"]:checked ~ .addon-box .check-icon {
    opacity: 1;
  }
  .addon-item input[type="checkbox"]:checked ~ .addon-box .addon-label {
    color: var(--color-brand-700);
    font-weight: 600;
  }
  /* Addon checkmark badge */
  .addon-check {
    opacity: 0;
    transform: scale(0.6);
    transition: opacity .15s, transform .15s;
  }
  .addon-item input[type="checkbox"]:checked ~ .addon-box .addon-check {
    opacity: 1;
    transform: scale(1);
  }
  /* Addon icon bg on checked */
  .addon-icon { transition: background-color .15s; }
  .addon-item input[type="checkbox"]:checked ~ .addon-box .addon-icon {
    background-color: var(--color-brand-600);
    color: white !important;
  }

  /* ── Size quick-pick active state ───────────────────────────── */
  .size-btn { transition: border-color .15s, background .15s, color .15s; }
  .size-btn.is-active {
    border-color: var(--color-brand-600);
    background: var(--color-brand-50);
    color: var(--color-brand-700);
    font-weight: 600;
  }

  /* ── Misc ───────────────────────────────────────────────────── */
  .price-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f4f4f5; }
  .price-row:last-child { border-bottom: none; }

  /* Shared with Android EstimateHero: same gradient, radius, spacing and hierarchy. */
  .estimate-hero {
    background: linear-gradient(135deg, #181714 0%, #123c29 52%, #1b5239 100%);
    border-radius: 20px;
    box-shadow: 0 1px 2px rgba(18, 52, 38, .08);
  }

  @keyframes countUp { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
  .price-animate { animation: countUp .25s ease both; }

  .step-badge { width: 24px; height: 24px; border-radius: 50%; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; }
</style>
@endsection

@section('content')
<main class="bg-surface-50 min-h-screen pb-24">

  {{-- ── Page Header ─────────────────────────────────────────── --}}
  <div class="bg-surface-50">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-8">
      <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
          <p class="page-kicker">Cost estimator</p>
          <h1 class="page-title">Plan your project</h1>
          <p class="page-sub">Adjust the options and see your estimate instantly — no commitment needed.</p>
        </div>
        <div class="w-12 h-12 rounded-[14px] bg-brand-50 flex items-center justify-center flex-shrink-0">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-brand-700)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="4" y="2" width="16" height="20" rx="2"/>
            <line x1="8" y1="6" x2="16" y2="6"/>
            <line x1="8" y1="11" x2="12" y2="11"/><line x1="10" y1="9" x2="10" y2="13"/>
            <line x1="15" y1="10" x2="18" y2="13"/><line x1="18" y1="10" x2="15" y2="13"/>
            <line x1="8" y1="17" x2="12" y2="17"/><line x1="15" y1="17" x2="18" y2="17"/>
          </svg>
        </div>
      </div>
    </div>
  </div>

  <div class="max-w-5xl mx-auto px-4 sm:px-6 pt-8">
    {{-- Mobile summary mirrors the native Android hero before the estimator steps. --}}
    <section class="estimate-hero lg:hidden px-5 py-5 mb-5 text-white" aria-label="Current estimate" aria-live="polite">
      <p class="text-[11px] font-bold text-white/60 uppercase tracking-wider">Your Estimate</p>
      <div data-estimate-total class="text-[34px] leading-none font-sans font-bold tracking-tight text-white price-animate mt-2">&#8369;5,000</div>
      <p data-estimate-summary class="text-white/80 text-sm mt-3 truncate">Garden Design | 100 sq m | Standard</p>
      <p class="text-white/60 text-xs font-medium mt-3">
        Typical range <span data-range-low>&#8369;4,000</span> &ndash; <span data-range-high>&#8369;6,250</span>
      </p>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

      {{-- ── Left: Steps ──────────────────────────────────────── --}}
      <div class="lg:col-span-2 space-y-5">

        {{-- Step 1: Project Type --}}
        <div class="bg-white rounded-2xl border border-surface-100 shadow-sm overflow-hidden">
          <div class="flex items-center gap-3 px-5 py-4 border-b border-surface-50">
            <span class="step-badge bg-brand-600 text-white">1</span>
            <div>
              <h3 class="text-sm font-semibold text-surface-900">Project type</h3>
              <p class="text-xs text-surface-400">What are you planning?</p>
            </div>
          </div>
          <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-3">
            {{-- Labels, descriptions and rates come from config/estimator.php so the
                 web page and the Android app cannot drift apart. Only the artwork
                 below is web-specific. --}}
            @php
              // Each icon is one <path> plus, for two of them, a second shape.
              // That second shape used to be smuggled into the path string by
              // closing the attribute early (...z" /><circle ...), which only
              // works when echoed raw. It is echoed escaped, so the quotes and
              // angle brackets ended up inside the d attribute: the browser
              // rejected the path, logged an error per icon on every page load,
              // and the pin lost its centre dot while the house lost its door.
              // Keep the shapes as their own data instead.
              $projectArt = [
                'design' => [
                    'path' => 'M12 2a9 9 0 0 1 9 9c0 6-9 13-9 13S3 17 3 11a9 9 0 0 1 9-9z',
                    'circle' => ['cx' => 12, 'cy' => 11, 'r' => 3],
                    'class' => 'bg-green-50 text-green-600',
                ],
                'maintenance' => [
                    'path' => 'M3 12h18M3 6h18M3 18h12',
                    'class' => 'bg-blue-50 text-blue-600',
                ],
                'hardscaping' => [
                    'path' => 'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',
                    'polyline' => '9 22 9 12 15 12 15 22',
                    'class' => 'bg-amber-50 text-amber-600',
                ],
              ];
            @endphp
            @foreach ($rateCard['project_types'] as $val => $projectType)
            @php
              $title = $projectType['label'];
              $desc = $projectType['description'];
              $rate = '₱'.number_format($projectType['rate']).'/sq m';
              $art = $projectArt[$val] ?? [
                  'path' => 'M3 12h18M3 6h18M3 18h12',
                  'class' => 'bg-surface-100 text-surface-600',
              ];
              $iconClass = $art['class'];
            @endphp
            <label class="step-card cursor-pointer">
              <input type="radio" name="project_type" value="{{ $val }}" class="sr-only" {{ $val === $rateCard['defaults']['project_type'] ? 'checked' : '' }} onchange="calculate()">
              <div class="card-body border border-surface-200 rounded-xl p-4 hover:border-brand-200 relative">
                {{-- Checkmark badge (top-right corner) --}}
                <div class="card-check absolute top-2.5 right-2.5 w-5 h-5 bg-brand-600 rounded-full flex items-center justify-center">
                  <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3.5"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div class="card-icon w-9 h-9 rounded-lg {{ $iconClass }} flex items-center justify-center mb-3 transition-colors">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="{{ $art['path'] }}"/>
                    @isset($art['circle'])
                      <circle cx="{{ $art['circle']['cx'] }}" cy="{{ $art['circle']['cy'] }}" r="{{ $art['circle']['r'] }}"/>
                    @endisset
                    @isset($art['polyline'])
                      <polyline points="{{ $art['polyline'] }}"/>
                    @endisset
                  </svg>
                </div>
                <p class="card-title text-sm font-semibold text-surface-900 mb-1">{{ $title }}</p>
                <p class="text-[11px] text-surface-400 leading-snug mb-3">{{ $desc }}</p>
                <span class="card-rate-label inline-block text-[10px] font-semibold bg-surface-100 text-surface-500 px-2 py-0.5 rounded-full">{{ $rate }}</span>
                <span class="card-selected-label items-center gap-1 text-[10px] font-semibold bg-brand-600 text-white px-2 py-0.5 rounded-full">
                  <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5"><polyline points="20 6 9 17 4 12"/></svg>
                  Selected
                </span>
              </div>
            </label>
            @endforeach
          </div>
        </div>

        {{-- Step 2: Property Size --}}
        <div class="bg-white rounded-2xl border border-surface-100 shadow-sm overflow-hidden">
          <div class="flex items-center gap-3 px-5 py-4 border-b border-surface-50">
            <span class="step-badge bg-brand-600 text-white">2</span>
            <div>
              <h3 class="text-sm font-semibold text-surface-900">Property size</h3>
              <p class="text-xs text-surface-400">Enter the area in square metres.</p>
            </div>
          </div>
          <div class="p-5">
            <div class="relative mb-1">
              <input type="number" id="size-input" aria-label="Property size in square metres" min="1" step="1" value="{{ $rateCard['defaults']['size'] }}" placeholder="e.g. 2500"
                     class="w-full border border-surface-200 rounded-xl px-4 py-3.5 text-2xl font-display font-bold text-surface-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 transition-all pr-20"
                     oninput="updateSizeUI(); calculate()">
              <span class="absolute right-4 top-1/2 -translate-y-1/2 text-surface-400 text-sm font-medium pointer-events-none">sq m</span>
            </div>
            <p id="size-error" class="hidden text-xs text-red-500 mt-1 mb-2">Please enter a valid size greater than 0.</p>

            {{-- Quick-pick buttons --}}
            <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-surface-50">
              <span class="text-[11px] text-surface-400 self-center mr-1">Quick pick:</span>
              @foreach ($rateCard['quick_sizes'] as $sz)
              <button type="button" onclick="setSize({{ $sz }})"
                      class="size-btn text-xs px-3 py-1.5 rounded-lg border border-surface-200 text-surface-600 hover:border-brand-400 hover:bg-brand-50 hover:text-brand-700 transition-colors font-medium">
                {{ number_format($sz) }} sq m
              </button>
              @endforeach
            </div>
          </div>
        </div>

        {{-- Step 3: Quality Tier --}}
        <div class="bg-white rounded-2xl border border-surface-100 shadow-sm overflow-hidden">
          <div class="flex items-center gap-3 px-5 py-4 border-b border-surface-50">
            <span class="step-badge bg-brand-600 text-white">3</span>
            <div>
              <h3 class="text-sm font-semibold text-surface-900">Quality tier</h3>
              <p class="text-xs text-surface-400">Choose the finish level.</p>
            </div>
          </div>
          <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-3">
            @php
              $tierBadgeClasses = [
                'standard' => 'bg-zinc-100 text-zinc-600',
                'premium'  => 'bg-violet-50 text-violet-700',
                'luxury'   => 'bg-amber-50 text-amber-700',
              ];
            @endphp
            @foreach ($rateCard['tiers'] as $val => $tier)
            @php
              $label = $tier['label'];
              $desc = $tier['description'];
              // 1.0 reads as "1×", 1.6 stays "1.6×".
              $mult = rtrim(rtrim(number_format($tier['multiplier'], 1), '0'), '.').'×';
              $badgeClass = $tierBadgeClasses[$val] ?? 'bg-surface-100 text-surface-600';
            @endphp
            <label class="tier-card cursor-pointer">
              <input type="radio" name="quality_tier" value="{{ $val }}" data-mult="{{ $mult }}" class="sr-only" {{ $val === $rateCard['defaults']['tier'] ? 'checked' : '' }} onchange="calculate()">
              <div class="tier-body h-full border border-surface-200 rounded-xl p-4 hover:border-brand-200">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-sm font-semibold text-surface-900">{{ $label }}</span>
                  {{-- Plain dot (unselected) --}}
                  <span class="tier-dot w-4 h-4 rounded-full bg-surface-200 border-2 border-surface-300"></span>
                  {{-- Checkmark (selected) --}}
                  <span class="tier-check w-5 h-5 bg-brand-600 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3.5"><polyline points="20 6 9 17 4 12"/></svg>
                  </span>
                </div>
                <p class="text-[11px] text-surface-400 leading-snug mb-3">{{ $desc }}</p>
                <span class="mt-3 inline-block text-[10px] font-bold {{ $badgeClass }} px-2 py-0.5 rounded-full">{{ $mult }} multiplier</span>
                <p class="mt-2 text-[10px] font-medium text-brand-700">See visualization in the package preview</p>
              </div>
            </label>
            @endforeach
          </div>
        </div>

        {{-- Step 4: Add-ons --}}
        <div class="bg-white rounded-2xl border border-surface-100 shadow-sm overflow-hidden">
          <div class="flex items-center gap-3 px-5 py-4 border-b border-surface-50">
            <span class="step-badge bg-brand-600 text-white">4</span>
            <div class="flex-1">
              <div class="flex items-center gap-2">
                <h3 class="text-sm font-semibold text-surface-900">Extra features</h3>
                <span id="addon-count-badge" class="hidden items-center gap-1 text-[10px] font-semibold bg-brand-600 text-white px-2 py-0.5 rounded-full">
                  <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5"><polyline points="20 6 9 17 4 12"/></svg>
                  <span id="addon-count-text">0</span> selected
                </span>
              </div>
              <p class="text-xs text-surface-400">Optional add-ons</p>
            </div>
          </div>
          <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
            @php
              $addonArt = [
                'irrigation' => ['M12 2a10 10 0 0 1 0 20A10 10 0 0 1 12 2zm0 4v4m0 4h.01',                            'text-blue-500',   'bg-blue-50'],
                'lighting'   => ['M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41', 'text-yellow-500', 'bg-yellow-50'],
                'water'      => ['M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z', 'text-cyan-500',   'bg-cyan-50'],
                'pergola'    => ['M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',                                    'text-amber-600',  'bg-amber-50'],
                'fence'      => ['M4 4h16v2H4zm0 7h16v2H4zm0 7h16v2H4z',                                              'text-zinc-500',   'bg-zinc-100'],
                // A real rounded square. This used to be the sharp-cornered
                // path plus a smuggled `rx="2"`, which broke the d attribute
                // once escaped - and would have been ignored even raw, because
                // rx is not a <path> attribute.
                'soil'       => ['M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z', 'text-green-600',  'bg-green-50'],
              ];
            @endphp
            @foreach ($rateCard['addons'] as $addonKey => $addon)
            @php
              // The JS reads these ids and the data-amount attribute below.
              $id = 'addon-'.$addonKey;
              $label = $addon['label'];
              $desc = $addon['description'];
              $amount = $addon['amount'];
              $price = '+ ₱'.number_format($amount);
              [$icon, $iconColor, $iconBg] = $addonArt[$addonKey] ?? ['M3 3h18v18H3z', 'text-surface-500', 'bg-surface-100'];
            @endphp
            <label class="addon-item cursor-pointer">
              <input type="checkbox" id="{{ $id }}" data-amount="{{ $amount }}" class="sr-only" onchange="calculate(); updateAddonCount()">
              <div class="addon-box border border-surface-200 rounded-xl p-3.5 hover:border-brand-200 flex items-start gap-3 relative">
                {{-- Checkmark badge (top-right) --}}
                <div class="addon-check absolute top-2.5 right-2.5 w-5 h-5 bg-brand-600 rounded-full flex items-center justify-center">
                  <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3.5"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                {{-- Feature icon --}}
                <div class="addon-icon w-9 h-9 rounded-lg {{ $iconBg }} {{ $iconColor }} flex items-center justify-center flex-shrink-0">
                  <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="{{ $icon }}"/></svg>
                </div>
                {{-- Content --}}
                <div class="flex-1 min-w-0 pr-5">
                  <div class="flex items-start justify-between gap-2">
                    <span class="addon-label text-sm font-medium text-surface-800 transition-colors leading-snug">{{ $label }}</span>
                    <span class="text-xs font-semibold text-brand-600 flex-shrink-0">{{ $price }}</span>
                  </div>
                  <p class="text-[11px] text-surface-400 mt-0.5 leading-snug">{{ $desc }}</p>
                </div>
              </div>
            </label>
            @endforeach
          </div>
        </div>

        {{-- Step 5: Products / Materials --}}
        <div class="bg-white rounded-2xl border border-surface-100 shadow-sm overflow-hidden">
          <div class="flex items-center gap-3 px-5 py-4 border-b border-surface-50">
            <span class="step-badge bg-brand-600 text-white">5</span>
            <div class="flex-1">
              <div class="flex items-center gap-2">
                <h3 class="text-sm font-semibold text-surface-900">Products / Materials</h3>
                <span id="product-count-badge" class="hidden items-center gap-1 text-[10px] font-semibold bg-brand-600 text-white px-2 py-0.5 rounded-full">
                  <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5"><polyline points="20 6 9 17 4 12"/></svg>
                  <span id="product-count-text">0</span> selected
                </span>
              </div>
              <p class="text-xs text-surface-400">Optional shop items to include in the estimate.</p>
            </div>
          </div>
          <div class="p-5">
            @if (($estimateProducts ?? collect())->isNotEmpty())
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach ($estimateProducts as $product)
                  <div class="estimate-product border border-surface-200 rounded-xl p-3.5 hover:border-brand-200 transition-colors"
                       data-name="{{ $product->name }}"
                       data-price="{{ (float) $product->price }}">
                    <div class="flex items-start gap-3">
                      <input type="checkbox"
                             aria-label="Include {{ $product->name }} in the estimate"
                             class="estimate-product-check mt-1 rounded border-surface-300 text-brand-600 focus:ring-brand-500"
                             onchange="toggleEstimateProduct(this); calculate()">
                      <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-3">
                          <div class="min-w-0">
                            <p class="text-sm font-medium text-surface-800 truncate">{{ $product->name }}</p>
                            <p class="text-[10px] text-surface-400 capitalize">{{ $product->category }} &middot; {{ $product->stock_qty }} in stock</p>
                          </div>
                          <p class="text-xs font-semibold text-brand-600 whitespace-nowrap">&#8369;{{ number_format((float) $product->price, 2) }}</p>
                        </div>
                        <div class="estimate-product-qty mt-3 hidden items-center justify-between gap-3">
                          <span class="text-[10px] text-surface-400">Quantity</span>
                          <input type="number" min="1" step="1" value="1"
                                 aria-label="Quantity of {{ $product->name }}"
                                 class="w-20 border border-surface-200 rounded-lg px-2 py-1.5 text-xs text-surface-700 outline-none focus:border-brand-500"
                                 oninput="calculate()">
                        </div>
                      </div>
                    </div>
                  </div>
                @endforeach
              </div>
            @else
              <div class="rounded-xl border border-dashed border-surface-200 p-5 text-center">
                <p class="text-sm font-medium text-surface-700">No active products available</p>
                <p class="text-xs text-surface-400 mt-1">Add active products in admin to include materials in estimates.</p>
              </div>
            @endif
          </div>
        </div>

      </div>{{-- end left col --}}

      {{-- ── Right: Estimate Card ──────────────────────────────── --}}
      <div class="lg:col-span-1">
        <div class="sticky top-6 space-y-4">

          {{-- Card header --}}
          <section class="estimate-hero hidden lg:block px-5 py-5 text-white" aria-label="Current estimate" aria-live="polite">
            <p class="text-[11px] font-bold text-white/60 uppercase tracking-wider">Your Estimate</p>
            <div id="total-price" data-estimate-total class="text-[34px] leading-none font-sans font-bold tracking-tight text-white price-animate mt-2">&#8369;5,000</div>
            <p data-estimate-summary class="text-white/80 text-sm mt-3 truncate">Garden Design | 100 sq m | Standard</p>
            <p class="text-white/60 text-xs font-medium mt-3">
              Typical range <span id="range-low" data-range-low>&#8369;4,000</span>
              &ndash; <span id="range-high" data-range-high>&#8369;6,250</span>
            </p>
          </section>

          <div class="bg-white rounded-2xl border border-surface-100 shadow-sm overflow-hidden">

          {{-- Breakdown --}}
          <div class="px-5 py-4 border-b border-surface-100">
            <p class="text-[10px] font-semibold text-surface-400 uppercase tracking-wider mb-3">Breakdown</p>
            <div id="breakdown-list" class="space-y-0.5 text-sm">
              <div class="price-row">
                <span class="text-surface-600 text-xs" id="base-label">Base (Design · 100 sq m)</span>
                <span class="font-medium text-surface-800 text-xs" id="base-amount">₱5,000</span>
              </div>
              <div class="price-row">
                <span class="text-surface-600 text-xs">Quality tier</span>
                <span class="font-medium text-surface-800 text-xs" id="tier-label">Standard (1×)</span>
              </div>
              <div id="addon-rows"></div>
            </div>
          </div>

          {{-- Generated package visualization --}}
          <div class="px-5 py-4 border-b border-surface-100">
            <div class="flex items-center justify-between gap-3 mb-3">
              <div>
                <p class="text-[10px] font-semibold text-surface-400 uppercase tracking-wider">Generated Package</p>
                <h3 id="package-title" class="text-sm font-semibold text-surface-900 mt-0.5">Garden Design Package</h3>
              </div>
              <span id="package-pill" class="text-[10px] font-semibold px-2 py-1 rounded-full bg-brand-50 text-brand-700 border border-brand-100">Standard</span>
            </div>
            <div class="mb-3 overflow-hidden rounded-xl border border-surface-100 bg-surface-100 shadow-sm">
              <button type="button"
                      onclick="openPackageZoom()"
                      aria-label="Open a larger view of the selected package visualization"
                      class="group relative mx-auto block aspect-[3/4] w-full max-w-xs cursor-zoom-in overflow-hidden bg-surface-200 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500">
                <img id="package-visual-sprite"
                     src="{{ asset('images/tier-package-visuals.png') }}"
                     alt="Standard starter garden package visualization"
                     class="absolute inset-y-0 left-0 h-auto min-h-full w-[300%] max-w-none object-cover transition-transform duration-500 ease-out"
                     style="transform: translateX(0%);">
                <span class="absolute right-3 top-3 z-10 inline-flex items-center gap-1.5 rounded-full border border-white/40 bg-black/55 px-2.5 py-1.5 text-[10px] font-semibold text-white shadow-sm backdrop-blur-sm transition group-hover:bg-black/70" aria-hidden="true">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="7"/>
                    <path d="m20 20-4-4"/>
                    <path d="M11 8v6M8 11h6"/>
                  </svg>
                  Zoom
                </span>
                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 via-black/35 to-transparent px-4 pb-4 pt-16 text-white">
                  <p class="text-[10px] font-semibold uppercase tracking-wider text-white/70">Example creation</p>
                  <p id="package-visual-title" class="mt-0.5 text-sm font-semibold">Starter Garden</p>
                  <p id="package-visual-caption" class="mt-1 text-[11px] leading-snug text-white/85">A practical garden using common plants, lawn, and simple edging.</p>
                </div>
              </button>
            </div>
            <div class="rounded-xl border border-surface-100 bg-white overflow-hidden">
              <div class="grid grid-cols-2 divide-x divide-surface-100 border-b border-surface-100">
                <div class="p-3">
                  <p class="text-[10px] text-surface-400">Area</p>
                  <p id="package-area" class="text-xs font-semibold text-surface-800 mt-0.5">100 sq m</p>
                </div>
                <div class="p-3">
                  <p class="text-[10px] text-surface-400">Items</p>
                  <p id="package-item-count" class="text-xs font-semibold text-surface-800 mt-0.5">1 service</p>
                </div>
              </div>
              <div class="p-3">
                <p class="text-[10px] font-semibold text-surface-400 uppercase tracking-wider mb-1.5">Includes</p>
                <div id="package-includes" class="space-y-1.5"></div>
              </div>
            </div>
          </div>

          {{-- CTA --}}
          <div class="p-5 space-y-2.5">
            <a href="{{ route('schedule') }}"
               class="w-full bg-brand-600 hover:bg-brand-700 text-white font-semibold py-3 rounded-xl text-sm transition-colors flex justify-center items-center gap-2 shadow-sm">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              Book Consultation
            </a>

            {{-- AR lives in the Android app, which owns the camera and ARCore.
                 This used to be a button that deep-linked to `ferosa://ar`; in a
                 desktop browser it could only ever open a modal saying "use your
                 phone", so it read as a broken control. A plain note states the
                 capability without offering a click that cannot deliver it. --}}
            <p class="flex justify-center items-center gap-1.5 pt-1 text-[11px] text-surface-400">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M12 3l8 4v10l-8 4-8-4V7l8-4z"/><path d="M12 12l8-5"/><path d="M12 12v9"/><path d="M12 12L4 7"/></svg>
              AR preview available in the Ferosa Android app
            </p>
            <button onclick="shareEstimate()"
                    class="w-full text-surface-400 hover:text-surface-600 text-xs py-1 flex justify-center items-center gap-1.5 transition-colors">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
              Share this estimate
            </button>
          </div>

          {{-- Disclaimer --}}
          <div class="px-5 pb-5">
            <p class="text-[10px] text-surface-400 leading-relaxed text-center">
              Estimates are indicative only. A site visit may be required for an accurate quote.
            </p>
          </div>
          </div>
        </div>
      </div>

    </div>{{-- end grid --}}
  </div>{{-- end container --}}
</main>

{{-- Package visualization zoom dialog --}}
<div id="package-zoom-dialog"
     class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/85 p-4 backdrop-blur-sm"
     role="dialog"
     aria-modal="true"
     aria-labelledby="package-zoom-title"
     onclick="closePackageZoomFromBackdrop(event)">
  <div class="relative overflow-hidden rounded-2xl border border-white/20 bg-surface-900 shadow-2xl"
       style="height: min(82vh, calc(92vw * 1.3333)); aspect-ratio: 3 / 4;">
    <img id="package-zoom-image"
         src="{{ asset('images/tier-package-visuals.png') }}"
         alt="Standard starter garden package visualization"
         class="absolute inset-y-0 left-0 h-auto min-h-full w-[300%] max-w-none object-cover transition-transform duration-500 ease-out"
         style="transform: translateX(0%);">
    <button id="package-zoom-close"
            type="button"
            onclick="closePackageZoom()"
            aria-label="Close enlarged package visualization"
            class="absolute right-3 top-3 z-20 flex h-10 w-10 items-center justify-center rounded-full border border-white/40 bg-black/60 text-white shadow-md backdrop-blur-sm transition hover:bg-black/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <path d="M6 6l12 12M18 6 6 18"/>
      </svg>
    </button>
    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/85 via-black/45 to-transparent px-5 pb-5 pt-24 text-white">
      <p class="text-[10px] font-semibold uppercase tracking-wider text-white/70">Example creation</p>
      <p id="package-zoom-title" class="mt-1 text-lg font-semibold">Starter Garden</p>
      <p id="package-zoom-caption" class="mt-1 max-w-md text-sm leading-snug text-white/85">A practical garden using common plants, lawn, and simple edging.</p>
    </div>
  </div>
</div>

@include('partials.mobile-bottom-customer')
@endsection

@section('scripts')
<script>
  // ─── Pricing config ───────────────────────────────────────────────────────
  // Rendered from config/estimator.php. The same structure is served to the
  // Android app at /api/mobile/estimator-rates, so a price change reaches the
  // web page and the app from one edit. Do not hardcode rates here again.
  const RATE_CARD = @json($rateCard);

  /** Object.fromEntries is Chrome 73+; some Android 7 WebViews are older. */
  function mapValues(source, pick) {
    const out = {};
    Object.keys(source).forEach(function (key) { out[key] = pick(source[key]); });
    return out;
  }

  const BASE_RATES = mapValues(RATE_CARD.project_types, t => t.rate);          // ₱ per sq m
  const PROJECT_NAME = mapValues(RATE_CARD.project_types, t => t.label);
  const TIER_MULT = mapValues(RATE_CARD.tiers, t => t.multiplier);
  const TIER_NAME = mapValues(RATE_CARD.tiers, t => t.label);
  const TIER_LABEL = mapValues(RATE_CARD.tiers, t => `${t.label} (${t.multiplier}×)`);
  const TIER_EXAMPLES = mapValues(RATE_CARD.tiers, t => t.examples);
  const TIER_VISUALS = mapValues(RATE_CARD.tiers, t => ({
    title: t.package_title,
    caption: t.caption,
    alt: `${t.label} ${t.package_title.toLowerCase()} package visualization`,
    // The sprite is three panels wide, so panel n starts at -(n × 100/3)%.
    position: -(t.visual_index * (100 / 3)),
  }));

  // ─── Helpers ─────────────────────────────────────────────────────────────
  function fmt(n) {
    return '\u20B1' + Math.round(n).toLocaleString('en-PH');
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function updateSizeUI() {
    const input = document.getElementById('size-input');
    const v = parseInt(input.value) || 0;
    const errorEl = document.getElementById('size-error');
    const invalid = v <= 0 && input.value !== '';
    errorEl.classList.toggle('hidden', !invalid);
    input.classList.toggle('border-red-400', invalid);
    input.classList.toggle('border-surface-200', !invalid);
    // highlight matching quick-pick
    document.querySelectorAll('.size-btn').forEach(btn => {
      const btnVal = parseInt(btn.textContent.replace(/[^0-9]/g, ''));
      btn.classList.toggle('is-active', btnVal === v);
    });
  }

  function setSize(val) {
    document.getElementById('size-input').value = val;
    updateSizeUI();
    calculate();
  }

  // ─── Main calculate ───────────────────────────────────────────────────────
  function calculate() {
    const typeEl = document.querySelector('input[name="project_type"]:checked');
    const tierEl = document.querySelector('input[name="quality_tier"]:checked');
    if (!typeEl || !tierEl) return;

    const type  = typeEl.value;
    const size  = parseInt(document.getElementById('size-input').value) || 0;
    const rate  = BASE_RATES[type] || 50;
    const mult  = TIER_MULT[tierEl.value] || 1;
    const base  = rate * size * mult;

    // Extras
    let extrasTotal = 0;
    const addonRows = [];
    document.querySelectorAll('.addon-item input[type="checkbox"]').forEach(cb => {
      if (cb.checked) {
        const amount = parseInt(cb.dataset.amount) || 0;
        extrasTotal += amount;
        const label  = cb.closest('.addon-item').querySelector('.text-sm.font-medium')?.textContent?.trim() || '';
        const fmtAmt = cb.closest('.addon-item').querySelector('.text-xs.font-semibold')?.textContent?.trim() || '';
        addonRows.push({ label, fmtAmt, amount });
      }
    });

    let productsTotal = 0;
    const productRows = [];
    document.querySelectorAll('.estimate-product').forEach(card => {
      const cb = card.querySelector('.estimate-product-check');
      if (!cb?.checked) return;

      const qtyInput = card.querySelector('.estimate-product-qty input');
      const qty = Math.max(1, parseInt(qtyInput?.value) || 1);
      const price = parseFloat(card.dataset.price) || 0;
      const amount = price * qty;
      const label = card.dataset.name || 'Product';
      productsTotal += amount;
      productRows.push({ label, qty, price, amount });
    });

    const total = base + extrasTotal + productsTotal;

    // ── Update total ──
    document.querySelectorAll('[data-estimate-total]').forEach(totalEl => {
      totalEl.textContent = fmt(total);
      totalEl.classList.remove('price-animate');
      void totalEl.offsetWidth;
      totalEl.classList.add('price-animate');
    });

    const estimateSummary = `${PROJECT_NAME[type]} | ${size.toLocaleString()} sq m | ${TIER_NAME[tierEl.value]}`;
    document.querySelectorAll('[data-estimate-summary]').forEach(summaryEl => {
      summaryEl.textContent = estimateSummary;
    });

    // ── Update breakdown ──
    const typeLabel = { design: 'Design', maintenance: 'Maintenance', hardscaping: 'Hardscaping' }[type];
    document.getElementById('base-label').textContent  = `Base (${typeLabel} · ${size.toLocaleString()} sq m)`;
    document.getElementById('base-amount').textContent = fmt(base);
    document.getElementById('tier-label').textContent  = TIER_LABEL[tierEl.value];

    // Addon and product rows
    const addonContainer = document.getElementById('addon-rows');
    const addonHtml = addonRows.map(r => `
      <div class="price-row">
        <span class="text-surface-600 text-xs">${escapeHtml(r.label)}</span>
        <span class="font-medium text-surface-800 text-xs">${fmt(r.amount)}</span>
      </div>
    `).join('');
    const productHtml = productRows.map(r => `
      <div class="price-row">
        <span class="text-surface-600 text-xs">${escapeHtml(r.label)} x ${r.qty}</span>
        <span class="font-medium text-surface-800 text-xs">${fmt(r.amount)}</span>
      </div>
    `).join('');
    addonContainer.innerHTML = addonHtml + productHtml + ((addonRows.length || productRows.length) ? `
      <div class="price-row font-semibold">
        <span class="text-surface-800 text-xs">Total</span>
        <span class="text-surface-900 text-xs">${fmt(total)}</span>
      </div>
    ` : '');

    // ── Range ──
    document.querySelectorAll('[data-range-low]').forEach(el => { el.textContent = fmt(total * RATE_CARD.range.low); });
    document.querySelectorAll('[data-range-high]').forEach(el => { el.textContent = fmt(total * RATE_CARD.range.high); });

    updateGeneratedPackage({
      type,
      typeLabel,
      tier: tierEl.value,
      size,
      base,
      addonRows,
      productRows,
    });
  }

  // ─── AR Visualizer ───────────────────────────────────────────────────────
  function updateGeneratedPackage(data) {
    const packageName = `${data.typeLabel} Package`;
    const tierName = data.tier.charAt(0).toUpperCase() + data.tier.slice(1);
    const addonCount = data.addonRows.length;
    const productCount = data.productRows.reduce((sum, item) => sum + item.qty, 0);
    const itemCount = 1 + addonCount + productCount;

    document.getElementById('package-title').textContent = packageName;
    document.getElementById('package-pill').textContent = tierName;
    document.getElementById('package-area').textContent = `${data.size.toLocaleString()} sq m`;
    document.getElementById('package-item-count').textContent = `${itemCount} item${itemCount === 1 ? '' : 's'}`;

    const visual = TIER_VISUALS[data.tier] || TIER_VISUALS.standard;
    const visualImage = document.getElementById('package-visual-sprite');
    visualImage.style.transform = `translateX(${visual.position}%)`;
    visualImage.alt = visual.alt;
    const zoomImage = document.getElementById('package-zoom-image');
    zoomImage.style.transform = `translateX(${visual.position}%)`;
    zoomImage.alt = visual.alt;
    document.getElementById('package-visual-title').textContent = visual.title;
    document.getElementById('package-visual-caption').textContent = visual.caption;
    document.getElementById('package-zoom-title').textContent = visual.title;
    document.getElementById('package-zoom-caption').textContent = visual.caption;

    const serviceLine = `
      <div class="flex items-start justify-between gap-3">
        <span class="text-xs text-surface-600">Service: ${escapeHtml(data.typeLabel)}</span>
        <span class="text-xs font-semibold text-surface-800">${fmt(data.base)}</span>
      </div>
    `;
    const tierLines = (TIER_EXAMPLES[data.tier] || []).map(item => `
      <div class="flex items-start gap-2">
        <span class="text-brand-600">✓</span>
        <span class="text-xs text-surface-600">${escapeHtml(item)}</span>
      </div>
    `).join('');
    const addonLines = data.addonRows.map(row => `
      <div class="flex items-start justify-between gap-3">
        <span class="text-xs text-surface-600">${escapeHtml(row.label)}</span>
        <span class="text-xs font-semibold text-surface-800">${fmt(row.amount)}</span>
      </div>
    `).join('');
    const productLines = data.productRows.map(row => `
      <div class="flex items-start justify-between gap-3">
        <span class="text-xs text-surface-600">${escapeHtml(row.label)} x ${row.qty}</span>
        <span class="text-xs font-semibold text-surface-800">${fmt(row.amount)}</span>
      </div>
    `).join('');

    document.getElementById('package-includes').innerHTML = serviceLine + tierLines + addonLines + productLines;
  }

  let packageZoomReturnFocus = null;

  function openPackageZoom() {
    const dialog = document.getElementById('package-zoom-dialog');
    packageZoomReturnFocus = document.activeElement;
    dialog.classList.remove('hidden');
    dialog.classList.add('flex');
    document.body.classList.add('overflow-hidden');
    document.getElementById('package-zoom-close').focus();
  }

  function closePackageZoom() {
    const dialog = document.getElementById('package-zoom-dialog');
    if (dialog.classList.contains('hidden')) return;
    dialog.classList.add('hidden');
    dialog.classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
    packageZoomReturnFocus?.focus();
  }

  function closePackageZoomFromBackdrop(event) {
    if (event.target === event.currentTarget) closePackageZoom();
  }

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closePackageZoom();
  });

  function toggleEstimateProduct(checkbox) {
    const card = checkbox.closest('.estimate-product');
    const qty = card.querySelector('.estimate-product-qty');
    card.classList.toggle('border-brand-500', checkbox.checked);
    card.classList.toggle('bg-brand-50', checkbox.checked);
    qty.classList.toggle('hidden', !checkbox.checked);
    qty.classList.toggle('flex', checkbox.checked);
    updateProductCount();
  }

  function updateProductCount() {
    const checked = document.querySelectorAll('.estimate-product-check:checked').length;
    const badge = document.getElementById('product-count-badge');
    const text = document.getElementById('product-count-text');
    if (!badge || !text) return;
    text.textContent = checked;
    badge.classList.toggle('hidden', checked === 0);
    badge.classList.toggle('inline-flex', checked > 0);
  }

  // ─── Share ────────────────────────────────────────────────────────────────
  function shareEstimate() {
    const total = document.querySelector('[data-estimate-total]')?.textContent || '';
    const text  = `My Ferosa landscaping estimate: ${total}. Get yours at ${location.href}`;
    if (navigator.share) {
      navigator.share({ title: 'Ferosa Cost Estimate', text, url: location.href });
    } else {
      navigator.clipboard?.writeText(text).then(() => alert('Link copied to clipboard!'));
    }
  }

  // ─── Addon count badge ────────────────────────────────────────────────────
  function updateAddonCount() {
    const checked = document.querySelectorAll('.addon-item input[type="checkbox"]:checked').length;
    const badge   = document.getElementById('addon-count-badge');
    document.getElementById('addon-count-text').textContent = checked;
    badge.classList.toggle('hidden',  checked === 0);
    badge.classList.toggle('inline-flex', checked > 0);
  }

  // ─── Init ─────────────────────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    updateSizeUI();
    calculate();
    updateAddonCount();
    updateProductCount();
  });
</script>
@endsection
