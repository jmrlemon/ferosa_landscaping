<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Appointment Receipt APT-{{ str_pad((string) $appointment->id, 6, '0', STR_PAD_LEFT) }} - Ferosa</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: system-ui, -apple-system, sans-serif; font-size: 13px; color: #181714; background: #f8f7f3; padding: 32px 16px; }
    .receipt { background: #fff; max-width: 480px; margin: 0 auto; padding: 36px 32px; border-radius: 12px; border: 1px solid #e2ded4; }
    .logo { font-size: 20px; font-weight: 800; letter-spacing: -.5px; color: #1b5239; margin-bottom: 4px; }
    .sub { font-size: 11px; color: #948e83; margin-bottom: 24px; }
    .divider { border: none; border-top: 1px solid #f0eee8; margin: 16px 0; }
    .row { display: flex; justify-content: space-between; gap: 16px; padding: 6px 0; }
    .row .label { color: #746f65; }
    .row .value { font-weight: 600; text-align: right; }
    .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .badge-scheduled { background:#fefce8; color:#ca8a04; border:1px solid #fef08a; }
    .badge-confirmed { background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; }
    .badge-completed { background:#eef7f1; color:#236746; border:1px solid #d8ecdf; }
    .badge-cancelled { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; }
    .badge-paid { background:#eef7f1; color:#236746; border:1px solid #d8ecdf; }
    table { width: 100%; border-collapse: collapse; }
    thead th { font-size: 10px; text-transform: uppercase; letter-spacing: .06em; color: #948e83; padding: 8px 0; border-bottom: 1px solid #f0eee8; text-align: left; }
    thead th:last-child { text-align: right; }
    tbody td { padding: 10px 0; border-bottom: 1px solid #f8f7f3; vertical-align: top; font-size: 13px; }
    tbody tr:last-child td { border-bottom: none; }
    td:last-child { text-align: right; }
    .total-row td { padding-top: 14px; font-weight: 700; font-size: 14px; border-bottom: none; }
    .footer { margin-top: 28px; text-align: center; font-size: 11px; color: #948e83; }
    .print-btn { display: block; text-align: center; margin: 20px auto 0; max-width: 480px; }
    .print-btn button { background: #181714; color: #fff; border: none; padding: 10px 28px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
    .print-btn button:hover { background: #272522; }
    @media print {
      body { background: #fff; padding: 0; }
      .receipt { border: none; border-radius: 0; padding: 24px; }
      .print-btn { display: none; }
    }
  </style>
</head>
<body>
@php
  $receiptNumber = 'APT-'.str_pad((string) $appointment->id, 6, '0', STR_PAD_LEFT);
  $service = $appointment->serviceType;
  $amount = (float) ($appointment->appointment_amount ?? $service->default_fee ?? 0);
  $status = $appointment->status ?? 'scheduled';
  $statusClass = match($status) {
    'confirmed' => 'badge-confirmed',
    'completed' => 'badge-completed',
    'cancelled' => 'badge-cancelled',
    default => 'badge-scheduled',
  };
@endphp

<div class="receipt">
  <div class="logo">Ferosa</div>
  <div class="sub">Garden & Landscaping &middot; ferosa.app</div>

  <div class="row">
    <span class="label">Receipt</span>
    <span class="value" style="font-family:monospace">{{ $receiptNumber }}</span>
  </div>
  <div class="row">
    <span class="label">Date</span>
    <span class="value">{{ optional($appointment->created_at)->format('F j, Y') }}</span>
  </div>
  <div class="row">
    <span class="label">Customer</span>
    <span class="value">{{ $appointment->user->name }}</span>
  </div>
  <div class="row">
    <span class="label">Email</span>
    <span class="value">{{ $appointment->user->email }}</span>
  </div>
  <div class="row">
    <span class="label">Appointment</span>
    <span class="value">{{ optional($appointment->appointment_at)->format('F j, Y g:i A') }}</span>
  </div>
  <div class="row">
    <span class="label">Service</span>
    <span class="value">{{ $service->name ?? 'Service appointment' }}</span>
  </div>
  <div class="row">
    <span class="label">Status</span>
    <span class="value">
      <span class="badge {{ $statusClass }}">{{ ucfirst($status) }}</span>
    </span>
  </div>

  <hr class="divider">

  <table>
    <thead>
      <tr>
        <th>Service</th>
        <th>Amount</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>{{ $service->name ?? 'Service appointment' }}</td>
        <td>PHP {{ number_format($amount, 2) }}</td>
      </tr>
      <tr class="total-row">
        <td>Total</td>
        <td>PHP {{ number_format($amount, 2) }}</td>
      </tr>
    </tbody>
  </table>

  <hr class="divider">

  <div class="row">
    <span class="label">Payment Status</span>
    <span class="value"><span class="badge badge-paid">Paid</span></span>
  </div>
  @if ($appointment->notes)
    <div class="row">
      <span class="label">Notes</span>
      <span class="value" style="max-width:60%;text-align:right">{{ $appointment->notes }}</span>
    </div>
  @endif

  <div class="footer">
    Thank you for choosing Ferosa Garden & Landscaping.<br>
    This receipt was generated on {{ now()->format('F j, Y \a\t g:i A') }}.
  </div>
</div>

<div class="print-btn">
  <button onclick="window.print()">
    Print / Save as PDF
  </button>
  &nbsp;
  <a href="{{ route('appointments') }}" style="font-size:13px;color:#746f65;text-decoration:none;margin-left:12px">Back to Appointments</a>
</div>

</body>
</html>
