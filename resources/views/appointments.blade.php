@extends('layouts.customer')

@section('title', 'My Appointments - Ferosa Landscaping')

@section('content')
<main class="customer-page">
  <x-page-head
    kicker="Your bookings"
    title="Appointments"
    sub="Track every scheduled visit, follow its progress, and manage changes in one place.">
    <x-slot:icon>
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18m-9.5 5.5 1.5 1.5 3-3"/>
      </svg>
    </x-slot:icon>
    <a href="{{ route('schedule') }}" class="btn btn-primary btn-sm">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14"/></svg>
      Book a service
    </a>
  </x-page-head>

  @if (session('status'))
    <x-alert type="success" class="mb-6 reveal">{{ session('status') }}</x-alert>
  @endif
  @if (session('error'))
    <x-alert type="error" class="mb-6 reveal">{{ session('error') }}</x-alert>
  @endif

  {{-- Status quick filters --}}
  <div class="mb-4 flex flex-wrap items-center gap-2 reveal reveal-1">
    <a href="{{ route('appointments', array_filter(['from' => request('from'), 'to' => request('to')])) }}"
       class="chip {{ request('status') ? '' : 'chip-active' }}">All</a>
    @foreach (['scheduled' => 'Scheduled', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $value => $label)
      <a href="{{ route('appointments', array_filter(['status' => $value, 'from' => request('from'), 'to' => request('to')])) }}"
         class="chip {{ request('status') === $value ? 'chip-active' : '' }}">{{ $label }}</a>
    @endforeach
  </div>

  {{-- Date range --}}
  <form method="GET" action="{{ route('appointments') }}" class="toolbar mb-6 reveal reveal-1" data-loading-label="Filtering...">
    <input type="hidden" name="status" value="{{ request('status') }}">
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-12 sm:items-end">
      <div class="sm:col-span-4">
        <label for="appt-from" class="field-label">From date</label>
        <input type="date" id="appt-from" name="from" value="{{ request('from') }}" class="field">
      </div>
      <div class="sm:col-span-4">
        <label for="appt-to" class="field-label">To date</label>
        <input type="date" id="appt-to" name="to" value="{{ request('to') }}" class="field">
      </div>
      <div class="col-span-2 flex gap-2 sm:col-span-4">
        <button class="btn btn-primary btn-sm flex-1" data-loading-label="Filtering...">Apply dates</button>
        @if(request('from') || request('to'))
          <a href="{{ route('appointments', array_filter(['status' => request('status')])) }}" class="btn btn-ghost btn-sm">Reset</a>
        @endif
      </div>
    </div>
  </form>

  {{-- List --}}
  <div class="space-y-4 reveal reveal-2">
    @forelse ($appointments as $appt)
      @php
        $st = $appt->status;
        $badge = match($st) {
          'confirmed'  => 'badge-info',
          'completed'  => 'badge-success',
          'cancelled'  => 'badge-danger',
          default      => 'badge-warning',
        };
        $isUpcoming = in_array($st, ['scheduled','confirmed'])
          && $appt->appointment_at
          && \Carbon\Carbon::parse($appt->appointment_at)->isFuture();
        $isPastOpen = in_array($st, ['scheduled','confirmed'])
          && $appt->appointment_at
          && ! \Carbon\Carbon::parse($appt->appointment_at)->isFuture();
        $statusLabel = $isPastOpen ? 'Past visit - awaiting team update' : ucfirst($st);
        $appointmentAmount = (float) ($appt->appointment_amount ?? 0);
        $hasCharge = $appointmentAmount > 0;
        $paymentLabel = $hasCharge
          ? ucfirst(str_replace('_', ' ', $appt->payment_status ?? 'unpaid'))
          : ($st === 'completed' ? 'No payment due' : 'Not due yet');
        $amountLabel = $hasCharge
          ? 'PHP '.number_format($appointmentAmount, 2)
          : ($st === 'completed' ? 'No charge' : 'To be confirmed');
        $canReschedule = $appt->isCustomerReschedulable();
        $canCancel = $appt->isCustomerChangeable();
        $step = match($st) {
          'confirmed' => 2,
          'completed' => 3,
          default     => 1,
        };
      @endphp

      <div class="customer-card lift overflow-hidden">

        {{-- Card Header --}}
        <div class="flex flex-col gap-3 border-b border-surface-100 p-4 sm:flex-row sm:items-start sm:justify-between sm:p-5">
          <div class="flex min-w-0 items-start gap-3.5">
            @php $apptDate = $appt->appointment_at ? \Carbon\Carbon::parse($appt->appointment_at) : null; @endphp
            <div class="flex h-12 w-12 flex-shrink-0 flex-col items-center justify-center rounded-xl border {{ $isUpcoming ? 'border-brand-100 bg-brand-50 text-brand-700' : 'border-surface-200 bg-surface-50 text-surface-500' }}">
              <span class="text-[9px] font-bold uppercase tracking-wide leading-none">{{ $apptDate?->format('M') ?? '—' }}</span>
              <span class="mt-0.5 font-display text-lg font-bold leading-none">{{ $apptDate?->format('j') ?? '' }}</span>
            </div>
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-[15px] font-bold text-surface-900">
                  {{ $appt->serviceType->name ?? 'Service' }}
                </h3>
                <span class="badge {{ $isPastOpen ? 'badge-danger' : $badge }}">{{ $statusLabel }}</span>
                @if ($isUpcoming)
                  <span class="badge badge-success">Upcoming</span>
                @endif
              </div>
              <p class="mt-1 text-xs font-medium text-surface-500">
                {{ $apptDate ? $apptDate->format('D, M j, Y \a\t g:i A') : '—' }}
              </p>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-1.5 self-start sm:self-center">
            @if ((float) ($appt->appointment_amount ?? 0) > 0)
              <a href="{{ route('appointments.invoice', $appt) }}" class="btn btn-secondary btn-sm">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h4"/></svg>
                Invoice
              </a>
            @endif
            @if (($appt->payment_status ?? 'unpaid') === 'paid')
              <a href="{{ route('appointments.receipt', $appt) }}" class="btn btn-secondary btn-sm">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h6"/></svg>
                Receipt
              </a>
            @endif

            {{-- Feedback / Reviewed --}}
            @if ($st === 'completed' && !$appt->feedback)
              <button onclick="openApptFeedbackModal({{ $appt->id }}, '{{ addslashes($appt->serviceType->name ?? 'Service') }}')"
                class="btn btn-sm border-amber-200 bg-amber-50 text-amber-700 hover:border-amber-300 hover:bg-amber-100">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                Leave feedback
              </button>
            @elseif ($st === 'completed' && $appt->feedback)
              <span class="badge badge-success">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8"><polyline points="20 6 9 17 4 12"/></svg>
                Reviewed
              </span>
            @endif

            {{-- Customer changes close when the team confirms the booking. --}}
            @if ($isUpcoming && $st === 'confirmed')
              <span class="text-xs text-surface-400">
                Confirmed bookings can no longer be cancelled or rescheduled online. Message the team if you need help.
              </span>
            @elseif ($isUpcoming && ! $canCancel)
              <span class="text-xs text-surface-400">
                Less than {{ \App\Models\Appointment::CHANGE_NOTICE_HOURS }} hours away - message the team to change it.
              </span>
            @endif
            @if ($canReschedule)
              <a href="{{ route('schedule', ['reschedule' => $appt->id]) }}" class="btn btn-secondary btn-sm">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/><polyline points="9 16 11 18 15 14"/></svg>
                Reschedule
              </a>
            @endif
            @if ($canCancel)
              <button onclick="openCancelApptModal({{ $appt->id }}, '{{ addslashes($appt->serviceType->name ?? 'Appointment') }}')"
                class="btn btn-danger btn-sm">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                Cancel
              </button>
            @endif
          </div>
        </div>

        {{-- Card Body --}}
        <div class="p-4 sm:p-5 grid grid-cols-1 sm:grid-cols-2 gap-5">

          {{-- Details --}}
          <div>
            <h4 class="text-xs font-semibold text-surface-900 mb-2">Details</h4>
            <div class="rounded-lg border border-surface-100 p-3 space-y-2 text-xs">
              <div class="flex gap-2">
                <span class="text-surface-400 w-20 shrink-0">Service</span>
                <span class="text-surface-700 font-medium">{{ $appt->serviceType->name ?? '—' }}</span>
              </div>
              <div class="flex gap-2">
                <span class="text-surface-400 w-20 shrink-0">Date & Time</span>
                <span class="text-surface-700">{{ $appt->appointment_at ? \Carbon\Carbon::parse($appt->appointment_at)->format('M j, Y · g:i A') : '—' }}</span>
              </div>
              <div class="flex gap-2">
                <span class="text-surface-400 w-20 shrink-0">Payment</span>
                <span class="text-surface-700 font-medium">{{ $paymentLabel }}</span>
              </div>
              <div class="flex gap-2">
                <span class="text-surface-400 w-20 shrink-0">Amount</span>
                <span class="text-surface-700 font-medium">{{ $amountLabel }}</span>
              </div>
              @if($appt->discountRequest)
                <div class="flex gap-2">
                  <span class="text-surface-400 w-20 shrink-0">Discount</span>
                  <span class="text-surface-700 font-medium">
                    {{ $appt->discountRequest->beneficiaryLabel() }} · {{ ucfirst($appt->discountRequest->status) }}
                    @if($appt->status === 'cancelled')
                      · request cannot be applied to a cancelled appointment
                    @elseif($appt->activeDiscount)
                      · PHP {{ number_format((float) $appt->activeDiscount->discount_amount, 2) }} applied
                    @elseif($appt->discountRequest->status === \App\Models\DiscountRequest::STATUS_REJECTED && $appt->discountRequest->rejection_reason)
                      · {{ $appt->discountRequest->rejection_reason }}
                    @endif
                  </span>
                </div>
              @endif
              @if ((float) ($appt->appointment_amount ?? 0) > 0 && $appt->balanceDue() > 0 && $appt->totalPaid() > 0)
                <div class="flex gap-2">
                  <span class="text-surface-400 w-20 shrink-0">Balance</span>
                  <span class="font-semibold text-orange-600">PHP {{ number_format($appt->balanceDue(), 2) }} due</span>
                </div>
              @endif
              @if ($appt->scope_notes)
                <div class="flex gap-2 pt-1 border-t border-surface-100">
                  <span class="text-surface-400 w-20 shrink-0">Scope</span>
                  <span class="whitespace-pre-line font-medium text-brand-700">{{ $appt->scope_notes }}</span>
                </div>
              @endif
              @if ($appt->notes)
                <div class="flex gap-2 pt-1 border-t border-surface-100">
                  <span class="text-surface-400 w-20 shrink-0">Notes</span>
                  <span class="text-surface-600 italic">"{{ $appt->notes }}"</span>
                </div>
              @endif
            </div>
          </div>

          {{-- Timeline --}}
          <div>
            <h4 class="text-xs font-semibold text-surface-900 mb-2">Progress</h4>
            @php
              $steps = [
                ['label' => 'Scheduled',  'desc' => optional($appt->appointment_at)->format('M d, Y g:i A')],
                ['label' => 'Confirmed',  'desc' => $step >= 2 ? 'Appointment confirmed' : 'Pending'],
                ['label' => 'Completed',  'desc' => $step >= 3 ? 'Service completed' : 'Pending'],
              ];
            @endphp
            <div class="relative ml-1.5">
              <div class="absolute left-[5px] top-1 bottom-1 w-px bg-surface-200"></div>
              <div class="space-y-4">
                @foreach ($steps as $i => $s)
                  @php
                    $idx  = $i + 1;
                    $done = $step > $idx || ($st === 'cancelled' && false);
                    $active = $step === $idx && $st !== 'cancelled';
                    if ($st === 'cancelled') { $done = false; $active = false; }
                    $circle = $done ? 'bg-brand-600 ring-brand-50 border-brand-600' :
                              ($active ? 'bg-brand-600 ring-brand-50 border-brand-600' : 'bg-white border-surface-300 ring-white');
                    $text   = $done || $active ? ($active ? 'text-brand-700' : 'text-surface-900') : 'text-surface-350';
                  @endphp
                  <div class="relative flex gap-3">
                    <div class="relative z-10 w-3 h-3 mt-0.5 rounded-full ring-2 {{ $circle }} flex items-center justify-center border">
                      @if ($done)
                        <svg width="7" height="7" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                      @elseif ($active)
                        <div class="w-1 h-1 bg-white rounded-full"></div>
                      @endif
                    </div>
                    <div class="min-w-0">
                      <p class="text-xs font-medium {{ $text }}">{{ $s['label'] }}</p>
                      <p class="text-[10px] {{ $active ? 'text-surface-600' : 'text-surface-400' }}">{{ $s['desc'] }}</p>
                      @if ($st === 'cancelled' && $i === 0)
                        <p class="text-[10px] text-red-600 font-semibold mt-0.5">Cancelled</p>
                      @endif
                    </div>
                  </div>
                @endforeach
              </div>
            </div>
          </div>

        </div>
      </div>
    @empty
      <div class="customer-empty">
        <div class="customer-empty-icon">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <h2 class="text-base font-bold text-surface-900 mb-1">No appointments yet</h2>
        <p class="text-surface-500 text-sm mb-5">Book a service and your schedule will appear here.</p>
        <a href="{{ route('schedule') }}" class="btn btn-primary btn-sm">Book a service</a>
      </div>
    @endforelse
  </div>

  @if (method_exists($appointments, 'links'))
    <div class="mt-6">{{ $appointments->links() }}</div>
  @endif
</main>

{{-- ── Cancel Appointment Modal ────────────────────────────────────── --}}
<div id="cancel-appt-modal" class="fixed inset-0 z-50 hidden flex items-end sm:items-center justify-center sm:p-4">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeCancelApptModal()"></div>
  <div class="relative bg-white w-full sm:max-w-sm rounded-t-2xl sm:rounded-2xl shadow-2xl">
    <div class="flex justify-center pt-3 pb-1 sm:hidden">
      <div class="w-10 h-1 bg-surface-200 rounded-full"></div>
    </div>
    <div class="px-4 sm:px-6 pt-4 sm:pt-5 pb-5 sm:pb-6">
      <div class="flex items-start gap-3 mb-3">
        <div class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center shrink-0">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-red-500"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div>
          <h3 class="text-base font-semibold text-surface-900">Cancel Appointment?</h3>
          <p class="text-xs text-surface-400 mt-0.5" id="cancel-appt-name"></p>
        </div>
      </div>
      <p class="text-sm text-surface-500 mb-5">Are you sure you want to cancel this appointment? This action cannot be undone.</p>
      <form id="cancel-appt-form" method="POST">
        @csrf
        @method('DELETE')
        <div class="mb-4">
          <label for="appt-cancel-reason" class="block text-xs font-medium text-surface-600 mb-1">Reason for cancellation <span class="text-red-500">*</span></label>
          <textarea id="appt-cancel-reason" name="cancel_reason" rows="3" required minlength="3" maxlength="500"
            class="w-full border border-surface-200 rounded-xl px-3 py-2.5 text-sm text-surface-700 outline-none focus:border-red-400 focus:ring-1 focus:ring-red-100 resize-none transition-all"
            placeholder="Example: I need to reschedule this service."></textarea>
        </div>
        <div class="flex flex-col-reverse sm:flex-row gap-2 sm:gap-3">
          <button type="button" onclick="closeCancelApptModal()"
            class="py-3 sm:py-2.5 sm:px-5 rounded-xl border border-surface-200 text-sm font-medium text-surface-500 hover:bg-surface-50 transition-colors touch-manipulation">
            Keep Appointment
          </button>
          <button type="submit" data-loading-label="Cancelling..."
            class="flex-1 bg-red-600 hover:bg-red-700 text-white text-sm font-medium py-3 sm:py-2.5 rounded-xl transition-colors touch-manipulation">
            Yes, Cancel
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- ── Feedback Modal ──────────────────────────────────────────────── --}}
<div id="appt-feedback-modal" class="fixed inset-0 z-50 hidden flex items-end sm:items-center justify-center sm:p-4">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeApptFeedbackModal()"></div>
  <div class="relative bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl shadow-2xl">
    <div class="flex justify-center pt-3 pb-1 sm:hidden">
      <div class="w-10 h-1 bg-surface-200 rounded-full"></div>
    </div>
    <div class="px-4 sm:px-6 pt-2 sm:pt-5 pb-5 sm:pb-6">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-base font-semibold text-surface-900">Rate Your Appointment</h3>
          <p class="text-xs text-surface-400 mt-0.5" id="appt-modal-service-name"></p>
        </div>
        <button type="button" aria-label="Close dialog" onclick="closeApptFeedbackModal()" class="p-1.5 text-surface-400 hover:text-surface-600 transition-colors rounded-lg">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <form method="POST" action="{{ route('feedback.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="appointment_id" id="appt-modal-appointment-id">
        <div>
          <label class="block text-xs font-medium text-surface-600 mb-3">Rating <span class="text-red-500">*</span></label>
          <div class="flex justify-between sm:justify-start sm:gap-3" id="appt-modal-star-group">
            @for ($i = 1; $i <= 5; $i++)
              <button type="button" data-value="{{ $i }}"
                class="appt-modal-star text-5xl sm:text-4xl text-surface-350 hover:text-amber-400 active:scale-90 transition-all focus:outline-none leading-none touch-manipulation"
                aria-label="{{ $i }} star">&#9733;</button>
            @endfor
          </div>
          <input type="hidden" name="rating" id="appt-modal-rating-input" value="">
        </div>
        <div>
          <label for="appt-feedback-comment" class="block text-xs font-medium text-surface-600 mb-1">Comment <span class="text-surface-400">(optional)</span></label>
          <textarea id="appt-feedback-comment" name="comment" rows="3"
            class="w-full border border-surface-200 rounded-xl px-3 py-2.5 text-sm text-surface-700 outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-100 resize-none transition-all"
            placeholder="Tell us about your experience…"></textarea>
        </div>
        <div class="flex flex-col-reverse sm:flex-row gap-2 sm:gap-3 pt-1">
          <button type="button" onclick="closeApptFeedbackModal()"
            class="py-3 sm:py-2.5 sm:px-4 rounded-xl border border-surface-200 text-sm font-medium text-surface-500 hover:bg-surface-50 transition-colors touch-manipulation">
            Cancel
          </button>
          <button type="submit" data-loading-label="Submitting..."
            class="btn btn-primary flex-1 touch-manipulation">
            Submit feedback
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

