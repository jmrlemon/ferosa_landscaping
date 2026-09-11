@extends('admin.layouts.workspace')

@section('title', 'Business Profile - Ferosa Admin')
@section('admin-section', 'business-profile')
@section('skip-label', 'Skip to business profile')
@section('header-eyebrow', 'Customer confidence')
@section('header-title', 'Business Profile')

{{-- No page-specific header actions: the layout renders the shared
     messages / notifications / account cluster. The sidebar already links
     back to the dashboard. --}}

@section('content')
    @if(session('success'))<div class="mb-5 rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm font-semibold text-brand-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-5 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700"><p class="font-bold">Please review the form.</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="mb-6"><p class="text-[10px] font-bold uppercase tracking-[.16em] text-brand-600">Customer confidence</p><h2 class="mt-2 text-3xl text-brand-950">Keep business details clear and accurate.</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-surface-500">These details support the trust panels customers see. Empty optional fields stay hidden instead of showing unverified claims.</p></div>

    <form method="POST" action="{{ route('admin.business-profile.update') }}" class="grid gap-6 xl:grid-cols-[1fr_340px]">
      @csrf @method('PUT')
      <div class="space-y-6">
        <section class="rounded-xl border border-surface-200 bg-white p-5 sm:p-6">
          <h3 class="font-bold text-surface-900">Contact and location</h3>
          <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-semibold text-surface-700">Business name *<input required name="business_name" value="{{ old('business_name', $businessProfile['business_name']) }}" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500"></label>
            <label class="block text-sm font-semibold text-surface-700">Service area<input name="service_area" value="{{ old('service_area', $businessProfile['service_area']) }}" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500"></label>
            <label class="block text-sm font-semibold text-surface-700 sm:col-span-2">Business address<input name="business_address" value="{{ old('business_address', $businessProfile['business_address']) }}" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500"></label>
            <label class="block text-sm font-semibold text-surface-700">Phone<input name="business_phone" value="{{ old('business_phone', $businessProfile['business_phone']) }}" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500"></label>
            <label class="block text-sm font-semibold text-surface-700">Email<input type="email" name="business_email" value="{{ old('business_email', $businessProfile['business_email']) }}" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500"></label>
            <label class="block text-sm font-semibold text-surface-700 sm:col-span-2">Business hours<input name="business_hours" value="{{ old('business_hours', $businessProfile['business_hours']) }}" placeholder="e.g. Monday to Saturday, 8:00 AM–5:00 PM" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500"></label>
          </div>
        </section>

        <section class="rounded-xl border border-surface-200 bg-white p-5 sm:p-6">
          <h3 class="font-bold text-surface-900">Customer expectations</h3><p class="mt-1 text-xs text-surface-400">Write plain, specific policies. Do not promise anything the business cannot consistently provide.</p>
          <div class="mt-5 space-y-4">
            <label class="block text-sm font-semibold text-surface-700">Booking notice<textarea name="booking_notice" rows="3" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500">{{ old('booking_notice', $businessProfile['booking_notice']) }}</textarea></label>
            <label class="block text-sm font-semibold text-surface-700">Service guarantee or quality promise<textarea name="service_guarantee" rows="4" placeholder="Optional—only add a promise Ferosa follows" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500">{{ old('service_guarantee', $businessProfile['service_guarantee']) }}</textarea></label>
            <label class="block text-sm font-semibold text-surface-700">Cancellation policy<textarea name="cancellation_policy" rows="4" placeholder="Explain notice periods, fees, or rescheduling rules" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500">{{ old('cancellation_policy', $businessProfile['cancellation_policy']) }}</textarea></label>
          </div>
        </section>
      </div>

      <aside class="h-fit rounded-xl border border-surface-200 bg-white p-5 xl:sticky xl:top-5">
        <p class="text-[10px] font-bold uppercase tracking-wider text-brand-600">Customer preview</p><h3 class="mt-2 font-display text-xl font-bold text-brand-950">{{ old('business_name', $businessProfile['business_name']) }}</h3>
        <dl class="mt-5 space-y-4 text-sm">@foreach([['Serving', old('service_area', $businessProfile['service_area'])], ['Visit', old('business_address', $businessProfile['business_address'])], ['Hours', old('business_hours', $businessProfile['business_hours'])], ['Phone', old('business_phone', $businessProfile['business_phone'])], ['Email', old('business_email', $businessProfile['business_email'])]] as [$label, $value])@if($value)<div><dt class="text-[10px] font-bold uppercase tracking-wider text-surface-400">{{ $label }}</dt><dd class="mt-1 font-semibold leading-5 text-surface-800">{{ $value }}</dd></div>@endif @endforeach</dl>
        <button class="mt-6 w-full rounded-xl bg-brand-700 px-4 py-3 text-sm font-bold text-white">Save business details</button>
      </aside>
    </form>
@endsection
