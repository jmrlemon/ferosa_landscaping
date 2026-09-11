{{-- Shared admin header cluster: messages, notifications, signed-in user.

     Every admin screen shows the same controls in the same place. Pages used to
     put ad-hoc links here instead ("Dashboard" on Business Profile, "Add
     project" on Project Portfolio), so the header changed shape as you moved
     around and the unread counts were only visible from the dashboard.

     Counts come from the AdminHeaderComposer, so this works on pages served by
     any controller. The notification panel is driven by
     resources/js/admin/notifications.js.

     Optional: $messagesUrl overrides the messages link (the dashboard renders
     messages as one of its own tabs). --}}
@php
    $headerMessagesUrl = $messagesUrl ?? route('admin.dashboard', ['tab' => 'messages']);
    $headerUser = auth()->user();
    $headerConfig = [
        'csrfToken' => csrf_token(),
        'notificationsUrl' => route('notifications'),
        'notificationsReadAllUrl' => route('notifications.read-all'),
        'notificationsBase' => url('/notifications'),
    ];
@endphp

{{-- Endpoints for resources/js/admin/notifications.js --}}
<script type="application/json" id="admin-header-config">@json($headerConfig)</script>

<a href="{{ $headerMessagesUrl }}"
   class="w-9 h-9 flex items-center justify-center text-surface-400 hover:text-surface-700 hover:bg-surface-50 rounded-lg transition-colors relative"
   title="Messages" aria-label="Open messages">
  <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
  @if(($totalUnreadMessages ?? 0) > 0)
    <span class="absolute top-1 right-1 bg-red-500 text-white text-[8px] font-bold min-w-[13px] h-[13px] px-0.5 rounded-full flex items-center justify-center leading-none">{{ $totalUnreadMessages > 9 ? '9+' : $totalUnreadMessages }}</span>
  @endif
</a>

<div class="relative">
  <button id="admin-notif-trigger" type="button" onclick="toggleAdminNotifPanel()"
          class="w-9 h-9 flex items-center justify-center text-surface-400 hover:text-surface-700 hover:bg-surface-50 rounded-lg transition-colors relative"
          title="Notifications" aria-label="Open notifications" aria-controls="admin-notif-panel" aria-expanded="false">
    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
    @if(($adminUnreadNotifications ?? 0) > 0)
      <span id="admin-notif-count" class="absolute top-1 right-1 bg-red-500 text-white text-[8px] font-bold min-w-[13px] h-[13px] px-0.5 rounded-full flex items-center justify-center leading-none">{{ $adminUnreadNotifications > 9 ? '9+' : $adminUnreadNotifications }}</span>
    @endif
  </button>

  <div id="admin-notif-panel" class="hidden absolute right-0 top-full mt-2 w-[calc(100vw-2rem)] sm:w-80 bg-white border border-surface-200 rounded-xl shadow-lg z-50 overflow-hidden">
    <div class="flex items-center justify-between px-4 py-2.5 border-b border-surface-100">
      <span class="text-[11px] font-semibold text-surface-500 uppercase tracking-wider">Notifications</span>
      <button type="button" onclick="markAdminNotificationsRead()" class="text-[11px] text-brand-600 hover:text-brand-700 font-medium">Mark all read</button>
    </div>
    <div id="admin-notif-list" class="max-h-72 overflow-y-auto divide-y divide-surface-100">
      <div class="px-4 py-6 text-center text-xs text-surface-400">Loading...</div>
    </div>
  </div>
</div>

<div class="ml-1 hidden items-center gap-2.5 border-l border-surface-100 pl-3 sm:flex">
  <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-950 text-xs font-bold text-white">
    {{ strtoupper(substr($headerUser?->name ?? 'A', 0, 1)) }}
  </div>
  <div class="hidden min-w-0 lg:block">
    <p class="max-w-36 truncate text-xs font-bold text-surface-800">{{ $headerUser?->name ?? 'Administrator' }}</p>
    <p class="mt-0.5 text-[9px] font-bold uppercase tracking-wider text-surface-400">{{ $headerUser?->isAdmin() ? 'Administrator' : 'Staff member' }}</p>
  </div>
</div>
