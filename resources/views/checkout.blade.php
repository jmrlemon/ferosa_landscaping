@extends('layouts.customer')

@section('title', 'Checkout - Ferosa Landscaping')

@section('styles')
<style>
  #checkout-mobile-bar { display: none; }
  body.in-app #checkout-btn { display: none; }
  /* A phone is too narrow for icon + name + stepper + subtotal on one line;
     the product name was collapsing to "De...". Let the row wrap so the
     stepper and subtotal drop underneath the full name. */
  body.in-app .cart-line { flex-wrap: wrap; gap: .6rem; }
  body.in-app .cart-line-info { flex-basis: 0; min-width: 8rem; }
  body.in-app .cart-line-controls {
    width: 100%;
    justify-content: space-between;
    padding-left: 4rem;
  }
  body.in-app #checkout-mobile-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 40;
    background: #fff;
    border-top: 1px solid #eae7df;
    padding: .65rem 1rem calc(.65rem + env(safe-area-inset-bottom));
    box-shadow: 0 -8px 24px rgba(18,52,38,.08);
  }
</style>
@endsection

@section('content')
@php
  $gcashName = $gcashSettings['name'] ?? null;
  $gcashNumber = $gcashSettings['number'] ?? null;
  $gcashQrUrl = $gcashSettings['qr_url'] ?? null;
  $gcashAvailable = filled($gcashNumber) || filled($gcashQrUrl);
  $selectedPaymentMethod = old('payment_method', 'cod');
  $selectedDiscountBeneficiary = $canRequestDiscount ? old('discount_beneficiary', 'none') : 'none';
  $vatRegistered = config('discounts.vat_registered');
  $vatRatePercent = max(0, (int) config('discounts.vat_rate_percent', 12));
  $cartItemCount = (int) ($cartSummary['cart_count'] ?? 0);
  $cartSubtotal = (float) ($cartSummary['subtotal'] ?? 0);
  $formatMoney = static fn (float $amount): string => rtrim(rtrim(number_format($amount, 2, '.', ','), '0'), '.');
  $showVatBreakdown = $vatRegistered === true
      && (bool) config('discounts.prices_include_vat', true)
      && (bool) config('discounts.all_checkout_products_vatable', false)
      && $vatRatePercent > 0;
  $initialVatAmount = $showVatBreakdown
      ? round($cartSubtotal * $vatRatePercent / (100 + $vatRatePercent), 2)
      : 0;
  $initialBeforeVat = $showVatBreakdown
      ? round($cartSubtotal - $initialVatAmount, 2)
      : $cartSubtotal;
  $checkoutTaxNotice = $checkoutTaxModeUnsupported
      ? 'Online checkout is paused because VAT-exclusive price calculation is not configured.'
      : ($showVatBreakdown
          ? 'Item prices already include VAT. Subtotal before VAT plus VAT equals your total.'
          : ($vatRegistered === true
              ? 'VAT is included in displayed prices where applicable. No extra VAT will be added at checkout.'
              : ($vatRegistered === false
                  ? 'Displayed prices are final. No additional VAT is added at checkout.'
                  : 'No separate VAT is added at checkout. Ferosa’s VAT registration status is still being confirmed.')));
