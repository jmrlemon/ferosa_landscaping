<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @include('partials.favicon')
  <title>Archived - Ferosa Landscaping Admin</title>
  <link rel="stylesheet" href="{{ asset('fonts/ferosa-fonts.css') }}">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @include('admin.partials.premium-theme')
</head>
<body class="bg-surface-50 text-surface-800 font-sans antialiased">
  <a href="#admin-main" class="skip-link">Skip to admin content</a>
  <header class="bg-white border-b border-surface-100">
    <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
      <div>
        <h1 class="text-lg font-semibold text-surface-900">Archived Items</h1>
        <p class="text-xs text-surface-400">Restore archived products and services.</p>
      </div>
      <div class="flex items-center gap-2">
        <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-surface-200 text-surface-600 hover:bg-surface-50 transition-colors">Back to Dashboard</a>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button class="px-3 py-1.5 text-xs font-medium rounded-lg bg-surface-900 text-white hover:bg-surface-800 transition-colors">Sign Out</button>
        </form>
      </div>
    </div>
  </header>

  <main id="admin-main" tabindex="-1" class="max-w-5xl mx-auto px-4 py-6 space-y-5">
    @if (session('status'))
      <div class="bg-brand-50 border border-brand-100 text-brand-700 px-4 py-2.5 rounded-lg text-sm">
        {{ session('status') }}
      </div>
    @endif

    @if ($errors->any())
      <div class="bg-red-50 border border-red-100 text-red-600 px-4 py-2.5 rounded-lg text-sm">
        <ul class="list-disc pl-4 space-y-0.5">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <section class="bg-white border border-surface-100 rounded-xl overflow-hidden">
      <div class="px-5 py-4 border-b border-surface-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-surface-900">Archived Products</h2>
        <span class="text-[10px] text-surface-400">{{ $archivedProducts->count() }} item(s)</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead>
            <tr class="text-left text-surface-400 text-[10px] uppercase tracking-wider border-b border-surface-100">
              <th class="px-5 py-3 font-medium">Name</th>
              <th class="px-5 py-3 font-medium">Category</th>
              <th class="px-5 py-3 font-medium">Price</th>
              <th class="px-5 py-3 font-medium">Archived</th>
              <th class="px-5 py-3 font-medium text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-surface-100">
            @forelse ($archivedProducts as $p)
              <tr class="hover:bg-surface-50 transition-colors">
                <td class="px-5 py-3 font-medium text-surface-800">{{ $p->name }}</td>
                <td class="px-5 py-3 text-surface-500">{{ $p->category }}</td>
                <td class="px-5 py-3 text-surface-700">&#8369;{{ number_format((float) $p->price, 2) }}</td>
                <td class="px-5 py-3 text-surface-400">{{ optional($p->archived_at)->format('M d, Y h:i A') }}</td>
                <td class="px-5 py-3 text-right">
                  <form method="POST" action="{{ route('admin.products.restore', $p) }}">
                    @csrf
                    @method('PUT')
                    <button class="text-[10px] font-medium text-brand-600 hover:text-brand-700 transition-colors">Restore</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td class="px-5 py-6 text-surface-400 text-center" colspan="5">No archived products.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>

    <section class="bg-white border border-surface-100 rounded-xl overflow-hidden">
      <div class="px-5 py-4 border-b border-surface-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-surface-900">Archived Services</h2>
        <span class="text-[10px] text-surface-400">{{ $archivedServices->count() }} item(s)</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead>
            <tr class="text-left text-surface-400 text-[10px] uppercase tracking-wider border-b border-surface-100">
              <th class="px-5 py-3 font-medium">Name</th>
              <th class="px-5 py-3 font-medium">Default Fee</th>
              <th class="px-5 py-3 font-medium">Archived</th>
              <th class="px-5 py-3 font-medium text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-surface-100">
            @forelse ($archivedServices as $s)
              <tr class="hover:bg-surface-50 transition-colors">
                <td class="px-5 py-3 font-medium text-surface-800">{{ $s->name }}</td>
                <td class="px-5 py-3 text-surface-700">&#8369;{{ number_format((float) $s->default_fee, 2) }}</td>
                <td class="px-5 py-3 text-surface-400">{{ optional($s->archived_at)->format('M d, Y h:i A') }}</td>
                <td class="px-5 py-3 text-right">
                  <form method="POST" action="{{ route('admin.services.restore', $s) }}">
                    @csrf
                    @method('PUT')
                    <button class="text-[10px] font-medium text-brand-600 hover:text-brand-700 transition-colors">Restore</button>
                  </form>
                </td>
              </tr>
            @empty
              <tr><td class="px-5 py-6 text-surface-400 text-center" colspan="4">No archived services.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
  </main>
</body>
</html>
