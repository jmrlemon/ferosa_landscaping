@extends('layouts.customer')

@section('title', 'Book a Service - Ferosa Landscaping')

@section('styles')
<style>
  .cal-day { min-height: 38px; transition: background .12s, color .12s, border-color .12s; }
  .cal-day:not(.past):not(.empty):hover { background: #f0faf0; color: #1a6320; }
  .cal-day.selected { background: #1f7a1f; color: #fff; font-weight: 600; border-radius: 8px; }
  .cal-day.today { outline: 2px solid #1f7a1f; border-radius: 8px; outline-offset: -2px; }
  .cal-day.past { color: #d4d4d4; cursor: not-allowed; }
  .booking-step .step-marker { transition: background .12s, color .12s; }
  .booking-step.is-done .step-marker { background: #123426; color: #fff; }
  .booking-step.is-active .step-marker { background: #1f7a1f; color: #fff; box-shadow: 0 0 0 3px rgba(31,122,31,.16); }
  .booking-step.is-done .step-label,
  .booking-step.is-active .step-label { color: #1c1917; }
  .time-slot { transition: border-color .12s, background .12s, color .12s, opacity .12s; }
  .time-slot.selected { border-color: #1f7a1f; background: #f0faf0; color: #1a6320; font-weight: 600; }
  .time-slot:disabled,
  .time-slot.booked {
    opacity: 0.65;
    border-color: #e4e4e7;
    background: #fafafa;
    color: #a1a1aa;
    cursor: not-allowed;
    font-weight: 500;
  }
</style>
@endsection

@section('content')
<main class="customer-page">

  {{-- Page header --}}
  <x-page-head
    kicker="{{ $rescheduling ? 'Move a visit' : 'Book a visit' }}"
    title="{{ $rescheduling ? 'Reschedule your visit' : 'Schedule a service' }}"
    sub="{{ $rescheduling
      ? 'Pick a new date and time. The service and starting fee stay the same.'
      : ($estimate ? 'Your estimate is ready. Add the visit location, then pick a date and time.' : 'Complete a cost estimate first, then choose the location, date, and time for your consultation.') }}">
    <x-slot:icon>
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M12 14v4M10 16h4"/>
      </svg>
    </x-slot:icon>
  </x-page-head>

  @if($taxExclusivePricingUnsupported && ! $rescheduling)
    <x-alert type="error" class="mb-6">
      Online booking is paused because VAT-exclusive price calculation is not configured.
    </x-alert>
  @endif

  @if (! $rescheduling && ! $estimate)
    <section class="customer-card reveal reveal-1 overflow-hidden" role="region" aria-labelledby="schedule-estimator-gate-title">
      <div class="grid gap-6 p-6 sm:p-8 md:grid-cols-[auto_1fr] md:items-center">
        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-50 text-brand-700" aria-hidden="true">
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h2m4 0h2M8 15h2m4 0h2M8 19h8"/>
          </svg>
        </div>
        <div>
          <p class="text-[10px] font-bold uppercase tracking-[.15em] text-brand-600">Step 1 of 2</p>
          <h2 id="schedule-estimator-gate-title" class="mt-2 font-display text-2xl font-bold text-surface-900">Start with a cost estimate</h2>
          <p class="mt-2 max-w-2xl text-sm leading-6 text-surface-600">
            Scheduling uses your project details to prepare the right consultation. Complete the cost estimator first, then select <span class="font-semibold text-brand-800">Book Consultation</span> to bring your estimate here.
          </p>
          <div class="mt-5 flex flex-wrap items-center gap-3">
            <a href="{{ route('estimator') }}" class="customer-action min-h-[46px] bg-brand-700 px-5 py-3 text-sm font-bold text-white shadow-soft hover:bg-brand-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
              Go to Cost Estimator
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
            <span class="text-xs text-surface-400">Your estimate will stay connected to the booking.</span>
          </div>
        </div>
      </div>
      <div class="border-t border-brand-100 bg-brand-50/60 px-6 py-4 sm:px-8">
        <ol class="grid gap-3 text-xs text-brand-900 sm:grid-cols-3">
          <li class="flex items-center gap-2"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-700 text-[10px] font-bold text-white">1</span>Build your estimate</li>
          <li class="flex items-center gap-2"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-100 text-[10px] font-bold text-brand-700">2</span>Book the consultation</li>
          <li class="flex items-center gap-2"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-100 text-[10px] font-bold text-brand-700">3</span>Choose your date and time</li>
        </ol>
      </div>
    </section>
  @else
    {{-- Booking progress. The steps are driven by what the customer has actually
       chosen (see updateStepper below); a strip that always highlighted step 1
       told them nothing about where they were. --}}
  <section class="mb-6 overflow-hidden rounded-2xl border border-brand-100 bg-white reveal reveal-1" aria-label="Booking progress">
    @php
      $bookingSteps = $rescheduling
        ? [['1', 'Service'], ['2', 'Date'], ['3', 'Time'], ['4', 'Confirm']]
        : [['1', 'Estimate'], ['2', 'Location'], ['3', 'Date'], ['4', 'Time'], ['5', 'Confirm']];
    @endphp
    <ol class="grid grid-cols-2 {{ $rescheduling ? 'sm:grid-cols-4' : 'sm:grid-cols-5' }}">
      @foreach($bookingSteps as [$number, $label])
        <li class="booking-step flex items-center gap-2 border-b border-r border-brand-50 px-3 py-3 last:border-r-0 sm:border-b-0" data-step="{{ $number }}">
          <span class="step-marker flex h-6 w-6 items-center justify-center rounded-full bg-brand-50 text-brand-700 text-[10px] font-bold" aria-hidden="true">{{ $number }}</span>
          <span class="step-label text-xs font-bold text-surface-500">{{ $label }}</span>
          <span class="step-state sr-only">not started</span>
        </li>
      @endforeach
    </ol>
  </section>

  {{-- Success flash --}}
  @if (session('status'))
    <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
  @endif

  {{-- Validation errors --}}
  @if ($errors->any())
    <x-alert type="error" class="mb-6">
      <ul class="list-disc space-y-0.5 pl-4">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </x-alert>
  @endif
  @if ($rescheduling)
    <div class="mb-6 rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-900">
      <p class="font-semibold">Moving your {{ $rescheduling->serviceType->name ?? 'service' }} visit.</p>
      <p class="mt-1 text-brand-800/80">
        Currently booked for {{ $rescheduling->appointment_at->format('M d, Y \a\t g:i A') }}.
        Choosing a new slot keeps the same booking and starting fee, and returns it to the team for confirmation.
      </p>
      <a href="{{ route('appointments') }}" class="mt-2 inline-flex text-xs font-bold text-brand-700 hover:text-brand-900">
        Keep the current time
      </a>
    </div>
  @elseif ($activeAppointment ?? null)
    <div class="mb-6 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-4 py-3 text-sm">
      <p class="font-semibold">You already have an active booking.</p>
      <p class="mt-1">
        {{ $activeAppointment->serviceType->name ?? 'Service' }} on
        {{ $activeAppointment->appointment_at->format('M d, Y \a\t g:i A') }}.
        Please cancel or complete this booking before scheduling another service.
      </p>
      <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
        <a href="{{ route('appointments') }}" class="inline-flex text-xs font-semibold text-amber-900 hover:text-amber-700">
          View appointment
        </a>
        @if ($activeAppointment->isCustomerReschedulable())
          <a href="{{ route('schedule', ['reschedule' => $activeAppointment->id]) }}" class="inline-flex text-xs font-semibold text-amber-900 hover:text-amber-700">
            Move it to another time
          </a>
        @endif
      </div>
    </div>
  @endif

  {{-- Booking form. In reschedule mode the same fields post to a different
       action: the server keeps the service and the fee from the record, so
       only the new time travels with the request. --}}
  <form method="POST"
        action="{{ $rescheduling ? route('appointments.reschedule', $rescheduling) : route('schedule.store') }}"
        id="booking-form" enctype="multipart/form-data">
    @csrf
    @if ($rescheduling)
      @method('PUT')
    @endif

    {{-- Hidden inputs populated by JS --}}
    <input type="hidden" id="availability-service-type" value="{{ $bookingService?->id }}">
    <input type="hidden" name="appointment_at"  id="hidden-appointment-at">
    <input type="hidden" name="notes"           id="hidden-notes">

    <div id="booking-container" class="grid grid-cols-1 md:grid-cols-5 gap-6">

        <fieldset class="md:col-span-5 customer-card p-5 sm:p-6">
          <legend class="px-1 text-sm font-bold text-surface-900">Senior Citizen or PWD discount request</legend>
          <p id="appointment-discount-help" class="text-xs leading-5 text-surface-500">
            @if($canRequestDiscount)
              Request Senior Citizen or PWD review. The fee stays unchanged unless an admin verifies your ID and the service eligibility, then approves the request. Configured business rules still apply.
            @elseif($rescheduling)
              Discount requests are available when booking a new eligible service, not while rescheduling an existing appointment.
            @elseif($bookingService && (float) $bookingService->default_fee > 0)
              Senior/PWD requests are unavailable until the business tax profile and this service are configured.
            @else
              Discount requests are available for paid appointment services only.
            @endif
          </p>
          <div class="mt-3 grid gap-2 text-sm text-surface-700 sm:grid-cols-3" aria-describedby="appointment-discount-help">
            <label class="flex items-center gap-2">
              <input type="radio" name="discount_beneficiary" value="none" @checked(! $canRequestDiscount || old('discount_beneficiary', 'none') === 'none') required class="h-4 w-4 text-brand-700 focus:ring-brand-500">
              No discount
            </label>
            <label class="flex items-center gap-2 {{ $canRequestDiscount ? 'cursor-pointer' : 'cursor-not-allowed text-surface-400' }}">
              <input type="radio" name="discount_beneficiary" value="senior" @checked($canRequestDiscount && old('discount_beneficiary') === 'senior') @disabled(! $canRequestDiscount) class="h-4 w-4 text-brand-700 focus:ring-brand-500 disabled:cursor-not-allowed">
              Senior Citizen
            </label>
            <label class="flex items-center gap-2 {{ $canRequestDiscount ? 'cursor-pointer' : 'cursor-not-allowed text-surface-400' }}">
              <input type="radio" name="discount_beneficiary" value="pwd" @checked($canRequestDiscount && old('discount_beneficiary') === 'pwd') @disabled(! $canRequestDiscount) class="h-4 w-4 text-brand-700 focus:ring-brand-500 disabled:cursor-not-allowed">
              PWD
            </label>
          </div>
          @if($canRequestDiscount)
            <div id="appointment-discount-evidence-wrap" class="mt-3 max-w-xl {{ in_array(old('discount_beneficiary'), ['senior', 'pwd'], true) ? '' : 'hidden' }}">
              <label for="appointment-discount-evidence" class="block text-xs font-semibold text-surface-700">Upload ID image <span class="text-red-600">*</span></label>
              <input id="appointment-discount-evidence" type="file" name="discount_id_evidence" accept="image/jpeg,image/png,image/webp"
                     {{ in_array(old('discount_beneficiary'), ['senior', 'pwd'], true) ? 'required' : '' }}
                     class="mt-1.5 block w-full text-xs text-surface-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-brand-800">
              <p class="mt-1.5 text-[11px] leading-4 text-surface-500">JPG, PNG, or WebP up to 5 MB. Only an admin can view it. Please show your original ID when the team performs the service.</p>
            </div>
          @else
            <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-900" role="status">
              @if($bookingService && (float) $bookingService->default_fee > 0)
                No discount ID is requested. You can book without a discount while Ferosa configures this service and confirms its VAT status.
              @else
                No discount ID is requested because this service has no fee.
              @endif
            </p>
          @endif
        </fieldset>

      {{-- Estimate/service context. The service is set by the server-side
           estimator mapping and is intentionally not an editable form field. --}}
      <div class="md:col-span-5 customer-card p-5 sm:p-6">
        <div class="grid md:grid-cols-[1fr_1.25fr] gap-5 md:items-center">
          <div>
            <div class="flex items-center gap-2 mb-2">
              <span class="w-6 h-6 rounded-full bg-brand-700 text-white text-[10px] font-bold flex items-center justify-center">1</span>
              <p class="text-[10px] font-bold uppercase tracking-[.13em] text-brand-600">{{ $rescheduling ? 'Booked service' : 'Prepared estimate' }}</p>
            </div>
            <h2 class="font-display text-xl font-bold text-surface-900">{{ $rescheduling ? 'Service stays as booked' : 'Your estimate is connected' }}</h2>
            <p class="mt-2 text-xs leading-5 text-surface-500">
              {{ $rescheduling
                ? 'Choose only a new date and time. Your service and consultation fee will not change.'
                : 'We carried your estimator choices into this consultation. Add the service address, then choose the visit date and time.' }}
            </p>
          </div>
          <div class="rounded-xl border border-brand-100 bg-brand-50/70 p-4">
            @if ($rescheduling)
              <p class="text-[10px] font-bold uppercase tracking-wider text-brand-600">Consultation service</p>
              <p class="mt-1 text-sm font-bold text-brand-950">{{ $bookingService?->name ?? 'Service' }}</p>
              <p class="mt-1 text-xs text-brand-800/75">{{ $bookingService?->customerPriceLabel() ?? 'Fee recorded with your appointment' }}</p>
            @else
              <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                <div>
                  <p class="text-[10px] uppercase tracking-wider text-brand-600">Project</p>
                  <p class="mt-0.5 text-sm font-semibold text-brand-950">{{ $estimate['project_type_label'] }}</p>
                </div>
                <div>
                  <p class="text-[10px] uppercase tracking-wider text-brand-600">Property</p>
                  <p class="mt-0.5 text-sm font-semibold text-brand-950">{{ number_format((int) $estimate['size']) }} sq m</p>
                </div>
                <div>
                  <p class="text-[10px] uppercase tracking-wider text-brand-600">Quality</p>
                  <p class="mt-0.5 text-sm font-semibold text-brand-950">{{ $estimate['tier_label'] }}</p>
                </div>
                <div>
                  <p class="text-[10px] uppercase tracking-wider text-brand-600">Estimate</p>
                  <p class="mt-0.5 text-sm font-bold text-brand-950">PHP {{ number_format((float) $estimate['total'], 2) }}</p>
                </div>
              </div>
              <div class="mt-3 border-t border-brand-100 pt-3 text-xs text-brand-800/75">
                <p><span class="font-semibold">Typical range:</span> PHP {{ number_format((float) $estimate['range_low'], 2) }}–PHP {{ number_format((float) $estimate['range_high'], 2) }}</p>
                <p class="mt-1"><span class="font-semibold">Consultation:</span> {{ $bookingService?->name }} · {{ $bookingService?->customerPriceLabel() }}</p>
                <p class="mt-2 text-[11px] leading-4">The project estimate is indicative. The consultation fee and final project quotation are separate.</p>
                <a href="{{ route('estimator') }}" class="mt-2 inline-flex font-bold text-brand-700 hover:text-brand-900">Change estimate</a>
              </div>
            @endif
          </div>
        </div>
      </div>

      @unless($rescheduling)
        {{-- Visit location --}}
        <section class="md:col-span-5 customer-card p-5 sm:p-6" aria-labelledby="visit-location-title">
          <div class="flex items-start gap-3">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-50 text-[10px] font-bold text-brand-700">2</span>
            <div>
              <h3 id="visit-location-title" class="text-sm font-bold text-surface-900">Where should the team visit?</h3>
              <p id="visit-location-hint" class="mt-1 text-xs leading-5 text-surface-500">Service visits are available within Bataan only. Choose the city or municipality and barangay, then enter the house number and street.</p>
            </div>
          </div>

          <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <span class="field-label">Service area</span>
              <div class="flex min-h-[42px] items-center rounded-xl border border-brand-100 bg-brand-50 px-3.5 text-sm font-semibold text-brand-900" aria-describedby="visit-location-hint">
                {{ $addressArea['name'] }}
              </div>
              <input type="hidden" id="site_province_code" name="site_province_code" value="{{ $addressArea['code'] }}">
            </div>
            <div>
              <label for="site_city_code" class="field-label">City / municipality <span class="text-red-500">*</span></label>
              <select id="site_city_code" name="site_city_code" class="field" required disabled data-selected="{{ old('site_city_code') }}">
                <option value="">Select a province or area first</option>
              </select>
            </div>
            <div>
              <label for="site_barangay_code" class="field-label">Barangay <span class="text-red-500">*</span></label>
              <select id="site_barangay_code" name="site_barangay_code" class="field" required disabled data-selected="{{ old('site_barangay_code') }}">
                <option value="">Select a city or municipality first</option>
              </select>
            </div>
            <div>
              <label for="site_street" class="field-label">House / street details <span class="text-red-500">*</span></label>
              <input type="text" id="site_street" name="site_street" value="{{ old('site_street') }}" maxlength="450" required
                placeholder="House/Unit No. and street name" autocomplete="street-address" class="field">
            </div>
          </div>
        </section>
      @endunless

      {{-- Calendar --}}
      <div class="md:col-span-3 customer-card p-5">
        <div class="flex items-center gap-2 mb-4">
          <span class="w-6 h-6 rounded-full bg-brand-50 text-brand-700 text-[10px] font-bold flex items-center justify-center">{{ $rescheduling ? 2 : 3 }}</span>
          <h3 class="text-sm font-bold text-surface-900">Select a date</h3>
        </div>
        <div class="border border-surface-100 rounded-lg overflow-hidden">

          {{-- Month nav --}}
          <div class="flex justify-between items-center px-4 py-3 bg-surface-50 border-b border-surface-100">
            <button type="button" onclick="prevMonth()" aria-label="Previous month" class="w-9 h-9 flex items-center justify-center hover:bg-surface-100 rounded-lg text-surface-500 transition-colors">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <span class="text-xs font-semibold text-surface-700" id="cal-month-label"></span>
            <button type="button" onclick="nextMonth()" aria-label="Next month" class="w-9 h-9 flex items-center justify-center hover:bg-surface-100 rounded-lg text-surface-500 transition-colors">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
          </div>

          {{-- Day-of-week headers --}}
          <div class="grid grid-cols-7 gap-0.5 px-3 pt-3 text-center text-[10px] text-surface-400 font-medium">
            <div>Su</div><div>Mo</div><div>Tu</div><div>We</div><div>Th</div><div>Fr</div><div>Sa</div>
          </div>

          {{-- Day cells rendered by JS --}}
          <div class="grid grid-cols-7 gap-1 p-3" id="cal-grid" role="grid" aria-label="Choose an appointment date"></div>
        </div>
        <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-[11px] text-surface-500">
          <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded bg-brand-700"></span>Selected</span>
          <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded border-2 border-brand-600"></span>Today</span>
          <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded bg-surface-200"></span>Unavailable</span>
        </div>
        <p class="mt-3 text-[11px] leading-5 text-surface-400">Appointments must be booked at least 24 hours in advance.</p>
      </div>

      {{-- Time & Details --}}
      <div class="md:col-span-2 space-y-5">

        {{-- Time slots --}}
        <div class="customer-card p-5">
          <div class="flex items-center gap-2 mb-4">
            <span class="w-6 h-6 rounded-full bg-brand-50 text-brand-700 text-[10px] font-bold flex items-center justify-center">{{ $rescheduling ? 3 : 4 }}</span>
            <h3 class="text-sm font-bold text-surface-900">Select a time</h3>
          </div>
          <div class="grid grid-cols-2 gap-2" id="time-slots">
            @foreach (\App\Models\Appointment::SLOT_TIMES as $t)
              <button type="button"
                class="time-slot min-h-[44px] border border-surface-200 py-2 rounded-xl text-xs font-bold text-surface-600"
                data-time="{{ $t }}"
                aria-pressed="false"
                onclick="selectTime(this)">
                {{ \Carbon\Carbon::createFromFormat('H:i', $t)->format('h:i A') }}
              </button>
            @endforeach
            <button type="button" disabled
              class="time-slot min-h-[44px] border border-surface-100 py-2 rounded-xl text-xs font-medium text-surface-350 bg-surface-50 cursor-not-allowed">
              05:30 PM
            </button>
          </div>
          <p id="time-slots-hint" class="mt-2 text-xs text-amber-700 hidden" role="status"></p>
        </div>

        {{-- Notes --}}
        <div class="customer-card p-5">
          <label for="notes-field" class="block text-sm font-bold text-surface-900 mb-1">Project notes <span class="font-normal text-surface-400">(optional)</span></label>
          <p class="text-[11px] text-surface-400 mb-3">Tell us about your space, goals, or anything the team should prepare for during this service visit.</p>
          <textarea id="notes-field"
            placeholder="For example: front garden, partial shade, easy-care plants, or gate access instructions."
            class="w-full border border-surface-200 rounded-xl px-3.5 py-3 text-sm text-surface-700 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100 h-24 resize-none transition-colors"></textarea>
        </div>

        {{-- Submit --}}
        <div id="selection-summary" class="text-xs text-surface-500 text-center hidden" role="status" aria-live="polite">
          <span id="summary-text"></span>
        </div>

        <button type="button" onclick="submitBooking()" id="booking-submit-btn"
          @disabled(($activeAppointment ?? null) || ($taxExclusivePricingUnsupported && ! $rescheduling))
          class="customer-action w-full min-h-[48px] bg-brand-700 hover:bg-brand-800 text-white font-bold py-3 text-sm shadow-soft disabled:opacity-60 disabled:cursor-not-allowed">
          @if ($rescheduling)
            Confirm New Time
          @elseif ($activeAppointment ?? null)
            Booking Limit Reached
          @else
            Confirm Booking
          @endif
        </button>
      </div>
    </div>
  </form>

  <section class="mt-8 rounded-[1.3rem] border border-brand-100 bg-brand-50 p-5 sm:p-6">
    <p class="text-[10px] font-bold uppercase tracking-[.15em] text-brand-600">After you submit</p>
    <h2 class="mt-2 font-display text-xl font-bold text-brand-950">Know what happens next.</h2>
    <div class="mt-5 grid gap-4 sm:grid-cols-3">
      @foreach([
        ['1', 'Booking recorded', 'Your appointment appears immediately in Appointments.'],
        ['2', 'Team review', 'Ferosa checks the visit details and updates the booking status.'],
        ['3', 'Stay informed', 'Follow email, Notifications, and Messages for changes or reminders.'],
      ] as [$number, $title, $copy])
        <div class="rounded-xl border border-brand-100 bg-white/80 p-4"><span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-700 text-[10px] font-bold text-white">{{ $number }}</span><h3 class="mt-3 text-sm font-bold text-brand-950">{{ $title }}</h3><p class="mt-1 text-xs leading-5 text-brand-800/70">{{ $copy }}</p></div>
      @endforeach
    </div>
    <p class="mt-4 text-xs leading-5 text-brand-800/75">The displayed consultation fee is the amount recorded for this booking.</p>
  </section>
  @endif

</main>

@include('partials.mobile-bottom-customer')
@endsection

@section('scripts')
@if ($rescheduling || $estimate)
<script>
  const SCHEDULE_AVAILABILITY_URL = @json(route('schedule.availability'));
  const IS_RESCHEDULING = @json($rescheduling !== null);
  // Declared here rather than beside IS_RESCHEDULING further down: the first
  // availability fetch runs during init, before that line has been evaluated.
  const RESCHEDULING_ID = @json($rescheduling?->id);

  @unless($rescheduling)
  const discountEvidenceWrap = document.getElementById('appointment-discount-evidence-wrap');
  const discountEvidenceInput = document.getElementById('appointment-discount-evidence');
  function syncAppointmentDiscountEvidence() {
    const beneficiary = document.querySelector('input[name="discount_beneficiary"]:checked')?.value || 'none';
    const requested = beneficiary !== 'none';
    if (discountEvidenceWrap) discountEvidenceWrap.classList.toggle('hidden', !requested);
    if (discountEvidenceInput) {
      discountEvidenceInput.required = requested;
      if (!requested) discountEvidenceInput.value = '';
    }
  }
  document.querySelectorAll('input[name="discount_beneficiary"]').forEach((input) => {
    input.addEventListener('change', syncAppointmentDiscountEvidence);
  });
  syncAppointmentDiscountEvidence();

  // ── Philippine visit location ───────────────────────────────────────────
  const siteArea = document.getElementById('site_province_code');
  const siteLocality = document.getElementById('site_city_code');
  const siteBarangay = document.getElementById('site_barangay_code');
  const siteStreet = document.getElementById('site_street');
  const siteLocalitiesUrl = @json(url('/api/philippine-addresses/areas'));
  const siteBarangaysUrl = @json(url('/api/philippine-addresses/localities'));
  let siteLocalityRequest = 0;
  let siteBarangayRequest = 0;

  function setSiteOptions(select, placeholder, options = [], selected = '') {
    const optionElements = [new Option(placeholder, '')];
    options.forEach(option => optionElements.push(new Option(option.name, option.code)));
    select.replaceChildren(...optionElements);
    select.disabled = options.length === 0;
    if (selected && options.some(option => option.code === selected)) {
      select.value = selected;
    }
    updateStepper();
  }

  async function fetchSiteOptions(url) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!response.ok) throw new Error('Visit locations could not be loaded.');
    const payload = await response.json();
    return Array.isArray(payload.data) ? payload.data : [];
  }

  async function loadSiteLocalities(selected = '') {
    const request = ++siteLocalityRequest;
    siteBarangayRequest++;
    setSiteOptions(siteLocality, 'Select a province or area first');
    setSiteOptions(siteBarangay, 'Select a city or municipality first');
    if (!siteArea.value) return;

    const areaCode = siteArea.value;
    siteLocality.setAttribute('aria-busy', 'true');
    setSiteOptions(siteLocality, 'Loading cities and municipalities...');
    try {
      const options = await fetchSiteOptions(`${siteLocalitiesUrl}/${encodeURIComponent(areaCode)}/localities`);
      if (request !== siteLocalityRequest) return;
      setSiteOptions(siteLocality, 'Select city or municipality', options, selected);
    } catch {
      if (request !== siteLocalityRequest) return;
      setSiteOptions(siteLocality, 'Could not load locations');
    } finally {
      if (request === siteLocalityRequest) siteLocality.removeAttribute('aria-busy');
    }
  }

  async function loadSiteBarangays(selected = '') {
    const request = ++siteBarangayRequest;
    setSiteOptions(siteBarangay, 'Select a city or municipality first');
    if (!siteLocality.value) return;

    const localityCode = siteLocality.value;
    siteBarangay.setAttribute('aria-busy', 'true');
    setSiteOptions(siteBarangay, 'Loading barangays...');
    try {
      const options = await fetchSiteOptions(`${siteBarangaysUrl}/${encodeURIComponent(localityCode)}/barangays`);
      if (request !== siteBarangayRequest) return;
      setSiteOptions(siteBarangay, 'Select barangay', options, selected);
    } catch {
      if (request !== siteBarangayRequest) return;
      setSiteOptions(siteBarangay, 'Could not load barangays');
    } finally {
      if (request === siteBarangayRequest) siteBarangay.removeAttribute('aria-busy');
    }
  }

  siteArea.addEventListener('change', () => loadSiteLocalities());
  siteLocality.addEventListener('change', () => loadSiteBarangays());
  siteBarangay.addEventListener('change', updateStepper);
  siteStreet.addEventListener('input', updateStepper);

  async function restoreSiteAddressSelection() {
    await loadSiteLocalities(siteLocality.dataset.selected || '');
    await loadSiteBarangays(siteBarangay.dataset.selected || '');
  }
  @endunless

  // ── Calendar state ────────────────────────────────────────────────────────
  const MONTHS = ['January','February','March','April','May','June',
                  'July','August','September','October','November','December'];

  const today = new Date();
  today.setHours(0,0,0,0);
  const minimumBookingAt = new Date();
  minimumBookingAt.setHours(minimumBookingAt.getHours() + 24);

  let viewYear  = today.getFullYear();
  let viewMonth = today.getMonth();   // 0-based

  let selectedDate = null;  // Date object
  let selectedTime = null;  // '09:00'

  function formatDateYmd(d) {
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
  }

  /** Normalize API/local times to HH:MM for Set lookup */
  function normalizeHi(t) {
    if (!t || typeof t !== 'string') return '';
    const p = t.trim().split(':');
    const h = String(parseInt(p[0], 10)).padStart(2, '0');
    const m = String(parseInt(p[1] ?? '0', 10)).padStart(2, '0');
    return `${h}:${m}`;
  }

  function slotDateTime(date, time) {
    const [h, m] = time.split(':').map(Number);
    return new Date(date.getFullYear(), date.getMonth(), date.getDate(), h, m, 0, 0);
  }

  function isSlotAllowed(date, time) {
    return slotDateTime(date, time) >= minimumBookingAt;
  }

  // Days no crew is dispatched, from Appointment::CLOSED_WEEKDAYS. The server
  // rejects them too - this only keeps the customer from picking one and being
  // told off for it.
  const CLOSED_WEEKDAYS = @json(\App\Models\Appointment::CLOSED_WEEKDAYS);

  function isDateBookable(date) {
    if (CLOSED_WEEKDAYS.includes(date.getDay())) return false;
    const slots = Array.from(document.querySelectorAll('.time-slot[data-time]'));
    return slots.some(btn => isSlotAllowed(date, btn.dataset.time));
  }

  function renderCalendar() {
    const label = document.getElementById('cal-month-label');
    label.textContent = MONTHS[viewMonth] + ' ' + viewYear;

    const grid = document.getElementById('cal-grid');
    grid.innerHTML = '';

    const firstDay = new Date(viewYear, viewMonth, 1).getDay(); // 0=Sun
    const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();

    // Empty leading cells
    for (let i = 0; i < firstDay; i++) {
      const blank = document.createElement('div');
      blank.className = 'cal-day empty py-1.5 text-center text-xs';
      grid.appendChild(blank);
    }

    // Day cells
    for (let d = 1; d <= daysInMonth; d++) {
      const date = new Date(viewYear, viewMonth, d);
      const isPast = date < today || !isDateBookable(date);
      const isToday = date.getTime() === today.getTime();
      const isSelected = selectedDate && date.getTime() === selectedDate.getTime();

      const cell = document.createElement('button');
      cell.type = 'button';
      cell.textContent = d;
      cell.className = 'cal-day py-1.5 text-center text-xs rounded-lg cursor-pointer select-none';
      cell.setAttribute('role', 'gridcell');
      cell.setAttribute('aria-label', date.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }));
      cell.setAttribute('aria-pressed', isSelected ? 'true' : 'false');

      if (isPast) {
        cell.classList.add('past');
        cell.disabled = true;
        cell.setAttribute('aria-disabled', 'true');
        // A closed day looks the same as a past one, so say which it is
        // rather than leaving the customer to guess why it is greyed out.
        if (CLOSED_WEEKDAYS.includes(date.getDay())) {
          const dayName = date.toLocaleDateString(undefined, { weekday: 'long' });
          cell.title = `No visits on ${dayName}`;
          cell.setAttribute('aria-label', `${cell.getAttribute('aria-label')} - no visits on ${dayName}`);
        }
      } else {
        if (isToday) cell.classList.add('today');
        if (isSelected) cell.classList.add('selected');
        cell.addEventListener('click', () => pickDate(date));
      }

      grid.appendChild(cell);
    }

    updateSummary();
  }

  function pickDate(date) {
    selectedDate = date;
    clearTimeSelection();
    renderCalendar();
    refreshTimeSlotAvailability();
  }

  function prevMonth() {
    if (viewMonth === 0) { viewMonth = 11; viewYear--; }
    else { viewMonth--; }
    renderCalendar();
  }

  function nextMonth() {
    if (viewMonth === 11) { viewMonth = 0; viewYear++; }
    else { viewMonth++; }
    renderCalendar();
  }

  // ── Time slots ────────────────────────────────────────────────────────────
  function clearTimeSelection() {
    selectedTime = null;
    document.querySelectorAll('.time-slot[data-time]').forEach(slot => {
      slot.classList.remove('selected');
      slot.setAttribute('aria-pressed', 'false');
    });
    updateSummary();
  }

  function selectTime(btn) {
    if (!btn || btn.disabled || btn.classList.contains('booked')) return;
    document.querySelectorAll('.time-slot[data-time]').forEach(t => {
      t.classList.remove('selected');
      t.setAttribute('aria-pressed', 'false');
    });
    btn.classList.add('selected');
    btn.setAttribute('aria-pressed', 'true');
    selectedTime = btn.dataset.time;
    updateSummary();
  }

  function setTimeSlotsHint(message) {
    const el = document.getElementById('time-slots-hint');
    if (!el) return;
    if (message) {
      el.textContent = message;
      el.classList.remove('hidden');
    } else {
      el.textContent = '';
      el.classList.add('hidden');
    }
  }

  function applyBookedTimes(bookedList) {
    const booked = new Set((bookedList || []).map(normalizeHi));
    document.querySelectorAll('.time-slot[data-time]').forEach(btn => {
      const t = normalizeHi(btn.dataset.time);
      const isTooSoon = selectedDate && !isSlotAllowed(selectedDate, t);
      if (booked.has(t) || isTooSoon) {
        btn.disabled = true;
        btn.classList.add('booked');
        btn.classList.remove('selected');
        if (selectedTime && normalizeHi(selectedTime) === t) selectedTime = null;
      } else {
        btn.disabled = false;
        btn.classList.remove('booked');
      }
      btn.setAttribute('aria-pressed', btn.classList.contains('selected') ? 'true' : 'false');
    });

    const pickable = Array.from(document.querySelectorAll('.time-slot[data-time]:not(:disabled)'));
    setTimeSlotsHint(pickable.length === 0 && selectedDate
      ? 'No eligible times are available. Appointments must be booked at least 24 hours in advance.'
      : '');

    const stillSelected = document.querySelector('.time-slot[data-time].selected:not(:disabled)');
    if (stillSelected) {
      selectedTime = stillSelected.dataset.time;
      updateSummary();
      return;
    }
    selectedTime = null;
    updateSummary();
  }

  async function refreshTimeSlotAvailability() {
    const serviceTypeId = document.getElementById('availability-service-type')?.value;
    if (!selectedDate || !serviceTypeId) {
      document.querySelectorAll('.time-slot[data-time]').forEach(btn => {
        btn.disabled = false;
        btn.classList.remove('booked');
      });
      setTimeSlotsHint('');
      clearTimeSelection();
      updateSummary();
      return;
    }

    const params = new URLSearchParams({
      service_type_id: serviceTypeId,
      date: formatDateYmd(selectedDate),
    });
    // The visit being moved does not block its own slot.
    if (RESCHEDULING_ID) params.set('exclude_appointment_id', RESCHEDULING_ID);
    try {
      const res = await fetch(`${SCHEDULE_AVAILABILITY_URL}?${params}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (!res.ok) throw new Error('availability failed');
      const data = await res.json();
      applyBookedTimes(data.booked_times);
    } catch (e) {
      console.error(e);
      setTimeSlotsHint('Could not load availability. You can still try to book; the server will reject double bookings.');
      document.querySelectorAll('.time-slot[data-time]').forEach(btn => {
        btn.disabled = false;
        btn.classList.remove('booked');
      });
      updateSummary();
    }
  }

  // ── Progress ──────────────────────────────────────────────────────────────
  // Step 1 is complete because the estimate/service was prepared before this
  // page opened, so the strip starts with the date as the active step.
  function updateStepper() {
    const hasDate = Boolean(selectedDate);
    const hasTime = hasDate && Boolean(selectedTime);
    const hasLocation = IS_RESCHEDULING || Boolean(
      document.getElementById('site_province_code')?.value
      && document.getElementById('site_city_code')?.value
      && document.getElementById('site_barangay_code')?.value
      && document.getElementById('site_street')?.value.trim()
    );
    const done = IS_RESCHEDULING
      ? [Boolean(document.getElementById('availability-service-type')?.value), hasDate, hasTime, false]
      : [Boolean(document.getElementById('availability-service-type')?.value), hasLocation, hasDate, hasTime, false];

    const confirmIndex = done.length - 1;
    done[confirmIndex] = done.slice(0, confirmIndex).every(Boolean);

    const firstOpen = done.findIndex(isDone => !isDone);

    document.querySelectorAll('.booking-step').forEach((step, index) => {
      const isDone = done[index] && index !== confirmIndex;
      const isActive = index === firstOpen || (index === confirmIndex && done[confirmIndex]);

      step.classList.toggle('is-done', isDone);
      step.classList.toggle('is-active', isActive && !isDone);
      step.setAttribute('aria-current', isActive && !isDone ? 'step' : 'false');

      const marker = step.querySelector('.step-marker');
      if (marker) marker.textContent = isDone ? '✓' : String(index + 1);

      const state = step.querySelector('.step-state');
      if (state) state.textContent = isDone ? 'completed' : (isActive ? 'current step' : 'not started');
    });
  }

  // ── Summary ───────────────────────────────────────────────────────────────
  function updateSummary() {
    const sum = document.getElementById('selection-summary');
    const txt = document.getElementById('summary-text');
    if (selectedDate && selectedTime) {
      const fmtDate = selectedDate.toLocaleDateString('en-US', { weekday:'short', month:'short', day:'numeric', year:'numeric' });
      const [h, m]  = selectedTime.split(':');
      const ampm    = +h >= 12 ? 'PM' : 'AM';
      const hh      = +h > 12 ? +h - 12 : (+h === 0 ? 12 : +h);
      txt.textContent = `📅 ${fmtDate} at ${hh}:${m} ${ampm}`;
      sum.classList.remove('hidden');
    } else {
      sum.classList.add('hidden');
    }
    updateStepper();
  }

  // ── Submit ────────────────────────────────────────────────────────────────
  function submitBooking() {
    if (!IS_RESCHEDULING && @json((bool) ($activeAppointment ?? null))) {
      alert('You already have an active booking. Please cancel or complete it before booking another service.');
      return;
    }

    if (!selectedDate) { alert('Please select a date.'); return; }
    if (!selectedTime)  { alert('Please select a time.'); return; }

    const serviceTypeId = document.getElementById('availability-service-type').value;
    if (!serviceTypeId) { alert('Your consultation service is unavailable. Please prepare a new estimate.'); return; }

    const form = document.getElementById('booking-form');
    if (!form.reportValidity()) return;

    // Build appointment_at as "YYYY-MM-DD HH:MM:00"
    const yyyy = selectedDate.getFullYear();
    const mm   = String(selectedDate.getMonth() + 1).padStart(2, '0');
    const dd   = String(selectedDate.getDate()).padStart(2, '0');
    const [hh, min] = selectedTime.split(':');
    const appointmentAt = `${yyyy}-${mm}-${dd} ${hh}:${min}:00`;
    const appointmentDate = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), selectedDate.getDate(), Number(hh), Number(min), 0, 0);
    if (appointmentDate < minimumBookingAt) {
      alert('Appointments must be scheduled at least 24 hours in advance.');
      return;
    }

    document.getElementById('hidden-appointment-at').value = appointmentAt;
    document.getElementById('hidden-notes').value          = document.getElementById('notes-field').value;

    const btn = document.getElementById('booking-submit-btn');
    btn.disabled = true;
    btn.dataset.loading = 'true';
    const busyLabel = IS_RESCHEDULING ? 'Moving...' : 'Booking...';
    btn.innerHTML = '<span class="inline-block w-3.5 h-3.5 border-2 border-current border-r-transparent rounded-full animate-spin"></span><span>' + busyLabel + '</span>';
    form.submit();
  }

  // ── Init ──────────────────────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    renderCalendar();
    updateStepper();
    @unless($rescheduling)
    restoreSiteAddressSelection();
    @endunless
  });
</script>
@endif
@endsection
