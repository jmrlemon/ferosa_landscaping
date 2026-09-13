<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @include('partials.favicon')
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="ferosa-user-role" content="{{ auth()->user()?->role ?? 'staff' }}">
  {{-- /admin, /admin/service-scheduling and /admin/ordering-delivery all render
       this one view, so a fixed title left every admin tab and every history
       entry reading "Admin Dashboard" with no way to tell them apart. --}}
  @php
      $sectionTitle = match ($activeTab ?? 'overview') {
          'appointments' => 'Appointments',
          'orders' => 'Orders & Delivery',
          'services' => 'Services',
          'products' => 'Inventory',
          'messages' => 'Messages',
          'archived' => 'Archived',
          'audit' => 'Audit Logs',
          'users' => 'Users',
          'feedbacks' => 'Feedback',
          'payment' => 'Billing',
          default => 'Dashboard',
      };
  @endphp
  <title>{{ $sectionTitle }} - Ferosa Admin</title>

  <link rel="stylesheet" href="{{ asset('fonts/ferosa-fonts.css') }}">

  @vite([
    'resources/css/app.css',
    'resources/js/app.js',
    'resources/js/admin/notifications.js',
    'resources/js/admin/dashboard.js',
  ])
  <style>
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .admin-chat-bubble {
      padding: .625rem 1rem;
      font-size: .875rem;
      line-height: 1.45;
      overflow-wrap: anywhere;
    }
    .admin-chat-bubble--mine {
      color: #fff;
      background: linear-gradient(135deg, #1a6320, #2d9a2d);
      border-radius: 1.25rem 1.25rem .3rem 1.25rem;
      box-shadow: 0 1px 3px rgba(26, 99, 32, .2);
    }
    .admin-chat-bubble--customer {
      color: #292824;
      background: #fff;
      border: 1px solid #e8e4db;
      border-radius: 1.25rem 1.25rem 1.25rem .3rem;
      box-shadow: 0 1px 2px rgba(18, 52, 38, .05);
    }
    summary::-webkit-details-marker { display: none; }
    @media (max-width: 767px) {
      #admin-sidebar { position: fixed; inset: 0 auto 0 0; width: 272px; z-index: 60; transform: translateX(-100%); transition: transform .22s ease; }
      #admin-sidebar.open { transform: translateX(0); }
      #admin-overlay { display: block; opacity: 0; pointer-events: none; transition: opacity .22s ease; }
      #admin-overlay.open { opacity: 1; pointer-events: auto; }
      #tabs-main > main { padding: 1rem !important; }
      #tab-messages > .flex { min-width: 0; }
      #tab-messages .admin-conversation-list { width: 100%; min-width: 0; }
      #tab-messages .admin-message-thread { display: none; min-width: 0; }
      #tab-messages.thread-open .admin-conversation-list { display: none; }
      #tab-messages.thread-open .admin-message-thread { display: flex; }
      #tabs-main table { min-width: 720px; }
      #tabs-main .admin-compact-table { min-width: 100%; }
      #tabs-main form[method="GET"] > div { min-width: min(100%, 15rem); flex: 1 1 auto; max-width: none; }
      #tabs-main form[method="GET"] > button { min-height: 2.75rem; flex: 1 1 auto; }
    }
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after { transition-duration: .01ms !important; animation-duration: .01ms !important; scroll-behavior: auto !important; }
    }
  </style>
  {{-- Same theme file every other admin page loads, so the dashboard tabs and
       the standalone admin pages share one set of cards, inputs and colours. --}}
  @include('admin.partials.premium-theme')
  <script>
    window.addEventListener('pageshow', function (event) {
      if (event.persisted) window.location.reload();
    });

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
      if (window.innerWidth >= 768) return;
      if (event.target.closest('#admin-sidebar a, #admin-sidebar button')) {
        setTimeout(() => toggleAdminSidebar(true), 80);
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && document.getElementById('admin-sidebar')?.classList.contains('open')) {
        toggleAdminSidebar(true);
      }
      if (event.key === 'Escape' && !document.getElementById('admin-notif-panel')?.classList.contains('hidden')) {
        document.getElementById('admin-notif-panel')?.classList.add('hidden');
        const trigger = document.getElementById('admin-notif-trigger');
        trigger?.setAttribute('aria-expanded', 'false');
        trigger?.focus();
      }
    });
  </script>