@endphp
<main class="customer-page">
  <a href="{{ route('shop') }}" class="mb-4 inline-flex items-center gap-1.5 text-xs font-bold text-surface-500 transition-colors hover:text-brand-700">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="15 18 9 12 15 6"/></svg>
    Back to shop
  </a>

  <x-page-head
    kicker="Almost there"
    title="Checkout"
    sub="Review your items, tell us where to deliver, and choose how you would like to pay.">
    <x-slot:icon>
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>
      </svg>
    </x-slot:icon>
  </x-page-head>

  @if($errors->any())
    <x-alert type="error" class="mb-6 reveal">
      <p class="font-bold">We could not place your order yet:</p>
      <ul class="mt-1 list-disc space-y-0.5 pl-4">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </x-alert>
  @endif

  <form method="POST" action="{{ route('checkout.store') }}" id="checkout-form" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="checkout_token" value="{{ $checkoutToken }}">
    <input type="hidden" name="cart_data" id="cart-data-input">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left column: Items + Delivery + Payment -->
      <div class="lg:col-span-2 space-y-6">

        <!-- Cart Items -->
        <div id="cart-items-container" class="customer-card p-5 sm:p-6">
          <ul id="cart-list" class="divide-y divide-surface-100"></ul>
          <div id="empty-cart-msg" class="hidden customer-empty shadow-none border-0">
            <div class="customer-empty-icon">
              <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            </div>
            <h2 class="text-base font-bold text-surface-900 mb-1">Your cart is empty</h2>
            <p class="text-surface-500 text-sm mb-5">Add products from the shop before placing an order.</p>
            <a href="{{ route('shop') }}" class="btn btn-primary btn-sm">Browse shop</a>
          </div>
        </div>

        <!-- Delivery Details -->
        <div class="customer-card p-5 sm:p-6">
          <h2 class="mb-4 font-display text-lg font-bold text-surface-900">How should we get it to you?</h2>

          <!-- Toggle Tabs -->
          <div class="mb-5 grid grid-cols-2 gap-2 rounded-xl border border-surface-200 bg-surface-50 p-1">
            <button type="button" id="tab-delivery"
              onclick="setDeliveryMethod('delivery')"
              class="rounded-lg px-3 py-2 text-xs font-bold transition-colors bg-white text-brand-800 shadow-sm">
              Delivery
            </button>
            <button type="button" id="tab-pickup"
              onclick="setDeliveryMethod('pickup')"
              class="rounded-lg px-3 py-2 text-xs font-bold transition-colors text-surface-500 hover:text-surface-800">
              Pick-up
            </button>
          </div>

          <input type="hidden" name="delivery_method" id="delivery_method_input" value="delivery">

          <!-- Delivery Fields -->
          <div id="delivery-fields" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="delivery_name" class="field-label">Full name <span class="text-red-500">*</span></label>
                <input type="text" id="delivery_name" name="delivery_name" value="{{ old('delivery_name') }}"
                  placeholder="e.g. Juan Dela Cruz" class="field">
              </div>
              <div>
                <label for="delivery_phone" class="field-label">Phone number <span class="text-red-500">*</span></label>
                <input type="text" id="delivery_phone" name="delivery_phone" value="{{ old('delivery_phone') }}"
                  placeholder="e.g. 09XX XXX XXXX" class="field">
              </div>
            </div>
            <p id="address-order-hint" class="text-xs leading-5 text-surface-500">
              Delivery is available within Bataan only. Choose your city or municipality and barangay, then enter your house or street details.
            </p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <span class="field-label">Delivery area</span>
                <div class="flex min-h-[42px] items-center rounded-xl border border-brand-100 bg-brand-50 px-3.5 text-sm font-semibold text-brand-900" aria-describedby="address-order-hint">
                  {{ $addressArea['name'] }}
                </div>
                <input type="hidden" id="delivery_province_code" name="delivery_province_code" value="{{ $addressArea['code'] }}" data-delivery-required>
              </div>
              <div>
                <label for="delivery_city_code" class="field-label">City / municipality <span class="text-red-500">*</span></label>
                <select id="delivery_city_code" name="delivery_city_code" class="field" disabled
                  data-selected="{{ old('delivery_city_code') }}" data-delivery-required>
                  <option value="">Select a province or area first</option>
                </select>
              </div>
            </div>
            <div>
              <label for="delivery_barangay_code" class="field-label">Barangay <span class="text-red-500">*</span></label>
              <select id="delivery_barangay_code" name="delivery_barangay_code" class="field" disabled
                data-selected="{{ old('delivery_barangay_code') }}" data-delivery-required>
                <option value="">Select a city or municipality first</option>
              </select>
            </div>
            <div>
              <label for="delivery_address" class="field-label">House / street details <span class="text-red-500">*</span></label>
              <input type="text" id="delivery_address" name="delivery_address" value="{{ old('delivery_address') }}"
                placeholder="House/Unit No. and street name" autocomplete="street-address" class="field" data-delivery-required>
            </div>
            <div>
              <label for="delivery_notes" class="field-label">Delivery notes <span class="font-normal normal-case tracking-normal text-surface-400">(optional)</span></label>
              <textarea id="delivery_notes" name="delivery_notes" rows="2"
                placeholder="e.g. Leave at the gate, call upon arrival"
                class="field resize-none">{{ old('delivery_notes') }}</textarea>
            </div>
          </div>

          <!-- Pick-up Info -->
          <div id="pickup-info" class="hidden">
            <div class="bg-brand-50 border border-brand-100 rounded-lg p-4 text-sm text-brand-800">
              <div class="flex gap-3 items-start">
                <svg class="w-5 h-5 text-brand-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                <div>
                  <p class="font-semibold text-brand-900 mb-0.5">Pick up at Ferosa:</p>
                  <p>A. Arellano Ave. Mulawin, Orani,<br>Philippines 2112</p>
                  <p class="text-brand-600 text-xs mt-1">Mon-Sat &bull; 8:00 AM - 5:00 PM</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Payment Method -->
        <div class="customer-card p-5 sm:p-6">
          <h2 class="mb-4 font-display text-lg font-bold text-surface-900">Payment method</h2>

          <div class="space-y-3">
            <!-- COD -->
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-surface-200 p-4 transition-colors hover:border-brand-300 hover:bg-surface-50 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
              <input type="radio" name="payment_method" value="cod" {{ $selectedPaymentMethod !== 'gcash' || ! $gcashAvailable || $selectedDiscountBeneficiary !== 'none' ? 'checked' : '' }}
                onchange="setPaymentMethod('cod')"
                class="mt-0.5 h-4 w-4 border-surface-300 text-brand-600 focus:ring-brand-500">
              <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-surface-900">Cash on delivery</p>
                <p class="mt-0.5 text-xs leading-5 text-surface-500">Pay when your order arrives at your door.</p>
              </div>
              <span class="badge badge-neutral">COD</span>
            </label>

            <!-- GCash -->
            <label class="flex items-start gap-3 rounded-xl border border-surface-200 p-4 transition-colors {{ $gcashAvailable ? 'cursor-pointer hover:border-brand-300 hover:bg-surface-50 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50' : 'cursor-not-allowed bg-surface-50 opacity-60' }}">
              <input type="radio" name="payment_method" value="gcash"
                onchange="setPaymentMethod('gcash')"
                {{ $selectedPaymentMethod === 'gcash' && $gcashAvailable && $selectedDiscountBeneficiary === 'none' ? 'checked' : '' }}
                {{ $gcashAvailable && $selectedDiscountBeneficiary === 'none' ? '' : 'disabled' }}
                class="mt-0.5 h-4 w-4 border-surface-300 text-brand-600 focus:ring-brand-500">
              <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-surface-900">GCash</p>
                <p class="mt-0.5 text-xs leading-5 text-surface-500">
                  {{ $gcashAvailable ? 'Scan the QR or send to the listed number, then provide the reference number.' : 'GCash payment is not available right now.' }}
                </p>
              </div>
              @unless($gcashAvailable)
                <span class="badge badge-neutral">Unavailable</span>
              @endunless
            </label>
          </div>

          <!-- GCash reference input -->
          <div id="gcash-reference-field" class="hidden mt-4">
            <div class="rounded-xl border border-sky-100 bg-sky-50 p-3 mb-4">
              <div class="grid grid-cols-1 sm:grid-cols-[140px,1fr] gap-3">
                @if($gcashQrUrl)
                  <button type="button" onclick="openGcashQrPreview()" class="block rounded-lg overflow-hidden bg-white border border-sky-100 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <img src="{{ $gcashQrUrl }}" alt="GCash QR code" class="w-full aspect-square object-contain">
                  </button>
                @else
                  <div class="rounded-lg bg-white border border-dashed border-sky-100 aspect-square flex items-center justify-center text-xs text-surface-400 text-center px-3">
                    QR not uploaded
                  </div>
                @endif
                <div class="text-sm">
                  <p class="text-[10px] uppercase tracking-wider font-semibold text-sky-700 mb-2">Send payment to</p>
                  <div class="space-y-2">
                    <div>
                      <p class="text-xs text-surface-400">Account Name</p>
                      <p class="font-semibold text-surface-900">{{ $gcashName ?: 'Ferosa Landscaping' }}</p>
                    </div>
                    <div>
                      <p class="text-xs text-surface-400">GCash Number</p>
                      <p class="font-mono font-semibold text-surface-900">{{ $gcashNumber ?: 'Not set' }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <label for="payment_reference" class="field-label">GCash reference number <span class="text-red-500">*</span></label>
            <input type="text" id="payment_reference" name="payment_reference" value="{{ old('payment_reference') }}"
              placeholder="e.g. 1234567890" class="field">
            <p class="field-hint">Enter the reference number after sending your payment.</p>
            <label for="payment_proof" class="field-label mt-4">Payment receipt <span class="text-red-500">*</span></label>
            <input type="file" id="payment_proof" name="payment_proof" accept="image/jpeg,image/png,image/webp"
              class="field file:mr-3 file:-my-1 file:-ml-1 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-xs file:font-bold file:text-brand-800">
            <p class="field-hint">Upload the GCash confirmation screen. JPG, PNG, or WebP; maximum 5 MB.</p>
            <div class="mt-3">
              <x-alert type="warning">
                Your payment will show as <strong>Pending verification</strong> until an administrator checks the reference and receipt.
              </x-alert>
            </div>
          </div>
        </div>

      </div>

      <!-- Summary -->
      <div class="lg:col-span-1">
        <div class="customer-card p-5 sm:p-6 sticky top-6">
          <h2 class="mb-5 font-display text-lg font-bold text-surface-900">Order summary</h2>

          <div class="mb-3 flex items-center justify-between text-sm text-surface-500">
            <span>{{ $showVatBreakdown ? 'Subtotal before VAT' : 'Subtotal' }} (<span id="summary-items">{{ $cartItemCount }}</span> items)</span>
            <span id="summary-subtotal" class="font-bold text-surface-800">&#8369;{{ $formatMoney($initialBeforeVat) }}</span>
          </div>
          @if($showVatBreakdown)
            <div id="summary-vat-row" data-vat-rate="{{ $vatRatePercent }}" class="mb-3 flex items-center justify-between text-sm text-surface-500">
              <span>VAT ({{ $vatRatePercent }}%)</span>
              <span id="summary-vat-amount" class="font-bold text-surface-800" aria-live="polite">&#8369;{{ $formatMoney($initialVatAmount) }}</span>
            </div>
          @endif
          <div class="mb-5 flex items-center justify-between text-sm text-surface-500">
            <span>Delivery</span>
            <span class="font-bold text-brand-600">Free</span>
          </div>

          <p class="mb-4 rounded-lg bg-surface-50 px-3 py-2 text-[11px] leading-5 text-surface-600" role="note">
            {{ $checkoutTaxNotice }}
          </p>

            <fieldset class="mb-5 rounded-xl border border-brand-100 bg-brand-50/50 p-3.5">
              <legend class="px-1 text-xs font-bold text-brand-900">Discount request</legend>
              <p id="discount-request-help" class="text-[11px] leading-5 text-surface-600">
                @if($canRequestDiscount)
                  Request Senior Citizen or PWD review. The total stays unchanged unless an admin verifies your ID and eligible items, then approves the request. Eligibility and configured business rules still apply.
                @elseif($hasPaidItems)
                  Senior/PWD requests are unavailable until the business tax profile and an eligible item are configured.
                @else
                  Discount requests are available for paid orders only.
                @endif
              </p>
              <div class="mt-2 grid gap-2 text-xs text-surface-700">
                <label class="flex items-center gap-2">
                  <input type="radio" name="discount_beneficiary" value="none" @checked($selectedDiscountBeneficiary === 'none') required class="h-4 w-4 text-brand-700 focus:ring-brand-500">
                  No discount
                </label>
                <label class="flex items-center gap-2 {{ $canRequestDiscount ? 'cursor-pointer' : 'cursor-not-allowed text-surface-400' }}">
                  <input type="radio" name="discount_beneficiary" value="senior" @checked($selectedDiscountBeneficiary === 'senior') @disabled(! $canRequestDiscount) class="h-4 w-4 text-brand-700 focus:ring-brand-500 disabled:cursor-not-allowed">
                  Senior Citizen
                </label>
                <label class="flex items-center gap-2 {{ $canRequestDiscount ? 'cursor-pointer' : 'cursor-not-allowed text-surface-400' }}">
                  <input type="radio" name="discount_beneficiary" value="pwd" @checked($selectedDiscountBeneficiary === 'pwd') @disabled(! $canRequestDiscount) class="h-4 w-4 text-brand-700 focus:ring-brand-500 disabled:cursor-not-allowed">
                  PWD
                </label>
              </div>
              @if($canRequestDiscount)
                <div id="discount-id-evidence-wrap" class="mt-3 {{ $selectedDiscountBeneficiary === 'none' ? 'hidden' : '' }}">
                  <label for="discount-id-evidence" class="block text-xs font-semibold text-surface-700">Upload ID image <span class="text-red-600">*</span></label>
                  <input id="discount-id-evidence" type="file" name="discount_id_evidence" accept="image/jpeg,image/png,image/webp"
                         {{ $selectedDiscountBeneficiary !== 'none' ? 'required' : '' }}
                         class="mt-1.5 block w-full text-xs text-surface-600 file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-xs file:font-semibold file:text-brand-800">
                  <p class="mt-1.5 text-[10px] leading-4 text-surface-500">JPG, PNG, or WebP up to 5 MB. Only an admin can view it. Please show your original ID when the order is delivered or picked up.</p>
                </div>
                <p id="discount-payment-hint" class="mt-2 hidden text-[10px] leading-4 text-amber-800" role="status">
                  Cash payment is required while a discount request is being reviewed.
                </p>
              @else
                <p class="mt-3 rounded-lg border border-amber-200 bg-white px-3 py-2 text-[11px] leading-5 text-amber-900" role="status">
                  @if($hasPaidItems)
                    No discount ID is requested. You can place the order without a discount while Ferosa configures eligible items and confirms its VAT status.
                  @else
                    No discount ID is requested for this cart because it contains no paid items.
                  @endif
                </p>
              @endif
            </fieldset>

          <div class="mb-6 flex items-center justify-between border-t border-surface-100 pt-4">
            <span id="summary-total-label" class="text-sm font-bold text-surface-900">Total</span>
            <span id="summary-total" class="font-display text-2xl font-bold text-surface-900">&#8369;{{ $formatMoney($cartSubtotal) }}</span>
          </div>

          <button type="submit" id="checkout-btn" data-loading-label="Placing order..." @disabled($checkoutTaxModeUnsupported) class="btn btn-primary btn-lg btn-block">
            Place order
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
          </button>

          <ul class="mt-5 space-y-2 border-t border-surface-100 pt-4 text-xs text-surface-500">
            <li class="flex items-start gap-2">
              <svg class="mt-px h-3.5 w-3.5 flex-shrink-0 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              Free delivery within our service area
            </li>
            <li class="flex items-start gap-2">
              <svg class="mt-px h-3.5 w-3.5 flex-shrink-0 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              Cash on delivery available
            </li>
            <li class="flex items-start gap-2">
              <svg class="mt-px h-3.5 w-3.5 flex-shrink-0 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              Track every update from your orders page
            </li>
          </ul>
        </div>
      </div>
    </div>

    {{-- In-app only: the summary card above falls to the very bottom of a
         single-column phone layout, so "Place order" ends up below the
         delivery form, the payment section and an alert - the customer has
         to scroll past all of it to buy. This mirrors that same button in a
         bar pinned to the bottom of the screen. It lives inside the form so
         it picks up the existing global submit-loading spinner for free. --}}
    <div id="checkout-mobile-bar">
      <div class="min-w-0">
        <p id="mobile-summary-total-label" class="text-[10px] font-bold uppercase tracking-wide text-surface-400">Total</p>
        <p id="mobile-summary-total" class="font-display text-lg font-bold text-surface-900 truncate">&#8369;{{ $formatMoney($cartSubtotal) }}</p>
      </div>
      <button type="submit" id="mobile-checkout-btn" data-loading-label="Placing order..." class="btn btn-primary btn-lg flex-shrink-0">
        Place order
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
      </button>
    </div>
  </form>
</main>

@if($gcashQrUrl)
<div id="gcash-qr-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/70 p-4">
  <button type="button" onclick="closeGcashQrPreview()" class="absolute inset-0 cursor-default" aria-label="Close QR preview"></button>
  <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-4">
    <div class="flex items-center justify-between mb-3">
      <div>
        <p class="text-sm font-semibold text-surface-900">GCash QR Code</p>
        <p class="text-xs text-surface-400">{{ $gcashName ?: 'Ferosa Landscaping' }}</p>
      </div>
      <button type="button" onclick="closeGcashQrPreview()" class="w-9 h-9 rounded-full border border-surface-200 text-surface-500 hover:bg-surface-50 flex items-center justify-center">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <img src="{{ $gcashQrUrl }}" alt="GCash QR code enlarged" class="w-full aspect-square object-contain rounded-xl border border-surface-100 bg-white">
    @if($gcashNumber)
      <p class="text-center text-sm font-mono font-semibold text-surface-900 mt-3">{{ $gcashNumber }}</p>
    @endif
  </div>
</div>
@endif

<script>
  // ── Delivery method toggle ───────────────────────────────────────────────
  function setDeliveryMethod(method) {
    document.getElementById('delivery_method_input').value = method;

    const deliveryFields = document.getElementById('delivery-fields');
    const pickupInfo     = document.getElementById('pickup-info');
    const tabDelivery    = document.getElementById('tab-delivery');
    const tabPickup      = document.getElementById('tab-pickup');

    const ACTIVE = ['bg-white', 'text-brand-800', 'shadow-sm'];
    const IDLE   = ['text-surface-500', 'hover:text-surface-800'];

    const [on, off] = method === 'delivery' ? [tabDelivery, tabPickup] : [tabPickup, tabDelivery];

    deliveryFields.classList.toggle('hidden', method !== 'delivery');
    pickupInfo.classList.toggle('hidden', method === 'delivery');
    deliveryFields.querySelectorAll('[data-delivery-required]').forEach(field => {
      field.required = method === 'delivery';
    });

    on.classList.remove(...IDLE);
    on.classList.add(...ACTIVE);
    off.classList.remove(...ACTIVE);
    off.classList.add(...IDLE);
  }

  // ── Philippine address selectors ────────────────────────────────────────
  const addressArea = document.getElementById('delivery_province_code');
  const addressLocality = document.getElementById('delivery_city_code');
  const addressBarangay = document.getElementById('delivery_barangay_code');
  const addressLocalitiesUrl = @json(url('/api/philippine-addresses/areas'));
  const addressBarangaysUrl = @json(url('/api/philippine-addresses/localities'));
  let localityRequest = 0;
  let barangayRequest = 0;

  function setAddressOptions(select, placeholder, options = [], selected = '') {
    const optionElements = [new Option(placeholder, '')];
    options.forEach(option => optionElements.push(new Option(option.name, option.code)));
    select.replaceChildren(...optionElements);
    select.disabled = options.length === 0;
    if (selected && options.some(option => option.code === selected)) {
      select.value = selected;
    }
  }

  async function fetchAddressOptions(url) {
    const response = await fetch(url, {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!response.ok) throw new Error('Address options could not be loaded.');
    const payload = await response.json();
    return Array.isArray(payload.data) ? payload.data : [];
  }

  async function loadLocalities(selected = '') {
    const request = ++localityRequest;
    barangayRequest++;
    setAddressOptions(addressLocality, 'Select a province or area first');
    setAddressOptions(addressBarangay, 'Select a city or municipality first');
    if (!addressArea.value) return;

    const areaCode = addressArea.value;
    addressLocality.setAttribute('aria-busy', 'true');
    setAddressOptions(addressLocality, 'Loading cities and municipalities...');
    try {
      const options = await fetchAddressOptions(`${addressLocalitiesUrl}/${encodeURIComponent(areaCode)}/localities`);
      if (request !== localityRequest) return;
      setAddressOptions(addressLocality, 'Select city or municipality', options, selected);
    } catch {
      if (request !== localityRequest) return;
      setAddressOptions(addressLocality, 'Could not load locations');
    } finally {
      if (request === localityRequest) addressLocality.removeAttribute('aria-busy');
    }
  }

  async function loadBarangays(selected = '') {
    const request = ++barangayRequest;
    setAddressOptions(addressBarangay, 'Select a city or municipality first');
    if (!addressLocality.value) return;

    const localityCode = addressLocality.value;
    addressBarangay.setAttribute('aria-busy', 'true');
    setAddressOptions(addressBarangay, 'Loading barangays...');
    try {
      const options = await fetchAddressOptions(`${addressBarangaysUrl}/${encodeURIComponent(localityCode)}/barangays`);
      if (request !== barangayRequest) return;
      setAddressOptions(addressBarangay, 'Select barangay', options, selected);
    } catch {
      if (request !== barangayRequest) return;
      setAddressOptions(addressBarangay, 'Could not load barangays');
    } finally {
      if (request === barangayRequest) addressBarangay.removeAttribute('aria-busy');
    }
  }

  addressArea.addEventListener('change', () => loadLocalities());
  addressLocality.addEventListener('change', () => loadBarangays());

  async function restoreAddressSelection() {
    await loadLocalities(addressLocality.dataset.selected || '');
    await loadBarangays(addressBarangay.dataset.selected || '');
  }

  // ── Payment method toggle ────────────────────────────────────────────────
  function setPaymentMethod(method) {
    const gcashField = document.getElementById('gcash-reference-field');
    const referenceInput = document.querySelector('[name="payment_reference"]');
    const proofInput = document.querySelector('[name="payment_proof"]');
    if (method === 'gcash') {
      gcashField.classList.remove('hidden');
      if (referenceInput) referenceInput.required = true;
      if (proofInput) proofInput.required = true;
    } else {
      gcashField.classList.add('hidden');
      if (referenceInput) referenceInput.required = false;
      if (proofInput) proofInput.required = false;
    }
  }

  function syncDiscountRequest() {
    const beneficiary = document.querySelector('input[name="discount_beneficiary"]:checked')?.value || 'none';
    const requestPending = beneficiary !== 'none';
    const evidenceWrap = document.getElementById('discount-id-evidence-wrap');
    const evidenceInput = document.getElementById('discount-id-evidence');
    const gcashOption = document.querySelector('input[name="payment_method"][value="gcash"]');
    const codOption = document.querySelector('input[name="payment_method"][value="cod"]');
    const paymentHint = document.getElementById('discount-payment-hint');
    const totalLabel = document.getElementById('summary-total-label');
    const mobileTotalLabel = document.getElementById('mobile-summary-total-label');

    if (evidenceWrap) evidenceWrap.classList.toggle('hidden', !requestPending);
    if (evidenceInput) {
      evidenceInput.required = requestPending;
      if (!requestPending) evidenceInput.value = '';
    }
    if (gcashOption) {
      gcashOption.disabled = !@json($gcashAvailable) || requestPending;
      if (requestPending && gcashOption.checked) {
        if (codOption) codOption.checked = true;
        setPaymentMethod('cod');
      }
    }
    if (paymentHint) paymentHint.classList.toggle('hidden', !requestPending);
    if (totalLabel) totalLabel.textContent = requestPending ? 'Total before review' : 'Total';
    if (mobileTotalLabel) mobileTotalLabel.textContent = requestPending ? 'Before review' : 'Total';
  }

  function openGcashQrPreview() {
    const modal = document.getElementById('gcash-qr-modal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
  }

  function closeGcashQrPreview() {
    const modal = document.getElementById('gcash-qr-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
  }

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeGcashQrPreview();
  });

  // ── Cart rendering ───────────────────────────────────────────────────────
  // Cart lines are built with innerHTML, so every value interpolated into one
  // has to be escaped. Product names and image paths are set in the admin, not
  // by the customer, but that is a reason to keep this cheap rather than a
  // reason to skip it.
  const HTML_ESCAPES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => HTML_ESCAPES[ch]);
  }

  function getCart() {
    try { return JSON.parse(localStorage.getItem('ferosa_cart')) || []; } catch { return []; }
  }

  function saveCart(cart) {
    localStorage.setItem('ferosa_cart', JSON.stringify(cart));
    renderCart();
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

  async function updateQty(id, delta) {
    const cart = getCart();
    const item = cart.find(i => i.id === id);
    if (!item) return;
    const quantity = item.qty + delta;

    try {
      const data = await cartRequest(`{{ url('/api/cart/items') }}/${id}`, {
        method: quantity <= 0 ? 'DELETE' : 'PUT',
        body: quantity <= 0 ? undefined : JSON.stringify({ quantity }),
      });
      saveCart(data.items);
      window.dispatchEvent(new CustomEvent('cartUpdated', { detail: data }));
    } catch (error) {
      window.alert(error.message);
    }
  }

  function renderCart() {
    const cart = getCart();
    const list = document.getElementById('cart-list');
    const emptyMsg = document.getElementById('empty-cart-msg');
    const checkoutBtn = document.getElementById('checkout-btn');
    const mobileCheckoutBtn = document.getElementById('mobile-checkout-btn');
    const cartDataInput = document.getElementById('cart-data-input');
    list.innerHTML = '';

    if (cart.length === 0) {
      emptyMsg.classList.remove('hidden');
      checkoutBtn.disabled = true;
      checkoutBtn.classList.add('opacity-40', 'pointer-events-none');
      mobileCheckoutBtn.disabled = true;
      mobileCheckoutBtn.classList.add('opacity-40', 'pointer-events-none');
      document.getElementById('summary-items').textContent = '0';
      document.getElementById('summary-subtotal').textContent = '\u20B10.00';
      document.getElementById('summary-total').textContent = '\u20B10.00';
      document.getElementById('mobile-summary-total').textContent = '\u20B10.00';
      updateVatSummary(0);
      cartDataInput.value = '';
      return;
    }

    emptyMsg.classList.add('hidden');
    checkoutBtn.disabled = false;
    checkoutBtn.classList.remove('opacity-40', 'pointer-events-none');
    mobileCheckoutBtn.disabled = false;
    mobileCheckoutBtn.classList.remove('opacity-40', 'pointer-events-none');

    let totalItems = 0, totalPrice = 0;

    cart.forEach(item => {
      totalItems += item.qty;
      const subtotal = item.price * item.qty;
      totalPrice += subtotal;

      const li = document.createElement('li');
      li.className = 'cart-line py-4 flex gap-4 items-center';
      // The cart payload carries image_url for every line (see CartService).
      // This used to draw a map pin for all of them, so the last screen before
      // paying showed no product images at all - and a location marker as the
      // stand-in for a plant. Fall back to a box, not a pin, when a product
      // genuinely has no photo.
      const thumbnail = item.image_url
        ? `<img src="${escapeHtml(item.image_url)}" alt="" loading="lazy"
                class="cart-line-icon w-12 h-12 rounded-lg object-cover flex-shrink-0 border border-surface-100">`
        : `<div class="cart-line-icon w-12 h-12 bg-brand-50 rounded-lg flex items-center justify-center flex-shrink-0">
             <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1f7a1f" stroke-width="1.5"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
           </div>`;
      li.innerHTML = `
        ${thumbnail}
        <div class="cart-line-info flex-1 min-w-0">
          <h3 class="text-sm font-medium text-surface-900 truncate">${escapeHtml(item.name)}</h3>
          <p class="text-xs text-surface-400">\u20B1${item.price.toLocaleString()} each</p>
        </div>
        <div class="cart-line-controls flex items-center gap-3 shrink-0">
          <div class="flex items-center gap-2 border border-surface-200 rounded-lg px-1.5 py-0.5">
            <button type="button" onclick="updateQty(${item.id}, -1)" aria-label="Decrease quantity of ${escapeHtml(item.name)}" class="w-5 h-5 flex items-center justify-center text-surface-400 hover:text-surface-700 transition-colors">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </button>
            <span class="text-xs font-medium text-surface-900 min-w-[1rem] text-center">${item.qty}</span>
            <button type="button" onclick="updateQty(${item.id}, 1)" aria-label="Increase quantity of ${escapeHtml(item.name)}" class="w-5 h-5 flex items-center justify-center text-surface-400 hover:text-surface-700 transition-colors">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </button>
          </div>
          <p class="text-sm font-semibold text-surface-900 w-20 text-right">\u20B1${subtotal.toLocaleString()}</p>
        </div>
      `;
      list.appendChild(li);
    });

    document.getElementById('summary-items').textContent = totalItems;
    const beforeVat = updateVatSummary(totalPrice);
    document.getElementById('summary-subtotal').textContent = '\u20B1' + beforeVat.toLocaleString('en-PH', {
      maximumFractionDigits: 2,
    });
    document.getElementById('summary-total').textContent = '\u20B1' + totalPrice.toLocaleString();
    document.getElementById('mobile-summary-total').textContent = '\u20B1' + totalPrice.toLocaleString();
    cartDataInput.value = JSON.stringify(cart);
  }

  function updateVatSummary(totalPrice) {
    const row = document.getElementById('summary-vat-row');
    const amount = document.getElementById('summary-vat-amount');
    if (!row || !amount) return totalPrice;

    const vatRate = Number(row.dataset.vatRate);
    if (!Number.isFinite(vatRate) || vatRate <= 0) return totalPrice;

    const totalCents = Math.round(totalPrice * 100);
    const includedVatCents = Math.round(totalCents * vatRate / (100 + vatRate));
    const includedVat = includedVatCents / 100;
    amount.textContent = '\u20B1' + includedVat.toLocaleString('en-PH', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
    return (totalCents - includedVatCents) / 100;
  }

  async function loadServerCart() {
    const legacy = getCart();
    try {
      const data = legacy.length
        ? await cartRequest('{{ url('/api/cart/sync') }}', { method: 'POST', body: JSON.stringify({ items: legacy }) })
        : await cartRequest('{{ url('/api/cart') }}');
      saveCart(data.items);
      window.dispatchEvent(new CustomEvent('cartUpdated', { detail: data }));
    } catch (error) {
      renderCart();
    }
  }

  document.querySelectorAll('input[name="discount_beneficiary"]').forEach((input) => {
    input.addEventListener('change', syncDiscountRequest);
  });
  setDeliveryMethod(@json(old('delivery_method') === 'pickup' ? 'pickup' : 'delivery'));
  restoreAddressSelection();
  setPaymentMethod(@json($selectedPaymentMethod === 'gcash' && $gcashAvailable && $selectedDiscountBeneficiary === 'none' ? 'gcash' : 'cod'));
  syncDiscountRequest();
  loadServerCart();
</script>
@include('partials.mobile-bottom-customer')
@endsection
