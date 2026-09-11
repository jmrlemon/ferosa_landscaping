@php
  $adminSection = $adminSection ?? '';
  $isAdmin = auth()->user()?->isAdmin();
  // Badge counts come from the workspace-sidebar view composer, so the same
  // numbers show on the dashboard tabs and on the standalone admin pages.
  $badges = $sidebarBadges ?? [];
  $badge = fn (string $key) => (int) ($badges[$key] ?? 0);
  $badgeCount = fn (int $count) => $count > 9 ? '9+' : (string) $count;
  $countBadgeClass = 'ml-auto flex h-[18px] min-w-[18px] flex-shrink-0 items-center justify-center rounded-full bg-red-500 px-1.5 text-[10px] font-bold leading-none text-white';
  $pillBadgeClass = 'ml-auto inline-flex min-h-[20px] flex-shrink-0 items-center justify-center whitespace-nowrap rounded-full px-2 text-[9px] font-bold leading-none';
  $navClass = fn (string $section) => trim(
      'flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-[13px] transition-colors '.
      ($adminSection === $section
          ? 'bg-brand-50 font-bold text-brand-800 shadow-[inset_3px_0_0_#236746]'
          : 'text-surface-500 hover:bg-surface-50 hover:text-surface-800')
  );
@endphp

