@php
  $isOrder = $kind === 'order';
  $reference = $isOrder ? $payable->order_number : ($payable->serviceType->name ?? 'Service visit');
  $issuedAt = $isOrder ? $payable->created_at : $payable->created_at;
  $customer = $payable->user;
  $statusClass = match ($payable->payment_status) {
      'paid' => 'paid',
      'partial' => 'partial',
      'refunded' => 'refunded',
      default => 'due',
  };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Invoice {{ $invoiceNumber }} — Ferosa</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: system-ui, -apple-system, sans-serif; font-size: 13px; color: #181714; background: #f8f7f3; padding: 32px 16px; }
    .invoice { background: #fff; max-width: 560px; margin: 0 auto; padding: 36px 32px; border-radius: 12px; border: 1px solid #e2ded4; }
    .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 24px; }
    .logo { font-size: 20px; font-weight: 800; letter-spacing: -.5px; color: #1b5239; }
    .sub { font-size: 11px; color: #948e83; margin-top: 2px; }
    .doc-type { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #948e83; text-align: right; }
    .doc-no { font-family: monospace; font-size: 15px; font-weight: 700; text-align: right; margin-top: 2px; }
    .divider { border: none; border-top: 1px solid #f0eee8; margin: 16px 0; }
    .row { display: flex; justify-content: space-between; padding: 5px 0; gap: 16px; }
    .row .label { color: #746f65; }
    .row .value { font-weight: 600; text-align: right; }
    .section-title { font-size: 10px; text-transform: uppercase; letter-spacing: .08em; color: #948e83; margin: 24px 0 8px; }
    table { width: 100%; border-collapse: collapse; }
    thead th { font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: #948e83; padding: 8px 0; border-bottom: 1px solid #f0eee8; text-align: left; }
    thead th:last-child, td:last-child { text-align: right; }
    tbody td { padding: 9px 0; border-bottom: 1px solid #f8f7f3; vertical-align: top; }
    tbody tr:last-child td { border-bottom: none; }
    .muted { color: #948e83; font-size: 11px; }
    .totals { margin-top: 8px; border-top: 1px solid #f0eee8; padding-top: 10px; }
    .balance { display: flex; justify-content: space-between; align-items: center; margin-top: 12px; padding: 14px 16px; border-radius: 10px; font-weight: 800; font-size: 15px; }
    .balance.due { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
    .balance.paid { background: #eef7f1; color: #1b5239; border: 1px solid #d8ecdf; }
    .balance.partial { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .balance.refunded { background: #f0eee8; color: #514d46; border: 1px solid #e2ded4; }
    .empty { color: #948e83; font-style: italic; padding: 12px 0; }
    .footer { margin-top: 28px; text-align: center; font-size: 11px; color: #948e83; line-height: 1.6; }
    .print-btn { display: block; text-align: center; margin: 20px auto 0; max-width: 560px; }
    .print-btn button, .print-btn a { display: inline-block; background: #181714; color: #fff; border: none; padding: 10px 28px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; }
    .print-btn a { background: #fff; color: #3b3833; border: 1px solid #e2ded4; margin-left: 6px; }
    @media print {
      body { background: #fff; padding: 0; }
      .invoice { border: none; border-radius: 0; padding: 24px; max-width: none; }
      .print-btn { display: none; }
    }
  </style>
</head>
<body>

<div class="invoice">
  <div class="head">
    <div>
      <div class="logo">Ferosa</div>
      <div class="sub">Garden &amp; Landscaping</div>
      <div class="sub">A. Arellano Ave. Mulawin, Orani, Bataan 2112</div>
    </div>
    <div>
      <div class="doc-type">Invoice</div>
      <div class="doc-no">{{ $invoiceNumber }}</div>
    </div>
  </div>

  <div class="row">
    <span class="label">Billed to</span>
    <span class="value">{{ $customer->name ?? 'Customer' }}</span>
  </div>
  <div class="row">
    <span class="label">Email</span>
    <span class="value">{{ $customer->email ?? '—' }}</span>
  </div>
  <div class="row">
    <span class="label">{{ $isOrder ? 'Order' : 'Service' }}</span>
    <span class="value" style="font-family:monospace">{{ $reference }}</span>
  </div>
  <div class="row">
    <span class="label">Issued</span>
    <span class="value">{{ optional($issuedAt)->format('F j, Y') }}</span>
  </div>
  @unless ($isOrder)
    <div class="row">
      <span class="label">Scheduled</span>
      <span class="value">{{ optional($payable->appointment_at)->format('F j, Y h:i A') }}</span>
    </div>
  @endunless

  <div class="section-title">Charges</div>
  <table>
    <thead>
      <tr><th>Description</th><th>Qty</th><th>Unit</th><th>Amount</th></tr>
    </thead>
    <tbody>
      @forelse ($lines as $line)
        <tr>
          <td>{{ $line['description'] }}</td>
          <td>{{ $line['qty'] }}</td>
          <td>&#8369;{{ number_format($line['unit'], 2) }}</td>
          <td>&#8369;{{ number_format($line['amount'], 2) }}</td>
        </tr>
      @empty
        <tr><td colspan="4" class="empty">No charges recorded.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="totals">
    <div class="row">
      <span class="label">Total billed</span>
      <span class="value">&#8369;{{ number_format($totalBilled, 2) }}</span>
    </div>
  </div>

  <div class="section-title">Payments received</div>
  <table>
    <thead>
      <tr><th>Date</th><th>Method</th><th>Reference</th><th>Amount</th></tr>
    </thead>
    <tbody>
      @forelse ($payable->activePayments as $payment)
        <tr>
          <td>{{ optional($payment->paid_at)->format('M d, Y') }}</td>
          <td>
            {{ $payment->methodLabel() }}
            @if ($payment->recordedBy)
              <div class="muted">recorded by {{ $payment->recordedBy->name }}</div>
            @endif
          </td>
          <td class="muted">{{ $payment->reference ?: '—' }}</td>
          <td>&#8369;{{ number_format((float) $payment->amount, 2) }}</td>
        </tr>
      @empty
        <tr><td colspan="4" class="empty">No payments received yet.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="totals">
    <div class="row">
      <span class="label">Total paid</span>
      <span class="value">&#8369;{{ number_format($totalPaid, 2) }}</span>
    </div>
  </div>

  <div class="balance {{ $statusClass }}">
    <span>{{ $balanceDue > 0 ? 'Balance due' : 'Balance' }}</span>
    <span>&#8369;{{ number_format($balanceDue, 2) }}</span>
  </div>

  <div class="footer">
    @if ($balanceDue > 0)
      Please settle the outstanding balance on or before your {{ $isOrder ? 'delivery' : 'service visit' }}.<br>
    @else
      This invoice is fully settled. Thank you for choosing Ferosa.<br>
    @endif
    Questions about this invoice? Message us from your account page.
  </div>
</div>

<div class="print-btn">
  <button type="button" onclick="window.print()">Print invoice</button>
  <a href="{{ $isOrder ? route('orders') : route('appointments') }}">Back</a>
</div>

</body>
</html>