@include('partials.mobile-bottom-customer')
@endsection

@section('scripts')
<script>
  // ── Cancel Modal ────────────────────────────────────────────────────────
  const cancelApptModal = document.getElementById('cancel-appt-modal');
  const cancelApptForm  = document.getElementById('cancel-appt-form');
  const cancelApptName  = document.getElementById('cancel-appt-name');

  function openCancelApptModal(apptId, name) {
    cancelApptForm.action = '/appointments/' + apptId + '/cancel';
    cancelApptName.textContent = name;
    cancelApptModal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }

  function closeCancelApptModal() {
    cancelApptModal.classList.add('hidden');
    document.body.style.overflow = '';
  }

  // ── Feedback Modal ──────────────────────────────────────────────────────
  const apptFeedbackModal  = document.getElementById('appt-feedback-modal');
  const apptApptId         = document.getElementById('appt-modal-appointment-id');
  const apptServiceNameEl  = document.getElementById('appt-modal-service-name');
  const apptRatingInput    = document.getElementById('appt-modal-rating-input');
  const apptStars          = document.querySelectorAll('.appt-modal-star');
  let apptSelected = 0;

  function paintApptStars(upTo) {
    apptStars.forEach((s, i) => {
      s.classList.toggle('text-amber-400', i < upTo);
      s.classList.toggle('text-surface-350', i >= upTo);
    });
  }

  apptStars.forEach((btn, idx) => {
    btn.addEventListener('mouseenter', () => paintApptStars(idx + 1));
    btn.addEventListener('mouseleave', () => paintApptStars(apptSelected));
    btn.addEventListener('click', () => {
      apptSelected = idx + 1;
      apptRatingInput.value = apptSelected;
      paintApptStars(apptSelected);
    });
  });

  function openApptFeedbackModal(apptId, serviceName) {
    apptApptId.value = apptId;
    apptServiceNameEl.textContent = serviceName;
    apptSelected = 0;
    apptRatingInput.value = '';
    paintApptStars(0);
    apptFeedbackModal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }

  function closeApptFeedbackModal() {
    apptFeedbackModal.classList.add('hidden');
    document.body.style.overflow = '';
  }

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeCancelApptModal(); closeApptFeedbackModal(); }
  });
</script>
@endsection
