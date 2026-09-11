@extends('admin.layouts.workspace')

@section('title', 'Stock History - Ferosa Admin')
@section('admin-section', 'products')
@section('skip-label', 'Skip to stock history')
@section('header-eyebrow', 'Inventory')
@section('header-title', 'Stock History')

@php
  use App\Models\StockMovement;

  $typeTone = fn (string $type) => match ($type) {
      StockMovement::TYPE_RESTOCK, StockMovement::TYPE_OPENING => 'bg-brand-50 text-brand-700 border-brand-100',
      StockMovement::TYPE_SALE => 'bg-blue-50 text-blue-700 border-blue-100',
      StockMovement::TYPE_RETURN => 'bg-indigo-50 text-indigo-700 border-indigo-100',
      StockMovement::TYPE_WASTAGE => 'bg-red-50 text-red-700 border-red-100',
      default => 'bg-amber-50 text-amber-700 border-amber-100',
  };

  $selectedProduct = filled($filters['product_id'] ?? null)
      ? $products->firstWhere('id', (int) $filters['product_id'])
      : null;
  $backUrl = $selectedProduct
      ? route('admin.products.edit', $selectedProduct)
      : route('admin.dashboard', ['tab' => 'products']);
@endphp

@section('content')
  @if(session('status'))
    <div class="mb-5 rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm font-semibold text-brand-800">{{ session('status') }}</div>
  @endif

  <div class="mb-6 flex items-start gap-4">
    <a href="{{ $backUrl }}"
       class="mt-0.5 inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg border border-surface-300 bg-white text-surface-600 transition-colors hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700"
       aria-label="{{ $selectedProduct ? 'Back to '.$selectedProduct->name : 'Back to inventory' }}">
      <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
      </svg>
    </a>
    <div>
      <p class="text-[10px] font-bold uppercase tracking-[.16em] text-brand-600">Inventory</p>
      <h2 class="mt-2 text-3xl text-brand-950">Every change to stock, and why it happened.</h2>
      <p class="mt-2 max-w-2xl text-sm leading-6 text-surface-500">
        Sales and cancellation returns are recorded automatically by the order flow.
        To record a delivery, wastage or a count correction, open the product and use its Stock panel.
      </p>
    </div>
  </div>

  <div class="grid gap-6 xl:grid-cols-[1fr_300px]">
    <section class="rounded-xl border border-surface-200 bg-white">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-surface-100 p-4 sm:p-5">
        <h3 class="font-bold text-surface-900">Movement history</h3>
        <form method="GET" action="{{ route('admin.inventory.index') }}" class="flex flex-wrap items-center gap-2">
          <select name="product_id" class="h-11 rounded-lg border border-surface-200 px-2.5 text-xs outline-none focus:border-brand-500">
            <option value="">All products</option>
            @foreach($products as $option)
              <option value="{{ $option->id }}" @selected(($filters['product_id'] ?? null) == $option->id)>{{ $option->name }}</option>
            @endforeach
          </select>
          <select name="type" class="h-11 rounded-lg border border-surface-200 px-2.5 text-xs outline-none focus:border-brand-500">
            <option value="">All types</option>
            @foreach([StockMovement::TYPE_OPENING, StockMovement::TYPE_RESTOCK, StockMovement::TYPE_SALE, StockMovement::TYPE_RETURN, StockMovement::TYPE_WASTAGE, StockMovement::TYPE_CORRECTION] as $type)
              <option value="{{ $type }}" @selected(($filters['type'] ?? null) === $type)>{{ ucfirst($type) }}</option>
            @endforeach
          </select>
          <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-surface-900 px-4 text-xs font-semibold text-white transition-colors hover:bg-surface-800">Filter</button>
          @if(($filters['type'] ?? null) || ($filters['product_id'] ?? null))
            <a href="{{ route('admin.inventory.index') }}" class="inline-flex h-11 items-center justify-center rounded-lg border border-surface-200 bg-white px-3 text-xs font-semibold text-surface-600 transition-colors hover:bg-surface-50">Clear</a>
          @endif
        </form>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="border-b border-surface-100 text-[10px] uppercase tracking-wider text-surface-400">
            <tr>
              <th class="px-4 py-2.5 font-semibold">Date</th>
              <th class="px-4 py-2.5 font-semibold">Product</th>
              <th class="px-4 py-2.5 font-semibold">Type</th>
              <th class="px-4 py-2.5 text-right font-semibold">Change</th>
              <th class="px-4 py-2.5 text-right font-semibold">After</th>
              <th class="px-4 py-2.5 font-semibold">Detail</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-surface-50">
            @forelse($movements as $movement)
              <tr class="hover:bg-surface-50/60">
                <td class="whitespace-nowrap px-4 py-3 text-surface-500">{{ optional($movement->created_at)->format('M d, Y') }}</td>
                <td class="px-4 py-3">
                  <a href="{{ route('admin.products.edit', $movement->product_id) }}" class="font-semibold text-surface-900 hover:text-brand-700">
                    {{ $movement->product->name ?? 'Deleted product' }}
                  </a>
                </td>
                <td class="px-4 py-3">
                  <span class="rounded border px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $typeTone($movement->type) }}">
                    {{ $movement->typeLabel() }}
                  </span>
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-right font-bold tabular-nums {{ $movement->quantity < 0 ? 'text-red-600' : 'text-brand-700' }}">
                  {{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-surface-700">{{ $movement->quantity_after }}</td>
                <td class="px-4 py-3 text-surface-500">
                  @if($movement->supplier)<div class="font-medium text-surface-700">{{ $movement->supplier }}</div>@endif
                  @if($movement->unit_cost !== null)
                    <div>&#8369;{{ number_format((float) $movement->unit_cost, 2) }} each · total &#8369;{{ number_format((float) $movement->totalCost(), 2) }}</div>
                  @endif
                  @if($movement->reference)<div class="font-mono text-[11px]">{{ $movement->reference }}</div>@endif
                  @if($movement->note)<div class="max-w-xs">{{ $movement->note }}</div>@endif
                  @if($movement->user)<div class="text-[11px] text-surface-400">by {{ $movement->user->name }}</div>@endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="px-4 py-10 text-center text-surface-400">No stock movements recorded yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($movements->hasPages())
        <div class="border-t border-surface-100 p-4">{{ $movements->links() }}</div>
      @endif
    </section>

    <section class="h-fit rounded-xl border border-amber-200 bg-amber-50/50 p-5">
      <h3 class="font-bold text-amber-900">Low stock</h3>
      @if($lowStock->isEmpty())
        <p class="mt-2 text-xs text-amber-800">Nothing is running low.</p>
      @else
        <ul class="mt-3 space-y-1.5 text-xs">
          @foreach($lowStock as $item)
            <li class="flex items-center justify-between gap-3">
              <a href="{{ route('admin.products.edit', $item) }}" class="font-medium text-amber-900 hover:underline">{{ $item->name }}</a>
              <span class="font-bold text-amber-700">{{ $item->stock_qty }} left</span>
            </li>
          @endforeach
        </ul>
      @endif
    </section>
  </div>
@endsection
