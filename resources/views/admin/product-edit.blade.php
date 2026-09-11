@extends('admin.layouts.workspace')

@section('title', 'Edit '.$product->name.' - Ferosa Admin')
@section('admin-section', 'products')
@section('skip-label', 'Skip to product editor')
@section('header-eyebrow', 'Inventory')
@section('header-title', 'Edit product')

@section('content')
    @if (session('status'))
      <div class="mb-4 rounded-lg border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-700">
        {{ session('status') }}
      </div>
    @endif

    @if (session('ar_model_warnings'))
      <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <p class="font-semibold">AR model uploaded with review warnings.</p>
        <ul class="mt-1 list-disc pl-5">
          @foreach (session('ar_model_warnings') as $warning)
            <li>{{ $warning }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    @if ($errors->any())
      <div class="mb-4 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
        <p class="font-semibold">Please check the form and try again.</p>
        <ul class="mt-1 list-disc pl-5">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="mb-5 flex items-center gap-4">
      <a href="{{ route('admin.dashboard', ['tab' => 'products']) }}" class="inline-flex h-9 w-9 items-center justify-center rounded border border-surface-400 text-surface-600 transition-colors hover:bg-white" aria-label="Back to products">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
      </a>
      <div>
        <h2 class="text-2xl font-bold text-brand-950">Edit: {{ $product->name }}</h2>
        <p class="mt-1 text-sm text-surface-500">Update shop details, stock, visibility, and product imagery.</p>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
      <div class="xl:col-span-2 space-y-6">
        <form id="edit-product-form" method="POST" action="{{ route('admin.products.update', $product) }}" enctype="multipart/form-data" class="space-y-6">
          @csrf
          @method('PUT')
          <input type="hidden" name="redirect_to" value="edit">

          <section class="overflow-hidden rounded-xl border border-surface-100 bg-white shadow-sm">
            <div class="border-b border-surface-200 px-5 py-4">
              <div class="flex items-center justify-between gap-3">
                <h3 class="font-semibold text-surface-900">Item Details</h3>
                <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700">Shop content</span>
              </div>
            </div>
            <div class="space-y-4 p-5">
              <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <label class="block text-sm font-medium text-surface-800">Product Name *
                  <input name="name" value="{{ old('name', $product->name) }}" required {{ $isStaffOrAdmin ? '' : 'disabled' }} class="mt-2 h-10 w-full rounded-lg border border-surface-200 px-3 text-base font-normal outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                </label>
                <label class="block text-sm font-medium text-surface-800">Category *
                  <input name="category" value="{{ old('category', $product->category) }}" required {{ $isStaffOrAdmin ? '' : 'disabled' }} class="mt-2 h-10 w-full rounded-lg border border-surface-200 px-3 text-base font-normal outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                </label>
              </div>

              <label class="block text-sm font-medium text-surface-800">Description
                <textarea name="description" rows="4" {{ $isStaffOrAdmin ? '' : 'disabled' }} class="mt-2 w-full rounded-lg border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500">{{ old('description', $product->description) }}</textarea>
              </label>
            </div>
          </section>

          <section class="overflow-hidden rounded-xl border border-surface-100 bg-white shadow-sm">
            <div class="border-b border-surface-200 px-5 py-4">
              <div class="flex items-center justify-between gap-3">
                <h3 class="font-semibold text-surface-900">Pricing & Inventory</h3>
                <span class="rounded-full {{ $product->is_active ? 'bg-brand-50 text-brand-700' : 'bg-surface-100 text-surface-600' }} px-2.5 py-1 text-xs font-semibold">{{ $product->is_active ? 'Active in Shop' : 'Hidden from Shop' }}</span>
              </div>
            </div>
            <div class="grid grid-cols-1 gap-4 p-5 lg:grid-cols-3">
              <label class="block text-sm font-medium text-surface-800">Price (&#8369;) *
                <input name="price" type="number" step="0.01" min="0" value="{{ old('price', $product->price) }}" required {{ $isStaffOrAdmin ? '' : 'disabled' }} class="mt-2 h-10 w-full rounded-lg border border-surface-200 px-3 text-base font-normal outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
              </label>
              {{-- Stock is not edited here. It is managed in the Stock panel
                   below, where every change is recorded with a reason. --}}
              <div class="block text-sm font-medium text-surface-800">Stock Qty
                <div class="mt-2 flex h-10 items-center justify-between gap-2 rounded-lg border border-dashed border-surface-200 bg-surface-50 px-3">
                  <span class="text-base font-bold {{ $product->stock_qty <= 5 ? 'text-amber-700' : 'text-surface-900' }}">{{ $product->stock_qty }}</span>
                  @if($canManageStock)
                    <a href="#stock-panel" class="text-xs font-semibold text-brand-700 hover:underline">Manage below</a>
                  @endif
                </div>
                <span class="mt-1 block text-xs font-normal text-surface-400">Changed through restock, wastage or correction.</span>
              </div>
              <label class="block text-sm font-medium text-surface-800">New Image (optional)
                <input name="image" type="file" accept="image/*" {{ $isStaffOrAdmin ? '' : 'disabled' }} class="mt-2 h-10 w-full rounded-lg border border-surface-200 bg-white text-sm text-surface-600 file:mr-3 file:h-full file:border-0 file:bg-surface-100 file:px-3 file:text-sm file:text-surface-700">
              </label>
              <label class="flex items-center gap-2 text-sm font-medium text-surface-700 lg:col-span-3">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $product->is_active) ? 'checked' : '' }} {{ $isStaffOrAdmin ? '' : 'disabled' }} class="h-4 w-4 rounded border-surface-300 text-brand-600 focus:ring-brand-500">
                Active in Shop
              </label>
            </div>
          </section>
        </form>

        @if($canManageStock)
          <div id="stock-panel" class="scroll-mt-5">
            @include('admin.partials.stock-panel', [
              'product' => $product,
              'showAllLink' => true,
              'hideHistory' => true,
            ])
          </div>
        @endif

        @include('admin.partials.ar-models', ['product' => $product])
      </div>

      <aside class="h-fit rounded-xl border border-surface-100 bg-white p-4 shadow-sm">
        <div class="flex h-44 items-center justify-center overflow-hidden rounded-lg bg-brand-50">
          @if($product->image_url)
            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="h-full w-full object-contain">
          @else
            <svg class="h-12 w-12 text-brand-200" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 19.5h16.5A1.5 1.5 0 0 0 21.75 18V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z"/></svg>
          @endif
        </div>
        <div class="mt-4 grid grid-cols-2 gap-2">
          <div class="rounded-lg border border-surface-100 bg-surface-50 p-3">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-surface-400">Price</p>
            <p class="mt-1 text-sm font-bold text-surface-900">&#8369;{{ number_format((float) $product->price, 2) }}</p>
          </div>
          <div class="rounded-lg border border-surface-100 bg-surface-50 p-3">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-surface-400">Stock Qty</p>
            <p class="mt-1 text-sm font-bold {{ $product->stock_qty <= 5 ? 'text-amber-700' : 'text-brand-700' }}">{{ $product->stock_qty }} units</p>
          </div>
        </div>
        @if($isStaffOrAdmin)
          <button type="submit" form="edit-product-form" class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg bg-brand-700 py-2.5 text-base font-semibold text-white transition-colors hover:bg-brand-800">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            Save Changes
          </button>
        @endif
        <a href="{{ route('admin.dashboard', ['tab' => 'products']) }}" class="mt-2 flex w-full items-center justify-center rounded-lg border border-surface-400 py-2.5 text-base font-medium text-surface-600 transition-colors hover:bg-surface-50">Cancel</a>
      </aside>
    </div>
@endsection
