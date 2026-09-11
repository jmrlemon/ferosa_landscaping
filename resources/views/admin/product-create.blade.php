<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @include('partials.favicon')
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Add Inventory Item - Ferosa Landscaping</title>

  <link rel="stylesheet" href="{{ asset('fonts/ferosa-fonts.css') }}">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @include('admin.partials.premium-theme')
</head>
<body class="min-h-screen bg-surface-100 text-surface-900 font-sans antialiased">
  <a href="#admin-main" class="skip-link">Skip to admin content</a>
  <header class="h-14 bg-white border-b border-surface-200 flex items-center justify-between px-5">
    <h1 class="text-sm font-semibold text-surface-600">Add Inventory Item</h1>
    <div class="flex items-center gap-2">
      <span class="rounded-md bg-brand-600 px-2.5 py-1 text-xs font-bold text-white">Ferosa Landscaping</span>
    </div>
  </header>

  <main id="admin-main" tabindex="-1" class="p-5">
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
        <h2 class="text-2xl font-bold text-brand-950">Add New Inventory Item</h2>
        <p class="mt-1 text-sm text-surface-500">Create a shop-ready material or supply with price, stock, and image details.</p>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
      <div class="xl:col-span-2">
        <form id="add-product-form" method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data" class="space-y-6">
          @csrf
          <section class="overflow-hidden rounded-xl border border-surface-100 bg-white shadow-sm">
            <div class="border-b border-surface-200 px-5 py-4">
              <div class="flex items-center justify-between gap-3">
                <h3 class="font-semibold text-surface-900">Item Details</h3>
                <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700">Required for shop</span>
              </div>
            </div>
            <div class="space-y-4 p-5">
              <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <label class="block text-sm font-medium text-surface-800">Product Name *
                  <input name="name" value="{{ old('name') }}" required class="mt-2 h-10 w-full rounded-lg border border-surface-200 px-3 text-base font-normal outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                </label>
                <div class="block text-sm font-medium text-surface-800">
                  <label for="category-input">Category *</label>
                  <div id="category-combo" class="relative mt-2">
                    <input id="category-input" name="category" value="{{ old('category') }}" autocomplete="off" maxlength="100" required
                           role="combobox" aria-expanded="false" aria-controls="category-list" aria-autocomplete="list"
                           placeholder="Pick one or type a new category"
                           class="h-10 w-full rounded-lg border border-surface-200 pl-3 pr-10 text-base font-normal outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                    <button id="category-toggle" type="button" aria-label="Show categories" tabindex="-1"
                            class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-lg text-surface-500 transition-colors hover:text-brand-700">
                      <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <ul id="category-list" role="listbox" hidden
                        class="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-surface-200 bg-white py-1 shadow-lg">
                      @foreach ($categories as $categoryOption)
                        <li role="option" data-value="{{ $categoryOption }}" tabindex="-1"
                            class="cursor-pointer px-3 py-2 text-base font-normal text-surface-800 hover:bg-brand-50 hover:text-brand-800">{{ ucfirst($categoryOption) }}</li>
                      @endforeach
                    </ul>
                  </div>
                  <p class="mt-1 text-xs font-normal text-surface-500">Choose an existing category or type a new one.</p>
                </div>
              </div>
              <label class="block text-sm font-medium text-surface-800">Description
                <textarea name="description" rows="4" class="mt-2 w-full rounded-lg border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500">{{ old('description') }}</textarea>
              </label>
            </div>
          </section>

          <section class="overflow-hidden rounded-xl border border-surface-100 bg-white shadow-sm">
            <div class="border-b border-surface-200 px-5 py-4">
              <div class="flex items-center justify-between gap-3">
                <h3 class="font-semibold text-surface-900">Pricing & Inventory</h3>
                <span class="rounded-full bg-surface-100 px-2.5 py-1 text-xs font-semibold text-surface-600">Visible when active</span>
              </div>
            </div>
            <div class="grid grid-cols-1 gap-4 p-5 lg:grid-cols-3">
              <label class="block text-sm font-medium text-surface-800">Price (&#8369;) *
                <input name="price" type="number" step="0.01" min="0" value="{{ old('price') }}" required class="mt-2 h-10 w-full rounded-lg border border-surface-200 px-3 text-base font-normal outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
              </label>
              <label class="block text-sm font-medium text-surface-800">Stock Qty *
                <input name="stock_qty" type="number" min="0" value="{{ old('stock_qty', 0) }}" class="mt-2 h-10 w-full rounded-lg border border-surface-200 px-3 text-base font-normal outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
              </label>
              <label class="block text-sm font-medium text-surface-800">Product Image
                <input name="image" type="file" accept="image/*" class="mt-2 h-10 w-full rounded-lg border border-surface-200 bg-white text-sm text-surface-600 file:mr-3 file:h-full file:border-0 file:bg-surface-100 file:px-3 file:text-sm file:text-surface-700">
              </label>
              <label class="flex items-center gap-2 text-sm font-medium text-surface-700 lg:col-span-3">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="h-4 w-4 rounded border-surface-300 text-brand-600 focus:ring-brand-500">
                Active in Shop
              </label>
            </div>
          </section>
        </form>
      </div>

      <aside class="h-fit rounded-xl border border-surface-100 bg-white p-4 shadow-sm">
        <div class="flex h-44 items-center justify-center rounded-lg bg-brand-50">
          <svg class="h-12 w-12 text-brand-200" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 19.5h16.5A1.5 1.5 0 0 0 21.75 18V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Z"/></svg>
        </div>
        <div class="mt-4 rounded-lg border border-brand-100 bg-brand-50 px-3 py-3">
          <p class="text-sm font-semibold text-brand-800">Before Saving</p>
          <p class="mt-1 text-xs leading-5 text-brand-700">Use a clear product photo and a short category name so customers can browse faster.</p>
        </div>
        <button type="submit" form="add-product-form" class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg bg-brand-700 py-2.5 text-base font-semibold text-white transition-colors hover:bg-brand-800">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          Add Inventory Item
        </button>
        <a href="{{ route('admin.dashboard', ['tab' => 'products']) }}" class="mt-2 flex w-full items-center justify-center rounded-lg border border-surface-400 py-2.5 text-base font-medium text-surface-600 transition-colors hover:bg-surface-50">Cancel</a>
      </aside>
    </div>
  </main>

  <script>
    (function () {
      const combo = document.getElementById('category-combo');
      const input = document.getElementById('category-input');
      const toggle = document.getElementById('category-toggle');
      const list = document.getElementById('category-list');
      if (!combo || !input || !toggle || !list) {
        return;
      }

      const options = Array.prototype.slice.call(list.querySelectorAll('[role="option"]'));

      function open() {
        filter();
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
      }

      function close() {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
      }

      // Typing narrows the list but never blocks a brand-new category name.
      function filter() {
        const needle = input.value.trim().toLowerCase();
        let shown = 0;
        options.forEach(function (option) {
          const match = needle === '' || option.dataset.value.toLowerCase().indexOf(needle) !== -1;
          option.hidden = !match;
          if (match) {
            shown += 1;
          }
        });
        if (shown === 0) {
          close();
        }
      }

      function choose(option) {
        input.value = option.dataset.value;
        close();
        input.focus();
      }

      toggle.addEventListener('mousedown', function (event) {
        event.preventDefault();
        if (list.hidden) {
          input.focus();
          input.value = '';
          open();
        } else {
          close();
        }
      });

      input.addEventListener('focus', open);
      input.addEventListener('input', function () {
        filter();
        if (list.hidden) {
          open();
        }
      });

      input.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          close();
        }
      });

      list.addEventListener('mousedown', function (event) {
        const option = event.target.closest('[role="option"]');
        if (option) {
          event.preventDefault();
          choose(option);
        }
      });

      document.addEventListener('mousedown', function (event) {
        if (!combo.contains(event.target)) {
          close();
        }
      });
    })();
  </script>
</body>
</html>
