<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @include('partials.favicon')
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Admin Account - Ferosa</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @include('admin.partials.premium-theme')
</head>
<body class="min-h-screen bg-surface-100 text-surface-900 font-sans antialiased">
  <a href="#admin-main" class="skip-link">Skip to admin account</a>

  <header class="flex h-14 items-center justify-between border-b border-surface-200 bg-white px-4 sm:px-5">
    <div class="flex min-w-0 items-center gap-3">
      <a href="{{ route('admin.dashboard') }}" class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg border border-surface-200 text-surface-500 hover:bg-surface-50 hover:text-brand-700" aria-label="Back to admin dashboard">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
      </a>
      <div class="min-w-0">
        <h1>Ferosa admin workspace</h1>
        <p class="truncate text-xs font-semibold text-surface-700">Account settings</p>
      </div>
    </div>
    <a href="{{ route('admin.dashboard') }}" class="rounded-lg bg-surface-900 px-3 py-2 text-xs font-bold text-white hover:bg-brand-800">Dashboard</a>
  </header>

  <main id="admin-main" tabindex="-1" class="p-4 sm:p-5">
    @if(session('success'))
      <div role="status" class="mb-5 rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm font-semibold text-brand-800">{{ session('success') }}</div>
    @endif

    @if($errors->any())
      <div role="alert" class="mb-5 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
        <p class="font-bold">Please review the highlighted account details.</p>
        <ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
      </div>
    @endif

    <div class="mb-6 grid gap-5 lg:grid-cols-[1fr_auto] lg:items-end">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[.16em] text-brand-600">Admin workspace account</p>
        <h2 class="mt-2 text-3xl text-brand-950 sm:text-4xl">Manage your work profile securely.</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-surface-500">Update the contact information used for this Ferosa operations account. Your access level stays protected and cannot be changed here.</p>
      </div>
      <div class="flex items-center gap-3 rounded-xl border border-surface-200 bg-white px-4 py-3 shadow-sm">
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-700 text-base font-bold text-white">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
        <div class="min-w-0">
          <p class="truncate text-sm font-bold text-surface-900">{{ $user->name }}</p>
          <p class="text-[10px] font-bold uppercase tracking-wider text-brand-600">{{ $user->role }} access</p>
        </div>
      </div>
    </div>

    <form method="POST" action="{{ route('admin.account.update') }}" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
      @csrf
      @method('PUT')

      <div class="space-y-6">
        <section class="rounded-xl border border-surface-200 bg-white p-5 sm:p-6">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-brand-600">Profile</p>
            <h3 class="mt-1 font-bold text-surface-900">Contact details</h3>
            <p class="mt-1 text-xs leading-5 text-surface-400">Use information the Ferosa team can recognize and contact.</p>
          </div>

          <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-semibold text-surface-700 sm:col-span-2">
              Full name
              <input required autocomplete="name" name="name" value="{{ old('name', $user->name) }}" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500">
            </label>
            <label class="block text-sm font-semibold text-surface-700">
              Email address
              <input required autocomplete="email" type="email" name="email" value="{{ old('email', $user->email) }}" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500">
            </label>
            <label class="block text-sm font-semibold text-surface-700">
              Phone number
              <input autocomplete="tel" name="phone_number" value="{{ old('phone_number', $user->phone_number) }}" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500">
            </label>
          </div>
        </section>

        <section class="rounded-xl border border-surface-200 bg-white p-5 sm:p-6">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-brand-600">Security</p>
            <h3 class="mt-1 font-bold text-surface-900">Change password</h3>
            <p class="mt-1 text-xs leading-5 text-surface-400">Leave these fields empty when you only want to update your contact details.</p>
          </div>

          <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-semibold text-surface-700 sm:col-span-2">
              Current password
              <input autocomplete="current-password" type="password" name="current_password" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500">
            </label>
            <label class="block text-sm font-semibold text-surface-700">
              New password
              <input autocomplete="new-password" type="password" name="password" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500">
            </label>
            <label class="block text-sm font-semibold text-surface-700">
              Confirm new password
              <input autocomplete="new-password" type="password" name="password_confirmation" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500">
            </label>
          </div>
        </section>
      </div>

      <aside class="h-fit space-y-4 xl:sticky xl:top-5">
        <section class="rounded-xl border border-surface-200 bg-white p-5">
          <p class="text-[10px] font-bold uppercase tracking-wider text-brand-600">Workspace access</p>
          <div class="mt-4 flex items-start gap-3 rounded-xl bg-brand-50 p-4">
            <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-brand-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <div>
              <p class="text-sm font-bold capitalize text-brand-950">{{ $user->role }} account</p>
              <p class="mt-1 text-xs leading-5 text-brand-800">This page remains inside the protected admin area. Access roles are managed separately.</p>
            </div>
          </div>
          <button type="submit" class="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-brand-700 px-4 py-3 text-sm font-bold text-white hover:bg-brand-800">
            Save account changes
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          </button>
        </section>

        <section class="rounded-xl border border-surface-200 bg-white p-5">
          <p class="text-sm font-bold text-surface-900">Finished working?</p>
          <p class="mt-1 text-xs leading-5 text-surface-400">Sign out on shared computers to protect customer and operations data.</p>
          <button type="submit" form="admin-account-logout" class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border border-red-200 px-4 py-2.5 text-sm font-bold text-red-600 hover:bg-red-50">
            Sign out securely
          </button>
        </section>
      </aside>
    </form>

    <form id="admin-account-logout" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>
  </main>
</body>
</html>
