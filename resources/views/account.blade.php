@extends('layouts.customer')

@section('title', 'Account - Ferosa Landscaping')

@section('styles')
<style>
  .account-hero {
    position: relative;
    overflow: hidden;
    background:
      radial-gradient(circle at 88% 8%, rgba(130,189,152,.28), transparent 34%),
      linear-gradient(135deg, #102e22 0%, #174c35 60%, #236747 100%);
    box-shadow: 0 20px 48px rgba(18,52,38,.15);
  }
  .account-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    opacity: .09;
    background-image: radial-gradient(circle, #fff 1px, transparent 1px);
    background-size: 22px 22px;
    -webkit-mask-image: linear-gradient(90deg, transparent 20%, #000 90%);
    mask-image: linear-gradient(90deg, transparent 20%, #000 90%);
  }
  .detail-row {
    display: grid;
    gap: .15rem;
    padding: .9rem 0;
    border-bottom: 1px solid #f0eee8;
  }
  .detail-row:last-child { border-bottom: 0; }
  @media (min-width: 640px) {
    .detail-row { grid-template-columns: 11rem 1fr; align-items: center; gap: 1rem; }
  }
</style>
@endsection

@section('content')
@php
  $initials = collect(explode(' ', trim($user->name)))
    ->filter()
    ->take(2)
    ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
    ->implode('');
@endphp
<main class="customer-page is-narrow space-y-6">

  <x-page-head
    kicker="Your account"
    title="Personal details"
    sub="Keep your details current so we can reach you about deliveries and visits.">
    <x-slot:icon>
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
      </svg>
    </x-slot:icon>
  </x-page-head>

  @if (session('status'))
    <x-alert type="success" class="reveal">{{ session('status') }}</x-alert>
  @endif

  @if ($errors->any())
    <x-alert type="error" class="reveal">
      <p class="font-bold">Please fix the following:</p>
      <ul class="mt-1 list-disc space-y-0.5 pl-4">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </x-alert>
  @endif

  {{-- ── Identity hero ───────────────────────────────────── --}}
  <section class="account-hero rounded-[1.4rem] px-5 py-6 text-white sm:px-7 sm:py-7 reveal reveal-1">
    <div class="relative flex flex-col gap-5 sm:flex-row sm:items-center">
      <div class="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-2xl border border-white/20 bg-white/12 font-display text-2xl font-bold backdrop-blur">
        {{ $initials ?: 'F' }}
      </div>
      <div class="min-w-0 flex-1">
        <h2 class="truncate font-display text-2xl font-bold tracking-[-.02em]">{{ $user->name }}</h2>
        <p class="mt-1 truncate text-sm text-white/70">{{ $user->email }}</p>
        <div class="mt-3 flex flex-wrap gap-2">
          <span class="rounded-full border border-white/20 bg-white/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide">
            {{ $user->account_type ?? 'Customer' }}
          </span>
          <span class="rounded-full border border-white/20 bg-white/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide">
            {{ ucfirst($user->role) }}
          </span>
          @if($user->created_at)
            <span class="rounded-full border border-white/20 bg-white/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide">
              Member since {{ $user->created_at->format('M Y') }}
            </span>
          @endif
        </div>
      </div>
    </div>
  </section>

  {{-- ── Profile View ────────────────────────────────────── --}}
  <div id="profile-view" class="customer-card p-5 sm:p-6 reveal reveal-2">
    <div class="mb-2 flex items-center justify-between gap-4">
      <h2 class="font-display text-lg font-bold text-surface-900">Profile information</h2>
      <button onclick="toggleEditMode()" class="btn btn-secondary btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M3 21l.53-3.71a2 2 0 0 1 .56-1.13L15.4 4.85a1.5 1.5 0 0 1 2.12 0l1.63 1.63a1.5 1.5 0 0 1 0 2.12L7.84 19.91a2 2 0 0 1-1.13.56L3 21Z"/></svg>
        Edit
      </button>
    </div>

    <div>
      <div class="detail-row">
        <span class="text-xs font-semibold uppercase tracking-wide text-surface-400">Name</span>
        <span class="text-sm font-bold text-surface-900">{{ $user->name }}</span>
      </div>
      <div class="detail-row">
        <span class="text-xs font-semibold uppercase tracking-wide text-surface-400">Email</span>
        <span class="text-sm font-bold text-surface-900 break-all">{{ $user->email }}</span>
      </div>
      <div class="detail-row">
        <span class="text-xs font-semibold uppercase tracking-wide text-surface-400">Phone</span>
        <span class="text-sm font-bold {{ $user->phone_number ? 'text-surface-900' : 'text-surface-400' }}">
          {{ $user->phone_number ?: 'Not added yet' }}
        </span>
      </div>
      <div class="detail-row">
        <span class="text-xs font-semibold uppercase tracking-wide text-surface-400">Account type</span>
        <span>
          <span class="badge {{ $user->account_type === 'Business' ? 'badge-info' : 'badge-success' }}">
            {{ $user->account_type ?? 'Customer' }}
          </span>
        </span>
      </div>
      <div class="detail-row">
        <span class="text-xs font-semibold uppercase tracking-wide text-surface-400">Role</span>
        <span>
          <span class="badge {{ $user->role === 'admin' ? 'badge-info' : ($user->role === 'staff' ? 'badge-success' : 'badge-neutral') }}">
            {{ ucfirst($user->role) }}
          </span>
        </span>
      </div>
    </div>

    @unless($user->phone_number)
      <div class="mt-5">
        <x-alert type="warning">
          Add a phone number so we can send SMS updates about your orders and appointments.
        </x-alert>
      </div>
    @endunless
  </div>

  {{-- ── Edit Form ─────────────────────────────────────── --}}
  <div id="profile-edit" class="hidden">
    <form method="POST" action="{{ route('account.update') }}" class="customer-card p-5 sm:p-6">
      @csrf
      @method('PUT')
      <h2 class="mb-5 font-display text-lg font-bold text-surface-900">Edit profile</h2>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label for="name" class="field-label">Name</label>
          <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required class="field">
        </div>
        <div>
          <label for="email" class="field-label">Email</label>
          <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required class="field">
        </div>
        <div class="sm:col-span-2">
          <label for="phone_number" class="field-label">Phone number</label>
          <input type="text" name="phone_number" id="phone_number" value="{{ old('phone_number', $user->phone_number) }}" class="field" placeholder="09XXXXXXXXX">
          <p class="field-hint">Used for SMS notifications about orders and appointments.</p>
        </div>
      </div>

      <div class="mt-6 border-t border-surface-100 pt-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <p class="text-xs font-bold uppercase tracking-wide text-surface-400">Change password</p>
          {{-- Submits the sibling form below: the OTP reset flow is guest-only,
               so we sign the customer out and deep-link them into it. --}}
          <button type="submit" form="forgot-password-form" class="text-xs font-semibold text-brand-700 hover:underline">Forgot password?</button>
        </div>
        <p class="field-hint mb-3">Leave these fields blank to keep your current password.</p>
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label for="current_password" class="field-label">Current password</label>
            <input type="password" name="current_password" id="current_password" class="field" autocomplete="current-password">
            <p class="field-hint">Required only when you are setting a new password.</p>
          </div>
          <div>
            <label for="password" class="field-label">New password</label>
            <input type="password" name="password" id="password" class="field" autocomplete="new-password">
          </div>
          <div>
            <label for="password_confirmation" class="field-label">Confirm password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" class="field" autocomplete="new-password">
          </div>
        </div>
      </div>

      <div class="mt-6 flex flex-wrap gap-3 border-t border-surface-100 pt-5">
        <button type="submit" data-loading-label="Saving..." class="btn btn-primary">Save changes</button>
        <button type="button" onclick="toggleEditMode()" class="btn btn-secondary">Cancel</button>
      </div>
    </form>

    {{-- Kept outside the profile form (forms cannot nest) and reached via the
         `form="forgot-password-form"` button in the password section. --}}
    <form id="forgot-password-form" method="POST" action="{{ route('logout') }}"
          onsubmit="return confirm('You will be signed out so you can reset your password using your mobile number. Continue?')">
      @csrf
      <input type="hidden" name="view" value="forgot">
    </form>
  </div>

  {{-- ── Session ─────────────────────────────────────────── --}}
  <div class="customer-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6 reveal reveal-3">
    <div>
      <h2 class="text-sm font-bold text-surface-900">Signed in on this device</h2>
      <p class="mt-1 text-sm text-surface-500">Sign out when you are finished, especially on shared devices.</p>
    </div>
    <form action="{{ route('logout') }}" method="POST" class="flex-shrink-0">
      @csrf
      <button type="submit" data-loading-label="Signing out..." class="btn btn-danger btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v1"/></svg>
        Sign out
      </button>
    </form>
  </div>

</main>

@include('partials.mobile-bottom-customer')
@endsection

@section('scripts')
<script>
  function toggleEditMode() {
    document.getElementById('profile-view').classList.toggle('hidden');
    document.getElementById('profile-edit').classList.toggle('hidden');
  }
  @if ($errors->any())
    document.addEventListener('DOMContentLoaded', () => {
      document.getElementById('profile-view').classList.add('hidden');
      document.getElementById('profile-edit').classList.remove('hidden');
    });
  @endif

</script>
@endsection