</head>
<body class="flex h-screen h-[100dvh] bg-surface-50 text-surface-800 overflow-hidden font-sans antialiased">
  <a href="#admin-main" class="skip-link">Skip to admin content</a>
  @php
    $isAdmin = auth()->user()?->isAdmin() === true;
    $isStaffOrAdmin = auth()->user()?->isStaffOrAdmin();
    $availableTabs = ['overview', 'appointments', 'orders', 'services', 'products', 'messages', 'feedbacks'];
    if ($isAdmin) {
        $availableTabs = array_merge($availableTabs, ['archived', 'audit', 'users', 'payment']);
    }
    $routeTab = match (request()->route()?->getName()) {
        'admin.service-scheduling' => 'appointments',
        'admin.ordering-delivery' => 'orders',
        default => null,
    };
    // $activeTab is resolved by AdminController so it can load only this tab's
    // data; the fallback keeps the view renderable on its own.
    $activeTab = $activeTab ?? $routeTab ?? (in_array(request('tab'), $availableTabs, true) ? request('tab') : 'overview');
    $tabClass = fn (string $tab, string $extra = '') => trim('tab-content '.$extra.' '.($activeTab === $tab ? 'active' : ''));
    $tabStyle = fn (string $tab) => $activeTab === $tab ? 'display:block' : 'display:none';
    // Tabs navigate server-side: the controller loads only the active tab's
    // data, so switching client-side would show empty tables.
    $tabUrl = fn (string $tab) => match ($tab) {
        'appointments' => route('admin.service-scheduling'),
        'orders' => route('admin.ordering-delivery'),
        'overview' => route('admin.dashboard'),
        default => route('admin.dashboard', ['tab' => $tab]),
    };
  @endphp

  <div id="admin-overlay" class="hidden fixed inset-0 bg-black/30 backdrop-blur-sm z-50" onclick="toggleAdminSidebar()" aria-hidden="true"></div>

  @include('admin.partials.workspace-sidebar', ['adminSection' => $activeTab])


  <!-- Main Content -->
  <div class="flex-1 flex flex-col overflow-hidden w-full">
    <header class="h-[68px] bg-white/90 backdrop-blur-xl border-b border-surface-100 flex items-center justify-between gap-4 px-4 sm:px-5 flex-shrink-0 relative z-30">
      <div class="flex items-center gap-3 min-w-0">
        <button id="admin-sidebar-trigger" type="button" onclick="toggleAdminSidebar()" class="md:hidden w-10 h-10 flex items-center justify-center rounded-xl border border-surface-200 text-surface-500" aria-label="Open admin navigation" aria-controls="admin-sidebar" aria-expanded="false">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <div class="min-w-0">
          <p class="text-[10px] font-bold uppercase tracking-[.14em] text-brand-600">Operations center</p>
          <p class="truncate text-sm font-bold text-surface-800">Ferosa workspace</p>
        </div>
      </div>
      <div class="flex items-center justify-end gap-1.5">
        @include('admin.partials.workspace-header-actions', ['messagesUrl' => $tabUrl('messages')])
      </div>
    </header>

    {{-- Messages tab (full-height messenger, outside the scrollable main) --}}
    <div id="tab-messages" style="display:{{ $activeTab === 'messages' ? 'flex' : 'none' }};flex:1;min-height:0;overflow:hidden;">
      <div class="flex h-full w-full">

        {{-- Left: Conversation list --}}
        <div class="admin-conversation-list w-80 min-w-0 border-r border-surface-100 flex flex-col bg-white flex-shrink-0">
          <div class="px-5 py-4 border-b border-surface-100 flex-shrink-0">
            <h1 class="text-sm font-semibold text-surface-900">Messages</h1>
            <p class="text-xs text-surface-400 mt-0.5">{{ $conversations->count() }} conversation{{ $conversations->count() !== 1 ? 's' : '' }}</p>
          </div>
          <div class="overflow-y-auto flex-1">
            @forelse($conversations as $convo)
              @php $unread = $convo->unread_count; $latest = $convo->latestMessage; @endphp
              <button type="button"
                onclick="openConversation({{ $convo->id }}, this.dataset.customerName)"
                data-convo-id="{{ $convo->id }}"
                data-customer-name="{{ $convo->customer->name }}"
                class="convo-btn w-full text-left px-4 py-3.5 border-b border-surface-100 hover:bg-surface-50 transition-colors flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-sm font-bold flex-shrink-0">
                  {{ strtoupper(substr($convo->customer->name, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-sm {{ $unread > 0 ? 'font-bold text-surface-900' : 'font-semibold text-surface-600' }} truncate">{{ $convo->customer->name }}</span>
                    @if($unread > 0)
                      <span class="bg-red-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full flex-shrink-0">{{ $unread }}</span>
                    @endif
                  </div>
                  <p class="text-xs {{ $unread > 0 ? 'text-surface-700 font-medium' : 'text-surface-400' }} truncate mt-0.5">
                    {{ $latest?->body ?? 'No messages yet' }}
                  </p>
                  @if($convo->last_message_at)
                    <p class="text-[10px] text-surface-400 mt-0.5">{{ $convo->last_message_at->diffForHumans() }}</p>
                  @endif
                </div>
              </button>
            @empty
              <div class="flex flex-col items-center justify-center py-16 text-center px-6">
                <svg class="w-10 h-10 text-surface-200 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                <p class="text-sm text-surface-400">No conversations yet</p>
              </div>
            @endforelse
          </div>
        </div>

        {{-- Right: Thread --}}
        <div class="admin-message-thread min-w-0 flex-1 flex flex-col bg-surface-50 overflow-hidden">
          {{-- Empty state --}}
          <div id="thread-empty" class="flex-1 flex flex-col items-center justify-center text-center px-6">
            <div class="w-16 h-16 bg-brand-50 rounded-full flex items-center justify-center mb-4">
              <svg class="w-8 h-8 text-brand-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
            </div>
            <p class="text-sm font-semibold text-surface-600">Select a conversation</p>
            <p class="text-xs text-surface-400 mt-1">Click a customer on the left to start chatting</p>
          </div>

          {{-- Active thread --}}
          <div id="thread-panel" style="display:none;flex-direction:column;height:100%;overflow:hidden;">
            {{-- Header --}}
            <div class="px-4 sm:px-5 py-3 border-b border-surface-100 bg-white flex-shrink-0 flex items-center gap-3">
              <button type="button" onclick="closeMobileConversation()" class="md:hidden flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-surface-500 hover:bg-surface-50" aria-label="Back to conversations">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/></svg>
              </button>
              <div id="thread-avatar" class="w-9 h-9 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-sm font-bold flex-shrink-0"></div>
              <div class="min-w-0">
                <p id="thread-name" class="truncate text-sm font-semibold text-surface-900"></p>
                <p class="text-xs text-surface-400">Customer</p>
              </div>
            </div>

            {{-- Messages --}}
            <div id="thread-messages" class="flex-1 overflow-y-auto px-5 py-4 space-y-3" style="scroll-behavior:smooth">
              <div class="flex items-center justify-center h-full">
                <div class="w-5 h-5 border-2 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
              </div>
            </div>

            {{-- Compose --}}
            <div class="border-t border-surface-100 bg-white px-4 py-3 flex-shrink-0">
              {{-- Selected-file chip --}}
              <div id="reply-attach-preview" class="hidden items-center gap-2 mb-2 rounded-xl border border-surface-200 bg-surface-50 px-3 py-2">
                <img id="reply-attach-thumb" alt="" class="hidden h-10 w-10 rounded-lg object-cover">
                <svg id="reply-attach-icon" class="hidden w-5 h-5 text-brand-600 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6"/>
                </svg>
                <span class="min-w-0 flex-1">
                  <span id="reply-attach-name" class="block truncate text-[12px] font-semibold text-surface-800"></span>
                  <span id="reply-attach-size" class="block text-[10px] text-surface-400"></span>
                </span>
                <button type="button" id="reply-attach-clear" aria-label="Remove attachment"
                  class="shrink-0 w-7 h-7 rounded-full hover:bg-surface-200 flex items-center justify-center text-surface-500">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>

              <form id="reply-form" method="POST" enctype="multipart/form-data" class="flex items-end gap-2">
                @csrf
                <input type="file" id="reply-attachment" aria-label="Reply attachment" name="attachment" class="hidden"
                       accept="{{ \App\Support\MessageAttachment::accept() }}">
                <button type="button" id="reply-attach-btn" aria-label="Attach a file or picture"
                  class="flex-shrink-0 w-11 h-11 rounded-full border border-surface-200 hover:bg-surface-50 flex items-center justify-center transition-colors text-surface-500">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/>
                  </svg>
                </button>
                <textarea
                  id="reply-body" aria-label="Reply message"
                  name="body"
                  rows="1"
                  placeholder="Type a reply&hellip;"
                  maxlength="2000"
                  class="flex-1 resize-none border border-surface-200 rounded-2xl px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 transition-all overflow-y-auto"
                  style="min-height:42px;max-height:120px"
                  onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();document.getElementById('reply-form').requestSubmit();}"
                ></textarea>
                <button type="submit" aria-label="Send message"
                  class="flex-shrink-0 w-11 h-11 bg-brand-600 hover:bg-brand-700 rounded-full flex items-center justify-center transition-colors">
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.269 20.876L5.999 12zm0 0h7.5"/>
                  </svg>
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- All other tabs in a scrollable wrapper --}}
    <div id="tabs-main" class="flex-1 overflow-y-auto" style="{{ $activeTab === 'messages' ? 'display:none' : '' }}">
    <main id="admin-main" tabindex="-1" class="w-full max-w-none px-4 sm:px-6 py-5 sm:py-6">

    @if (session('status'))
      <div role="status" class="bg-brand-50 border border-brand-100 text-brand-700 px-4 py-2.5 rounded-lg text-sm mb-5">
        {{ session('status') }}
      </div>
    @endif

    @if ($errors->any())
      <div role="alert" class="bg-red-50 border border-red-100 text-red-600 px-4 py-2.5 rounded-lg text-sm mb-5">
        <ul class="list-disc pl-4 space-y-0.5">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div id="admin-toast-stack" class="fixed top-4 right-4 z-[70] space-y-2 pointer-events-none" aria-live="polite" aria-atomic="true"></div>

    <!-- OVERVIEW TAB -->
    <div id="tab-overview" class="{{ $tabClass('overview', 'space-y-5') }}" style="{{ $tabStyle('overview') }}">
      @php
        $adminName = trim((string) auth()->user()?->name);
        $adminFirstName = $adminName !== '' ? explode(' ', $adminName)[0] : 'there';
        $greeting = match (true) {
          now()->hour < 12 => 'Good morning',
          now()->hour < 18 => 'Good afternoon',
          default => 'Good evening',
        };
        $liveOrderQueue = (int) ($orderFlowStats['pending'] ?? 0);
        $attentionTotal = $liveOrderQueue
          + (int) $pendingAppointments
          + ($isAdmin ? (int) ($orderFlowStats['unpaid'] ?? 0) : 0)
          + (int) $lowStockProducts->count()
          + (int) $totalUnreadMessages;
      @endphp

      <section aria-labelledby="admin-overview-title" class="relative overflow-hidden rounded-[1.75rem] bg-brand-950 px-5 py-6 text-white shadow-[0_24px_70px_rgba(8,29,21,.18)] sm:px-7 sm:py-7">
        <div aria-hidden="true" class="absolute -right-16 -top-24 h-72 w-72 rounded-full border border-white/10 bg-white/[.035]"></div>
        <div aria-hidden="true" class="absolute -bottom-24 right-28 h-48 w-48 rounded-full border border-brand-400/20"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div class="max-w-2xl">
            <div class="mb-4 flex flex-wrap items-center gap-2 text-[11px] font-bold uppercase tracking-[.16em] text-brand-200">
              <span class="inline-flex h-2 w-2 rounded-full bg-brand-300 shadow-[0_0_0_5px_rgba(130,189,152,.12)]"></span>
              {{ now()->format('l, F j') }}
            </div>
            <h1 id="admin-overview-title" class="font-display text-3xl font-semibold leading-tight sm:text-4xl">{{ $greeting }}, {{ $adminFirstName }}.</h1>
            <p class="mt-3 max-w-xl text-sm leading-6 text-brand-100/80 sm:text-[15px]">
              Keep today&rsquo;s customer work moving. You have {{ number_format($attentionTotal) }} item{{ $attentionTotal === 1 ? '' : 's' }} across the live operations queues.
            </p>
          </div>
          <div class="flex flex-wrap gap-2.5">
            @if($isAdmin)
              <a href="{{ route('admin.reports.overview', array_filter(['sales_from' => $salesFrom ?? request('sales_from'), 'sales_to' => $salesTo ?? request('sales_to')])) }}"
                 class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-white/15 bg-white/10 px-4 text-sm font-semibold text-white transition hover:bg-white/15">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m4 6V7m4 10v-3M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
                View report
              </a>
            @endif
            <a href="{{ route('admin.service-scheduling') }}"
               class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-white px-4 text-sm font-bold text-brand-950 shadow-sm transition hover:bg-brand-50">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6"/></svg>
              Open schedule
            </a>
          </div>
        </div>
      </section>

      <section aria-labelledby="needs-attention-title">
        <div class="mb-3 flex items-end justify-between gap-4">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-[.16em] text-brand-600">Live operations</p>
            <h2 id="needs-attention-title" class="mt-1 font-display text-xl font-semibold text-surface-900">Needs attention</h2>
          </div>
          <p class="hidden text-xs text-surface-400 sm:block">Select a queue to start working</p>
        </div>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
          <a href="{{ $tabUrl('orders') }}" class="group min-h-[122px] rounded-2xl border border-amber-200/70 bg-amber-50/70 p-4 text-left transition hover:-translate-y-0.5 hover:bg-amber-50 hover:shadow-md">
            <div class="flex items-start justify-between gap-3">
              <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
              </span>
              <svg class="h-4 w-4 text-amber-500 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
            </div>
            <p class="mt-4 text-2xl font-bold text-amber-900">{{ $liveOrderQueue }}</p>
            <p class="mt-0.5 text-xs font-semibold text-amber-800">Orders to process</p>
            @if($ordersAwaitingConfirmation > 0)
              <p class="mt-1 text-[10px] text-amber-700">{{ $ordersAwaitingConfirmation }} awaiting receipt</p>
            @endif
          </a>

          <a href="{{ $tabUrl('appointments') }}" class="group min-h-[122px] rounded-2xl border border-blue-200/70 bg-blue-50/70 p-4 text-left transition hover:-translate-y-0.5 hover:bg-blue-50 hover:shadow-md">
            <div class="flex items-start justify-between gap-3">
              <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-100 text-blue-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              </span>
              <svg class="h-4 w-4 text-blue-500 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
            </div>
            <p class="mt-4 text-2xl font-bold text-blue-900">{{ $pendingAppointments }}</p>
            <p class="mt-0.5 text-xs font-semibold text-blue-800">Active appointments</p>
            <p class="mt-1 text-[10px] text-blue-700">
              {{ $todayAppointments }} today
              @if($overdueAppointments > 0)
                &middot; {{ $overdueAppointments }} overdue
              @endif
            </p>
          </a>

          @if($isAdmin)
          <a href="{{ $tabUrl('payment') }}" class="group min-h-[122px] rounded-2xl border border-rose-200/70 bg-rose-50/70 p-4 text-left transition hover:-translate-y-0.5 hover:bg-rose-50 hover:shadow-md">
            <div class="flex items-start justify-between gap-3">
              <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-100 text-rose-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h2m4 0h4M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/></svg>
              </span>
              <svg class="h-4 w-4 text-rose-500 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
            </div>
            <p class="mt-4 text-2xl font-bold text-rose-900">{{ $orderFlowStats['unpaid'] ?? 0 }}</p>
            <p class="mt-0.5 text-xs font-semibold text-rose-800">Unpaid orders</p>
            <p class="mt-1 text-[10px] text-rose-700">Review billing status</p>
          </a>
          @else
          <a href="{{ $tabUrl('orders') }}" class="group min-h-[122px] rounded-2xl border border-indigo-200/70 bg-indigo-50/70 p-4 text-left transition hover:-translate-y-0.5 hover:bg-indigo-50 hover:shadow-md">
            <div class="flex items-start justify-between gap-3">
              <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              </span>
              <svg class="h-4 w-4 text-indigo-500 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
            </div>
            <p class="mt-4 text-2xl font-bold text-indigo-900">{{ $ordersAwaitingConfirmation }}</p>
            <p class="mt-0.5 text-xs font-semibold text-indigo-800">Delivery follow-ups</p>
            <p class="mt-1 text-[10px] text-indigo-700">Record customer receipt</p>
          </a>
          @endif

          <a href="{{ $tabUrl('products') }}" class="group min-h-[122px] rounded-2xl border {{ $lowStockProducts->count() > 0 ? 'border-orange-200/70 bg-orange-50/70 hover:bg-orange-50' : 'border-brand-200/70 bg-brand-50/70 hover:bg-brand-50' }} p-4 text-left transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="flex items-start justify-between gap-3">
              <span class="flex h-9 w-9 items-center justify-center rounded-xl {{ $lowStockProducts->count() > 0 ? 'bg-orange-100 text-orange-700' : 'bg-brand-100 text-brand-700' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
              </span>
              <svg class="h-4 w-4 {{ $lowStockProducts->count() > 0 ? 'text-orange-500' : 'text-brand-500' }} transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
            </div>
            <p class="mt-4 text-2xl font-bold {{ $lowStockProducts->count() > 0 ? 'text-orange-900' : 'text-brand-900' }}">{{ $lowStockProducts->count() }}</p>
            <p class="mt-0.5 text-xs font-semibold {{ $lowStockProducts->count() > 0 ? 'text-orange-800' : 'text-brand-800' }}">Low-stock items</p>
            <p class="mt-1 text-[10px] {{ $lowStockProducts->count() > 0 ? 'text-orange-700' : 'text-brand-700' }}">{{ $lowStockProducts->count() > 0 ? 'Reorder soon' : 'Inventory is healthy' }}</p>
          </a>

          <a href="{{ $tabUrl('messages') }}" class="group col-span-2 min-h-[122px] rounded-2xl border border-violet-200/70 bg-violet-50/70 p-4 text-left transition hover:-translate-y-0.5 hover:bg-violet-50 hover:shadow-md lg:col-span-1">
            <div class="flex items-start justify-between gap-3">
              <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-100 text-violet-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
              </span>
              <svg class="h-4 w-4 text-violet-500 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
            </div>
            <p class="mt-4 text-2xl font-bold text-violet-900">{{ $totalUnreadMessages }}</p>
            <p class="mt-0.5 text-xs font-semibold text-violet-800">Unread messages</p>
            <p class="mt-1 text-[10px] text-violet-700">Reply to customers</p>
          </a>
        </div>
      </section>

      @if($isAdmin)
      <section aria-labelledby="business-snapshot-title" class="rounded-2xl border border-surface-100 bg-white p-4 shadow-[0_10px_35px_rgba(18,52,38,.04)] sm:p-5">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-[.16em] text-brand-600">Performance</p>
            <h2 id="business-snapshot-title" class="mt-1 font-display text-xl font-semibold text-surface-900">Business snapshot</h2>
          </div>
          <p class="text-xs text-surface-400">{{ ($salesFrom || $salesTo) ? 'Filtered reporting period' : 'All-time reporting period' }}</p>
        </div>
        <div class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-surface-100 bg-surface-100 lg:grid-cols-4">
          <div class="bg-white p-4 sm:p-5">
            <p class="text-[10px] font-bold uppercase tracking-wider text-surface-400">Paid revenue</p>
            <p class="mt-2 text-xl font-bold text-brand-800 sm:text-2xl">&#8369;{{ number_format($recognizedRevenue, 2) }}</p>
            <p class="mt-1 text-[11px] text-surface-400">Paid, non-cancelled orders</p>
          </div>
          <div class="bg-white p-4 sm:p-5">
            <p class="text-[10px] font-bold uppercase tracking-wider text-surface-400">Order value</p>
            <p class="mt-2 text-xl font-bold text-surface-900 sm:text-2xl">&#8369;{{ number_format($totalSales, 2) }}</p>
            <p class="mt-1 text-[11px] text-surface-400">{{ number_format($totalOrders) }} order{{ $totalOrders === 1 ? '' : 's' }} recorded</p>
          </div>
          <div class="bg-white p-4 sm:p-5">
            <p class="text-[10px] font-bold uppercase tracking-wider text-surface-400">Fulfilled orders</p>
            <p class="mt-2 text-xl font-bold text-surface-900 sm:text-2xl">{{ number_format($deliveredOrders) }}</p>
            <p class="mt-1 text-[11px] text-surface-400">Delivered or completed</p>
          </div>
          <div class="bg-white p-4 sm:p-5">
            <p class="text-[10px] font-bold uppercase tracking-wider text-surface-400">Customer rating</p>
            <p class="mt-2 text-xl font-bold text-surface-900 sm:text-2xl">
              @if($avgRating){{ number_format((float) $avgRating, 1) }}<span class="text-sm font-medium text-amber-500"> / 5</span>@else<span class="text-surface-400">Not rated</span>@endif
            </p>
            <p class="mt-1 text-[11px] text-surface-400">From {{ number_format($feedbacks->total()) }} review{{ $feedbacks->total() === 1 ? '' : 's' }}</p>
          </div>
        </div>
      </section>
      @else
      <section aria-labelledby="team-snapshot-title" class="rounded-2xl border border-surface-100 bg-white p-4 shadow-[0_10px_35px_rgba(18,52,38,.04)] sm:p-5">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-[.16em] text-brand-600">Team snapshot</p>
            <h2 id="team-snapshot-title" class="mt-1 font-display text-xl font-semibold text-surface-900">Today&rsquo;s operations</h2>
          </div>
          <p class="text-xs text-surface-400">Live queue counts</p>
        </div>
        <div class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-surface-100 bg-surface-100 lg:grid-cols-4">
          <div class="bg-white p-4 sm:p-5"><p class="text-[10px] font-bold uppercase tracking-wider text-surface-400">Open orders</p><p class="mt-2 text-xl font-bold text-amber-700 sm:text-2xl">{{ number_format($liveOrderQueue) }}</p><p class="mt-1 text-[11px] text-surface-400">Pending fulfilment</p></div>
          <div class="bg-white p-4 sm:p-5"><p class="text-[10px] font-bold uppercase tracking-wider text-surface-400">Active visits</p><p class="mt-2 text-xl font-bold text-blue-700 sm:text-2xl">{{ number_format($pendingAppointments) }}</p><p class="mt-1 text-[11px] text-surface-400">Scheduled or confirmed</p></div>
          <div class="bg-white p-4 sm:p-5"><p class="text-[10px] font-bold uppercase tracking-wider text-surface-400">Delivery flow</p><p class="mt-2 text-xl font-bold text-indigo-700 sm:text-2xl">{{ number_format($orderFlowStats['for_delivery'] ?? 0) }}</p><p class="mt-1 text-[11px] text-surface-400">In transit or delivered</p></div>
          <div class="bg-white p-4 sm:p-5"><p class="text-[10px] font-bold uppercase tracking-wider text-surface-400">Unread messages</p><p class="mt-2 text-xl font-bold text-violet-700 sm:text-2xl">{{ number_format($totalUnreadMessages) }}</p><p class="mt-1 text-[11px] text-surface-400">Customer conversations</p></div>
        </div>
      </section>
      @endif

      <section aria-label="Priority work queues" class="grid grid-cols-1 gap-5 xl:grid-cols-2">
        <div class="overflow-hidden rounded-2xl border border-surface-100 bg-white shadow-[0_10px_35px_rgba(18,52,38,.04)]">
          <div class="flex items-center justify-between gap-3 border-b border-surface-100 px-5 py-4">
            <div>
              <h2 class="font-display text-lg font-semibold text-surface-900">Priority appointments</h2>
              <p class="mt-0.5 text-xs text-surface-400">Overdue and nearest schedules first</p>
            </div>
            <a href="{{ route('admin.service-scheduling') }}" class="rounded-lg px-2 py-1 text-xs font-bold text-brand-700 hover:bg-brand-50">View all</a>
          </div>
          <div class="divide-y divide-surface-100">
            @forelse($priorityAppointments as $priorityAppointment)
              @php
                $priorityApptAt = $priorityAppointment->appointment_at;
                $priorityApptOverdue = $priorityApptAt && $priorityApptAt->isPast();
              @endphp
              <a href="{{ route('admin.appointments.show', $priorityAppointment) }}" class="group flex min-h-[72px] items-center gap-3 px-4 py-3 transition hover:bg-surface-50 sm:px-5">
                <div class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-xl {{ $priorityApptOverdue ? 'bg-rose-50 text-rose-700' : 'bg-brand-50 text-brand-700' }}">
                  <span class="text-[9px] font-bold uppercase tracking-wider">{{ $priorityApptAt?->format('M') ?? 'TBD' }}</span>
                  <span class="text-base font-bold leading-none">{{ $priorityApptAt?->format('d') ?? '--' }}</span>
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-2">
                    <p class="truncate text-sm font-bold text-surface-900">{{ $priorityAppointment->user->name ?? 'Customer' }}</p>
                    @if($priorityApptOverdue)<span class="shrink-0 rounded-full bg-rose-50 px-2 py-0.5 text-[9px] font-bold uppercase text-rose-700">Overdue</span>@endif
                  </div>
                  <p class="mt-0.5 truncate text-xs text-surface-500">{{ $priorityAppointment->serviceType->name ?? 'Service visit' }} &middot; {{ $priorityApptAt?->format('g:i A') ?? 'Time pending' }}</p>
                </div>
                <svg class="h-4 w-4 shrink-0 text-surface-400 transition group-hover:translate-x-0.5 group-hover:text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
              </a>
            @empty
              <div class="px-5 py-10 text-center">
                <p class="text-sm font-semibold text-surface-600">Schedule is clear</p>
                <p class="mt-1 text-xs text-surface-400">No active appointments need attention.</p>
              </div>
            @endforelse
          </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-surface-100 bg-white shadow-[0_10px_35px_rgba(18,52,38,.04)]">
          <div class="flex items-center justify-between gap-3 border-b border-surface-100 px-5 py-4">
            <div>
              <h2 class="font-display text-lg font-semibold text-surface-900">Orders requiring action</h2>
              <p class="mt-0.5 text-xs text-surface-400">New, confirmed, and delivery-stage orders</p>
            </div>
            <a href="{{ route('admin.ordering-delivery') }}" class="rounded-lg px-2 py-1 text-xs font-bold text-brand-700 hover:bg-brand-50">View all</a>
          </div>
          <div class="divide-y divide-surface-100">
            @forelse($priorityOrders as $priorityOrder)
              @php
                $priorityOrderTone = match($priorityOrder->status) {
                  'pending' => 'bg-amber-50 text-amber-700',
                  'confirmed' => 'bg-blue-50 text-blue-700',
                  'out_for_delivery' => 'bg-violet-50 text-violet-700',
                  default => 'bg-brand-50 text-brand-700',
                };
              @endphp
              <a href="{{ route('admin.orders.show', $priorityOrder) }}" class="group flex min-h-[72px] items-center gap-3 px-4 py-3 transition hover:bg-surface-50 sm:px-5">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-surface-50 text-xs font-bold text-surface-700 ring-1 ring-surface-100">
                  {{ strtoupper(substr($priorityOrder->user->name ?? '?', 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-2">
                    <p class="truncate text-sm font-bold text-surface-900">{{ $priorityOrder->order_number }}</p>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[9px] font-bold uppercase {{ $priorityOrderTone }}">{{ str_replace('_', ' ', $priorityOrder->status) }}</span>
                  </div>
                  <p class="mt-0.5 truncate text-xs text-surface-500">{{ $priorityOrder->user->name ?? 'Customer' }} &middot; &#8369;{{ number_format((float) $priorityOrder->total_amount, 2) }}</p>
                </div>
                <svg class="h-4 w-4 shrink-0 text-surface-400 transition group-hover:translate-x-0.5 group-hover:text-brand-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
              </a>
            @empty
              <div class="px-5 py-10 text-center">
                <p class="text-sm font-semibold text-surface-600">Order queue is clear</p>
                <p class="mt-1 text-xs text-surface-400">No active orders need attention.</p>
              </div>
            @endforelse
          </div>
        </div>
      </section>

      @if($isAdmin)
      <!-- Filters -->
      <div class="bg-white rounded-2xl border border-surface-100 p-5">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
          <div>
            <h2 class="font-display text-lg font-semibold text-surface-900">Reporting window</h2>
            <p class="text-xs text-surface-400 mt-0.5">Filter financial metrics without changing the live operations queues.</p>
          </div>
          <form method="GET" action="{{ route('admin.dashboard') }}" class="flex flex-wrap items-end gap-2">
            <input type="hidden" name="tab" value="overview">
            <div>
              <label class="block text-[10px] font-medium text-surface-400 mb-1">From</label>
              <input type="date" name="sales_from" aria-label="Sales report from date" value="{{ $salesFrom ?? request('sales_from') }}"
                     class="h-11 rounded-lg border border-surface-200 px-3 text-xs text-surface-700 outline-none transition-colors focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
            </div>
            <div>
              <label class="block text-[10px] font-medium text-surface-400 mb-1">To</label>
              <input type="date" name="sales_to" aria-label="Sales report to date" value="{{ $salesTo ?? request('sales_to') }}"
                     class="h-11 rounded-lg border border-surface-200 px-3 text-xs text-surface-700 outline-none transition-colors focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
            </div>
            <button class="inline-flex h-11 items-center justify-center rounded-lg bg-surface-900 px-4 text-xs font-medium text-white transition-colors hover:bg-surface-800">Apply</button>
            <a href="{{ route('admin.reports.overview', array_filter(['sales_from' => $salesFrom ?? request('sales_from'), 'sales_to' => $salesTo ?? request('sales_to')])) }}"
               class="inline-flex h-11 items-center justify-center gap-1.5 rounded-lg border border-surface-200 bg-white px-4 text-xs font-medium text-surface-600 transition-colors hover:bg-surface-50">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m4 6V7m4 10v-3M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
              View Report
            </a>
            <a href="{{ route('admin.dashboard', ['tab' => 'overview']) }}" class="inline-flex h-11 items-center justify-center rounded-lg border border-surface-200 bg-white px-3 text-xs font-medium text-surface-500 transition-colors hover:border-surface-300 hover:text-surface-800">Reset</a>
          </form>
        </div>
      </div>
      @endif

      @if($isAdmin)
      <!-- System readiness -->
      <details class="group overflow-hidden rounded-2xl border border-surface-100 bg-white">
        <summary class="flex cursor-pointer list-none flex-col gap-2 px-5 py-4 marker:hidden sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-sm font-semibold text-surface-900">System readiness</h2>
            <p class="text-xs text-surface-400 mt-0.5">Configuration and module status for administrators.</p>
          </div>
          <span class="inline-flex items-center gap-2 self-start rounded-full bg-brand-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-brand-700 sm:self-auto">
            View {{ count($moduleCards) }} modules
            <svg class="h-3.5 w-3.5 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
          </span>
        </summary>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 p-5">
          @foreach($moduleCards as $module)
            @php
              $tone = $module['tone'] ?? 'brand';
              $toneClass = match($tone) {
                'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                'blue' => 'bg-blue-50 text-blue-700 border-blue-100',
                'amber' => 'bg-amber-50 text-amber-700 border-amber-100',
                'indigo' => 'bg-indigo-50 text-indigo-700 border-indigo-100',
                'sky' => 'bg-sky-50 text-sky-700 border-sky-100',
                'purple' => 'bg-purple-50 text-purple-700 border-purple-100',
                'green' => 'bg-brand-50 text-brand-700 border-brand-100',
                'rose' => 'bg-rose-50 text-rose-700 border-rose-100',
                default => 'bg-brand-50 text-brand-700 border-brand-100',
              };
            @endphp
            <div class="rounded-xl border border-surface-100 bg-surface-50 p-4 hover:bg-white hover:shadow-sm transition-all">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <h3 class="text-sm font-semibold text-surface-900">{{ $module['name'] }}</h3>
                  <p class="mt-1 text-xs leading-5 text-surface-500">{{ $module['description'] }}</p>
                </div>
                <span class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $toneClass }}">{{ $module['status'] }}</span>
              </div>
              <div class="mt-3 flex items-center justify-between gap-3">
                <span class="text-xs font-semibold text-surface-700">{{ $module['metric'] }}</span>
                @if(!empty($module['tab']))
                  <a href="{{ $tabUrl($module['tab']) }}" class="text-xs font-semibold text-brand-700 hover:text-brand-800">Open</a>
                @else
                  <span class="text-xs font-medium text-surface-400">Customer side</span>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      </details>
      @endif

      @if($isAdmin)
      <!-- Legacy KPI Cards (replaced by the business snapshot above) -->
      <div class="hidden" aria-hidden="true">
        <div class="bg-surface-900 rounded-xl p-5 lg:col-span-2 text-white">
          <p class="text-xs font-medium text-surface-400">Total Sales</p>
          <p class="text-2xl font-display font-bold mt-1">PHP {{ number_format($totalSales, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-surface-100">
          <p class="text-[10px] font-medium text-surface-400 uppercase tracking-wider">Total Orders</p>
          <p class="text-2xl font-bold text-surface-900 mt-2">{{ $totalOrders }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-surface-100">
          <p class="text-[10px] font-medium text-surface-400 uppercase tracking-wider">Pending Orders</p>
          <p class="text-2xl font-bold text-amber-600 mt-2">{{ $pendingOrders }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-surface-100">
          <p class="text-[10px] font-medium text-surface-400 uppercase tracking-wider">Pending Appts</p>
          <p class="text-2xl font-bold text-blue-600 mt-2">{{ $pendingAppointments }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-surface-100">
          <p class="text-[10px] font-medium text-surface-400 uppercase tracking-wider">Total Users</p>
          <p class="text-2xl font-bold text-surface-900 mt-2">{{ $totalUsers }}</p>
        </div>
      </div>
      @endif

      @if($isAdmin)
      <!-- Charts Row 1 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="bg-white rounded-xl border border-surface-100 p-5">
          <h2 class="text-sm font-semibold text-surface-900 mb-5">Monthly order value (last 6 months)</h2>
          @php $maxMonthly = max(1, (float) $monthlySales->max('total')); @endphp
          <div class="grid grid-cols-6 gap-3 items-end min-h-[160px]">
            @foreach ($monthlySales as $row)
              @php $heightPercent = (int) round(($row['total'] / $maxMonthly) * 100); @endphp
              <div class="flex flex-col items-center gap-2 group relative" tabindex="0" role="img" aria-label="{{ $row['label'] }} order value: PHP {{ number_format($row['total'], 2) }}">
                <div aria-hidden="true" class="absolute -top-8 bg-surface-900 text-white text-[10px] py-1 px-2 rounded opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
                  PHP {{ number_format($row['total'], 2) }}
                </div>
                <div class="w-full bg-surface-50 rounded-lg h-36 flex items-end overflow-hidden">
                  <div class="w-full bg-brand-500 rounded-lg transition-all duration-700" style="height: {{ max(8, $heightPercent) }}%;"></div>
                </div>
                <p class="text-[10px] font-medium text-surface-400 uppercase">{{ $row['label'] }}</p>
              </div>
            @endforeach
          </div>
        </div>

        <div class="bg-white rounded-xl border border-surface-100 overflow-hidden flex flex-col">
          <div class="px-5 py-4 border-b border-surface-100">
            <h2 class="text-sm font-semibold text-surface-900">Order value by status</h2>
          </div>
          <div class="overflow-x-auto flex-1">
            <table class="w-full text-xs">
              <thead>
                <tr class="text-left text-surface-400 text-[10px] uppercase tracking-wider border-b border-surface-100">
                  <th class="px-5 py-3 font-medium">Status</th>
                  <th class="px-5 py-3 font-medium text-right">Orders</th>
                  <th class="px-5 py-3 font-medium text-right">Sales</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-surface-100">
                @forelse ($salesByStatus as $row)
                  <tr class="hover:bg-surface-50 transition-colors">
                    <td class="px-5 py-3">
                      <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-surface-100 text-surface-700">
                        {{ ucfirst(str_replace('_', ' ', $row->status)) }}
                      </span>
                    </td>
                    <td class="px-5 py-3 text-right font-medium text-surface-700">{{ $row->order_count }}</td>
                    <td class="px-5 py-3 text-right font-semibold text-surface-900">PHP {{ number_format((float) $row->sales_total, 2) }}</td>
                  </tr>
                @empty
                  <tr><td class="px-5 py-6 text-surface-400 text-center" colspan="3">No order data yet.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Charts Row 2 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="bg-white rounded-xl border border-surface-100 p-5">
          <h2 class="text-sm font-semibold text-surface-900 mb-4">Week vs week order value</h2>
          <div class="grid grid-cols-2 gap-4">
            <div class="bg-surface-50 rounded-lg p-4 border border-surface-100">
              <p class="text-[10px] text-surface-400 font-medium uppercase tracking-wider mb-1">This week</p>
              <p class="text-lg font-bold text-brand-600">PHP {{ number_format($thisWeekSales, 2) }}</p>
            </div>
            <div class="bg-surface-50 rounded-lg p-4 border border-surface-100">
              <p class="text-[10px] text-surface-400 font-medium uppercase tracking-wider mb-1">Last week</p>
              <p class="text-lg font-bold text-surface-600">PHP {{ number_format($lastWeekSales, 2) }}</p>
            </div>
          </div>
          @if ($weekSalesDeltaPct !== null)
            <div class="mt-4 flex items-center gap-2">
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $weekSalesDeltaPct >= 0 ? 'bg-brand-50 text-brand-700' : 'bg-red-50 text-red-600' }}">
                {{ $weekSalesDeltaPct >= 0 ? '+' : '' }}{{ $weekSalesDeltaPct }}%
              </span>
              <span class="text-xs text-surface-400">vs last week</span>
            </div>
          @else
            <p class="mt-4 text-xs text-surface-400">Compare appears once last week has sales.</p>
          @endif
        </div>

        <div class="bg-white rounded-xl border border-surface-100 overflow-hidden flex flex-col">
          <div class="px-5 py-4 border-b border-surface-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-surface-900">Top Products</h2>
            <span class="text-[10px] font-medium text-surface-400 uppercase tracking-wider bg-surface-50 px-2 py-0.5 rounded">By Vol</span>
          </div>
          <div class="overflow-x-auto flex-1">
            <table class="w-full text-xs">
              <thead>
                <tr class="text-left text-surface-400 text-[10px] uppercase tracking-wider border-b border-surface-100">
                  <th class="px-5 py-3 font-medium">Product</th>
                  <th class="px-5 py-3 font-medium text-right">Units Sold</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-surface-100">
                @forelse ($topProducts as $row)
                  <tr class="hover:bg-surface-50 transition-colors">
                    <td class="px-5 py-3 font-medium text-surface-800">{{ $row['name'] }}</td>
                    <td class="px-5 py-3 text-right font-semibold text-surface-700">{{ $row['qty'] }}</td>
                  </tr>
                @empty
                  <tr><td class="px-5 py-6 text-surface-400 text-center" colspan="2">No line items recorded yet.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
      @endif
    </div>

    <!-- APPOINTMENTS TAB -->
    <div id="tab-appointments" class="{{ $tabClass('appointments') }}" style="{{ $tabStyle('appointments') }}">
      <div class="bg-white rounded-xl border border-surface-100 overflow-hidden">
        <div class="p-5 border-b border-surface-100">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div>
              <h1 class="text-sm font-semibold text-surface-900">Appointments</h1>
              <p class="text-xs text-surface-400 mt-0.5">Confirm new bookings first, then manage upcoming visits and completed work.</p>
            </div>
          </div>
          <form method="GET" action="{{ route('admin.dashboard') }}" class="flex flex-wrap items-end gap-2">
            <input type="hidden" name="tab" value="appointments">
            <div>
              <label class="block text-[10px] font-medium text-surface-400 mb-1">Status</label>
              <select name="appt_status" aria-label="Filter appointments by status" class="h-11 min-w-[120px] rounded-lg border border-surface-200 px-3 text-xs text-surface-600 outline-none focus:border-brand-500">
                <option value="">All</option>
                @foreach (['scheduled', 'confirmed', 'completed', 'cancelled'] as $st)
                  <option value="{{ $st }}" {{ ($apptStatus ?? '') === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                @endforeach
              </select>
            </div>
            <div class="relative flex-1 max-w-[240px]">
              <label class="block text-[10px] font-medium text-surface-400 mb-1">Search</label>
              <div class="relative">
                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-surface-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="search" name="appt_q" aria-label="Search appointments" value="{{ $apptQ ?? '' }}" placeholder="Name, email, service..."
                       class="h-11 w-full rounded-lg border border-surface-200 pl-8 pr-3 text-xs outline-none transition-colors focus:border-brand-500">
              </div>
            </div>
            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-surface-900 px-4 text-xs font-medium text-white transition-colors hover:bg-surface-800">Filter</button>
            <a href="{{ route('admin.dashboard', ['tab' => 'appointments']) }}" class="inline-flex h-11 items-center justify-center rounded-lg border border-surface-200 bg-white px-3 text-xs font-medium text-surface-500 transition-colors hover:border-surface-300 hover:text-surface-800">Reset</a>
          </form>
        </div>

        <div class="divide-y divide-surface-100 md:hidden">
          @forelse ($appointments as $appt)
            @php
              $mobileApptBadge = match($appt->status) {
                'scheduled' => 'bg-amber-50 text-amber-700',
                'confirmed' => 'bg-blue-50 text-blue-700',
                'completed' => 'bg-brand-50 text-brand-700',
                'cancelled' => 'bg-red-50 text-red-600',
                default => 'bg-surface-50 text-surface-600',
              };
            @endphp
            <article class="p-4">
              <div class="flex items-start gap-3">
                <div class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                  <span class="text-[9px] font-bold uppercase">{{ $appt->appointment_at?->format('M') ?? 'TBD' }}</span>
                  <span class="text-base font-bold leading-none">{{ $appt->appointment_at?->format('d') ?? '--' }}</span>
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                      <h3 class="truncate text-sm font-bold text-surface-900">{{ $appt->user->name ?? 'Customer' }}</h3>
                      <p class="mt-0.5 truncate text-xs text-surface-500">{{ $appt->serviceType->name ?? 'Service visit' }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-1 text-[9px] font-bold uppercase {{ $mobileApptBadge }}">{{ $appt->status }}</span>
                  </div>
                  <p class="mt-2 text-xs text-surface-500">{{ $appt->appointment_at?->format('M j, Y \a\t g:i A') ?? 'Schedule pending' }}</p>
                  <div class="mt-3 flex items-center justify-between gap-3 border-t border-surface-100 pt-3">
                    <span class="text-[10px] font-bold uppercase tracking-wide {{ ($appt->payment_status ?? 'unpaid') === 'paid' ? 'text-brand-700' : 'text-rose-600' }}">{{ ucfirst($appt->payment_status ?? 'unpaid') }}</span>
                    <a href="{{ route('admin.appointments.show', $appt) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-brand-950 px-4 text-sm font-bold text-white">Manage</a>
                  </div>
                </div>
              </div>
            </article>
          @empty
            <div class="px-5 py-10 text-center text-sm text-surface-400">No appointments match your filters.</div>
          @endforelse
        </div>

        <div class="hidden overflow-x-auto md:block">
          <table class="w-full text-xs whitespace-nowrap">
            <thead>
              <tr class="text-left text-surface-400 text-[10px] uppercase tracking-wider border-b border-surface-100">
                <th class="px-5 py-3 font-medium">Customer</th>
                <th class="px-5 py-3 font-medium">Service</th>
                <th class="px-5 py-3 font-medium">Date & Time</th>
                <th class="px-5 py-3 font-medium">Status</th>
                <th class="px-5 py-3 font-medium text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-surface-100">
              @forelse ($appointments as $appt)
                @php
                  $apptBadge = match($appt->status) {
                    'scheduled' => 'bg-amber-50 text-amber-700 border-amber-100',
                    'confirmed' => 'bg-blue-50 text-blue-700 border-blue-100',
                    'completed' => 'bg-brand-50 text-brand-700 border-brand-100',
                    'cancelled' => 'bg-red-50 text-red-600 border-red-100',
                    default     => 'bg-surface-50 text-surface-600 border-surface-200',
                  };
                @endphp
                <tr class="hover:bg-surface-50 transition-colors">
                  <td class="px-5 py-3">
                    <div class="flex items-center gap-2.5">
                      <div class="h-7 w-7 rounded-full bg-brand-50 text-brand-600 flex items-center justify-center text-[10px] font-bold border border-brand-100 shrink-0">
                        {{ strtoupper(substr($appt->user->name ?? '?', 0, 1)) }}
                      </div>
                      <div>
                        <p class="font-medium text-surface-900">{{ $appt->user->name ?? 'N/A' }}</p>
                        <p class="text-[10px] text-surface-400">{{ $appt->user->email ?? '' }}</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-5 py-3 text-surface-700 font-medium">{{ $appt->serviceType->name ?? 'N/A' }}</td>
                  <td class="px-5 py-3 text-surface-500">
                    {{ $appt->appointment_at ? \Carbon\Carbon::parse($appt->appointment_at)->format('M d, Y · g:i A') : 'N/A' }}
                  </td>
                  <td class="px-5 py-3">
                    <span class="admin-status-badge px-2 py-0.5 rounded text-[10px] font-medium border {{ $apptBadge }}">{{ ucfirst($appt->status) }}</span>
                    <div class="mt-1">
                      <span class="admin-payment-badge text-[9px] font-semibold border px-1.5 py-0.5 rounded uppercase tracking-wide
                        {{ $appt->payment_status === 'paid' ? 'bg-brand-50 text-brand-700 border-brand-100' : 'bg-surface-50 text-surface-500 border-surface-200' }}">
                        {{ ucfirst($appt->payment_status ?? 'unpaid') }}
                      </span>
                    </div>
                  </td>
                  <td class="px-5 py-3">
                    <div class="flex items-center justify-end gap-1.5">
                      {{-- View detail --}}
                      <button type="button"
                        onclick="openApptDetail({{ json_encode([
                          'id'             => $appt->id,
                          'status'         => $appt->status,
                          'payment_status' => $appt->payment_status ?? 'unpaid',
                          'appointment_amount' => number_format((float) ($appt->appointment_amount ?? $appt->serviceType->default_fee ?? 0), 2),
                          'service'        => $appt->serviceType->name ?? 'N/A',
                          'appointment_at' => $appt->appointment_at ? \Carbon\Carbon::parse($appt->appointment_at)->format('D, M j, Y \a\t g:i A') : 'N/A',
                          'notes'          => $appt->notes ?? '',
                          'customer_name'  => $appt->user->name ?? 'N/A',
                          'customer_email' => $appt->user->email ?? '',
                          'feedback_rating' => $appt->feedback?->rating,
                          'feedback_comment'=> $appt->feedback?->comment ?? '',
                        ]) }})"
                        class="text-surface-400 hover:text-brand-600 transition-colors" title="View Details">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                      </button>

                      {{-- Operational status form; payment is an admin-only control. --}}
                      <form method="POST" action="{{ route('admin.appointments.status', $appt) }}" class="admin-status-form flex items-center gap-1.5" data-detail-url="{{ route('admin.appointments.show', $appt) }}">
                        @csrf
                        @method('PUT')
                        <div class="flex flex-col gap-1 min-w-[90px]">
                          @php
                            $appointmentStatuses = $isAdmin
                              ? ['scheduled', 'confirmed', 'completed', 'cancelled']
                              : array_values(array_unique([$appt->status, ...($appt::STATUS_TRANSITIONS[$appt->status] ?? [])]));
                            $hasAppointmentTransition = count($appointmentStatuses) > 1;
                          @endphp
                          <select aria-label="Change status for appointment {{ $appt->id }}" name="status" class="border border-surface-200 rounded px-2 py-0.5 text-[10px] text-surface-600 outline-none focus:border-brand-500 w-full" {{ $isAdmin || $hasAppointmentTransition ? '' : 'disabled' }}>
                            @foreach ($appointmentStatuses as $st)
                              <option value="{{ $st }}" {{ $appt->status === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                            @endforeach
                          </select>
                          @if($isAdmin)
                            <select aria-label="Change payment status for appointment {{ $appt->id }}" name="payment_status" class="border border-surface-200 rounded px-2 py-0.5 text-[10px] {{ $appt->payment_status === 'paid' ? 'text-brand-600 bg-brand-50 font-medium' : 'text-surface-600' }} outline-none focus:border-brand-500 w-full">
                              <option value="unpaid" {{ ($appt->payment_status ?? 'unpaid') === 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                              <option value="paid" {{ $appt->payment_status === 'paid' ? 'selected' : '' }}>Paid</option>
                            </select>
                          @endif
                        </div>
                        @if($isAdmin || $hasAppointmentTransition)
                          <button class="bg-brand-600 text-white rounded px-2.5 py-2 text-[10px] font-medium hover:bg-brand-700 disabled:opacity-70 disabled:cursor-wait transition-colors h-full" data-saving-label="Saving...">Save</button>
                        @endif
                      </form>

                      @if($isAdmin)
                        <form method="POST" action="{{ route('admin.appointments.archive', $appt) }}"
                              data-confirm-title="Archive this appointment?"
                              data-confirm="{{ $appt->user?->name ?? 'This customer' }}'s {{ $appt->serviceType?->name ?? 'appointment' }} moves to Archived. You can restore it from the Archived page."
                              data-confirm-action="Archive">
                          @csrf
                          @method('PUT')
                          <button class="p-1 text-surface-400 hover:text-red-500 rounded transition-colors" title="Archive">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                          </button>
                        </form>
                      @endif
                    </div>
                  </td>
                </tr>
              @empty
                <tr><td class="px-5 py-8 text-surface-400 text-center" colspan="5">No appointments match your filters.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if (method_exists($appointments, 'links'))
          <div class="p-4 border-t border-surface-100">
            {{ $appointments->appends(['tab' => 'appointments', 'appt_status' => request('appt_status'), 'appt_q' => request('appt_q')])->links() }}
          </div>
        @endif
      </div>
    </div>

    <!-- ORDERS TAB -->
    <div id="tab-orders" class="{{ $tabClass('orders') }}" style="{{ $tabStyle('orders') }}">
      <div class="bg-white rounded-xl border border-surface-100 overflow-hidden">
        <div class="p-5 border-b border-surface-100">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div>
              <h1 class="text-sm font-semibold text-surface-900">Orders &amp; Delivery</h1>
              <p class="text-xs text-surface-400 mt-0.5">Process new orders, assign delivery, and record fulfilment proof.</p>
            </div>
            @if($isAdmin)
            <a href="{{ route('admin.reports.orders-csv', array_filter(['order_status' => request('order_status'), 'order_q' => request('order_q')])) }}"
               class="inline-flex h-11 items-center gap-1.5 rounded-lg border border-surface-200 bg-white px-3 text-xs font-medium text-surface-600 transition-colors hover:bg-surface-50">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
              Export CSV
            </a>
            @endif
          </div>

          <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div class="rounded-lg border border-amber-100 bg-amber-50 px-3 py-2">
              <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700">Order Queue</p>
              <p class="mt-1 text-lg font-bold text-amber-700">{{ $orderFlowStats['pending'] ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-blue-100 bg-blue-50 px-3 py-2">
              <p class="text-[10px] font-semibold uppercase tracking-wide text-blue-700">Delivery Flow</p>
              <p class="mt-1 text-lg font-bold text-blue-700">{{ $orderFlowStats['for_delivery'] ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-brand-100 bg-brand-50 px-3 py-2">
              <p class="text-[10px] font-semibold uppercase tracking-wide text-brand-700">Completed</p>
              <p class="mt-1 text-lg font-bold text-brand-700">{{ $orderFlowStats['completed'] ?? 0 }}</p>
            </div>
            @if($isAdmin)
              <div class="rounded-lg border border-red-100 bg-red-50 px-3 py-2">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-red-600">Unpaid Billing</p>
                <p class="mt-1 text-lg font-bold text-red-600">{{ $orderFlowStats['unpaid'] ?? 0 }}</p>
              </div>
            @else
              <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-indigo-700">Delivery follow-ups</p>
                <p class="mt-1 text-lg font-bold text-indigo-700">{{ $ordersAwaitingConfirmation }}</p>
              </div>
            @endif
          </div>

          <div class="flex flex-col xl:flex-row xl:items-end justify-between gap-3">
            <form method="GET" action="{{ route('admin.dashboard') }}" class="flex flex-wrap items-end gap-2 flex-1">
              <input type="hidden" name="tab" value="orders">
              <div>
                <label class="block text-[10px] font-medium text-surface-400 mb-1">Status</label>
                <select name="order_status" aria-label="Filter orders by status" class="h-11 min-w-[120px] rounded-lg border border-surface-200 px-3 text-xs text-surface-600 outline-none focus:border-brand-500">
                  <option value="">All</option>
                  @foreach (['pending', 'confirmed', 'out_for_delivery', 'delivered', 'completed', 'cancelled'] as $st)
                    <option value="{{ $st }}" {{ request('order_status') === $st ? 'selected' : '' }}>
                      {{ ucfirst(str_replace('_', ' ', $st)) }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="relative flex-1 max-w-[240px]">
                <label class="block text-[10px] font-medium text-surface-400 mb-1">Search</label>
                <div class="relative">
                  <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-surface-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                  <input type="search" name="order_q" aria-label="Search orders" value="{{ request('order_q') }}" placeholder="Order #, name, email"
                         class="h-11 w-full rounded-lg border border-surface-200 pl-8 pr-3 text-xs outline-none transition-colors focus:border-brand-500">
                </div>
              </div>
              <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-surface-900 px-4 text-xs font-medium text-white transition-colors hover:bg-surface-800">Filter</button>
              <a href="{{ route('admin.dashboard', ['tab' => 'orders']) }}" class="inline-flex h-11 items-center justify-center rounded-lg border border-surface-200 bg-white px-3 text-xs font-medium text-surface-500 transition-colors hover:border-surface-300 hover:text-surface-800">Reset</a>
            </form>

            {{-- admin.orders.bulk-status is behind the `admin` middleware, so
                 showing this to staff only gave them a 403. --}}
            @if($isAdmin)
            <form method="POST" action="{{ route('admin.orders.bulk-status') }}" class="flex items-center gap-2 bg-surface-50 p-1.5 rounded-lg border border-surface-100" id="admin-bulk-orders-form">
              @csrf
              <span class="text-[10px] font-medium text-surface-400 uppercase tracking-wider ml-1 hidden sm:block">Bulk:</span>
              <select name="status" aria-label="Bulk status to apply to selected orders" class="border border-surface-200 rounded-lg px-2 py-1 text-[10px] text-surface-600 outline-none focus:border-brand-500 w-28">
                @foreach (['pending', 'confirmed', 'cancelled'] as $st)
                  <option value="{{ $st }}">{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
                @endforeach
              </select>
              <button type="submit" class="bg-white border border-surface-200 text-surface-700 rounded-lg px-2.5 py-1 text-[10px] font-medium hover:bg-surface-50 transition-colors">Apply</button>
            </form>
            @endif
          </div>
        </div>

        <div class="divide-y divide-surface-100 md:hidden">
          @forelse ($adminOrders as $order)
            @php
              $mobileOrderBadge = match($order->status) {
                'pending' => 'bg-amber-50 text-amber-700',
                'confirmed' => 'bg-blue-50 text-blue-700',
                'out_for_delivery' => 'bg-violet-50 text-violet-700',
                'delivered', 'completed' => 'bg-brand-50 text-brand-700',
                'cancelled' => 'bg-red-50 text-red-600',
                default => 'bg-surface-50 text-surface-600',
              };
            @endphp
            <article class="p-4">
              <div class="flex items-start gap-3">
                @if($isAdmin)
                  <input type="checkbox" name="order_ids[]" value="{{ $order->id }}" class="admin-order-cb mt-1 h-5 w-5 shrink-0 rounded border-surface-300 text-brand-600 focus:ring-brand-500" form="admin-bulk-orders-form" aria-label="Select order {{ $order->order_number }}">
                @endif
                <div class="min-w-0 flex-1">
                  <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                      <h3 class="font-mono text-xs font-bold text-surface-900">{{ $order->order_number }}</h3>
                      <p class="mt-1 truncate text-sm font-semibold text-surface-700">{{ $order->user->name ?? 'Customer' }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-1 text-[9px] font-bold uppercase {{ $mobileOrderBadge }}">{{ str_replace('_', ' ', $order->status) }}</span>
                  </div>
                  <div class="mt-3 grid grid-cols-2 gap-3 rounded-xl bg-surface-50 p-3">
                    <div>
                      <p class="text-[9px] font-bold uppercase tracking-wide text-surface-400">Amount</p>
                      <p class="mt-1 text-sm font-bold text-surface-900">&#8369;{{ number_format((float) $order->total_amount, 2) }}</p>
                    </div>
                    <div>
                      <p class="text-[9px] font-bold uppercase tracking-wide text-surface-400">Payment</p>
                      <p class="mt-1 text-sm font-bold {{ ($order->payment_status ?? 'unpaid') === 'paid' ? 'text-brand-700' : (($order->payment_status ?? 'unpaid') === 'rejected' ? 'text-red-700' : 'text-amber-700') }}">{{ ucfirst(str_replace('_', ' ', $order->payment_status ?? 'unpaid')) }}</p>
                    </div>
                  </div>
                  <div class="mt-3 flex items-center justify-between gap-3">
                    <span class="text-[11px] text-surface-400">{{ optional($order->created_at)->format('M j, Y') }}</span>
                    <a href="{{ route('admin.orders.show', $order) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-brand-950 px-4 text-sm font-bold text-white">Manage</a>
                  </div>
                </div>
              </div>
            </article>
          @empty
            <div class="px-5 py-10 text-center text-sm text-surface-400">No orders match your filters.</div>
          @endforelse
        </div>

        <div class="hidden overflow-x-auto md:block">
          <table class="w-full min-w-[1120px] text-xs whitespace-nowrap">
            <thead>
              <tr class="text-left text-surface-400 text-[10px] uppercase tracking-wider border-b border-surface-100">
                @if($isAdmin)
                  <th class="px-5 py-3 w-8">
                    <input type="checkbox" id="admin-select-all-orders" aria-label="Select all orders" class="w-3.5 h-3.5 rounded border-surface-300 text-brand-600 focus:ring-brand-500" form="admin-bulk-orders-form">
                  </th>
                @endif
                <th class="px-5 py-3 font-medium">Order #</th>
                <th class="px-5 py-3 font-medium">Customer</th>
                <th class="px-5 py-3 font-medium">Amount</th>
                <th class="px-5 py-3 font-medium">Date</th>
                <th class="px-5 py-3 font-medium">Status</th>
                <th class="px-5 py-3 font-medium text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-surface-100">
              @forelse ($adminOrders as $order)
                <tr class="hover:bg-surface-50 transition-colors">
                  @if($isAdmin)
                    <td class="px-5 py-3">
                      <input type="checkbox" name="order_ids[]" aria-label="Select order {{ $order->order_number }}" value="{{ $order->id }}" class="admin-order-cb w-3.5 h-3.5 rounded border-surface-300 text-brand-600 focus:ring-brand-500" form="admin-bulk-orders-form">
                    </td>
                  @endif
                  <td class="px-5 py-3">
                    <div class="flex items-center gap-1.5">
                      <span class="font-mono font-medium text-surface-800 bg-surface-50 px-1.5 py-0.5 rounded text-[10px]">{{ $order->order_number }}</span>
                      <button type="button"
                        onclick="openOrderDetail({{ json_encode([
                          'id'              => $order->id,
                          'order_number'    => $order->order_number,
                          'status'          => $order->status,
                          'payment_status'  => $order->payment_status ?? 'unpaid',
                          'total_amount'    => number_format((float)$order->total_amount, 2),
                          'created_at'      => optional($order->created_at)->format('M d, Y h:i A'),
                          'delivery_method' => $order->delivery_method ?? 'delivery',
                          'payment_method'  => $order->payment_method ?? 'cod',
                          'delivery_name'   => $order->delivery_name,
                          'delivery_phone'  => $order->delivery_phone,
                          'delivery_address'=> $order->delivery_address,
                          'delivery_city'   => $order->delivery_city,
                          'delivery_notes'  => $order->delivery_notes,
                          'delivery_proof_url' => $order->delivery_proof_url ? route('orders.delivery-proof', $order) : null,
                          'delivered_at' => optional($order->delivered_at)->format('M d, Y h:i A'),
                          'customer_confirmed_at' => optional($order->customer_confirmed_at)->format('M d, Y h:i A'),
                          'cancel_reason' => $order->cancel_reason,
                          'cancelled_at' => optional($order->cancelled_at)->format('M d, Y h:i A'),
                          'customer_name'   => $order->user->name ?? 'N/A',
                          'customer_email'  => $order->user->email ?? '',
                          'customer_phone'  => $order->user->phone_number ?? '',
                          'items'           => $order->items ?? [],
                        ]) }})"
                        class="text-surface-400 hover:text-brand-600 transition-colors" title="View Details">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                      </button>
                    </div>
                  </td>
                  <td class="px-5 py-3">
                    <div class="flex items-center gap-2">
                      <div class="h-6 w-6 rounded-full bg-surface-100 text-surface-500 flex items-center justify-center text-[10px] font-bold border border-surface-200">
                        {{ strtoupper(substr($order->user->name ?? '?', 0, 1)) }}
                      </div>
                      <div>
                        <p class="font-medium text-surface-800">{{ $order->user->name ?? 'N/A' }}</p>
                        <p class="text-[10px] text-surface-400">{{ $order->user->email ?? '' }}</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-5 py-3 font-semibold text-surface-700">PHP {{ number_format((float) $order->total_amount, 2) }}</td>
                  <td class="px-5 py-3 text-surface-500">{{ optional($order->created_at)->format('M d, Y h:i A') }}</td>
                  <td class="px-5 py-3">
                    @php
                      $orderBadge = match($order->status) {
                        'pending' => 'bg-amber-50 text-amber-700 border-amber-100',
                        'confirmed' => 'bg-blue-50 text-blue-700 border-blue-100',
                        'out_for_delivery' => 'bg-indigo-50 text-indigo-700 border-indigo-100',
                        'delivered' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                        'completed' => 'bg-brand-50 text-brand-700 border-brand-100',
                        'cancelled' => 'bg-red-50 text-red-600 border-red-100',
                        default => 'bg-surface-50 text-surface-600 border-surface-200',
                      };
                    @endphp
                    <span class="admin-status-badge px-2 py-0.5 rounded text-[10px] font-medium border {{ $orderBadge }}">
                      {{ $order->status === 'delivered' && ! $order->customer_confirmed_at ? 'Delivered - Pending Confirmation' : ucfirst(str_replace('_', ' ', $order->status)) }}
                    </span>
                    <div class="mt-1.5 space-y-0.5">
                      @php
                        $odm = $order->delivery_method ?? 'delivery';
                        $opm = $order->payment_method ?? 'cod';
                      @endphp
                      <div>
                        @if($odm === 'pickup')
                          <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold border bg-purple-50 text-purple-700 border-purple-100 uppercase tracking-wide">Pick-up</span>
                        @else
                          <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold border bg-blue-50 text-blue-700 border-blue-100 uppercase tracking-wide">Delivery</span>
                        @endif
                        @if($opm === 'gcash')
                          <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold border bg-sky-50 text-sky-700 border-sky-100 uppercase tracking-wide">GCash</span>
                        @else
                          <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold border bg-amber-50 text-amber-700 border-amber-100 uppercase tracking-wide">COD</span>
                        @endif
                        <span class="admin-payment-badge px-1.5 py-0.5 rounded text-[9px] font-semibold border uppercase tracking-wide {{ match($order->payment_status ?? 'unpaid') { 'paid' => 'bg-brand-50 text-brand-700 border-brand-100', 'pending_verification' => 'bg-amber-50 text-amber-700 border-amber-100', 'rejected' => 'bg-red-50 text-red-700 border-red-100', 'refunded' => 'bg-purple-50 text-purple-700 border-purple-100', default => 'bg-surface-50 text-surface-500 border-surface-200' } }}">
                          {{ ucfirst(str_replace('_', ' ', $order->payment_status ?? 'unpaid')) }}
                        </span>
                        @if($order->delivery_proof_url)
                          <span class="admin-proof-badge px-1.5 py-0.5 rounded text-[9px] font-semibold border bg-emerald-50 text-emerald-700 border-emerald-100 uppercase tracking-wide">Proof</span>
                        @endif
                      </div>
                      @if($odm === 'delivery' && $order->delivery_address)
                        <p class="text-[9px] text-surface-400 truncate max-w-[140px]" title="{{ $order->delivery_address }}, {{ $order->delivery_city }}">
                          {{ $order->delivery_address }}, {{ $order->delivery_city }}
                        </p>
                      @endif
                      @if($order->status === 'cancelled')
                        <p class="text-[9px] text-red-500 truncate max-w-[140px]" title="{{ $order->cancel_reason }}">
                          Cancelled {{ optional($order->cancelled_at)->format('M d, Y h:i A') ?? 'recorded' }}
                        </p>
                      @endif
                    </div>
                  </td>
                  <td class="px-5 py-3">
                    <div class="flex items-center justify-end gap-1.5">
                      @php
                        $inlineStatuses = $isAdmin
                          ? match($order->status) {
                              'pending' => ['pending', 'confirmed', 'cancelled'],
                              'confirmed' => ['confirmed', 'cancelled'],
                              default => [$order->status],
                            }
                          : match($order->status) {
                              'pending' => ['pending', 'confirmed'],
                              default => [$order->status],
                            };
                      @endphp
                      <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="admin-status-form flex items-center gap-1.5" data-detail-url="{{ route('admin.orders.show', $order) }}">
                        @csrf
                        @method('PUT')
                        <div class="flex flex-col gap-1 min-w-[100px]">
                          <select aria-label="Change status for order {{ $order->order_number }}" name="status" class="border border-surface-200 rounded px-2 py-0.5 text-[10px] text-surface-600 outline-none focus:border-brand-500 w-full">
                            @foreach ($inlineStatuses as $status)
                              <option value="{{ $status }}" {{ $order->status === $status ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                              </option>
                            @endforeach
                          </select>
                          @if($isAdmin)
                            <select aria-label="Change payment status for order {{ $order->order_number }}" name="payment_status" class="border border-surface-200 rounded px-2 py-0.5 text-[10px] {{ $order->payment_status === 'paid' ? 'text-brand-600 bg-brand-50 font-medium' : 'text-surface-600' }} outline-none focus:border-brand-500 w-full">
                              <option value="unpaid" {{ $order->payment_status === 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                              <option value="pending_verification" {{ $order->payment_status === 'pending_verification' ? 'selected' : '' }}>Pending verification</option>
                              <option value="paid" {{ $order->payment_status === 'paid' ? 'selected' : '' }}>Paid</option>
                              <option value="rejected" {{ $order->payment_status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                              <option value="refunded" {{ $order->payment_status === 'refunded' ? 'selected' : '' }}>Refunded</option>
                            </select>
                          @endif
                          @if(in_array($order->status, ['confirmed', 'out_for_delivery'], true))
                            <a href="{{ route('admin.orders.show', $order) }}" class="text-[9px] font-medium text-indigo-600 hover:text-indigo-800">{{ $order->status === 'confirmed' ? 'Open details to dispatch' : 'Open details for proof' }}</a>
                          @endif
                        </div>
                        @if($isAdmin || count($inlineStatuses) > 1)
                          <button type="submit" class="bg-brand-600 text-white rounded px-2.5 py-2 text-[10px] font-medium hover:bg-brand-700 disabled:opacity-70 disabled:cursor-wait transition-colors h-full" data-saving-label="Saving...">Save</button>
                        @endif
                      </form>
                      @if($isAdmin)
                        <form method="POST" action="{{ route('admin.orders.archive', $order) }}"
                              data-confirm-title="Archive this order?"
                              data-confirm="Order {{ $order->order_number }} moves to Archived. You can restore it from the Archived page."
                              data-confirm-action="Archive">
                          @csrf
                          @method('PUT')
                          <button class="p-1 text-surface-400 hover:text-red-500 rounded transition-colors" title="Archive">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                          </button>
                        </form>
                      @endif
                    </div>
                  </td>
                </tr>
              @empty
                <tr><td class="px-5 py-8 text-surface-400 text-center" colspan="7">No orders match your filters.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- SERVICES TAB -->
    <div id="tab-services" class="{{ $tabClass('services') }}" style="{{ $tabStyle('services') }}">
      <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <div class="flex items-center gap-3">
              <h1 class="text-2xl font-bold text-surface-900">Services</h1>
              <span class="inline-flex min-w-8 items-center justify-center rounded-lg bg-brand-600 px-2.5 py-1 text-sm font-bold text-white">{{ $serviceStats['total'] ?? (method_exists($services, 'total') ? $services->total() : $services->count()) }}</span>
            </div>
            <p class="mt-1 text-sm text-surface-500">{{ $isAdmin ? 'Manage customer-bookable services used in scheduling and cost estimation.' : 'View the services used by scheduling and cost estimation. Pricing changes are handled by an administrator.' }}</p>
          </div>
          @if($isAdmin)
            <a href="{{ route('admin.services.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-brand-800">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5"/></svg>
              Add Service
            </a>
          @else
            <span class="inline-flex items-center rounded-lg border border-surface-200 bg-surface-50 px-3 py-2 text-xs font-semibold text-surface-500">Read-only catalogue</span>
          @endif
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <div class="rounded-xl border border-surface-100 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-surface-400">Service Types</p>
            <p class="mt-2 text-2xl font-bold text-surface-900">{{ $serviceStats['total'] ?? 0 }}</p>
          </div>
          <div class="rounded-xl border border-brand-100 bg-brand-50 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">Active Services</p>
            <p class="mt-2 text-2xl font-bold text-brand-800">{{ $serviceStats['active'] ?? 0 }}</p>
          </div>
          <div class="rounded-xl border border-surface-100 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-surface-400">Scheduling Module</p>
            <p class="mt-2 text-sm font-bold text-surface-900">Customer-bookable</p>
          </div>
        </div>

        <form method="GET" action="{{ route('admin.dashboard') }}" class="rounded-xl border border-surface-100 bg-white p-4 shadow-sm">
          <input type="hidden" name="tab" value="services">
          <div class="grid grid-cols-1 gap-2 md:grid-cols-12">
            <div class="relative md:col-span-9">
              <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-surface-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z"/></svg>
              <input type="search" name="service_q" aria-label="Search services" value="{{ $serviceQ ?? request('service_q') }}" placeholder="Search services..." class="h-10 w-full rounded-lg border border-surface-200 px-3 pl-9 text-sm outline-none transition-colors focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
            </div>
            <div class="flex gap-2 md:col-span-3">
              <button type="submit" class="h-10 flex-1 rounded-lg bg-brand-700 px-4 text-sm font-semibold text-white transition-colors hover:bg-brand-800">Search</button>
              <a href="{{ route('admin.dashboard', ['tab' => 'services']) }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-surface-200 px-4 text-sm font-medium text-surface-500 transition-colors hover:border-surface-300 hover:text-surface-800">Reset</a>
            </div>
          </div>
        </form>

        <div class="space-y-3">
          @forelse ($services as $service)
            <article class="rounded-xl border border-surface-100 bg-white p-4 shadow-sm transition-all hover:border-brand-100 hover:shadow-md">
              <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3 min-w-0">
                  <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border border-brand-100 bg-brand-50 text-brand-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/></svg>
                  </div>
                  <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                      <h3 class="truncate text-base font-bold text-surface-900">{{ $service->name }}</h3>
                      <span class="rounded-lg border px-2.5 py-1 text-xs font-semibold {{ $service->is_active ? 'border-brand-100 bg-brand-50 text-brand-700' : 'border-surface-200 bg-surface-50 text-surface-500' }}">{{ $service->is_active ? 'Active' : 'Hidden' }}</span>
                    </div>
                    <p class="mt-1 text-sm text-surface-500">Customer-bookable service for scheduling and estimation.</p>
                  </div>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                  <div class="rounded-lg border border-surface-100 bg-surface-50 px-4 py-3 sm:min-w-40">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-surface-400">Default Fee</p>
                    <p class="mt-1 text-sm font-bold text-surface-900">PHP {{ number_format((float) $service->default_fee, 2) }}</p>
                  </div>
                  @if($isAdmin)
                    <a href="{{ route('admin.services.edit', $service) }}" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-brand-600 px-3 py-2.5 text-sm font-semibold text-brand-700 transition-colors hover:bg-brand-50">
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1-1.13-1.897l8.932-8.931Z"/></svg>
                      Edit
                    </a>
                  @else
                    <span class="text-xs font-medium text-surface-400">Read-only</span>
                  @endif
                </div>
              </div>
            </article>
          @empty
            <div class="rounded-xl border border-surface-100 bg-white py-12 text-center text-sm text-surface-400">No services found.</div>
          @endforelse
        </div>

        @if (method_exists($services, 'links'))
          <div class="rounded-xl border border-surface-100 bg-white p-4">
            {{ $services->appends(['tab' => 'services', 'service_q' => $serviceQ ?? null])->links() }}
          </div>
        @endif
      </div>
    </div>
    <!-- PRODUCTS TAB -->
    <div id="tab-products" class="{{ $tabClass('products') }}" style="{{ $tabStyle('products') }}">
      <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <div class="flex items-center gap-3">
              <h1 class="text-2xl font-bold text-surface-900">Inventory</h1>
              <span class="inline-flex min-w-8 items-center justify-center rounded-lg bg-brand-600 px-2.5 py-1 text-sm font-bold text-white">{{ method_exists($products, 'total') ? $products->total() : $products->count() }}</span>
            </div>
            <p class="mt-1 text-sm text-surface-500">{{ $isAdmin ? 'Manage materials, supplies, stock visibility, and AR-ready products.' : 'View stock levels and AR readiness. Product and stock changes are handled by an administrator.' }}</p>
          </div>
          @if($isAdmin)
            <a href="{{ route('admin.products.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-brand-800">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5"/></svg>
              Add Inventory Item
            </a>
          @else
            <span class="inline-flex items-center rounded-lg border border-surface-200 bg-surface-50 px-3 py-2 text-xs font-semibold text-surface-500">Read-only inventory</span>
          @endif
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-xl border border-surface-100 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-surface-400">Inventory Items</p>
            <p class="mt-2 text-2xl font-bold text-surface-900">{{ $productStats['total'] ?? 0 }}</p>
          </div>
          <div class="rounded-xl border border-brand-100 bg-brand-50 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">Active in Shop</p>
            <p class="mt-2 text-2xl font-bold text-brand-800">{{ $productStats['active'] ?? 0 }}</p>
          </div>
          <div class="rounded-xl border border-amber-100 bg-amber-50 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Low Stock</p>
            <p class="mt-2 text-2xl font-bold text-amber-700">{{ $productStats['low_stock'] ?? 0 }}</p>
          </div>
          <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">AR Ready</p>
            <p class="mt-2 text-2xl font-bold text-emerald-700">{{ $productStats['ar_ready'] ?? 0 }}</p>
          </div>
        </div>

        <form method="GET" action="{{ route('admin.dashboard') }}" class="rounded-xl border border-surface-100 bg-white p-4 shadow-sm">
          <input type="hidden" name="tab" value="products">
          <div class="grid grid-cols-1 gap-2 md:grid-cols-12">
            <div class="relative md:col-span-5">
              <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-surface-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z"/></svg>
              <input type="search" name="product_q" aria-label="Search products" value="{{ $productQ ?? request('product_q') }}" placeholder="Search by product or category..." class="h-10 w-full rounded-lg border border-surface-200 px-3 pl-9 text-sm outline-none transition-colors focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
            </div>
            <select name="product_category" aria-label="Filter products by category" class="h-10 rounded-lg border border-surface-200 bg-white px-3 text-sm outline-none transition-colors focus:border-brand-500 focus:ring-1 focus:ring-brand-500 md:col-span-4">
              <option value="">All Categories</option>
              @foreach(($productCategories ?? collect()) as $categoryOption)
                <option value="{{ $categoryOption }}" @selected(($productCategory ?? '') === $categoryOption)>{{ ucfirst($categoryOption) }}</option>
              @endforeach
            </select>
            <div class="flex gap-2 md:col-span-3">
              <button type="submit" class="h-10 flex-1 rounded-lg bg-brand-700 px-4 text-sm font-semibold text-white transition-colors hover:bg-brand-800">Filter</button>
              <a href="{{ route('admin.dashboard', ['tab' => 'products']) }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-surface-200 px-4 text-sm font-medium text-surface-500 transition-colors hover:border-surface-300 hover:text-surface-800">Reset</a>
            </div>
          </div>
        </form>

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
          @forelse ($products as $product)
            @php
              $stockClass = $product->stock_qty === 0 ? 'bg-red-50 text-red-600 border-red-100' : ($product->stock_qty <= 5 ? 'bg-amber-50 text-amber-700 border-amber-100' : 'bg-brand-50 text-brand-700 border-brand-100');
            @endphp
            <article class="group overflow-hidden rounded-xl border border-surface-100 bg-white shadow-sm transition-all hover:-translate-y-0.5 hover:border-brand-100 hover:shadow-md">
              <div class="relative flex h-44 items-center justify-center bg-brand-50">
                @if($product->image_url)
                  <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="product-card-image h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]">
                @endif
                <div class="product-card-image-placeholder {{ $product->image_url ? 'hidden' : 'flex' }} h-full w-full items-center justify-center" aria-hidden="true">
                  <svg class="h-12 w-12 text-brand-200" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 19.5h16.5A1.5 1.5 0 0 0 21.75 18V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z"/></svg>
                </div>
                <span class="absolute left-3 top-3 rounded-full border border-white/70 bg-white/90 px-2.5 py-1 text-[11px] font-semibold text-surface-700 shadow-sm">{{ ucfirst($product->category) }}</span>
              </div>
              <div class="p-4">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <h3 class="truncate text-base font-bold text-surface-900">{{ $product->name }}</h3>
                    <p class="mt-1 line-clamp-2 min-h-[2.5rem] text-sm leading-5 text-surface-500">{{ $product->description ?: 'No description added yet.' }}</p>
                  </div>
                  <span class="shrink-0 rounded-lg border px-2.5 py-1 text-xs font-semibold {{ $product->is_active ? 'border-brand-100 bg-brand-50 text-brand-700' : 'border-surface-200 bg-surface-50 text-surface-500' }}">{{ $product->is_active ? 'Active' : 'Hidden' }}</span>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2 rounded-lg border border-surface-100 bg-surface-50 p-3">
                  <div>
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-surface-400">Price</p>
                    <p class="mt-1 text-sm font-bold text-surface-900">&#8369;{{ number_format((float) $product->price, 2) }}</p>
                  </div>
                  <div>
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-surface-400">Stock Qty</p>
                    <p class="mt-1 text-sm font-bold {{ $product->stock_qty === 0 ? 'text-red-600' : ($product->stock_qty <= 5 ? 'text-amber-700' : 'text-brand-700') }}">{{ $product->stock_qty }} units</p>
                  </div>
                </div>
                <div class="mt-3 flex items-center justify-between gap-3">
                  <span class="rounded-lg border px-2.5 py-1 text-xs font-semibold {{ $stockClass }}">{{ $product->stock_qty === 0 ? 'Out of stock' : ($product->stock_qty <= 5 ? 'Low stock' : 'In stock') }}</span>
                  @if($product->plantModel)
                    <span class="rounded-lg border border-emerald-100 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">AR Ready</span>
                  @else
                    <span class="rounded-lg border border-surface-200 bg-surface-50 px-2.5 py-1 text-xs font-semibold text-surface-400">No AR</span>
                  @endif
                </div>
                @if($isAdmin)
                  <a href="{{ route('admin.products.edit', $product) }}" class="mt-4 flex items-center justify-center gap-1.5 rounded-lg border border-brand-600 px-3 py-2.5 text-sm font-semibold text-brand-700 transition-colors hover:bg-brand-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                    Edit
                  </a>
                @else
                  <div class="mt-4 flex items-center justify-center rounded-lg border border-surface-200 bg-surface-50 px-3 py-2.5 text-xs font-medium text-surface-400">Read-only inventory</div>
                @endif
              </div>
            </article>
          @empty
            <div class="col-span-full rounded-xl border border-surface-100 bg-white py-12 text-center text-sm text-surface-400">No products found.</div>
          @endforelse
        </div>

        @if (method_exists($products, 'links'))
          <div class="rounded-xl border border-surface-100 bg-white p-4">
            {{ $products->appends(['tab' => 'products', 'product_q' => $productQ ?? null, 'product_category' => $productCategory ?? null])->links() }}
          </div>
        @endif
      </div>
    </div>

    <!-- BILLING TAB -->
    @if($isAdmin)
    <div id="tab-payment" class="{{ $tabClass('payment') }}" style="{{ $tabStyle('payment') }}">
      <div class="space-y-5">
        <div>
          <h1 class="text-2xl font-bold text-surface-900">Billing</h1>
          <p class="mt-1 text-sm text-surface-500">Manage payment setup, unpaid records, and customer checkout billing details.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div class="rounded-xl border border-red-100 bg-red-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-red-600">Unpaid Orders</p>
            <p class="mt-2 text-2xl font-bold text-red-600">{{ $orderFlowStats['unpaid'] ?? 0 }}</p>
          </div>
          <div class="rounded-xl border border-brand-100 bg-brand-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">Billing Setup</p>
            <p class="mt-2 text-sm font-bold text-brand-800">{{ (!empty($gcashSettings['name']) || !empty($gcashSettings['number'])) ? 'Configured' : 'Needs Setup' }}</p>
          </div>
          <div class="rounded-xl border border-surface-100 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-surface-400">Receipt Support</p>
            <p class="mt-2 text-sm font-bold text-surface-900">Orders & appointments</p>
          </div>
        </div>

      <div class="bg-white rounded-xl border border-surface-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-surface-100">
          <h2 class="text-sm font-semibold text-surface-900">Billing Configuration</h2>
          <p class="text-xs text-surface-400 mt-0.5">These details appear during customer checkout when they choose GCash.</p>
        </div>

        <form method="POST" action="{{ route('admin.payment-settings.update') }}" enctype="multipart/form-data" class="p-5 grid grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
          @csrf
          @method('PUT')

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="block text-[10px] font-medium text-surface-400 mb-1">GCash Account Name</label>
              <input name="gcash_name" aria-label="GCash account name" value="{{ old('gcash_name', $gcashSettings['name'] ?? '') }}" placeholder="e.g. Maria Santos"
                     class="w-full border border-surface-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-brand-500 transition-colors">
            </div>

            <div>
              <label class="block text-[10px] font-medium text-surface-400 mb-1">GCash Number</label>
              <input name="gcash_number" aria-label="GCash mobile number" value="{{ old('gcash_number', $gcashSettings['number'] ?? '') }}" placeholder="e.g. 0917 123 4567"
                     class="w-full border border-surface-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-brand-500 transition-colors">
            </div>

            <div class="sm:col-span-2">
              <label class="block text-[10px] font-medium text-surface-400 mb-1">Upload GCash QR</label>
              <input name="gcash_qr" type="file" aria-label="GCash QR code image" accept="image/*"
                     class="w-full text-xs text-surface-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:bg-surface-100 file:text-surface-700 hover:file:bg-surface-200">
              <p class="text-[11px] text-surface-400 mt-1">PNG or JPG, up to 5MB.</p>
            </div>

            @if(!empty($gcashSettings['qr_url']))
              <label class="inline-flex items-center gap-2 text-xs text-surface-500 sm:col-span-2">
                <input type="checkbox" name="remove_gcash_qr" value="1" class="w-3.5 h-3.5 rounded border-surface-300 text-red-500 focus:ring-red-500">
                Remove current QR image
              </label>
            @endif

            <div class="sm:col-span-2">
              <button class="bg-surface-900 text-white rounded-lg px-4 py-2 text-xs font-medium hover:bg-surface-800 transition-colors">
                Save Billing Details
              </button>
            </div>
          </div>

          <div class="rounded-xl border border-surface-100 bg-surface-50 p-4">
            <p class="text-[10px] font-semibold uppercase tracking-wider text-surface-400 mb-3">Customer Preview</p>
            @if(!empty($gcashSettings['qr_url']))
              <img src="{{ $gcashSettings['qr_url'] }}" alt="GCash QR code" class="w-full aspect-square object-contain rounded-lg bg-white border border-surface-100 mb-3">
            @else
              <div class="w-full aspect-square rounded-lg bg-white border border-dashed border-surface-200 flex items-center justify-center text-center text-xs text-surface-400 mb-3 px-4">
                No QR uploaded yet
              </div>
            @endif
            <p class="text-xs text-surface-500">Name</p>
            <p class="text-sm font-semibold text-surface-900 mb-2">{{ $gcashSettings['name'] ?: 'Not set' }}</p>
            <p class="text-xs text-surface-500">Number</p>
            <p class="text-sm font-semibold text-surface-900">{{ $gcashSettings['number'] ?: 'Not set' }}</p>
          </div>
        </form>
      </div>
      </div>
    </div>
    @endif

    @if($isAdmin)
    <!-- ARCHIVED TAB -->
    <div id="tab-archived" class="{{ $tabClass('archived', 'space-y-5') }}" style="{{ $tabStyle('archived') }}">
      <h1 class="sr-only">Archived items</h1>
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <!-- Archived Inventory -->
        <div class="bg-white rounded-xl border border-surface-100 overflow-hidden flex flex-col">
          <div class="px-5 py-4 border-b border-surface-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-surface-900">Archived Inventory</h2>
            <span class="text-[10px] text-surface-400">{{ ($archivedProducts ?? collect([]))->count() }} items</span>
          </div>
          <div class="p-5 flex-1 space-y-2">
            @forelse (($archivedProducts ?? collect([])) as $p)
              <div class="flex items-center justify-between p-3 bg-surface-50 border border-surface-100 rounded-lg">
                <div>
                  <p class="text-xs font-medium text-surface-800">{{ $p->name }}</p>
                  <p class="text-[10px] text-surface-400 mt-0.5">{{ $p->category }} &middot; &#8369;{{ number_format((float) ($p->price ?? 0), 2) }}</p>
                </div>
                @if($isStaffOrAdmin)
                  <form method="POST" action="{{ route('admin.products.restore', $p) }}">
                    @csrf
                    @method('PUT')
                    <button class="text-[10px] font-medium text-brand-600 hover:text-brand-700 transition-colors">Restore</button>
                  </form>
                @endif
              </div>
            @empty
              <p class="text-xs text-surface-400 text-center py-4">No archived inventory.</p>
            @endforelse
          </div>
        </div>

        <!-- Archived Services -->
        <div class="bg-white rounded-xl border border-surface-100 overflow-hidden flex flex-col">
          <div class="px-5 py-4 border-b border-surface-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-surface-900">Archived Services</h2>
            <span class="text-[10px] text-surface-400">{{ ($archivedServices ?? collect([]))->count() }} items</span>
          </div>
          <div class="p-5 flex-1 space-y-2">
            @forelse (($archivedServices ?? collect([])) as $s)
              <div class="flex items-center justify-between p-3 bg-surface-50 border border-surface-100 rounded-lg">
                <div>
                  <p class="text-xs font-medium text-surface-800">{{ $s->name }}</p>
                  <p class="text-[10px] text-surface-400 mt-0.5">&#8369;{{ number_format((float) ($s->default_fee ?? 0), 2) }}</p>
                </div>
                @if($isStaffOrAdmin)
                  <form method="POST" action="{{ route('admin.services.restore', $s) }}">
                    @csrf
                    @method('PUT')
                    <button class="text-[10px] font-medium text-brand-600 hover:text-brand-700 transition-colors">Restore</button>
                  </form>
                @endif
              </div>
            @empty
              <p class="text-xs text-surface-400 text-center py-4">No archived services.</p>
            @endforelse
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <!-- Archived Orders -->
        <div class="bg-white rounded-xl border border-surface-100 overflow-hidden flex flex-col">
          <div class="px-5 py-4 border-b border-surface-100">
            <h2 class="text-sm font-semibold text-surface-900">Archived Orders</h2>
          </div>
          <div class="p-5 flex-1 space-y-2">
            @forelse (($archivedOrders ?? []) as $o)
              <div class="flex items-center justify-between p-3 bg-surface-50 border border-surface-100 rounded-lg">
                <div>
                  <p class="text-xs font-medium text-surface-800 font-mono">{{ $o->order_number }}</p>
                  <p class="text-[10px] text-surface-400 mt-0.5">{{ $o->user->name ?? 'N/A' }} &middot; &#8369;{{ number_format((float) ($o->total_amount ?? 0), 2) }}</p>
                </div>
                @if($isAdmin)
                  <form method="POST" action="{{ route('admin.orders.restore', $o) }}">
                    @csrf
                    @method('PUT')
                    <button class="text-[10px] font-medium text-brand-600 hover:text-brand-700 transition-colors">Restore</button>
                  </form>
                @endif
              </div>
            @empty
              <p class="text-xs text-surface-400 text-center py-4">No archived orders.</p>
            @endforelse
          </div>
          @if (isset($archivedOrders) && method_exists($archivedOrders, 'links'))
            <div class="p-4 border-t border-surface-100">
              {{ $archivedOrders->appends(['tab' => 'archived'])->links() }}
            </div>
          @endif
        </div>

        <!-- Archived Appointments -->
        <div class="bg-white rounded-xl border border-surface-100 overflow-hidden flex flex-col">
          <div class="px-5 py-4 border-b border-surface-100">
            <h2 class="text-sm font-semibold text-surface-900">Archived Appointments</h2>
          </div>
          <div class="p-5 flex-1 space-y-2">
            @forelse (($archivedAppointments ?? []) as $a)
              <div class="flex items-center justify-between p-3 bg-surface-50 border border-surface-100 rounded-lg">
                <div>
                  <p class="text-xs font-medium text-surface-800">{{ $a->user->name ?? 'N/A' }}</p>
                  <p class="text-[10px] text-surface-400 mt-0.5">{{ $a->serviceType->name ?? 'N/A' }} &middot; {{ optional($a->appointment_at)->format('M d, Y') }}</p>
                </div>
                @if($isStaffOrAdmin)
                  <form method="POST" action="{{ route('admin.appointments.restore', $a) }}">
                    @csrf
                    @method('PUT')
                    <button class="text-[10px] font-medium text-brand-600 hover:text-brand-700 transition-colors">Restore</button>
                  </form>
                @endif
              </div>
            @empty
              <p class="text-xs text-surface-400 text-center py-4">No archived appointments.</p>
            @endforelse
          </div>
          @if (isset($archivedAppointments) && method_exists($archivedAppointments, 'links'))
            <div class="p-4 border-t border-surface-100">
              {{ $archivedAppointments->appends(['tab' => 'archived'])->links() }}
            </div>
          @endif
        </div>
      </div>
    </div>

    <!-- AUDIT TAB -->
    <div id="tab-audit" class="{{ $tabClass('audit') }}" style="{{ $tabStyle('audit') }}">
      <div class="bg-white rounded-xl border border-surface-100 overflow-hidden">
        <div class="p-5 border-b border-surface-100 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
          <div>
            <h1 class="text-sm font-semibold text-surface-900">Audit Logs</h1>
            <p class="text-xs text-surface-400 mt-0.5">See who changed a record, what they did, and when it happened.</p>
          </div>
          <form method="GET" action="{{ route('admin.dashboard') }}" class="flex items-end gap-2">
            <input type="hidden" name="tab" value="audit">
            <div class="relative">
              <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-surface-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
              <input type="search" name="audit_q" aria-label="Search audit log" value="{{ $auditQ ?? request('audit_q') }}" placeholder="Activity, user, target, or ID"
                     class="h-11 w-52 rounded-lg border border-surface-200 pl-8 pr-3 text-xs outline-none transition-colors focus:border-brand-500">
            </div>
            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-surface-900 px-4 text-xs font-medium text-white transition-colors hover:bg-surface-800">Search</button>
            <a href="{{ route('admin.dashboard', ['tab' => 'audit']) }}" class="inline-flex h-11 items-center justify-center rounded-lg border border-surface-200 bg-white px-3 text-xs font-medium text-surface-500 transition-colors hover:border-surface-300 hover:text-surface-800">Reset</a>
          </form>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-xs whitespace-nowrap">
            <thead>
              <tr class="text-left text-surface-400 text-[10px] uppercase tracking-wider border-b border-surface-100">
                <th class="px-5 py-3 font-medium">Time</th>
                <th class="px-5 py-3 font-medium">Actor</th>
                <th class="px-5 py-3 font-medium">What the user did</th>
                <th class="px-5 py-3 font-medium">Target</th>
                <th class="px-5 py-3 font-medium">IP</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-surface-100">
              @forelse (($auditLogs ?? []) as $log)
                <tr class="hover:bg-surface-50 transition-colors">
                  <td class="px-5 py-3 text-surface-500">{{ optional($log->created_at)->format('M d, Y h:i A') }}</td>
                  <td class="px-5 py-3">
                    <div class="flex items-center gap-2">
                      <div class="h-6 w-6 rounded-full bg-surface-100 text-surface-500 flex items-center justify-center text-[10px] font-bold border border-surface-200">
                        {{ strtoupper(substr($log->actor->name ?? 'S', 0, 1)) }}
                      </div>
                      <div>
                        <p class="font-medium text-surface-800">{{ $log->actor->name ?? 'System' }}</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-5 py-3">
                    @php
                      $actionBadge = match(true) {
                        str_contains($log->action, 'create') => 'bg-brand-50 text-brand-700 border-brand-100',
                        str_contains($log->action, 'restore') => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                        str_contains($log->action, 'archive'),
                        str_contains($log->action, 'cancel') => 'bg-red-50 text-red-700 border-red-100',
                        str_contains($log->action, 'update'),
                        str_contains($log->action, 'confirmed') => 'bg-blue-50 text-blue-700 border-blue-100',
                        default => 'bg-surface-50 text-surface-600 border-surface-200',
                      };
                    @endphp
                    <p class="max-w-lg whitespace-normal font-medium leading-5 text-surface-800">{{ $log->description }}</p>
                    <span class="mt-1 inline-flex px-2 py-0.5 rounded text-[10px] font-medium border {{ $actionBadge }}" title="Technical action code">
                      {{ $log->action_label }}
                    </span>
                  </td>
                  <td class="px-5 py-3">
                    <span class="text-[10px] bg-surface-50 px-1.5 py-0.5 rounded text-surface-600">{{ $log->target_label }}</span>
                  </td>
                  <td class="px-5 py-3 font-mono text-[10px] text-surface-400">{{ $log->ip ?? '-' }}</td>
                </tr>
              @empty
                <tr><td class="px-5 py-8 text-surface-400 text-center" colspan="5">No audit logs match your filters.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if (isset($auditLogs) && method_exists($auditLogs, 'links'))
          <div class="p-4 border-t border-surface-100">
            {{ $auditLogs->appends(['tab' => 'audit'])->links() }}
          </div>
        @endif
      </div>
    </div>
    @endif

    <!-- USERS TAB -->
    @if($isAdmin)
    <div id="tab-users" class="{{ $tabClass('users') }}" style="{{ $tabStyle('users') }}">
      <div class="bg-white rounded-xl border border-surface-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-surface-100">
          <h1 class="text-sm font-semibold text-surface-900">User Directory</h1>
          <p class="text-xs text-surface-400 mt-0.5">Manage system access roles for all users.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs whitespace-nowrap">
            <thead>
              <tr class="text-left text-surface-400 text-[10px] uppercase tracking-wider border-b border-surface-100">
                <th class="px-5 py-3 font-medium">User</th>
                <th class="px-5 py-3 font-medium">Account Type</th>
                <th class="px-5 py-3 font-medium">Joined</th>
                <th class="px-5 py-3 font-medium">Role</th>
                <th class="px-5 py-3 font-medium text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-surface-100">
              @foreach ($users as $u)
                <tr class="hover:bg-surface-50 transition-colors {{ $u->id === auth()->id() ? 'bg-brand-50/30' : '' }}">
                  <td class="px-5 py-3">
                    <div class="flex items-center gap-2.5">
                      <div class="h-7 w-7 rounded-full {{ $u->id === auth()->id() ? 'bg-brand-50 text-brand-600 border-brand-100' : 'bg-surface-100 text-surface-500 border-surface-200' }} flex items-center justify-center text-[10px] font-bold border">
                        {{ strtoupper(substr($u->name, 0, 1)) }}
                      </div>
                      <div>
                        <p class="font-medium text-surface-900">
                          {{ $u->name }}
                          @if($u->id === auth()->id())
                            <span class="ml-1 text-[10px] font-medium text-brand-600 bg-brand-50 px-1.5 py-0.5 rounded">You</span>
                          @endif
                        </p>
                        <p class="text-[10px] text-surface-400">{{ $u->email }}</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-5 py-3">
                    <span class="px-2 py-0.5 rounded text-[10px] font-medium border {{ $u->account_type === 'Business' ? 'bg-purple-50 text-purple-600 border-purple-100' : 'bg-surface-50 text-surface-600 border-surface-200' }}">
                      {{ $u->account_type ?? 'Customer' }}
                    </span>
                  </td>
                  <td class="px-5 py-3 text-surface-500">{{ $u->created_at->format('M d, Y') }}</td>
                  <td class="px-5 py-3">
                    @php
                      $roleBadge = match($u->role) {
                        'admin' => 'bg-indigo-50 text-indigo-700 border-indigo-100',
                        'staff' => 'bg-brand-50 text-brand-700 border-brand-100',
                        default => 'bg-surface-50 text-surface-600 border-surface-200',
                      };
                    @endphp
                    <span class="px-2 py-0.5 rounded text-[10px] font-medium border {{ $roleBadge }}">
                      {{ ucfirst($u->role) }}
                    </span>
                  </td>
                  <td class="px-5 py-3">
                    <div class="flex items-center justify-end">
                      @if($u->id !== auth()->id())
                        <form method="POST" action="{{ route('admin.users.role', $u) }}" class="flex items-center gap-1.5">
                          @csrf
                          @method('PUT')
                          <select name="role" class="border border-surface-200 rounded-lg px-2 py-1 text-[10px] text-surface-600 outline-none focus:border-brand-500 w-20">
                            <option value="user" {{ $u->role === 'user' ? 'selected' : '' }}>User</option>
                            <option value="staff" {{ $u->role === 'staff' ? 'selected' : '' }}>Staff</option>
                            <option value="admin" {{ $u->role === 'admin' ? 'selected' : '' }}>Admin</option>
                          </select>
                          <button class="bg-surface-900 text-white rounded-lg px-2.5 py-1 text-[10px] font-medium hover:bg-surface-800 transition-colors">Set</button>
                        </form>
                      @else
                        <span class="text-[10px] text-surface-400">Cannot edit self</span>
                      @endif
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
    @endif

    {{-- ─── Feedbacks Tab ─────────────────────────────────────────────── --}}
    <div id="tab-feedbacks" class="{{ $tabClass('feedbacks') }}" style="{{ $tabStyle('feedbacks') }}">
      <div class="bg-white rounded-xl border border-surface-100 overflow-hidden mb-4">
        <div class="px-5 py-4 border-b border-surface-100 flex items-center justify-between gap-4 flex-wrap">
          <div>
            <h1 class="text-sm font-semibold text-surface-900">Customer Feedback</h1>
            <p class="text-xs text-surface-400 mt-0.5">
              {{ $feedbacks->total() }} submission{{ $feedbacks->total() !== 1 ? 's' : '' }}
              @if($avgRating)
                &mdash; avg rating
                <span class="font-semibold text-amber-500">{{ number_format($avgRating, 1) }}&nbsp;&#9733;</span>
              @endif
            </p>
          </div>
          <form method="GET" action="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
            <input type="hidden" name="tab" value="feedbacks">
            <input type="text" name="feedback_q" aria-label="Search feedback" value="{{ $feedbackQ }}" placeholder="Search…"
              class="h-11 w-44 rounded-lg border border-surface-200 px-3 text-xs outline-none focus:border-brand-500">
            <button class="inline-flex h-11 items-center justify-center rounded-lg bg-surface-900 px-4 text-xs font-medium text-white transition-colors hover:bg-surface-800">Search</button>
            @if($feedbackQ)
              <a href="{{ route('admin.dashboard', ['tab' => 'feedbacks']) }}" class="inline-flex h-11 items-center justify-center rounded-lg border border-surface-200 bg-white px-3 text-xs font-medium text-surface-500 transition-colors hover:border-surface-300 hover:text-surface-800">Reset</a>
            @endif
          </form>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs whitespace-nowrap">
            <thead>
              <tr class="text-left text-surface-400 text-[10px] uppercase tracking-wider border-b border-surface-100">
                <th class="px-5 py-3 font-medium">User</th>
                <th class="px-5 py-3 font-medium">Rating</th>
                <th class="px-5 py-3 font-medium">About</th>
                <th class="px-5 py-3 font-medium">Comment</th>
                <th class="px-5 py-3 font-medium">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-surface-100">
              @forelse ($feedbacks as $fb)
                <tr class="hover:bg-surface-50 transition-colors">
                  <td class="px-5 py-3">
                    <p class="font-medium text-surface-900">{{ $fb->user->name }}</p>
                    <p class="text-[10px] text-surface-400">{{ $fb->user->email }}</p>
                  </td>
                  <td class="px-5 py-3">
                    <span class="text-amber-400 tracking-tighter text-sm">
                      {{ str_repeat('★', $fb->rating) }}<span class="text-surface-350">{{ str_repeat('★', 5 - $fb->rating) }}</span>
                    </span>
                  </td>
                  <td class="px-5 py-3">
                    @if($fb->order)
                      <span class="px-2 py-0.5 rounded text-[10px] font-medium border bg-brand-50 text-brand-700 border-brand-100">
                        Order {{ $fb->order->order_number }}
                      </span>
                    @elseif($fb->appointment)
                      <span class="px-2 py-0.5 rounded text-[10px] font-medium border bg-amber-50 text-amber-700 border-amber-100">
                        Appointment {{ optional($fb->appointment->appointment_at)->format('M d, Y') }}
                      </span>
                    @elseif($fb->product)
                      <span class="px-2 py-0.5 rounded text-[10px] font-medium border bg-blue-50 text-blue-700 border-blue-100">{{ $fb->product->name }}</span>
                    @elseif($fb->serviceType)
                      <span class="px-2 py-0.5 rounded text-[10px] font-medium border bg-purple-50 text-purple-700 border-purple-100">{{ $fb->serviceType->name }}</span>
                    @endif
                  </td>
                  <td class="px-5 py-3 max-w-xs whitespace-normal text-surface-600">{{ $fb->comment ?: '—' }}</td>
                  <td class="px-5 py-3 text-surface-400">{{ $fb->created_at->format('M d, Y') }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="5" class="px-5 py-8 text-center text-surface-400">No feedback submitted yet.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
      {{ $feedbacks->appends(['tab' => 'feedbacks', 'feedback_q' => $feedbackQ])->links() }}
    </div>

    </main>
    </div>{{-- /tabs-main --}}
  </div>{{-- /flex-col wrapper --}}


  {{-- ── Admin Order Detail Modal ──────────────────────────────────────── --}}
  <div id="admin-order-modal" class="fixed inset-0 z-50 hidden flex items-end sm:items-center justify-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="od-dialog-title">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeOrderDetail()" aria-hidden="true"></div>
    <div id="od-dialog-panel" tabindex="-1" class="relative bg-white w-full sm:max-w-2xl max-h-[92vh] sm:max-h-[90vh] flex flex-col sm:rounded-2xl rounded-t-2xl shadow-2xl animate-od-up sm:animate-od-in overflow-hidden">

      {{-- drag handle - mobile only --}}
      <div class="flex justify-center pt-3 pb-1 sm:hidden">
        <div class="w-10 h-1 bg-surface-200 rounded-full"></div>
      </div>

      {{-- Header --}}
      <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-surface-100">
        <div>
          <h2 id="od-dialog-title" class="text-base font-semibold text-surface-900">Order Details</h2>
          <p class="text-xs text-surface-400 mt-0.5">Order <span id="od-order-number" class="font-mono text-brand-600 font-semibold"></span></p>
        </div>
        <div class="flex items-center gap-2">
          <span id="od-status-badge" class="text-[10px] font-semibold uppercase tracking-wide px-2.5 py-1 rounded border"></span>
          <button type="button" onclick="closeOrderDetail()" class="ml-1 flex h-10 w-10 items-center justify-center rounded-xl text-surface-400 hover:bg-surface-50 hover:text-surface-700 transition-colors" aria-label="Close order details">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
      </div>

      {{-- Body (scrollable) --}}
      <div class="overflow-y-auto flex-1 px-4 sm:px-6 py-4 sm:py-5 space-y-4 sm:space-y-5">

        {{-- Customer + Meta row --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

          {{-- Customer Info --}}
          <div class="bg-surface-50 rounded-xl p-4 border border-surface-100">
            <p class="text-[10px] font-semibold text-surface-400 uppercase tracking-wider mb-2">Customer</p>
            <div class="flex items-center gap-3 mb-3">
              <div id="od-avatar" class="h-9 w-9 rounded-full bg-brand-50 text-brand-600 flex items-center justify-center text-sm font-bold border border-brand-100 shrink-0"></div>
              <div class="min-w-0">
                <p id="od-customer-name" class="text-sm font-semibold text-surface-900 truncate"></p>
                <p id="od-customer-email" class="text-xs text-surface-400 truncate"></p>
              </div>
            </div>
            <div class="space-y-1">
              <div class="flex items-center gap-1.5 text-xs text-surface-500">
                <svg class="w-3.5 h-3.5 text-surface-400 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                <span id="od-customer-phone">—</span>
              </div>
            </div>
          </div>

          {{-- Order Meta --}}
          <div class="bg-surface-50 rounded-xl p-4 border border-surface-100 space-y-2">
            <p class="text-[10px] font-semibold text-surface-400 uppercase tracking-wider mb-2">Order Info</p>
            <div class="flex justify-between text-xs">
              <span class="text-surface-400">Date Placed</span>
              <span id="od-created-at" class="font-medium text-surface-700"></span>
            </div>
            <div class="flex justify-between text-xs">
              <span class="text-surface-400">Total Amount</span>
              <span id="od-total" class="font-bold text-surface-900 text-sm"></span>
            </div>
            <div class="flex justify-between text-xs">
              <span class="text-surface-400">Payment</span>
              <span id="od-payment-badge" class="font-medium"></span>
            </div>
            <div class="flex justify-between text-xs">
              <span class="text-surface-400">Payment Status</span>
              <span id="od-payment-status-badge"></span>
            </div>
          </div>
        </div>

        {{-- Delivery Info --}}
        <div class="bg-surface-50 rounded-xl p-4 border border-surface-100">
          <p class="text-[10px] font-semibold text-surface-400 uppercase tracking-wider mb-3">Delivery / Pickup</p>
          <div id="od-delivery-content" class="text-sm text-surface-600 space-y-1"></div>
        </div>

        {{-- Delivery Proof --}}
        <div class="bg-surface-50 rounded-xl p-4 border border-surface-100">
          <p class="text-[10px] font-semibold text-surface-400 uppercase tracking-wider mb-3">Delivery Proof</p>
          <div id="od-proof-content" class="text-sm text-surface-600"></div>
        </div>

        {{-- Cancellation Record --}}
        <div id="od-cancel-record" class="hidden bg-red-50 rounded-xl p-4 border border-red-100">
          <p class="text-[10px] font-semibold text-red-500 uppercase tracking-wider mb-2">Cancellation Record</p>
          <div id="od-cancel-content" class="text-sm text-surface-600 space-y-1"></div>
        </div>

        {{-- Items --}}
        <div>
          <p class="text-[10px] font-semibold text-surface-400 uppercase tracking-wider mb-2">Items Ordered</p>
          <div class="rounded-xl border border-surface-100 overflow-x-auto">
            <table class="w-full text-xs min-w-[380px]">
              <thead class="bg-surface-50">
                <tr class="text-left text-surface-400 text-[10px] uppercase tracking-wider border-b border-surface-100">
                  <th class="px-4 py-2.5 font-medium">Product</th>
                  <th class="px-4 py-2.5 font-medium text-center">Qty</th>
                  <th class="px-4 py-2.5 font-medium text-right">Unit Price</th>
                  <th class="px-4 py-2.5 font-medium text-right">Subtotal</th>
                </tr>
              </thead>
              <tbody id="od-items-tbody" class="divide-y divide-surface-100"></tbody>
              <tfoot class="border-t border-surface-200 bg-surface-50">
                <tr>
                  <td colspan="3" class="px-4 py-2.5 text-right text-xs font-semibold text-surface-700">Total</td>
                  <td class="px-4 py-2.5 text-right text-sm font-bold text-surface-900" id="od-items-total"></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

      </div>{{-- /body --}}
    </div>
  </div>

  {{-- ── Admin Appointment Detail Modal ─────────────────────────────────── --}}
  <div id="admin-appt-modal" class="fixed inset-0 z-50 hidden flex items-end sm:items-center justify-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="ad-dialog-title">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeApptDetail()" aria-hidden="true"></div>
    <div id="ad-dialog-panel" tabindex="-1" class="relative bg-white w-full sm:max-w-lg max-h-[92vh] sm:max-h-[88vh] flex flex-col sm:rounded-2xl rounded-t-2xl shadow-2xl overflow-hidden" style="animation: od-in .18s ease-out">

      <div class="flex justify-center pt-3 pb-1 sm:hidden">
        <div class="w-10 h-1 bg-surface-200 rounded-full"></div>
      </div>

      {{-- Header --}}
      <div class="flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-surface-100">
        <div>
          <h2 id="ad-dialog-title" class="text-base font-semibold text-surface-900">Appointment Details</h2>
          <p class="text-xs text-surface-400 mt-0.5" id="ad-service-label"></p>
        </div>
        <div class="flex items-center gap-2">
          <span id="ad-status-badge" class="text-[10px] font-semibold uppercase tracking-wide px-2.5 py-1 rounded border"></span>
          <button type="button" onclick="closeApptDetail()" class="ml-1 flex h-10 w-10 items-center justify-center rounded-xl text-surface-400 hover:bg-surface-50 hover:text-surface-700 transition-colors" aria-label="Close appointment details">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
      </div>

      {{-- Body --}}
      <div class="overflow-y-auto flex-1 px-4 sm:px-6 py-4 sm:py-5 space-y-4">

        {{-- Customer + Appointment row --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="bg-surface-50 rounded-xl p-4 border border-surface-100">
            <p class="text-[10px] font-semibold text-surface-400 uppercase tracking-wider mb-2">Customer</p>
            <div class="flex items-center gap-3">
              <div id="ad-avatar" class="h-9 w-9 rounded-full bg-brand-50 text-brand-600 flex items-center justify-center text-sm font-bold border border-brand-100 shrink-0"></div>
              <div class="min-w-0">
                <p id="ad-customer-name" class="text-sm font-semibold text-surface-900 truncate"></p>
                <p id="ad-customer-email" class="text-xs text-surface-400 truncate"></p>
              </div>
            </div>
          </div>

          <div class="bg-surface-50 rounded-xl p-4 border border-surface-100 space-y-2">
            <p class="text-[10px] font-semibold text-surface-400 uppercase tracking-wider mb-2">Appointment Info</p>
            <div class="flex justify-between text-xs">
              <span class="text-surface-400">Date & Time</span>
              <span id="ad-date" class="font-medium text-surface-700 text-right max-w-[140px]"></span>
            </div>
            <div class="flex justify-between text-xs">
              <span class="text-surface-400">Payment</span>
              <span id="ad-payment-badge"></span>
            </div>
            <div class="flex justify-between text-xs">
              <span class="text-surface-400">Amount</span>
              <span id="ad-amount" class="font-semibold text-surface-900"></span>
            </div>
          </div>
        </div>

        {{-- Notes --}}
        <div class="bg-surface-50 rounded-xl p-4 border border-surface-100">
          <p class="text-[10px] font-semibold text-surface-400 uppercase tracking-wider mb-2">Notes</p>
          <p id="ad-notes" class="text-sm text-surface-600 italic leading-relaxed"></p>
        </div>

        {{-- Feedback --}}
        <div id="ad-feedback-section" class="bg-surface-50 rounded-xl p-4 border border-surface-100">
          <p class="text-[10px] font-semibold text-surface-400 uppercase tracking-wider mb-2">Customer Feedback</p>
          <div id="ad-feedback-stars" class="flex gap-0.5 mb-1.5"></div>
          <p id="ad-feedback-comment" class="text-xs text-surface-500 italic"></p>
        </div>

      </div>
    </div>
  </div>

  <style>
    @keyframes od-in  { from { opacity:0; transform:scale(.97) translateY(8px); } to { opacity:1; transform:none; } }
    @keyframes od-up  { from { transform:translateY(100%); opacity:0; } to { transform:translateY(0); opacity:1; } }
    .animate-od-in { animation: od-in .18s ease-out; }
    .animate-od-up { animation: od-up .25s cubic-bezier(.32,1,.5,1); }
    #admin-order-modal > div:last-child { animation: od-in .18s ease-out; }
  </style>

  {{-- Server values for resources/js/admin/dashboard.js --}}
  @php
      $adminDashboardConfig = [
          'csrfToken' => csrf_token(),
          'activeTab' => $activeTab,
          'notificationsUrl' => route('notifications'),
          'notificationsReadAllUrl' => route('notifications.read-all'),
          'notificationsBase' => url('/notifications'),
          'conversationsBase' => url('/admin/conversations'),
          'maxAttachmentKb' => \App\Support\MessageAttachment::MAX_KB,
          'maxAttachmentMb' => round(\App\Support\MessageAttachment::MAX_KB / 1024),
      ];
  @endphp
  <script type="application/json" id="admin-dashboard-config">@json($adminDashboardConfig)</script>
  @include('partials.confirm-dialog')
</body>
</html>
