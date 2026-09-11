<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #181714;">
  <p>Hi {{ $appointment->user->name ?? 'there' }},</p>
  <p>Your landscaping appointment is booked.</p>
  <p>
    <strong>Service:</strong> {{ $appointment->serviceType->name ?? 'Service' }}<br>
    <strong>Date:</strong> {{ $appointment->appointment_at ? \Carbon\Carbon::parse($appointment->appointment_at)->format('l, F j, Y \a\t g:i A') : '' }}<br>
    <strong>Status:</strong> {{ ucfirst($appointment->status) }}
  </p>
  @if ($appointment->notes)
    <p><strong>Your notes:</strong> {{ $appointment->notes }}</p>
  @endif
  <p>We will contact you if anything changes.</p>
  <p>— Ferosa Landscaping</p>
</body>
</html>
