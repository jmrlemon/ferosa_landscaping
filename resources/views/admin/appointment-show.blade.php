<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @include('partials.favicon')
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Manage Schedule - Ferosa Landscaping</title>

  <link rel="stylesheet" href="{{ asset('fonts/ferosa-fonts.css') }}">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @include('admin.partials.premium-theme')
</head>
@php
  $statusTone = match($appointment->status) {
    'scheduled' => 'bg-amber-100 text-amber-800',
    'confirmed' => 'bg-blue-100 text-blue-800',
    'completed' => 'bg-brand-100 text-brand-800',
    'cancelled' => 'bg-red-100 text-red-700',
    default => 'bg-surface-100 text-surface-700',
  };
  $amount = (float) ($appointment->appointment_amount ?? $appointment->serviceType->default_fee ?? 0);
  $isAdmin = (bool) ($isAdmin ?? auth()->user()?->isAdmin());
  $availableStatuses = array_values(array_unique([$appointment->status, ...($appointment::STATUS_TRANSITIONS[$appointment->status] ?? [])]));
  $hasOperationalTransition = count($availableStatuses) > 1;
  $selectedPaymentStatus = $isAdmin
    ? old('payment_status', $appointment->payment_status ?? 'unpaid')
    : ($appointment->payment_status ?? 'unpaid');
  $requestedStatus = old('status');
  $selectedStatus = is_string($requestedStatus) && in_array($requestedStatus, $availableStatuses, true)
    ? $requestedStatus
    : $appointment->status;
  if ($selectedStatus === 'completed' && $selectedPaymentStatus !== 'paid' && $appointment->status !== 'completed') {
    $selectedStatus = $appointment->status;
  }
  $closedStatusLabel = $appointment->status === 'cancelled' ? 'cancelled' : 'complete';
