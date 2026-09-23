@extends('layouts.customer')

@section('title', 'Report damaged plants - Ferosa Landscaping')

@section('content')
<main class="customer-page is-narrow">
  <div class="mb-5">
    <a href="{{ route('orders') }}" class="text-sm font-semibold text-brand-700 hover:text-brand-900">&larr; Back to orders</a>
  </div>

  <div class="customer-card overflow-hidden reveal">
    <div class="border-b border-surface-100 p-5 sm:p-7">
      <p class="page-kicker">Damage claim · {{ $order->order_number }}</p>
      <h1 class="page-title">Report damaged plants</h1>
      <p class="page-sub">Select only the affected items. Add clear photos taken within 24 hours of receiving your order.</p>
    </div>

    @if ($errors->any())
      <div class="m-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
        <p class="font-bold">Please check the claim details.</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
      </div>
    @endif

    <form method="POST" action="{{ route('returns.store', $order) }}" enctype="multipart/form-data" class="space-y-6 p-5 sm:p-7" data-return-form>
      @csrf

      <fieldset>
        <legend class="text-sm font-bold text-surface-900">1. Choose affected items</legend>
        <p class="mt-1 text-xs text-surface-500">You can include more than one plant in the same claim.</p>
        <div class="mt-4 space-y-4">
          @forelse($order->orderItems as $index => $item)
            @php($remaining = $remainingQuantities[$item->id] ?? 0)
            @if($remaining > 0)
              <div class="rounded-xl border border-surface-200 p-4" data-return-item>
                <label class="flex cursor-pointer items-start gap-3">
                  <input type="checkbox" name="items[{{ $index }}][include]" value="1" class="mt-1 h-4 w-4 rounded border-surface-300 text-brand-700" data-return-item-toggle>
                  <span class="min-w-0 flex-1">
                    <span class="block font-semibold text-surface-900">{{ $item->name }}</span>
                    <span class="block text-xs text-surface-500">Purchased: {{ $item->qty }} · Available to claim: {{ $remaining }} · &#8369;{{ number_format((float) $item->price, 2) }} each</span>
                  </span>
                </label>
                <input type="hidden" name="items[{{ $index }}][order_item_id]" value="{{ $item->id }}">
                <div class="mt-4 hidden grid-cols-1 gap-4 sm:grid-cols-2" data-return-item-fields aria-hidden="true">
                  <label class="text-sm font-medium">Affected quantity
                    <input type="number" name="items[{{ $index }}][quantity]" min="1" max="{{ $remaining }}" value="{{ old("items.$index.quantity", 1) }}" class="field mt-1.5">
                  </label>
                  <label class="text-sm font-medium">Problem
                    <select name="items[{{ $index }}][issue_type]" class="field mt-1.5">
                      <option value="damaged_on_arrival">Damaged on arrival</option>
                      <option value="unhealthy_on_arrival">Unhealthy on arrival</option>
                      <option value="wrong_item">Wrong item</option>
                      <option value="missing_quantity">Missing quantity</option>
                    </select>
                  </label>
                  <label class="text-sm font-medium sm:col-span-2">Describe what happened
                    <textarea name="items[{{ $index }}][issue_description]" rows="3" maxlength="1000" class="field mt-1.5" placeholder="Describe the visible damage or problem.">{{ old("items.$index.issue_description") }}</textarea>
                  </label>
                  <label class="text-sm font-medium sm:col-span-2">Preferred solution
                    <select name="items[{{ $index }}][preferred_resolution]" class="field mt-1.5">
                      <option value="replacement">Replacement</option>
                      <option value="refund">Refund</option>
                    </select>
                  </label>
                </div>
              </div>
            @endif
          @empty
            <p class="rounded-xl border border-surface-200 bg-surface-50 p-4 text-sm text-surface-500">This order has no stored line items available for a claim.</p>
          @endforelse
        </div>
      </fieldset>

      <fieldset class="border-t border-surface-100 pt-6">
        <legend class="text-sm font-bold text-surface-900">2. Add photos and context</legend>
        <label class="mt-4 block text-sm font-medium">Clear evidence photos
          <input type="file" name="evidence[]" accept="image/jpeg,image/png,image/webp" multiple class="mt-1.5 block w-full rounded-xl border border-surface-200 bg-white text-sm file:mr-4 file:border-0 file:bg-brand-50 file:px-4 file:py-3 file:font-semibold file:text-brand-800" data-evidence-input>
          <span class="mt-1 block text-xs text-surface-500">1–5 JPG, PNG, or WebP photos. Maximum 5 MB each.</span>
        </label>
        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-5" data-evidence-preview aria-live="polite"></div>
        <label class="mt-4 block text-sm font-medium">Contact or pickup notes <span class="font-normal text-surface-400">(optional)</span>
          <textarea name="customer_contact_notes" rows="2" maxlength="500" class="field mt-1.5">{{ old('customer_contact_notes') }}</textarea>
        </label>
      </fieldset>

      <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-900">
        Ferosa reviews each claim before approving a replacement or refund. Damaged plants are not automatically returned to saleable stock, and a physical return is required only if the admin requests it.
      </div>

      <button type="submit" class="btn btn-primary w-full justify-center" data-loading-label="Submitting claim…">Submit damage claim</button>
    </form>
  </div>
</main>

@include('partials.mobile-bottom-customer')

<script>
  document.querySelectorAll('[data-return-item]').forEach(item => {
    const toggle = item.querySelector('[data-return-item-toggle]');
    const fields = item.querySelector('[data-return-item-fields]');
    if (!toggle || !fields) return;

    const sync = () => {
      fields.classList.toggle('hidden', !toggle.checked);
      fields.classList.toggle('grid', toggle.checked);
      fields.setAttribute('aria-hidden', toggle.checked ? 'false' : 'true');
    };

    toggle.addEventListener('change', sync);
    sync();
  });

  const evidenceInput = document.querySelector('[data-evidence-input]');
  const evidencePreview = document.querySelector('[data-evidence-preview]');
  evidenceInput?.addEventListener('change', () => {
    evidencePreview.replaceChildren();
    Array.from(evidenceInput.files || []).slice(0, 5).forEach(file => {
      const image = document.createElement('img');
      image.className = 'aspect-square w-full rounded-lg border border-surface-200 object-cover';
      image.alt = `Preview of ${file.name}`;
      image.src = URL.createObjectURL(file);
      image.addEventListener('load', () => URL.revokeObjectURL(image.src), { once: true });
      evidencePreview.appendChild(image);
    });
  });
</script>
@endsection
