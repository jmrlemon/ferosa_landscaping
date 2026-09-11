@extends('layouts.customer')

@section('title', 'Home - Ferosa Landscaping')

@section('styles')
<style>
  .home-shell { --home-ink: #163126; }
  .welcome-panel {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    background:
      radial-gradient(circle at 87% 12%, rgba(130, 189, 152, .24), transparent 28%),
      linear-gradient(135deg, #102e22 0%, #174c35 58%, #236747 100%);
    box-shadow: 0 24px 60px rgba(18, 52, 38, .16);
  }
  .welcome-panel::before {
    content: '';
    position: absolute;
    inset: 0;
    z-index: -1;
    opacity: .09;
    background-image: radial-gradient(circle, #fff 1px, transparent 1px);
    background-size: 24px 24px;
    -webkit-mask-image: linear-gradient(90deg, transparent 10%, #000 80%);
    mask-image: linear-gradient(90deg, transparent 10%, #000 80%);
  }
  .garden-plan {
    position: relative;
    min-height: 270px;
    border: 1px solid rgba(255,255,255,.15);
    background:
      linear-gradient(rgba(255,255,255,.055) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,.055) 1px, transparent 1px),
      rgba(255,255,255,.07);
    background-size: 28px 28px;
    backdrop-filter: blur(12px);
  }
  .garden-bed {
    position: absolute;
    border: 1px solid rgba(216,236,223,.33);
    background: rgba(216,236,223,.12);
  }
  .garden-dot {
    position: absolute;
    width: 13px;
    height: 13px;
    border-radius: 999px;
    background: #a8d3b7;
    box-shadow: 0 0 0 6px rgba(168,211,183,.1);
  }
  .action-tile, .service-tile, .product-tile {
    transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
  }
  .action-tile:hover, .service-tile:hover, .product-tile:hover {
    transform: translateY(-2px);
    border-color: #cbded1;
    box-shadow: 0 14px 36px rgba(18,52,38,.07);
  }
  .status-pulse { box-shadow: 0 0 0 5px rgba(85,158,116,.12); }
  .section-kicker { letter-spacing: .14em; }
  .product-photo { aspect-ratio: 4 / 3; }
  @keyframes revealHome {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
  }
  .home-reveal { animation: revealHome .46s cubic-bezier(.22,1,.36,1) both; }
  .home-delay-1 { animation-delay: .06s; }
  .home-delay-2 { animation-delay: .12s; }
  @media (max-width: 639px) {
    .welcome-panel { border-radius: 1.25rem; }
    .garden-plan { min-height: 220px; }
  }
</style>
@endsection

@section('content')
@php
  $firstName = \Illuminate\Support\Str::before(auth()->user()->name, ' ');
  $orderStatus = match($latestOrder?->status) {
    'confirmed' => ['Confirmed', 'bg-blue-50 text-blue-700 border-blue-100'],
    'out_for_delivery' => ['On the way', 'bg-violet-50 text-violet-700 border-violet-100'],
    'delivered' => ['Delivered', 'bg-brand-50 text-brand-700 border-brand-100'],
    'completed' => ['Completed', 'bg-brand-50 text-brand-700 border-brand-100'],
    'cancelled' => ['Cancelled', 'bg-red-50 text-red-700 border-red-100'],
    default => ['Pending', 'bg-amber-50 text-amber-700 border-amber-100'],
  };
  $appointmentStatus = $nextAppointment?->status === 'confirmed'
    ? ['Confirmed', 'bg-brand-50 text-brand-700 border-brand-100']
    : ['Awaiting confirmation', 'bg-amber-50 text-amber-700 border-amber-100'];
@endphp

<main class="customer-page home-shell">
  <section class="welcome-panel rounded-[1.6rem] px-6 py-7 sm:px-9 sm:py-9 lg:px-11 lg:py-10 text-white home-reveal">
    <div class="grid lg:grid-cols-[1.15fr_.85fr] gap-8 lg:gap-12 items-center">
      <div class="max-w-2xl">
        <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[.13em] text-white/75">
          <span class="w-1.5 h-1.5 rounded-full bg-brand-300 status-pulse"></span>
          Your outdoor space, organized
        </div>
        <p class="mt-6 text-sm text-white/62">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ $firstName }}</p>
        <h1 class="mt-2 max-w-xl font-display text-4xl sm:text-5xl lg:text-[3.4rem] leading-[1.05] font-bold tracking-[-.025em]">
          Bring your garden plans to life.
        </h1>
        <p class="mt-5 max-w-xl text-sm sm:text-base leading-7 text-white/68">
          Estimate your project, schedule a service, shop for plants, and follow every update from one calm, simple workspace.
        </p>
        <div class="mt-8 flex flex-col sm:flex-row gap-3">
          <a href="{{ route('schedule') }}" class="customer-action min-h-[48px] bg-white px-5 py-3 text-sm font-bold text-brand-800 hover:bg-brand-50 shadow-lg shadow-black/10">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Book a service
          </a>
          <a href="{{ route('estimator') }}" class="customer-action min-h-[48px] border border-white/20 bg-white/10 px-5 py-3 text-sm font-semibold text-white hover:bg-white/20">
            Build an estimate
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
          </a>
          <a href="ferosa://ar?designId=demo" class="app-only customer-action min-h-[48px] border border-white/20 bg-white/10 px-5 py-3 text-sm font-semibold text-white hover:bg-white/20">
            Open AR visualizer
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7V4h3M17 4h3v3M20 17v3h-3M7 20H4v-3M9 9h6v6H9z"/></svg>
          </a>
        </div>
        <div class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-white/58">
          <span class="inline-flex items-center gap-2">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg>
            Orani, Bataan
          </span>
          <span class="inline-flex items-center gap-2">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
            Estimate before you book
          </span>
        </div>
      </div>

      <div class="garden-plan rounded-[1.35rem] p-5" aria-label="A simple three-step landscape project plan">
        <div class="garden-bed rounded-[40%_60%_58%_42%] left-[10%] top-[13%] w-[42%] h-[34%] rotate-[-8deg]"></div>
        <div class="garden-bed rounded-[55%_45%_35%_65%] right-[9%] bottom-[13%] w-[48%] h-[36%] rotate-[7deg]"></div>
        <span class="garden-dot left-[19%] top-[28%]"></span>
        <span class="garden-dot left-[36%] top-[20%]"></span>
        <span class="garden-dot right-[20%] bottom-[28%]"></span>
        <span class="garden-dot right-[38%] bottom-[18%]"></span>
        <div class="absolute left-5 right-5 bottom-5 rounded-2xl border border-white/15 bg-[#102e22]/80 p-4 backdrop-blur-lg">
          <p class="text-[10px] font-bold uppercase tracking-[.15em] text-brand-200">Your project path</p>
          <div class="mt-3 grid grid-cols-3 gap-2">
            @foreach ([['01','Estimate'],['02','Schedule'],['03','Track']] as [$step, $label])
              <div>
                <p class="font-display text-lg font-bold text-white">{{ $step }}</p>
                <p class="mt-0.5 text-[11px] text-white/58">{{ $label }}</p>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="mt-7 grid grid-cols-2 lg:grid-cols-4 gap-3 home-reveal home-delay-1" aria-label="Account overview">
    <div class="customer-card p-4 sm:p-5">
      <p class="text-[11px] font-bold uppercase tracking-[.12em] text-surface-400">Active orders</p>
      <div class="mt-3 flex items-end justify-between gap-3">
        <p class="font-display text-3xl font-bold text-surface-900">{{ $activityCounts['active_orders'] }}</p>
        <a href="{{ route('orders') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-800">View</a>
      </div>
    </div>
    <div class="customer-card p-4 sm:p-5">
      <p class="text-[11px] font-bold uppercase tracking-[.12em] text-surface-400">Services completed</p>
      <div class="mt-3 flex items-end justify-between gap-3">
        <p class="font-display text-3xl font-bold text-surface-900">{{ $activityCounts['completed_services'] }}</p>
        <a href="{{ route('appointments') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-800">History</a>
      </div>
    </div>
    <div class="customer-card p-4 sm:p-5">
      <p class="text-[11px] font-bold uppercase tracking-[.12em] text-surface-400">Plants available</p>
      <div class="mt-3 flex items-end justify-between gap-3">
        <p class="font-display text-3xl font-bold text-surface-900">{{ $activityCounts['catalog_items'] }}</p>
        <a href="{{ route('shop') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-800">Shop</a>
      </div>
    </div>
    <div class="customer-card p-4 sm:p-5">
      <p class="text-[11px] font-bold uppercase tracking-[.12em] text-surface-400">Need help?</p>
      <div class="mt-3 flex items-end justify-between gap-3">
        <p class="text-base font-bold text-surface-900">Message us</p>
        <a href="{{ route('messages') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-800">Open chat</a>
      </div>
    </div>
  </section>

  <section class="mt-11 home-reveal home-delay-2">
    <div class="flex items-end justify-between gap-4 mb-5">
      <div>
        <p class="section-kicker text-[11px] font-bold uppercase text-brand-600">Start here</p>
        <h2 class="mt-1.5 font-display text-2xl sm:text-3xl font-bold tracking-[-.02em] text-surface-900">What would you like to do?</h2>
      </div>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
      @foreach ([
        ['estimator', 'Estimate a project', 'See a starting cost range before making a commitment.', 'M4 19h16M6 17V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12M9 8h6m-6 4h3', 'bg-rose-50'],
        ['schedule', 'Book a service', 'Choose a landscaping service, available day, and time.', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z', 'bg-brand-50'],
        ['shop', 'Browse the nursery', 'Find plants, materials, and garden essentials in stock.', 'M16 11V7a4 4 0 0 0-8 0v4M5 9h14l1 12H4L5 9Z', 'bg-amber-50'],
        ['messages', 'Ask Ferosa', 'Share questions and project details directly with the team.', 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-5l-5 5v-5Z', 'bg-blue-50'],
      ] as [$routeName, $title, $copy, $path, $tone])
        <a href="{{ route($routeName) }}" class="action-tile group rounded-[1.15rem] border border-surface-200 bg-white p-5 sm:p-6">
          <div class="w-11 h-11 rounded-[.9rem] {{ $tone }} text-brand-700 flex items-center justify-center">
            <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
          </div>
          <h3 class="mt-5 text-[15px] font-bold text-surface-900 group-hover:text-brand-700">{{ $title }}</h3>
          <p class="mt-2 text-[13px] leading-5 text-surface-500">{{ $copy }}</p>
          <span class="mt-5 inline-flex items-center gap-1.5 text-xs font-bold text-brand-600">
            Get started
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m9 18 6-6-6-6"/></svg>
          </span>
        </a>
      @endforeach
    </div>
  </section>

  <section class="mt-12">
    <div class="flex items-end justify-between gap-4 mb-5">
      <div>
        <p class="section-kicker text-[11px] font-bold uppercase text-brand-600">Live activity</p>
        <h2 class="mt-1.5 font-display text-2xl sm:text-3xl font-bold tracking-[-.02em] text-surface-900">Your latest updates</h2>
      </div>
    </div>
    <div class="grid lg:grid-cols-2 gap-5">
      <article class="customer-card p-5 sm:p-6">
        <div class="flex items-start justify-between gap-4">
          <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center">
              <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z"/></svg>
            </div>
            <div>
              <p class="text-[11px] font-bold uppercase tracking-[.12em] text-surface-400">Next appointment</p>
              <h3 class="mt-0.5 text-base font-bold text-surface-900">{{ $nextAppointment?->serviceType?->name ?? 'No upcoming service' }}</h3>
            </div>
          </div>
          @if($nextAppointment)
            <span class="rounded-full border px-2.5 py-1 text-[10px] font-bold {{ $appointmentStatus[1] }}">{{ $appointmentStatus[0] }}</span>
          @endif
        </div>
        @if($nextAppointment)
          <div class="mt-6 grid grid-cols-2 gap-3 rounded-xl bg-surface-50 p-4">
            <div>
              <p class="text-[10px] font-semibold uppercase tracking-wider text-surface-400">Date</p>
              <p class="mt-1 text-sm font-bold text-surface-800">{{ $nextAppointment->appointment_at->format('M j, Y') }}</p>
            </div>
            <div>
              <p class="text-[10px] font-semibold uppercase tracking-wider text-surface-400">Time</p>
              <p class="mt-1 text-sm font-bold text-surface-800">{{ $nextAppointment->appointment_at->format('g:i A') }}</p>
            </div>
          </div>
          <a href="{{ route('appointments') }}" class="mt-5 inline-flex items-center gap-2 text-xs font-bold text-brand-600 hover:text-brand-800">Manage appointment <span aria-hidden="true">&rarr;</span></a>
        @else
          <p class="mt-5 text-sm leading-6 text-surface-500">When you book a service, the confirmed date and time will appear here.</p>
          <a href="{{ route('schedule') }}" class="mt-5 customer-action border border-brand-200 bg-brand-50 px-4 py-2.5 text-xs font-bold text-brand-700 hover:bg-brand-100">Schedule a service</a>
        @endif
      </article>

      <article class="customer-card p-5 sm:p-6">
        <div class="flex items-start justify-between gap-4">
          <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center">
              <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 0 0-8 0v4M5 9h14l1 12H4L5 9Z"/></svg>
            </div>
            <div>
              <p class="text-[11px] font-bold uppercase tracking-[.12em] text-surface-400">Latest order</p>
              <h3 class="mt-0.5 text-base font-bold text-surface-900">{{ $latestOrder?->order_number ?? 'No orders yet' }}</h3>
            </div>
          </div>
          @if($latestOrder)
            <span class="rounded-full border px-2.5 py-1 text-[10px] font-bold {{ $orderStatus[1] }}">{{ $orderStatus[0] }}</span>
          @endif
        </div>
        @if($latestOrder)
          <div class="mt-6 grid grid-cols-2 gap-3 rounded-xl bg-surface-50 p-4">
            <div>
              <p class="text-[10px] font-semibold uppercase tracking-wider text-surface-400">Items</p>
              <p class="mt-1 text-sm font-bold text-surface-800">{{ count($latestOrder->items ?? []) }} item{{ count($latestOrder->items ?? []) === 1 ? '' : 's' }}</p>
            </div>
            <div>
              <p class="text-[10px] font-semibold uppercase tracking-wider text-surface-400">Total</p>
              <p class="mt-1 text-sm font-bold text-surface-800">PHP {{ number_format((float) $latestOrder->total_amount, 2) }}</p>
            </div>
          </div>
          <a href="{{ route('orders') }}" class="mt-5 inline-flex items-center gap-2 text-xs font-bold text-brand-600 hover:text-brand-800">View order activity <span aria-hidden="true">&rarr;</span></a>
        @else
          <p class="mt-5 text-sm leading-6 text-surface-500">Your purchases and delivery updates will stay organized here.</p>
          <a href="{{ route('shop') }}" class="mt-5 customer-action border border-brand-200 bg-brand-50 px-4 py-2.5 text-xs font-bold text-brand-700 hover:bg-brand-100">Browse the shop</a>
        @endif
      </article>
    </div>
  </section>

  @if($featuredServices->isNotEmpty())
    <section class="mt-12">
      <div class="flex items-end justify-between gap-4 mb-5">
        <div>
          <p class="section-kicker text-[11px] font-bold uppercase text-brand-600">Services</p>
          <h2 class="mt-1.5 font-display text-2xl sm:text-3xl font-bold tracking-[-.02em] text-surface-900">Plan your next visit</h2>
        </div>
        <a href="{{ route('schedule') }}" class="hidden sm:inline-flex text-xs font-bold text-brand-600 hover:text-brand-800">See availability &rarr;</a>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($featuredServices as $service)
          <a href="{{ route('schedule', ['service' => $service->id]) }}" class="service-tile rounded-[1.15rem] border border-surface-200 bg-white p-5">
            <div class="flex items-center justify-between gap-3">
              <span class="w-9 h-9 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center font-display font-bold">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
              <span class="text-[10px] font-bold uppercase tracking-wider text-surface-400">Available</span>
            </div>
            <h3 class="mt-5 text-[15px] font-bold text-surface-900">{{ $service->name }}</h3>
            <p class="mt-2 text-[13px] leading-5 text-surface-500">Professional on-site care tailored to your space.</p>
            <p class="mt-5 text-xs font-bold text-brand-700">From PHP {{ number_format((float) $service->default_fee, 0) }}</p>
          </a>
        @endforeach
      </div>
    </section>
  @endif

  @if($featuredProducts->isNotEmpty())
    <section class="mt-12">
      <div class="flex items-end justify-between gap-4 mb-5">
        <div>
          <p class="section-kicker text-[11px] font-bold uppercase text-brand-600">From the nursery</p>
          <h2 class="mt-1.5 font-display text-2xl sm:text-3xl font-bold tracking-[-.02em] text-surface-900">Fresh additions</h2>
        </div>
        <a href="{{ route('shop') }}" class="text-xs font-bold text-brand-600 hover:text-brand-800">Browse all &rarr;</a>
      </div>
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        @foreach($featuredProducts as $product)
          <a href="{{ route('products.show', $product) }}" class="product-tile overflow-hidden rounded-[1.15rem] border border-surface-200 bg-white group">
            <div class="product-photo overflow-hidden bg-brand-50">
              @if($product->image_url)
                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.035]" loading="lazy">
              @else
                <div class="h-full w-full flex items-center justify-center text-brand-300">
                  <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21V11m0 0C12 6 7 4 4 7m8 4c0-5 5-7 8-4M7 21h10"/></svg>
                </div>
              @endif
            </div>
            <div class="p-4">
              <p class="text-[10px] font-bold uppercase tracking-wider text-brand-600">{{ $product->category ?: 'Garden' }}</p>
              <h3 class="mt-1.5 text-sm font-bold text-surface-900 line-clamp-1">{{ $product->name }}</h3>
              <p class="mt-2 text-xs font-bold text-surface-700">PHP {{ number_format((float) $product->price, 2) }}</p>
            </div>
          </a>
        @endforeach
      </div>
    </section>
  @endif

  @if($featuredProjects->isNotEmpty())
    <section class="mt-12">
      <div class="mb-5 flex items-end justify-between gap-4">
        <div>
          <p class="section-kicker text-[11px] font-bold uppercase text-brand-600">Completed work</p>
          <h2 class="mt-1.5 font-display text-2xl font-bold tracking-[-.02em] text-surface-900 sm:text-3xl">See what Ferosa has brought to life</h2>
        </div>
        <a href="{{ route('projects.index') }}" class="text-xs font-bold text-brand-600 hover:text-brand-800">View all projects &rarr;</a>
      </div>
      <div class="grid gap-4 md:grid-cols-3">
        @foreach($featuredProjects as $project)
          <a href="{{ route('projects.show', $project) }}" class="group overflow-hidden rounded-[1.15rem] border border-surface-200 bg-white transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="aspect-[4/3] overflow-hidden bg-brand-50">
              @if($project->cover_image_url)
                <img src="{{ $project->cover_image_url }}" alt="{{ $project->title }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.035]" loading="lazy">
              @else
                <div class="flex h-full items-center justify-center text-brand-300"><svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M4 20h16M6 20V9l6-5 6 5v11M9 20v-6h6v6"/></svg></div>
              @endif
            </div>
            <div class="p-5"><p class="text-[10px] font-bold uppercase tracking-wider text-brand-600">{{ $project->service_name ?: 'Ferosa project' }}</p><h3 class="mt-2 font-display text-lg font-bold text-surface-950">{{ $project->title }}</h3><p class="mt-2 line-clamp-2 text-sm leading-6 text-surface-500">{{ $project->summary }}</p></div>
          </a>
        @endforeach
      </div>
    </section>
  @endif

  <section class="mt-12 grid gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="Business details">
    @foreach([
      ['Service area', $businessProfile['service_area']],
      ['Visit address', $businessProfile['business_address']],
      ['Business hours', $businessProfile['business_hours']],
      ['Booking notice', $businessProfile['booking_notice']],
    ] as [$label, $value])
      @if($value)
        <div class="rounded-2xl border border-surface-200 bg-white p-4"><p class="text-[10px] font-bold uppercase tracking-wider text-surface-400">{{ $label }}</p><p class="mt-1.5 text-sm font-bold leading-5 text-surface-800">{{ $value }}</p></div>
      @endif
    @endforeach
  </section>

  <section class="mt-12 mb-3 rounded-[1.4rem] border border-brand-100 bg-brand-50 px-5 py-6 sm:px-7 sm:py-7">
    <div class="grid md:grid-cols-[1fr_auto] gap-6 items-center">
      <div>
        <p class="section-kicker text-[11px] font-bold uppercase text-brand-600">Clear from the start</p>
        <h2 class="mt-2 font-display text-2xl font-bold text-brand-950">A simpler way to plan landscaping work.</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-brand-800/75">Use the estimator for a starting range, schedule when you are ready, and keep messages, appointments, and order updates together.</p>
      </div>
      <a href="{{ route('estimator') }}" class="customer-action min-h-[46px] bg-brand-700 px-5 py-3 text-sm font-bold text-white hover:bg-brand-800">Try the estimator</a>
    </div>
  </section>
</main>

@include('partials.mobile-bottom-customer')
@endsection
