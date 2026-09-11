@extends('layouts.customer')

@section('title', $project->title.' - Ferosa Projects')

@section('content')
<main class="customer-page">
  <a href="{{ route('projects.index') }}" class="mb-5 inline-flex items-center gap-2 text-xs font-bold text-brand-700">&larr; Completed projects</a>

  <section class="grid gap-7 lg:grid-cols-[1fr_360px] lg:items-end">
    <div>
      <div class="flex flex-wrap gap-2 text-[10px] font-bold uppercase tracking-[.14em] text-brand-650">
        @if($project->service_name)<span>{{ $project->service_name }}</span>@endif
        @if($project->completed_at)<span class="text-surface-400">Completed {{ $project->completed_at->format('F Y') }}</span>@endif
      </div>
      <h1 class="mt-3 font-display text-3xl font-bold tracking-[-.025em] text-surface-950 sm:text-5xl">{{ $project->title }}</h1>
      <p class="mt-4 max-w-3xl whitespace-pre-line text-[15px] leading-7 text-surface-600">{{ $project->summary }}</p>
    </div>
    <div class="grid grid-cols-2 gap-3 rounded-2xl border border-surface-200 bg-white p-5">
      <div><p class="text-[10px] font-bold uppercase tracking-wider text-surface-400">Location</p><p class="mt-1 text-sm font-bold text-surface-900">{{ $project->location ?: 'Not listed' }}</p></div>
      <div><p class="text-[10px] font-bold uppercase tracking-wider text-surface-400">Duration</p><p class="mt-1 text-sm font-bold text-surface-900">{{ $project->duration_label ?: 'Not listed' }}</p></div>
    </div>
  </section>

  <section class="mt-8 overflow-hidden rounded-[1.5rem] border border-surface-200 bg-brand-50">
    @if($project->cover_image_url)
      <img src="{{ $project->cover_image_url }}" alt="Completed view of {{ $project->title }}" class="max-h-[680px] w-full object-cover">
    @else
      <div class="flex min-h-[360px] items-center justify-center text-brand-300"><svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.1"><path d="M4 20h16M6 20V9l6-5 6 5v11M9 20v-6h6v6"/></svg></div>
    @endif
  </section>

  @if($project->before_image_url || $project->after_image_url)
    <section class="mt-10">
      <p class="text-[11px] font-bold uppercase tracking-[.14em] text-brand-600">Transformation</p>
      <h2 class="mt-2 font-display text-2xl font-bold text-surface-950">Before and after</h2>
      <div class="mt-5 grid gap-5 md:grid-cols-2">
        @foreach([['Before', $project->before_image_url], ['After', $project->after_image_url]] as [$label, $image])
          @if($image)
            <figure class="overflow-hidden rounded-2xl border border-surface-200 bg-white"><img src="{{ $image }}" alt="{{ $label }} {{ $project->title }}" loading="lazy" decoding="async" class="aspect-[4/3] w-full object-cover"><figcaption class="px-4 py-3 text-xs font-bold text-surface-600">{{ $label }}</figcaption></figure>
          @endif
        @endforeach
      </div>
    </section>
  @endif

  @if($project->client_quote)
    <figure class="mt-10 rounded-[1.4rem] border border-brand-100 bg-brand-50 p-6 sm:p-8">
      @if($project->rating)<p class="text-sm tracking-[.2em] text-amber-500" aria-label="{{ $project->rating }} out of 5 stars">{{ str_repeat('★', $project->rating) }}</p>@endif
      <blockquote class="mt-3 max-w-4xl font-display text-xl font-bold leading-8 text-brand-950">&ldquo;{{ $project->client_quote }}&rdquo;</blockquote>
      <figcaption class="mt-4 text-xs font-bold uppercase tracking-wider text-brand-700">Project client feedback</figcaption>
    </figure>
  @endif

  <section class="mt-10 rounded-[1.4rem] bg-brand-950 px-6 py-7 text-white sm:px-8">
    <div class="grid gap-6 md:grid-cols-[1fr_auto] md:items-center">
      <div><p class="text-[10px] font-bold uppercase tracking-[.16em] text-brand-200">Your space can start with a conversation</p><h2 class="mt-2 font-display text-2xl font-bold">Plan a practical next step with Ferosa.</h2><p class="mt-2 text-sm text-brand-100/70">{{ $businessProfile['booking_notice'] }}</p></div>
      <div class="flex flex-wrap gap-3"><a href="{{ route('messages') }}" class="rounded-xl border border-white/20 px-4 py-3 text-xs font-bold">Ask a question</a><a href="{{ route('schedule') }}" class="rounded-xl bg-white px-4 py-3 text-xs font-bold text-brand-950">Book a visit</a></div>
    </div>
  </section>

  @if($relatedProjects->isNotEmpty())
    <section class="mt-12"><div class="mb-5 flex items-end justify-between"><h2 class="font-display text-2xl font-bold text-surface-950">Similar work</h2><a href="{{ route('projects.index') }}" class="text-xs font-bold text-brand-700">View all &rarr;</a></div><div class="grid gap-4 md:grid-cols-3">@foreach($relatedProjects as $related)<a href="{{ route('projects.show', $related) }}" class="rounded-2xl border border-surface-200 bg-white p-5"><p class="text-[10px] font-bold uppercase tracking-wider text-brand-600">{{ $related->service_name }}</p><h3 class="mt-2 font-display text-lg font-bold text-surface-900">{{ $related->title }}</h3><p class="mt-2 line-clamp-2 text-sm text-surface-500">{{ $related->summary }}</p></a>@endforeach</div></section>
  @endif
</main>
@include('partials.mobile-bottom-customer')
@endsection
