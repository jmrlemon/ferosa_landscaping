<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #181714;">
  <p>Hi {{ $order->user->name ?? 'there' }},</p>
  <p>Thank you for your order. We received it and will prepare it for fulfillment.</p>
  <p><strong>Order number:</strong> {{ $order->order_number }}<br>
     <strong>Total:</strong> PHP {{ number_format((float) $order->total_amount, 2) }}<br>
     <strong>Payment:</strong> {{ ucfirst(str_replace('_', ' ', $order->payment_status ?? 'unpaid')) }}</p>
  @if($order->payment_method === 'gcash')
    <p>Your GCash receipt was received privately. An administrator will verify the reference and amount before fulfillment.</p>
  @endif
  @if (is_array($order->items) && count($order->items))
    <p><strong>Items</strong></p>
    <ul>
      @foreach ($order->items as $line)
        <li>{{ $line['name'] ?? 'Item' }} × {{ (int) ($line['qty'] ?? 1) }} — PHP {{ number_format((float) ($line['price'] ?? 0) * (int) ($line['qty'] ?? 1), 2) }}</li>
      @endforeach
    </ul>
  @endif
  <p>You can track status anytime under <em>Orders</em> in your account.</p>
  <p>— Ferosa Landscaping</p>
</body>
</html>
