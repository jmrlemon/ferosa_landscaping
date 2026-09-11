@php
  use App\Models\StockMovement;

  /**
   * Stock level, movement entry forms and recent history for one product.
   *
   * Expects: $product and, unless history is hidden, $movements
   *          (Collection|Paginator). Optional: $showAllLink and $hideHistory.
   */
  $showAllLink = $showAllLink ?? false;
  $hideHistory = $hideHistory ?? false;

  $typeTone = fn (string $type) => match ($type) {
      StockMovement::TYPE_RESTOCK, StockMovement::TYPE_OPENING => 'bg-brand-50 text-brand-700 border-brand-100',
      StockMovement::TYPE_SALE => 'bg-blue-50 text-blue-700 border-blue-100',
      StockMovement::TYPE_RETURN => 'bg-indigo-50 text-indigo-700 border-indigo-100',
      StockMovement::TYPE_WASTAGE => 'bg-red-50 text-red-700 border-red-100',
      default => 'bg-amber-50 text-amber-700 border-amber-100',
  };
@endphp

<section class="overflow-hidden rounded-xl border border-surface-100 bg-white shadow-sm">
  <div class="flex flex-wrap items-center justify-between gap-3 border-b border-surface-200 px-5 py-4">
    <div>
      <h3 class="font-semibold text-surface-900">Stock</h3>
      <p class="text-xs text-surface-500">Every change is recorded with a reason.</p>
    </div>
    <div class="flex items-center gap-3">
      @if($hideHistory && $showAllLink)
        <a href="{{ route('admin.inventory.index', ['product_id' => $product->id]) }}"
           class="rounded-lg border border-surface-200 px-3 py-2 text-xs font-semibold text-surface-600 transition-colors hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700">
          View history
        </a>
      @endif
      <div class="text-right">
        <p class="text-[10px] font-bold uppercase tracking-wider text-surface-400">On hand</p>
        <p class="font-display text-2xl font-bold {{ $product->stock_qty <= 5 ? 'text-amber-600' : 'text-surface-900' }}">{{ $product->stock_qty }}</p>
      </div>
    </div>
  </div>

  <div class="grid gap-5 p-5 lg:grid-cols-2">
    {{-- Restock: the quantity genuinely is the change, so it is entered as one. --}}
    <form method="POST" action="{{ route('admin.inventory.restock', $product) }}" class="space-y-3 rounded-lg border border-surface-200 p-4">
      @csrf
      <div>
        <p class="text-sm font-semibold text-surface-900">Restock</p>
        <p class="text-xs text-surface-500">Stock arriving from a supplier.</p>
      </div>
      <label class="block text-xs font-semibold text-surface-700">Quantity received *
        <input type="number" name="quantity" min="1" required
               class="mt-1.5 h-10 w-full rounded-lg border border-surface-200 px-3 text-base font-normal outline-none focus:border-brand-500">
      </label>
      <div class="grid gap-3 sm:grid-cols-2">
        <label class="block text-xs font-semibold text-surface-700">Supplier
          <input type="text" name="supplier" maxlength="255" placeholder="e.g. Bataan Supply"
                 class="mt-1.5 h-10 w-full rounded-lg border border-surface-200 px-3 text-base font-normal outline-none focus:border-brand-500">
        </label>
        <label class="block text-xs font-semibold text-surface-700">Unit cost
          <input type="number" name="unit_cost" step="0.01" min="0" placeholder="0.00"
                 class="mt-1.5 h-10 w-full rounded-lg border border-surface-200 px-3 text-base font-normal outline-none focus:border-brand-500">
        </label>
      </div>
      <label class="block text-xs font-semibold text-surface-700">Note
        <input type="text" name="note" maxlength="1000"
               class="mt-1.5 h-10 w-full rounded-lg border border-surface-200 px-3 text-base font-normal outline-none focus:border-brand-500">
      </label>
      <button type="submit" class="w-full rounded-lg bg-brand-700 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">Record restock</button>
    </form>

    {{-- Wastage takes a quantity lost; a correction takes the level counted. --}}
    <form method="POST" action="{{ route('admin.inventory.adjust', $product) }}" class="space-y-3 rounded-lg border border-surface-200 p-4" data-adjust-form>
      @csrf
      <div>
        <p class="text-sm font-semibold text-surface-900">Wastage or correction</p>
        <p class="text-xs text-surface-500">Stock written off, or a level fixed after counting.</p>
      </div>
      <label class="block text-xs font-semibold text-surface-700">Reason *
        <select name="type" required data-adjust-type
                class="mt-1.5 h-10 w-full rounded-lg border border-surface-200 px-3 text-base font-normal outline-none focus:border-brand-500">
          <option value="{{ StockMovement::TYPE_WASTAGE }}">Wastage — spoiled, damaged, lost</option>
          <option value="{{ StockMovement::TYPE_CORRECTION }}">Correction — after a physical count</option>
        </select>
      </label>
      <label class="block text-xs font-semibold text-surface-700">
        <span data-adjust-label>Quantity lost *</span>
        <input type="number" name="quantity" min="0" required value="{{ old('quantity') }}"
               data-adjust-input data-stock="{{ (int) $product->stock_qty }}"
               class="mt-1.5 h-10 w-full rounded-lg border border-surface-200 px-3 text-base font-normal outline-none focus:border-brand-500">
        <span class="mt-1 block text-xs font-normal text-surface-400" data-adjust-hint>How many units were lost. Stock is {{ $product->stock_qty }}.</span>
      </label>
      <label class="block text-xs font-semibold text-surface-700">Explanation *
        <textarea name="note" rows="2" required maxlength="1000" placeholder="e.g. spoiled after heavy rain"
                  class="mt-1.5 w-full rounded-lg border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500">{{ old('note') }}</textarea>
      </label>
      <button type="submit" class="w-full rounded-lg bg-surface-900 py-2.5 text-sm font-semibold text-white hover:bg-surface-800">Record adjustment</button>
    </form>
  </div>

  @unless($hideHistory)
  <div class="border-t border-surface-100">
    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
      <p class="text-sm font-semibold text-surface-900">Movement history</p>
      @if($showAllLink)
        <a href="{{ route('admin.inventory.index', ['product_id' => $product->id]) }}" class="text-xs font-semibold text-brand-700 hover:underline">Full history &rarr;</a>
      @endif
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead class="border-y border-surface-100 bg-surface-50 text-[10px] uppercase tracking-wider text-surface-400">
          <tr>
            <th class="px-5 py-2 font-semibold">Date</th>
            <th class="px-3 py-2 font-semibold">Type</th>
            <th class="px-3 py-2 text-right font-semibold">Change</th>
            <th class="px-3 py-2 text-right font-semibold">After</th>
            <th class="px-5 py-2 font-semibold">Detail</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-surface-50">
          @forelse($movements as $movement)
            <tr>
              <td class="whitespace-nowrap px-5 py-2.5 text-surface-500">{{ optional($movement->created_at)->format('M d, Y') }}</td>
              <td class="px-3 py-2.5">
                <span class="rounded border px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $typeTone($movement->type) }}">{{ $movement->typeLabel() }}</span>
              </td>
              <td class="whitespace-nowrap px-3 py-2.5 text-right font-bold tabular-nums {{ $movement->quantity < 0 ? 'text-red-600' : 'text-brand-700' }}">
                {{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}
              </td>
              <td class="whitespace-nowrap px-3 py-2.5 text-right font-semibold tabular-nums text-surface-700">{{ $movement->quantity_after }}</td>
              <td class="px-5 py-2.5 text-surface-500">
                @if($movement->supplier)<div class="font-medium text-surface-700">{{ $movement->supplier }}</div>@endif
                @if($movement->unit_cost !== null)
                  <div>&#8369;{{ number_format((float) $movement->unit_cost, 2) }} each · total &#8369;{{ number_format((float) $movement->totalCost(), 2) }}</div>
                @endif
                @if($movement->reference)<div class="font-mono text-[11px]">{{ $movement->reference }}</div>@endif
                @if($movement->note)<div>{{ $movement->note }}</div>@endif
                @if($movement->user)<div class="text-[11px] text-surface-400">by {{ $movement->user->name }}</div>@endif
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="px-5 py-8 text-center text-surface-400">No movements recorded yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if(method_exists($movements, 'hasPages') && $movements->hasPages())
      <div class="border-t border-surface-100 p-4">{{ $movements->links() }}</div>
    @endif
  </div>
  @endunless
</section>

<script>
  // Wastage is entered as "how many were lost"; a correction is entered as the
  // level actually counted, which is what an admin has in hand after counting.
  document.querySelectorAll('[data-adjust-form]').forEach(function (form) {
    var type = form.querySelector('[data-adjust-type]');
    var label = form.querySelector('[data-adjust-label]');
    var hint = form.querySelector('[data-adjust-hint]');
    var input = form.querySelector('[data-adjust-input]');
    if (!type || !label || !hint || !input) return;

    var stock = parseInt(input.dataset.stock, 10) || 0;

    function sync() {
      if (type.value === 'correction') {
        label.textContent = 'Counted level *';
        hint.textContent = 'The quantity you actually counted. System currently says ' + stock + '.';
        input.removeAttribute('max');
      } else {
        label.textContent = 'Quantity lost *';
        hint.textContent = 'How many units were lost. Stock is ' + stock + '.';
        input.setAttribute('max', String(stock));
      }
    }

    type.addEventListener('change', sync);
    sync();
  });
</script>
