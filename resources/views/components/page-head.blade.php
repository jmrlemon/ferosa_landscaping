@props([
  'kicker' => null,
  'title' => '',
  'sub' => null,
])

<div {{ $attributes->merge(['class' => 'page-head reveal']) }}>
  <div class="page-head-row flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="page-head-main min-w-0">
      <div class="min-w-0">
        @if($kicker)
          <p class="page-kicker">{{ $kicker }}</p>
        @endif
        <h1 class="page-title">{{ $title }}</h1>
        @if($sub)
          <p class="page-sub">{{ $sub }}</p>
        @endif
      </div>
      {{-- Optional icon tile, mirroring the estimator screen's header. Pages
           pass raw SVG through <x-slot:icon>; it is decorative, so the slot
           markup carries aria-hidden rather than a label. --}}
      @isset($icon)
        <div class="page-head-icon" aria-hidden="true">{{ $icon }}</div>
      @endisset
    </div>
    @if(trim($slot))
      <div class="flex flex-shrink-0 flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
  </div>
</div>
