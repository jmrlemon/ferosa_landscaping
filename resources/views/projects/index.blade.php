@extends('layouts.customer')

@section('title', 'Completed Projects - Ferosa Landscaping')

@section('content')
<main class="customer-page">
  <section class="overflow-hidden rounded-[1.6rem] bg-brand-950 px-6 py-9 text-white sm:px-9 sm:py-12">
    <div class="grid gap-8 lg:grid-cols-[1fr_320px] lg:items-end">
      <div>
        <p class="text-[11px] font-bold uppercase tracking-[.18em] text-brand-200">Verified work by Ferosa</p>
        <h1 class="mt-3 max-w-3xl font-display text-3xl font-bold tracking-[-.025em] sm:text-5xl">Landscapes planned around real homes and real needs.</h1>
        <p class="mt-4 max-w-2xl text-sm leading-7 text-brand-100/75">Explore projects the Ferosa team has chosen to publish. Each entry is managed from the staff workspace so the details and images stay authentic.</p>
      </div>
      <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
        <p class="text-[10px] font-bold uppercase tracking-wider text-brand-200">Serving</p>
        <p class="mt-2 text-lg font-bold">{{ $businessProfile['service_area'] ?: 'Orani, Bataan' }}</p>
        <a href="{{ route('schedule') }}" class="mt-4 inline-flex min-h-[42px] items-center rounded-xl bg-white px-4 py-2 text-xs font-bold text-brand-900">Plan a consultation</a>
      </div>
    </div>
  </section>

  @if($services->isNotEmpty())
    <form action="{{ route('projects.index') }}" method="GET" class="mt-6 flex flex-wrap items-center gap-2" aria-label="Filter projects">
      <a href="{{ route('projects.index') }}" class="chip {{ $selectedService === '' ? 'chip-active' : '' }}">All work</a>
      @foreach($services as $service)
        <a href="{{ route('projects.index', ['service' => $service]) }}" class="chip {{ $selectedService === $service ? 'chip-active' : '' }}">{{ $service }}</a>
      @endforeach
    </form>
  @endif

  @if($projects->isEmpty())
    <section class="customer-empty mt-8">
      <div class="customer-empty-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 20h16M6 20V9l6-5 6 5v11M9 20v-6h6v6"/></svg></div>
      <h2 class="text-base font-bold text-surface-900">Verified case studies are being prepared</h2>
      <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-surface-500">Ferosa has not published a matching project yet. Ask the team for examples related to your space or start with a consultation.</p>
      <div class="mt-5 flex flex-wrap justify-center gap-3">
        <a href="{{ route('messages') }}" class="btn btn-secondary btn-sm">Ask the team</a>
        <a href="{{ route('schedule') }}" class="btn btn-primary btn-sm">Book a visit</a>
      </div>
    </section>
  @else
    <section class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
      @foreach($projects as $project)
        <a href="{{ route('projects.show', $project) }}" class="group overflow-hidden rounded-[1.25rem] border border-surface-200 bg-white transition duration-200 hover:-translate-y-1 hover:shadow-lg">
          <div class="aspect-[4/3] overflow-hidden bg-brand-50">
            @if($project->cover_image_url)
              <img src="{{ $project->cover_image_url }}" alt="{{ $project->title }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.035]" loading="lazy">
            @else
              <div class="flex h-full items-center justify-center text-brand-300"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M4 20h16M6 20V9l6-5 6 5v11M9 20v-6h6v6"/></svg></div>
            @endif
          </div>
          <div class="p-5">
            <div class="flex flex-wrap gap-2 text-[10px] font-bold uppercase tracking-wider text-brand-650">
              @if($project->service_name)<span>{{ $project->service_name }}</span>@endif
              @if($project->location)<span class="text-surface-400">&middot; {{ $project->location }}</span>@endif
            </div>
            <h2 class="mt-2 font-display text-xl font-bold text-surface-950">{{ $project->title }}</h2>
            <p class="mt-2 line-clamp-2 text-sm leading-6 text-surface-500">{{ $project->summary }}</p>
            <p class="mt-4 text-xs font-bold text-brand-700">View project story &rarr;</p>
          </div>
        </a>
      @endforeach
    </section>
    <div class="mt-8">{{ $projects->links() }}</div>
  @endif
</main>
@include('partials.mobile-bottom-customer')
@endsection
