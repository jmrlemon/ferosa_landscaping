{{--
  Ferosa pagination.

  Replaces Laravel's stock `tailwind` view, whose `dark:` variants rendered
  slate-black page buttons for anyone whose OS is in dark mode while the rest
  of the UI stayed light. This view has no dark variants and uses the same
  surface/brand tokens as the admin workspace and the storefront.
--}}
@php
  $link = 'inline-flex h-9 min-w-9 items-center justify-center border-r border-surface-200 px-3 text-sm font-medium transition-colors last:border-r-0';
  // Also used as the simple-paginator view, which passes no $elements and
  // cannot count its total.
  $elements = $elements ?? [];
  $hasTotal = $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
@endphp

@if ($paginator->hasPages())
  <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}"
       class="flex flex-wrap items-center justify-between gap-3">

    @if ($hasTotal)
      <p class="hidden text-xs text-surface-500 sm:block">
        Showing
        <span class="font-semibold text-surface-700">{{ $paginator->firstItem() ?? 0 }}</span>–<span class="font-semibold text-surface-700">{{ $paginator->lastItem() ?? 0 }}</span>
        of <span class="font-semibold text-surface-700">{{ $paginator->total() }}</span>
      </p>
    @endif

    <div class="ml-auto flex overflow-hidden rounded-lg border border-surface-200 bg-white">
      {{-- Previous --}}
      @if ($paginator->onFirstPage())
        <span aria-disabled="true" aria-label="Previous page"
              class="{{ $link }} cursor-not-allowed text-surface-350">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15 19-7-7 7-7"/></svg>
        </span>
      @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page"
           class="{{ $link }} text-surface-500 hover:bg-surface-50 hover:text-brand-700">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15 19-7-7 7-7"/></svg>
        </a>
      @endif

      {{-- Page numbers: hidden on the narrowest screens, where the arrows and
           the current-page marker are enough. --}}
      @foreach ($elements as $element)
        @if (is_string($element))
          <span aria-disabled="true" class="{{ $link }} hidden text-surface-400 sm:inline-flex">{{ $element }}</span>
        @endif

        @if (is_array($element))
          @foreach ($element as $page => $url)
            @if ($page == $paginator->currentPage())
              <span aria-current="page" class="{{ $link }} bg-brand-700 font-bold text-white">{{ $page }}</span>
            @else
              <a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                 class="{{ $link }} hidden text-surface-600 hover:bg-surface-50 hover:text-brand-700 sm:inline-flex">{{ $page }}</a>
            @endif
          @endforeach
        @endif
      @endforeach

      {{-- Next --}}
      @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page"
           class="{{ $link }} text-surface-500 hover:bg-surface-50 hover:text-brand-700">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
        </a>
      @else
        <span aria-disabled="true" aria-label="Next page"
              class="{{ $link }} cursor-not-allowed text-surface-350">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
        </span>
      @endif
    </div>
  </nav>
@endif
