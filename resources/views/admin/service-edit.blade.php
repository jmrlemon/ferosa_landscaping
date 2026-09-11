<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @include('partials.favicon')
  <title>Edit Service - Ferosa Landscaping</title>
  <link rel="stylesheet" href="{{ asset('fonts/ferosa-fonts.css') }}">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @include('admin.partials.premium-theme')
</head>
<body class="min-h-screen bg-surface-100 text-surface-900 font-sans antialiased">
  <a href="#admin-main" class="skip-link">Skip to admin content</a>
  <header class="h-14 bg-white border-b border-surface-200 flex items-center justify-between px-5">
    <h1 class="text-sm font-semibold text-surface-600">Edit Service</h1>
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
        <p class="font-semibold">Please check the form and try again.</p>
        <ul class="mt-1 list-disc pl-5">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="mb-5 flex items-center gap-4">
      <a href="{{ route('admin.dashboard', ['tab' => 'services']) }}" class="inline-flex h-9 w-9 items-center justify-center rounded border border-surface-400 text-surface-600 transition-colors hover:bg-white" aria-label="Back to services">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
      </a>
      <div>
        <h2 class="text-2xl font-bold text-brand-950">Edit: {{ $service->name }}</h2>
        <p class="mt-1 text-sm text-surface-500">Update service pricing and availability for booking and estimation.</p>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
      <div class="xl:col-span-2">
        <form id="edit-service-form" method="POST" action="{{ route('admin.services.update', $service) }}" class="space-y-6">
          @csrf
          @method('PUT')
          <input type="hidden" name="redirect_to" value="edit">
          <section class="overflow-hidden rounded-xl border border-surface-100 bg-white shadow-sm">
            <div class="border-b border-surface-200 px-5 py-4 flex items-center justify-between gap-3">
              <h3 class="font-semibold text-surface-900">Service Details</h3>
              <span class="rounded-full {{ $service->is_active ? 'bg-brand-50 text-brand-700' : 'bg-surface-100 text-surface-600' }} px-2.5 py-1 text-xs font-semibold">{{ $service->is_active ? 'Active in Scheduling' : 'Hidden from Scheduling' }}</span>
            </div>
            <div class="grid grid-cols-1 gap-4 p-5 lg:grid-cols-2">
              <label class="block text-sm font-medium text-surface-800">Service Name *
                <input name="name" value="{{ old('name', $service->name) }}" required {{ $isStaffOrAdmin ? '' : 'disabled' }} class="mt-2 h-10 w-full rounded-lg border border-surface-200 px-3 text-base font-normal outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
              </label>
              <label class="block text-sm font-medium text-surface-800">Default Fee (PHP) *
                <input name="default_fee" type="number" step="0.01" min="0" value="{{ old('default_fee', $service->default_fee) }}" required {{ $isStaffOrAdmin ? '' : 'disabled' }} class="mt-2 h-10 w-full rounded-lg border border-surface-200 px-3 text-base font-normal outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
              </label>
              <label class="flex items-center gap-2 text-sm font-medium text-surface-700 lg:col-span-2">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $service->is_active) ? 'checked' : '' }} {{ $isStaffOrAdmin ? '' : 'disabled' }} class="h-4 w-4 rounded border-surface-300 text-brand-600 focus:ring-brand-500">
                Active in Scheduling
              </label>
            </div>
          </section>
        </form>
      </div>

      <aside class="h-fit rounded-xl border border-surface-100 bg-white p-4 shadow-sm">
        <div class="flex h-44 items-center justify-center rounded-lg bg-brand-50">
          <svg class="h-12 w-12 text-brand-200" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/></svg>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-2">
          <div class="rounded-lg border border-surface-100 bg-surface-50 p-3">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-surface-400">Default Fee</p>
            <p class="mt-1 text-sm font-bold text-surface-900">PHP {{ number_format((float) $service->default_fee, 2) }}</p>
          </div>
          <div class="rounded-lg border border-surface-100 bg-surface-50 p-3">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-surface-400">Status</p>
            <p class="mt-1 text-sm font-bold {{ $service->is_active ? 'text-brand-700' : 'text-surface-500' }}">{{ $service->is_active ? 'Active' : 'Hidden' }}</p>
          </div>
        </div>
        @if($isStaffOrAdmin)
          <button type="submit" form="edit-service-form" class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg bg-brand-700 py-2.5 text-base font-semibold text-white transition-colors hover:bg-brand-800">Save Changes</button>
        @endif
        <a href="{{ route('admin.dashboard', ['tab' => 'services']) }}" class="mt-2 flex w-full items-center justify-center rounded-lg border border-surface-400 py-2.5 text-base font-medium text-surface-600 transition-colors hover:bg-surface-50">Cancel</a>
      </aside>
    </div>
  </main>
</body>
</html>
