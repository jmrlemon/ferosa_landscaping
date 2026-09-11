<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @include('partials.favicon')
  <title>Revenue Overview Report - Ferosa</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    @media print {
      .no-print { display: none !important; }
      body { background: white !important; }
      .report-page { box-shadow: none !important; border: none !important; }
    }
  </style>
  @include('admin.partials.premium-theme')
</head>
<body class="bg-surface-50 text-surface-900 font-sans antialiased" style="background:#fafafa">
  <a href="#admin-main" class="skip-link">Skip to report</a>
  <main id="admin-main" tabindex="-1" class="max-w-5xl mx-auto px-5 py-6">
    <div class="no-print flex flex-wrap items-center justify-between gap-3 mb-5">
      <a href="{{ route('admin.dashboard', array_filter(['tab' => 'overview', 'sales_from' => $salesFrom, 'sales_to' => $salesTo])) }}"
         class="text-xs font-medium text-surface-500 hover:text-surface-800">
        Back to Overview
      </a>
      <div class="flex flex-wrap gap-2">
        <button type="button" onclick="window.print()"
          class="inline-flex items-center gap-1.5 rounded-lg border border-surface-200 bg-white px-4 py-2 text-xs font-medium text-surface-600 hover:bg-surface-50">
          Print
        </button>
        <a href="{{ route('admin.reports.overview-csv', array_filter(['sales_from' => $salesFrom, 'sales_to' => $salesTo])) }}"
           class="inline-flex items-center gap-1.5 rounded-lg bg-surface-900 px-4 py-2 text-xs font-medium text-white hover:bg-surface-800">
          Download CSV
        </a>
      </div>
    </div>

    <section class="report-page bg-white border border-surface-100 rounded-xl shadow-sm overflow-hidden">
      <div class="px-6 py-5 border-b border-surface-100 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <p class="text-[10px] font-semibold text-surface-350 uppercase tracking-wider">Ferosa Landscaping</p>
          <h1 class="text-xl font-bold text-surface-900 mt-1">Revenue Overview Report</h1>
          <p class="text-xs text-surface-350 mt-1">Generated {{ $generatedAt->format('M d, Y h:i A') }}</p>
        </div>
        <div class="text-xs text-surface-500 sm:text-right">
          <p><span class="text-surface-350">From:</span> {{ $salesFrom ?: 'Any' }}</p>
          <p><span class="text-surface-350">To:</span> {{ $salesTo ?: 'Any' }}</p>
        </div>
      </div>

      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 p-6 border-b border-surface-100">
        <div class="rounded-lg bg-surface-900 text-white p-4">
          <p class="text-[10px] text-surface-400 uppercase tracking-wider">Total Sales</p>
          <p class="text-xl font-bold mt-1">PHP {{ number_format($totalSales, 2) }}</p>
        </div>
        <div class="rounded-lg border border-surface-100 p-4">
          <p class="text-[10px] text-surface-350 uppercase tracking-wider">Total Orders</p>
          <p class="text-xl font-bold mt-1">{{ $totalOrders }}</p>
        </div>
        <div class="rounded-lg border border-surface-100 p-4">
          <p class="text-[10px] text-surface-350 uppercase tracking-wider">Delivered Orders</p>
          <p class="text-xl font-bold mt-1">{{ $deliveredOrders }}</p>
        </div>
        <div class="rounded-lg border border-surface-100 p-4">
          <p class="text-[10px] text-surface-350 uppercase tracking-wider">Pending Orders</p>
          <p class="text-xl font-bold mt-1">{{ $pendingOrders }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-0 border-b border-surface-100">
        <div class="p-6 lg:border-r border-surface-100">
          <h2 class="text-sm font-semibold mb-3">Sales by Status</h2>
          <table class="w-full text-xs">
            <thead>
              <tr class="text-left text-surface-350 uppercase tracking-wider border-b border-surface-100">
                <th class="py-2 font-medium">Status</th>
                <th class="py-2 font-medium text-right">Orders</th>
                <th class="py-2 font-medium text-right">Sales</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-surface-100">
              @forelse($salesByStatus as $row)
                <tr>
                  <td class="py-2">{{ ucfirst(str_replace('_', ' ', $row->status)) }}</td>
                  <td class="py-2 text-right">{{ $row->order_count }}</td>
                  <td class="py-2 text-right font-semibold">PHP {{ number_format((float) $row->sales_total, 2) }}</td>
                </tr>
              @empty
                <tr><td colspan="3" class="py-5 text-center text-surface-350">No order data.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="p-6">
          <h2 class="text-sm font-semibold mb-3">Week vs Week Revenue</h2>
          <div class="grid grid-cols-2 gap-3">
            <div class="rounded-lg border border-surface-100 p-4">
              <p class="text-[10px] text-surface-350 uppercase tracking-wider">This Week</p>
              <p class="text-lg font-bold text-green-600 mt-1">PHP {{ number_format($thisWeekSales, 2) }}</p>
            </div>
            <div class="rounded-lg border border-surface-100 p-4">
              <p class="text-[10px] text-surface-350 uppercase tracking-wider">Last Week</p>
              <p class="text-lg font-bold mt-1">PHP {{ number_format($lastWeekSales, 2) }}</p>
            </div>
          </div>
          <p class="text-xs text-surface-350 mt-3">
            Change: {{ $weekSalesDeltaPct === null ? 'N/A' : (($weekSalesDeltaPct >= 0 ? '+' : '').$weekSalesDeltaPct.'%') }}
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-0">
        <div class="p-6 lg:border-r border-surface-100">
          <h2 class="text-sm font-semibold mb-3">Monthly Sales</h2>
          <table class="w-full text-xs">
            <tbody class="divide-y divide-surface-100">
              @foreach($monthlySales as $row)
                <tr>
                  <td class="py-2">{{ $row['label'] }}</td>
                  <td class="py-2 text-right font-semibold">PHP {{ number_format($row['total'], 2) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="p-6">
          <h2 class="text-sm font-semibold mb-3">Top Products</h2>
          <table class="w-full text-xs">
            <tbody class="divide-y divide-surface-100">
              @forelse($topProducts as $row)
                <tr>
                  <td class="py-2">{{ $row['name'] }}</td>
                  <td class="py-2 text-right font-semibold">{{ $row['qty'] }}</td>
                </tr>
              @empty
                <tr><td class="py-5 text-center text-surface-350">No line items recorded.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
