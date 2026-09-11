<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @include('partials.favicon')
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="ferosa-user-role" content="{{ auth()->user()?->role ?? 'staff' }}">
  <title>@yield('title', 'Ferosa Admin')</title>

  <link rel="stylesheet" href="{{ asset('fonts/ferosa-fonts.css') }}">

  @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/admin/notifications.js'])
  @include('admin.partials.premium-theme')
  <style>
    @media (max-width: 767px) {
      #admin-sidebar {
        position: fixed;
        inset: 0 auto 0 0;
        width: 272px;
        z-index: 60;
        transform: translateX(-100%);
        transition: transform .22s ease;
      }
      #admin-sidebar.open { transform: translateX(0); }
      #admin-overlay {
        display: block;
        opacity: 0;
        pointer-events: none;
        transition: opacity .22s ease;
      }
      #admin-overlay.open { opacity: 1; pointer-events: auto; }
      #admin-workspace-main > main { padding: 1rem !important; }
    }
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after {
        transition-duration: .01ms !important;
        animation-duration: .01ms !important;
        scroll-behavior: auto !important;
      }
    }
  </style>
  <script>
    function toggleAdminSidebar(forceClose = false) {
      const sidebar = document.getElementById('admin-sidebar');
      const overlay = document.getElementById('admin-overlay');
      const trigger = document.getElementById('admin-sidebar-trigger');
      if (!sidebar || !overlay) return;

      const shouldOpen = !forceClose && !sidebar.classList.contains('open');
      sidebar.classList.toggle('open', shouldOpen);
      overlay.classList.toggle('open', shouldOpen);
      trigger?.setAttribute('aria-expanded', String(shouldOpen));
      document.body.style.overflow = shouldOpen ? 'hidden' : '';

      if (shouldOpen) {
        window.setTimeout(() => document.getElementById('admin-sidebar-close')?.focus(), 100);
      } else if (window.innerWidth < 768 && document.activeElement?.closest('#admin-sidebar')) {
        trigger?.focus();
      }
    }

    window.addEventListener('resize', function () {
      if (window.innerWidth >= 768) toggleAdminSidebar(true);
    });

    document.addEventListener('click', function (event) {
      if (window.innerWidth < 768 && event.target.closest('#admin-sidebar a')) {
        window.setTimeout(() => toggleAdminSidebar(true), 80);
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && document.getElementById('admin-sidebar')?.classList.contains('open')) {
        toggleAdminSidebar(true);
      }
    });
  </script>
  @stack('head')
</head>
<body class="flex h-screen h-[100dvh] overflow-hidden bg-surface-50 font-sans text-surface-800 antialiased">
  <a href="#admin-main" class="skip-link">@yield('skip-label', 'Skip to admin content')</a>

  <div id="admin-overlay" class="hidden fixed inset-0 z-50 bg-black/30 backdrop-blur-sm" onclick="toggleAdminSidebar(true)" aria-hidden="true"></div>

  @include('admin.partials.workspace-sidebar', [
      'adminSection' => trim($__env->yieldContent('admin-section', '')),
  ])

  <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
    <header class="relative z-30 flex h-[68px] flex-shrink-0 items-center justify-between gap-4 border-b border-surface-100 bg-white/90 px-4 backdrop-blur-xl sm:px-5">
      <div class="flex min-w-0 items-center gap-3">
        <button id="admin-sidebar-trigger" type="button" onclick="toggleAdminSidebar()" class="flex h-10 w-10 items-center justify-center rounded-xl border border-surface-200 text-surface-500 md:hidden" aria-label="Open admin navigation" aria-controls="admin-sidebar" aria-expanded="false">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <div class="min-w-0">
          <p class="text-[10px] font-bold uppercase tracking-[.14em] text-brand-600">@yield('header-eyebrow', 'Admin workspace')</p>
          <h1 class="truncate text-sm font-bold text-surface-800">@yield('header-title', 'Ferosa workspace')</h1>
        </div>
      </div>
      {{-- Same cluster on every admin screen. Pages add their own actions via
           the `header-actions` section; they no longer replace the header. --}}
      <div class="flex flex-shrink-0 items-center justify-end gap-1.5">
        @yield('header-actions')
        @include('admin.partials.workspace-header-actions')
      </div>
    </header>

    <div id="admin-workspace-main" class="min-h-0 flex-1 overflow-y-auto">
      <main id="admin-main" tabindex="-1" class="w-full max-w-none px-4 py-5 sm:px-6 sm:py-6">
        @yield('content')
      </main>
    </div>
  </div>
  @include('partials.confirm-dialog')
</body>
</html>
