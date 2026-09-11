@extends('layouts.customer')

@section('title', 'Notifications – Ferosa')

@section('content')
<div class="customer-page is-narrow">
  <x-page-head
    kicker="Activity"
    title="Notifications"
    sub="Stay updated on your orders and appointments.">
    <x-slot:icon>
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
      </svg>
    </x-slot:icon>
    @if(collect($notifications)->whereNull('read_at')->count() > 0)
      <form method="POST" action="{{ route('notifications.read-all') }}">
        @csrf
        <button type="submit" class="btn btn-soft btn-sm">Mark all read</button>
      </form>
    @endif
  </x-page-head>

  @if(count($notifications) === 0)
    <div class="customer-empty py-12">
      <div class="customer-empty-icon">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
      </div>
      <h2 class="text-sm font-semibold text-surface-900 mb-1">No notifications yet</h2>
      <p class="text-surface-400 text-sm">You'll see updates about your orders and appointments here.</p>
    </div>
  @else
    <div class="customer-card overflow-hidden divide-y divide-surface-100">
      @foreach($notifications as $notif)
        @php
          $isUnread = is_null($notif['read_at']);
          $url = $notif['data']['url'] ?? '#';
          $message = $notif['data']['message'] ?? 'New notification';
          $type = $notif['data']['type'] ?? 'general';
        @endphp
        <a href="{{ $url }}"
           onclick="fetch('{{ route('notifications.read', $notif['id']) }}', {method:'POST', headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','X-Requested-With':'XMLHttpRequest'}})"
           class="flex items-start gap-3 px-5 py-4 hover:bg-surface-50 transition-colors {{ $isUnread ? 'bg-brand-50/50' : '' }}">
          {{-- Indicator dot --}}
          <div class="flex-shrink-0 mt-1.5">
            <div class="w-2.5 h-2.5 rounded-full {{ $isUnread ? 'bg-brand-500' : 'bg-surface-200' }}"></div>
          </div>
          {{-- Content --}}
          <div class="flex-1 min-w-0">
            <p class="text-sm text-surface-800 leading-snug {{ $isUnread ? 'font-medium' : '' }}">{{ $message }}</p>
            <p class="text-xs text-surface-400 mt-1">{{ $notif['created_at'] }}</p>
          </div>
          {{-- Arrow --}}
          <svg class="w-4 h-4 text-surface-400 flex-shrink-0 mt-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
          </svg>
        </a>
      @endforeach
    </div>
  @endif
</div>
@endsection