@endphp
<body class="min-h-screen bg-surface-100 font-sans text-surface-900 antialiased">
  <a href="#admin-main" class="skip-link">Skip to admin content</a>
  <header class="flex h-14 items-center justify-between border-b border-surface-200 bg-white px-5">
    <h1 class="text-sm font-semibold text-surface-600">Service Scheduling</h1>
    <div class="flex items-center gap-2">
      <span class="rounded-md bg-brand-600 px-2.5 py-1 text-xs font-bold text-white">Ferosa Landscaping</span>
    </div>
  </header>

  <main id="admin-main" tabindex="-1" class="p-5">
    @if (session('status'))
      <div class="mb-4 rounded-lg border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
      <div class="mb-4 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
        <p class="font-semibold">Please check the details and try again.</p>
        <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
      </div>
    @endif

    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div class="flex items-center gap-4">
        <a href="{{ route('admin.service-scheduling') }}" class="inline-flex h-9 w-9 items-center justify-center rounded border border-surface-400 text-surface-600 hover:bg-white" aria-label="Back to service scheduling">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
        </a>
        <div>
          <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-2xl font-bold text-brand-950">Schedule #{{ str_pad((string) $appointment->id, 5, '0', STR_PAD_LEFT) }}</h2>
            <span class="rounded-md px-3 py-1 text-sm font-semibold {{ $statusTone }}">{{ ucfirst($appointment->status) }}</span>
          </div>
          <p class="mt-1 text-sm text-surface-500">{{ $isAdmin ? 'Manage the customer booking, payment status, service details, and site visit notes.' : 'Confirm visits, coordinate schedules, and keep the customer informed.' }}</p>
        </div>
      </div>

      @if($isStaffOrAdmin)
        <div class="flex flex-wrap gap-2">
          @if($appointment->status === 'scheduled')
            <form method="POST" action="{{ route('admin.appointments.status', $appointment) }}">
              @csrf @method('PUT')
              <input type="hidden" name="redirect_to" value="show">
              <input type="hidden" name="status" value="confirmed">
              @if($isAdmin)<input type="hidden" name="payment_status" value="{{ $appointment->payment_status ?? 'unpaid' }}">@endif
              <button class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Confirm</button>
            </form>
          @endif
          @if(! in_array($appointment->status, ['cancelled', 'completed'], true))
            <form method="POST" action="{{ route('admin.appointments.status', $appointment) }}"
                  data-confirm-title="Cancel this booking?"
                  data-confirm="{{ $appointment->user?->name ?? 'This customer' }}'s {{ $appointment->serviceType?->name ?? 'booking' }} on {{ $appointment->appointment_at?->format('M j, Y \a\t g:i A') }} will be cancelled. The customer is notified."
                  data-confirm-action="Cancel booking">
              @csrf @method('PUT')
              <input type="hidden" name="redirect_to" value="show">
              <input type="hidden" name="status" value="cancelled">
              @if($isAdmin)<input type="hidden" name="payment_status" value="{{ $appointment->payment_status ?? 'unpaid' }}">@endif
              <button class="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Cancel</button>
            </form>
          @endif
        </div>
      @endif
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
      <div class="space-y-6 xl:col-span-3">
        <section class="overflow-hidden rounded-xl border border-surface-100 bg-white shadow-sm">
          <div class="border-b border-surface-200 px-5 py-4"><h3 class="font-semibold">Service Details</h3></div>
          <div class="flex items-start gap-4 p-5">
            <div class="flex h-20 w-20 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
              <svg class="h-9 w-9" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.25 7.5 18 8.25l-.25-.75a2 2 0 0 0-1.25-1.25L15.75 6l.75-.25a2 2 0 0 0 1.25-1.25L18 3.75l.25.75a2 2 0 0 0 1.25 1.25l.75.25-.75.25a2 2 0 0 0-1.25 1.25Z"/></svg>
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-lg font-bold">{{ $appointment->serviceType->name ?? 'Service booking' }}</p>
              <p class="mt-1 text-sm text-surface-500">{{ $appointment->serviceType->description ?? 'Customer-bookable landscaping service.' }}</p>
              <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <p class="text-xs text-surface-400">Schedule</p>
                  <p class="font-semibold">{{ optional($appointment->appointment_at)->format('M d, Y') ?? 'No date' }}</p>
                  <p class="text-sm text-surface-500">{{ optional($appointment->appointment_at)->format('h:i A') ?? '' }}</p>
                </div>
                <div>
                  <p class="text-xs text-surface-400">Service Fee</p>
                  <p class="font-semibold text-brand-800">PHP {{ number_format($amount, 2) }}</p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-surface-100 bg-white shadow-sm">
          <div class="border-b border-surface-200 px-5 py-4"><h3 class="font-semibold">Booking Summary</h3></div>
          <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2">
            <div>
              <p class="text-xs text-surface-400">Payment Status</p>
              <p class="text-lg font-semibold">{{ ucfirst($appointment->payment_status ?? 'unpaid') }}</p>
            </div>
            <div>
              <p class="text-xs text-surface-400">Created</p>
              <p class="text-lg font-semibold">{{ optional($appointment->created_at)->format('M d, Y h:i A') }}</p>
            </div>
            <div class="sm:col-span-2">
              <p class="text-xs text-surface-400">Visit Location</p>
              <p class="mt-1 rounded-lg border border-brand-100 bg-brand-50 p-3 text-sm font-medium text-brand-900">{{ $appointment->site_address ?: 'No visit location recorded.' }}</p>
            </div>
            <div class="sm:col-span-2">
              <p class="text-xs text-surface-400">Customer Notes</p>
              <p class="mt-1 rounded-lg border border-surface-100 bg-surface-50 p-3 text-sm text-surface-700">{{ $appointment->notes ?: 'No notes provided.' }}</p>
            </div>
            @if($appointment->scope_notes)
              <div class="sm:col-span-2">
                <p class="text-xs text-surface-400">Confirmed Scope</p>
                <p class="mt-1 whitespace-pre-line rounded-lg border border-brand-100 bg-brand-50 p-3 text-sm text-brand-800">{{ $appointment->scope_notes }}</p>
              </div>
            @endif
            @if($appointment->cancel_reason)
              <div class="sm:col-span-2">
                <p class="text-xs text-red-400">Cancel Reason</p>
                <p class="mt-1 rounded-lg border border-red-100 bg-red-50 p-3 text-sm text-red-700">{{ $appointment->cancel_reason }}</p>
              </div>
            @endif
          </div>

          @include('admin.partials.payment-ledger', [
            'payable' => $appointment,
            'storeRoute' => route('admin.appointments.payments.store', $appointment),
            'invoiceRoute' => route('appointments.invoice', $appointment),
            'isAdmin' => $isAdmin,
          ])
        </section>

        @if($isAdmin && $history->isNotEmpty())
          <section class="overflow-hidden rounded-xl border border-surface-100 bg-white shadow-sm">
            <div class="border-b border-surface-200 px-5 py-4"><h3 class="font-semibold">Activity History</h3></div>
            <div class="divide-y divide-surface-100">
              @foreach($history as $entry)
                <div class="px-5 py-3">
                  <p class="text-sm font-medium text-surface-800">{{ $entry->description }}</p>
                  <p class="mt-1 text-xs text-surface-400">{{ $entry->actor->name ?? 'System' }} · {{ optional($entry->created_at)->format('M d, Y h:i A') }}</p>
                </div>
              @endforeach
            </div>
          </section>
        @endif
      </div>

      <aside class="space-y-6 xl:col-span-2">
        <section class="rounded-xl border border-surface-100 bg-white shadow-sm">
          <div class="border-b border-surface-200 px-5 py-4"><h3 class="font-semibold">Customer Information</h3></div>
          <div class="p-5">
            <div class="flex items-center gap-3">
              <div class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-800 text-lg font-bold text-white">{{ strtoupper(substr($appointment->user->name ?? 'C', 0, 1)) }}</div>
              <div>
                <p class="font-semibold">{{ $appointment->user->name ?? 'Customer' }}</p>
                <p class="text-sm text-surface-500">{{ $appointment->user->email ?? '' }}</p>
              </div>
            </div>
            <div class="mt-4 space-y-2 text-sm text-surface-700">
              <p>{{ $appointment->user->phone_number ?? 'No phone number' }}</p>
              <a href="{{ route('admin.dashboard', ['tab' => 'messages']) }}" class="flex w-full items-center justify-center rounded-lg border border-brand-600 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">Send Message</a>
            </div>
          </div>
        </section>

        <section class="rounded-xl border border-surface-100 bg-white shadow-sm">
          <div class="border-b border-surface-200 px-5 py-4"><h3 class="font-semibold">Update Schedule</h3></div>
          <form method="POST" action="{{ route('admin.appointments.status', $appointment) }}" class="space-y-4 p-5">
            @csrf @method('PUT')
            <input type="hidden" name="redirect_to" value="show">
            <label class="block text-sm font-medium">Status
              <select id="appointment-status-select" name="status" data-current-status="{{ $appointment->status }}" data-payment-status="{{ $selectedPaymentStatus }}" @if(in_array('completed', $availableStatuses, true)) aria-describedby="appointment-completion-payment-notice" @endif class="mt-2 h-10 w-full rounded-lg border border-surface-200 px-3 outline-none focus:border-brand-600" {{ $isAdmin || $hasOperationalTransition ? '' : 'disabled' }}>
                @foreach($availableStatuses as $status)
                  <option value="{{ $status }}" @if($status === 'completed') data-requires-paid @disabled($selectedPaymentStatus !== 'paid' && $appointment->status !== 'completed') @endif {{ $selectedStatus === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                @endforeach
              </select>
            </label>
            @if(in_array('completed', $availableStatuses, true))
              <p id="appointment-completion-payment-notice" role="status" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800" @if($selectedPaymentStatus === 'paid' || $appointment->status === 'completed') hidden @endif>Payment must be marked Paid before this appointment can be completed. Record the payment first.</p>
            @endif
            @if($isAdmin)
              <label class="block text-sm font-medium">Payment Status
                <select name="payment_status" data-appointment-payment-status-select class="mt-2 h-10 w-full rounded-lg border border-surface-200 px-3 outline-none focus:border-brand-600">
                  <option value="unpaid" {{ $selectedPaymentStatus === 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                  <option value="paid" {{ $selectedPaymentStatus === 'paid' ? 'selected' : '' }}>Paid</option>
                </select>
              </label>
            @else
              <p class="rounded-lg border border-surface-200 bg-surface-50 px-3 py-2 text-xs text-surface-500">Payment review is handled by an administrator.</p>
            @endif
            @if($isAdmin || $hasOperationalTransition)
              <button class="w-full rounded-lg bg-brand-700 py-2.5 font-semibold text-white hover:bg-brand-800">Save Changes</button>
            @else
              <p class="rounded-lg border border-surface-200 bg-surface-50 px-3 py-2 text-xs text-surface-500">This visit is {{ $closedStatusLabel }}. No further staff action is needed.</p>
            @endif
          </form>
        </section>
        @if($isStaffOrAdmin && in_array($appointment->status, ['scheduled', 'confirmed'], true))
          <section class="rounded-xl border border-surface-100 bg-white shadow-sm">
            <div class="border-b border-surface-200 px-5 py-4">
              <h3 class="font-semibold">Move Visit</h3>
              <p class="mt-1 text-xs text-surface-500">
                Customers can move their own visit until it is
                {{ \App\Models\Appointment::CHANGE_NOTICE_HOURS }} hours away. After that they are
                told to message the team - this is how you do it for them. The same booking, its
                fee and its scope all stay as they are.
              </p>
            </div>
            <form method="POST" action="{{ route('admin.appointments.reschedule', $appointment) }}" class="space-y-4 p-5">
              @csrf @method('PUT')
              <div class="grid grid-cols-2 gap-3">
                <label class="block text-sm font-medium">Date
                  <input type="date"
                         name="move_date"
                         required
                         min="{{ now()->format('Y-m-d') }}"
                         value="{{ old('move_date', $appointment->appointment_at->format('Y-m-d')) }}"
                         class="mt-2 h-10 w-full rounded-lg border border-surface-200 px-3 outline-none focus:border-brand-600">
                </label>
                <label class="block text-sm font-medium">Time
                  <select name="move_time" required
                          class="mt-2 h-10 w-full rounded-lg border border-surface-200 px-3 outline-none focus:border-brand-600">
                    @foreach(\App\Models\Appointment::SLOT_TIMES as $slot)
                      <option value="{{ $slot }}"
                        @selected(old('move_time', $appointment->appointment_at->format('H:i')) === $slot)>
                        {{ \Carbon\Carbon::createFromFormat('H:i', $slot)->format('g:i A') }}
                      </option>
                    @endforeach
                  </select>
                </label>
              </div>
              {{-- The two controls are joined into one appointment_at by
                   MoveAppointmentRequest, so this form needs no JavaScript. --}}
              <p class="text-xs text-surface-500">
                Currently {{ $appointment->appointment_at->format('M d, Y \a\t g:i A') }}. The customer is notified of the new time.
                No crew is dispatched on {{ implode(' or ', \App\Models\Appointment::closedDayNames()) }}, so those days are refused.
              </p>
              <button class="w-full rounded-lg bg-brand-700 py-2.5 font-semibold text-white hover:bg-brand-800">Move Visit</button>
            </form>
          </section>
          @if($isAdmin)
          <section class="rounded-xl border border-surface-100 bg-white shadow-sm">
            <div class="border-b border-surface-200 px-5 py-4">
              <h3 class="font-semibold">Adjust Scope &amp; Cost</h3>
              <p class="mt-1 text-xs text-surface-500">One visit, one slot. Add the extra work the customer asked for here rather than booking a second appointment.</p>
            </div>
            <form method="POST" action="{{ route('admin.appointments.scope', $appointment) }}" class="space-y-4 p-5">
              @csrf @method('PUT')
              <label class="block text-sm font-medium">Confirmed scope
                <textarea name="scope_notes"
                          rows="4"
                          maxlength="1000"
                          placeholder="e.g. Hardscaping (front walkway) + Lawn Care (front and side lawn)"
                          class="mt-2 w-full rounded-lg border border-surface-200 p-3 text-sm outline-none focus:border-brand-600">{{ old('scope_notes', $appointment->scope_notes) }}</textarea>
              </label>
              <label class="block text-sm font-medium">Total service fee (PHP)
                <input type="number"
                       name="appointment_amount"
                       step="0.01"
                       min="0"
                       required
                       value="{{ old('appointment_amount', number_format($amount, 2, '.', '')) }}"
                       class="mt-2 h-10 w-full rounded-lg border border-surface-200 px-3 outline-none focus:border-brand-600">
              </label>
              <p class="text-xs text-surface-500">Booking fee was PHP {{ number_format((float) ($appointment->serviceType->default_fee ?? 0), 2) }}. The customer is notified of the new total.</p>
              <button class="w-full rounded-lg bg-brand-700 py-2.5 font-semibold text-white hover:bg-brand-800">Save Scope</button>
            </form>
          </section>
          @endif
        @endif
      </aside>
    </div>
  </main>
  <script>
    const appointmentStatusSelect = document.getElementById('appointment-status-select');
    const appointmentPaymentSelect = document.querySelector('[data-appointment-payment-status-select]');
    const completedStatusOption = appointmentStatusSelect?.querySelector('option[value="completed"]');
    const completionPaymentNotice = document.getElementById('appointment-completion-payment-notice');

    function syncAppointmentCompletionGate() {
      const paymentStatus = appointmentPaymentSelect?.value || appointmentStatusSelect?.dataset.paymentStatus || 'unpaid';
      const isPaid = paymentStatus === 'paid';
      const isAlreadyCompleted = appointmentStatusSelect?.dataset.currentStatus === 'completed';

      if (completedStatusOption) completedStatusOption.disabled = !isPaid && !isAlreadyCompleted;
      if (completionPaymentNotice) completionPaymentNotice.hidden = isPaid || isAlreadyCompleted;

      if (!isPaid && appointmentStatusSelect?.value === 'completed' && !isAlreadyCompleted) {
        appointmentStatusSelect.value = appointmentStatusSelect.dataset.currentStatus;
      }
    }

    if (appointmentPaymentSelect) {
      appointmentPaymentSelect.addEventListener('change', syncAppointmentCompletionGate);
    }
    syncAppointmentCompletionGate();
  </script>
  @include('partials.confirm-dialog')
</body>
</html>