<aside id="admin-sidebar" aria-label="Admin navigation" class="z-20 flex w-64 flex-shrink-0 flex-col justify-between border-r border-surface-100 bg-white">
  <div class="min-h-0 overflow-y-auto">
    {{-- Height is locked to the workspace topbar (68px) so both bottom borders line up. --}}
    <div class="sticky top-0 z-10 flex h-[68px] items-center gap-3 border-b border-surface-100 bg-white/95 px-4 backdrop-blur">
      <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-950 shadow-sm">
        <svg class="h-5 w-5 text-brand-100" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 4.5c-6.6.2-11.3 3-13.5 8.3M5.2 19c1.1-5.2 4.8-8.7 10.8-10.7M19.5 4.5c.4 6.8-2.8 11.3-8.1 11.8-2.3.2-4.3-.8-5.4-3.5"/></svg>
      </div>
      <div class="min-w-0 flex-1">
        <span class="block font-display text-base font-semibold leading-none text-brand-950">Ferosa</span>
        <span class="mt-1 block text-[9px] font-bold uppercase tracking-[.18em] text-surface-400">{{ $isAdmin ? 'Admin workspace' : 'Team workspace' }}</span>
      </div>
      <button id="admin-sidebar-close" type="button" onclick="toggleAdminSidebar(true)" class="flex h-10 w-10 items-center justify-center rounded-xl text-surface-500 hover:bg-surface-50 md:hidden" aria-label="Close admin navigation">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
      </button>
    </div>

    <nav aria-label="Primary admin" class="flex w-full flex-col space-y-0.5 px-3 py-3">
      <p class="mb-1 px-2 text-[10px] font-semibold uppercase tracking-wider text-surface-400">Dashboard</p>
      <a href="{{ route('admin.dashboard', ['tab' => 'overview']) }}" class="{{ $navClass('overview') }}">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
        Overview
      </a>

      <p class="mb-1 mt-4 px-2 text-[10px] font-semibold uppercase tracking-wider text-surface-400">Operations</p>
      <a href="{{ route('admin.service-scheduling') }}" class="{{ $navClass('appointments') }}">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        <span class="truncate">Appointments</span>
        @if($badge('appointments_overdue') > 0)
          <span title="{{ $badge('appointments_overdue') }} overdue appointment{{ $badge('appointments_overdue') !== 1 ? 's' : '' }}" class="{{ $pillBadgeClass }} border border-red-200 bg-red-50 text-red-700">{{ $badgeCount($badge('appointments_overdue')) }} overdue</span>
        @elseif($badge('appointments_pending') > 0)
          <span title="{{ $badge('appointments_pending') }} appointment{{ $badge('appointments_pending') !== 1 ? 's' : '' }} awaiting confirmation" class="{{ $pillBadgeClass }} border border-amber-200 bg-amber-50 text-amber-700">{{ $badgeCount($badge('appointments_pending')) }} pending</span>
        @endif
      </a>
      <a href="{{ route('admin.ordering-delivery') }}" class="{{ $navClass('orders') }}">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 12 3l9 4.5M5 8.5V17l7 4 7-4V8.5M12 12v9m-7-12.5 7 4 7-4"/></svg>
        <span class="truncate">Orders &amp; Delivery</span>
        @if($badge('orders_pending') > 0)
          <span title="{{ $badge('orders_pending') }} order{{ $badge('orders_pending') !== 1 ? 's' : '' }} awaiting processing" class="{{ $pillBadgeClass }} border border-amber-200 bg-amber-50 text-amber-700">{{ $badgeCount($badge('orders_pending')) }} actions</span>
        @endif
      </a>
      <a href="{{ route('admin.dashboard', ['tab' => 'services']) }}" class="{{ $navClass('services') }}">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        {{ $isAdmin ? 'Services' : 'Services · read-only' }}
      </a>
      <a href="{{ route('admin.dashboard', ['tab' => 'products']) }}" class="{{ $navClass('products') }}">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        {{ $isAdmin ? 'Inventory' : 'Stock levels · read-only' }}
        @if($badge('low_stock') > 0)
          <span title="{{ $badge('low_stock') }} low-stock product{{ $badge('low_stock') !== 1 ? 's' : '' }}" class="{{ $countBadgeClass }}">{{ $badgeCount($badge('low_stock')) }}</span>
        @endif
      </a>
      {{-- Stock movements live inside each product's edit page, under Inventory.
           A second top-level entry only split one job across two places. --}}
      <a href="{{ route('admin.projects.index') }}" class="{{ $navClass('projects') }}" @if($adminSection === 'projects') aria-current="page" @endif>
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20h16M6 20V9l6-5 6 5v11M9 20v-6h6v6"/></svg>
        Project Portfolio
      </a>
      <a href="{{ route('admin.dashboard', ['tab' => 'messages']) }}" class="{{ $navClass('messages') }}">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
        Messages
        @if($badge('unread_messages') > 0)
          <span title="{{ $badge('unread_messages') }} unread message{{ $badge('unread_messages') !== 1 ? 's' : '' }}" class="{{ $countBadgeClass }}">{{ $badgeCount($badge('unread_messages')) }}</span>
        @endif
      </a>
      @if($isAdmin)
        <a href="{{ route('admin.dashboard', ['tab' => 'payment']) }}" class="{{ $navClass('payment') }}">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2m2-6h2a2 2 0 012 2v2a2 2 0 01-2 2h-2m0-6v6"/></svg>
          Billing
        </a>
      @endif

      <p class="mb-1 mt-4 px-2 text-[10px] font-semibold uppercase tracking-wider text-surface-400">System</p>
      @if($isAdmin)
        <a href="{{ route('admin.business-profile.edit') }}" class="{{ $navClass('business-profile') }}" @if($adminSection === 'business-profile') aria-current="page" @endif>
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 10h.01M9 14h.01M15 10h.01M15 14h.01M10 21v-4h4v4"/></svg>
          Business Profile
        </a>
      @endif
      @if($isAdmin)
        <a href="{{ route('admin.dashboard', ['tab' => 'archived']) }}" class="{{ $navClass('archived') }}">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
          Archived
        </a>
        <a href="{{ route('admin.dashboard', ['tab' => 'audit']) }}" class="{{ $navClass('audit') }}">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a2 2 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          Audit Logs
        </a>
      @endif
      <a href="{{ route('admin.dashboard', ['tab' => 'feedbacks']) }}" class="{{ $navClass('feedbacks') }}">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
        Feedback
        @if($badge('feedback') > 0)
          <span id="admin-feedback-badge" data-count="{{ $badge('feedback') }}" title="{{ $badge('feedback') }} feedback submission{{ $badge('feedback') !== 1 ? 's' : '' }}" class="{{ $countBadgeClass }}">{{ $badgeCount($badge('feedback')) }}</span>
        @endif
      </a>
      @if($isAdmin)
        <a href="{{ route('admin.dashboard', ['tab' => 'users']) }}" class="{{ $navClass('users') }}">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
          Users
        </a>
      @endif
    </nav>
  </div>

  <div class="border-t border-surface-100 bg-white/70 p-3">
    <div class="flex items-center gap-3 rounded-xl border border-surface-200 bg-surface-50/80 p-2.5">
      <a href="{{ route('admin.account.edit') }}" class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-brand-700 text-sm font-bold text-white" aria-label="Open admin account">
        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
      </a>
      <a href="{{ route('admin.account.edit') }}" class="min-w-0 flex-1">
        <span class="block truncate text-[13px] font-bold text-surface-800">{{ auth()->user()->name }}</span>
        <span class="block text-[10px] font-medium text-surface-400">{{ ucfirst(auth()->user()->role) }} account</span>
      </a>
      <form method="POST" action="{{ route('logout') }}" class="flex-shrink-0">
        @csrf
        <button type="submit" aria-label="Sign out" title="Sign out" class="flex h-9 w-9 items-center justify-center rounded-lg text-surface-400 transition-colors hover:bg-red-50 hover:text-red-600">
          <svg class="h-[17px] w-[17px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        </button>
      </form>
    </div>
  </div>
</aside>
