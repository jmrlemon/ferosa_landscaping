@php
  $r = request()->route()?->getName();
  $active = fn (string $name) => $r === $name ? 'mobile-nav-active' : '';
  $shopActive = in_array($r, ['shop', 'checkout'], true) ? 'mobile-nav-active' : '';
  $moreActive = in_array($r, [
    'projects.index', 'projects.show', 'orders', 'orders.confirmation', 'orders.receipt',
    'appointments', 'account', 'feedback', 'messages', 'notifications',
  ], true) ? 'mobile-nav-active' : '';
@endphp
<style>
  .mobile-customer-nav {
    padding-bottom: max(.45rem, env(safe-area-inset-bottom));
    box-shadow: 0 -10px 35px rgba(18,52,38,.07);
  }
  body.in-app .mobile-customer-nav { display: none !important; }
  .mobile-nav-link {
    position: relative;
    min-height: 54px;
    color: var(--color-surface-400);
    transition: color .16s ease;
  }
  .mobile-nav-link.mobile-nav-active { color: #1b5239; font-weight: 700; }
  .mobile-nav-link.mobile-nav-active::before {
    content: '';
    position: absolute;
    top: -1px;
    left: 50%;
    width: 24px;
    height: 3px;
    transform: translateX(-50%);
    border-radius: 0 0 999px 999px;
    background: #347f57;
  }
</style>
<nav class="mobile-customer-nav lg:hidden fixed bottom-0 inset-x-0 z-50 border-t border-surface-100 bg-white/95 backdrop-blur-xl" aria-label="Primary navigation">
  @guest
  {{-- Home, Estimate and Book are all behind auth, so a guest tapping them
       would only ever bounce to the login screen. Show the two public
       destinations and a way in instead. --}}
  <div class="grid grid-cols-3 max-w-md mx-auto px-2 pt-1 text-[11px] text-center">
    <a href="{{ route('shop') }}" class="mobile-nav-link {{ $shopActive }} flex flex-col items-center justify-center gap-1" @if($shopActive) aria-current="page" @endif>
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 0 0-8 0v4M5 9h14l1 12H4L5 9Z"/></svg>
      Shop
    </a>
    <a href="{{ route('projects.index') }}" class="mobile-nav-link {{ $active('projects.index') }} flex flex-col items-center justify-center gap-1" @if($r === 'projects.index') aria-current="page" @endif>
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20h16M6 20V9l6-5 6 5v11M9 20v-6h6v6"/></svg>
      Projects
    </a>
    <a href="{{ route('login') }}" class="mobile-nav-link flex flex-col items-center justify-center gap-1">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h7a3 3 0 0 1 3 3v1"/></svg>
      Sign in
    </a>
  </div>
  @else
  <div class="grid grid-cols-5 max-w-md mx-auto px-2 pt-1 text-[11px] text-center">
    <a href="{{ route('home') }}" class="mobile-nav-link {{ $active('home') }} flex flex-col items-center justify-center gap-1" @if($r === 'home') aria-current="page" @endif>
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0 7-7 7 7M5 10v10a1 1 0 0 0 1 1h3m10-11 2 2m-2-2v10a1 1 0 0 1-1 1h-3m-6 0a1 1 0 0 0 1-1v-4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v4a1 1 0 0 0 1 1m-6 0h6"/></svg>
      Home
    </a>
    <a href="{{ route('shop') }}" class="mobile-nav-link {{ $shopActive }} flex flex-col items-center justify-center gap-1" @if($shopActive) aria-current="page" @endif>
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 0 0-8 0v4M5 9h14l1 12H4L5 9Z"/></svg>
      Shop
    </a>
    <a href="{{ route('estimator') }}" class="mobile-nav-link {{ $active('estimator') }} flex flex-col items-center justify-center gap-1" @if($r === 'estimator') aria-current="page" @endif>
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2"/><path stroke-linecap="round" d="M8 7h8M8 12h2m4 0h2M8 16h2m4 0h2"/></svg>
      Estimate
    </a>
    <a href="{{ route('schedule') }}" class="mobile-nav-link {{ $active('schedule') }} flex flex-col items-center justify-center gap-1" @if($r === 'schedule') aria-current="page" @endif>
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z"/></svg>
      Book
    </a>
    <button type="button" onclick="toggleSidebar(this)" data-sidebar-trigger class="mobile-nav-link {{ $moreActive }} flex flex-col items-center justify-center gap-1" aria-label="Open more navigation" aria-controls="customer-sidebar" aria-expanded="false">
      <span class="relative">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>
        @if(isset($customerPendingConfirmations) && $customerPendingConfirmations > 0)
          <span class="absolute -top-2 -right-2 bg-red-500 text-white text-[8px] font-bold min-w-[15px] h-[15px] px-0.5 rounded-full flex items-center justify-center leading-none">{{ $customerPendingConfirmations > 9 ? '9+' : $customerPendingConfirmations }}</span>
        @endif
      </span>
      More
    </button>
  </div>
  @endguest
</nav>
