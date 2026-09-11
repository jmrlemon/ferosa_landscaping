<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Low Stock Alert — Ferosa</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: system-ui, -apple-system, sans-serif; font-size: 14px; color: #181714; background: #f8f7f3; padding: 32px 16px; }
    .wrapper { max-width: 560px; margin: 0 auto; }
    .card { background: #fff; border-radius: 12px; border: 1px solid #e2ded4; overflow: hidden; }
    .header { background: #1b5239; padding: 24px 28px; }
    .header .logo { font-size: 22px; font-weight: 800; letter-spacing: -.5px; color: #fff; margin-bottom: 2px; }
    .header .subtitle { font-size: 12px; color: #d8ecdf; }
    .alert-banner { background: #fefce8; border-bottom: 1px solid #fef08a; padding: 12px 28px; display: flex; align-items: center; gap: 10px; }
    .alert-banner .icon { font-size: 20px; }
    .alert-banner p { font-size: 13px; font-weight: 600; color: #854d0e; }
    .body { padding: 24px 28px; }
    .intro { font-size: 13px; color: #3b3833; margin-bottom: 20px; line-height: 1.6; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    thead th { font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: #948e83; padding: 8px 12px; border-bottom: 2px solid #f0eee8; text-align: left; background: #f8f7f3; }
    tbody td { padding: 10px 12px; border-bottom: 1px solid #f0eee8; font-size: 13px; vertical-align: middle; }
    tbody tr:last-child td { border-bottom: none; }
    .product-name { font-weight: 600; color: #181714; }
    .category { font-size: 11px; color: #746f65; margin-top: 2px; }
    .stock-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
    .stock-critical { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
    .stock-low { background: #fefce8; color: #ca8a04; border: 1px solid #fef08a; }
    .stock-zero { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .footer { padding: 16px 28px; background: #f8f7f3; border-top: 1px solid #f0eee8; font-size: 11px; color: #948e83; text-align: center; }
    .footer a { color: #1b5239; text-decoration: none; }
    .cta { margin-top: 16px; }
    .cta a { display: inline-block; background: #1b5239; color: #fff; font-weight: 600; font-size: 13px; padding: 10px 24px; border-radius: 8px; text-decoration: none; }
  </style>
</head>
<body>
<div class="wrapper">
  <div class="card">

    <div class="header">
      <div class="logo">Ferosa</div>
      <div class="subtitle">Garden &amp; Landscaping — Admin Alert</div>
    </div>

    <div class="alert-banner">
      <span class="icon">⚠️</span>
      <p>Low Stock Warning — {{ $products->count() }} product{{ $products->count() > 1 ? 's' : '' }} need{{ $products->count() === 1 ? 's' : '' }} restocking</p>
    </div>

    <div class="body">
      <p class="intro">
        The following products have reached a low stock level (5 units or fewer) as a result of a recent order.
        Please restock these items promptly to avoid order fulfillment issues.
      </p>

      <table>
        <thead>
          <tr>
            <th>Product</th>
            <th style="text-align:center">Stock Remaining</th>
          </tr>
        </thead>
        <tbody>
          @foreach($products as $product)
          <tr>
            <td>
              <div class="product-name">{{ $product->name }}</div>
              @if($product->category)
              <div class="category">{{ $product->category }}</div>
              @endif
            </td>
            <td style="text-align:center">
              @if($product->stock_qty === 0)
                <span class="stock-badge stock-zero">Out of Stock</span>
              @elseif($product->stock_qty <= 2)
                <span class="stock-badge stock-critical">{{ $product->stock_qty }} unit{{ $product->stock_qty !== 1 ? 's' : '' }}</span>
              @else
                <span class="stock-badge stock-low">{{ $product->stock_qty }} units</span>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>

      <div class="cta">
        <a href="{{ url('/admin/dashboard?tab=products') }}">View Products in Dashboard &rarr;</a>
      </div>
    </div>

    <div class="footer">
      This is an automated alert from Ferosa &mdash; {{ now()->format('F j, Y \a\t g:i A') }}<br>
      <a href="{{ url('/admin/dashboard') }}">Admin Dashboard</a>
    </div>

  </div>
</div>
</body>
</html>
