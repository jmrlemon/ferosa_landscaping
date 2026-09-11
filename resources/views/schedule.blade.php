@extends('layouts.customer')

@section('title', 'Book a Service')

@section('styles')
<style>
  .cal-day { min-height: 38px; transition: background .12s, color .12s, border-color .12s; }
  .cal-day:not(.past):not(.empty):hover { background: #f0faf0; color: #1a6320; }
  .cal-day.selected { background: #1f7a1f; color: #fff; font-weight: 600; border-radius: 8px; }
  .cal-day.today { outline: 2px solid #1f7a1f; border-radius: 8px; outline-offset: -2px; }
  .cal-day.past { color: #d4d4d4; cursor: not-allowed; }
  .booking-step .step-marker { transition: background .12s, color .12s; }
  .booking-step.is-done .step-marker { background: #123426; color: #fff; }
  .booking-step.is-active .step-marker { background: #1f7a1f; color: #fff; box-shadow: 0 0 0 3px rgba(31,122,31,.16); }
  .booking-step.is-done .step-label,
  .booking-step.is-active .step-label { color: #1c1917; }
  .time-slot { transition: border-color .12s, background .12s, color .12s, opacity .12s; }
  .time-slot.selected { border-color: #1f7a1f; background: #f0faf0; color: #1a6320; font-weight: 600; }
  .time-slot:disabled,
  .time-slot.booked {
    opacity: 0.65;
    border-color: #e4e4e7;
    background: #fafafa;
    color: #a1a1aa;
    cursor: not-allowed;
    font-weight: 500;
  }
</style>
@endsection

@section('content')
<main class="customer-page">

  {{-- Page header --}}
  <x-page-head
    kicker="{{ $rescheduling ? 'Move a visit' : 'Book a visit' }}"
    title="{{ $rescheduling ? 'Reschedule your visit' : 'Schedule a service' }}"
    sub="{{ $rescheduling
      ? 'Pick a new date and time. The service and starting fee stay the same.'
      : 'Pick your service, then a date and time. We confirm within one business day.' }}">
    <x-slot:icon>
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M12 14v4M10 16h4"/>
      </svg>
    </x-slot:icon>
  </x-page-head>

  {{-- Booking progress. The steps are driven by what the customer has actually
       chosen (see updateStepper below); a strip that always highlighted step 1
       told them nothing about where they were. --}}
  <section class="mb-6 overflow-hidden rounded-2xl border border-brand-100 bg-white reveal reveal-1" aria-label="Booking progress">
    <ol class="grid grid-cols-2 sm:grid-cols-4">
      @foreach([['1', 'Service'], ['2', 'Date'], ['3', 'Time'], ['4', 'Confirm']] as [$number, $label])
        <li class="booking-step flex items-center gap-2 border-b border-r border-brand-50 px-3 py-3 last:border-r-0 sm:border-b-0" data-step="{{ $number }}">
          <span class="step-marker flex h-6 w-6 items-center justify-center rounded-full bg-brand-50 text-brand-700 text-[10px] font-bold" aria-hidden="true">{{ $number }}</span>
          <span class="step-label text-xs font-bold text-surface-500">{{ $label }}</span>
          <span class="step-state sr-only">not started</span>
        </li>
      @endforeach
    </ol>
  </section>

  {{-- Success flash --}}
  @if (session('status'))
    <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
  @endif

  {{-- Validation errors --}}
  @if ($errors->any())
    <x-alert type="error" class="mb-6">
      <ul class="list-disc space-y-0.5 pl-4">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </x-alert>
  @endif
  @if ($rescheduling)
    <div class="mb-6 rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-900">
      <p class="font-semibold">Moving your {{ $rescheduling->serviceType->name ?? 'service' }} visit.</p>
      <p class="mt-1 text-brand-800/80">
        Currently booked for {{ $rescheduling->appointment_at->format('M d, Y \a\t g:i A') }}.
        Choosing a new slot keeps the same booking and starting fee, and returns it to the team for confirmation.
      </p>
      <a href="{{ route('appointments') }}" class="mt-2 inline-flex text-xs font-bold text-brand-700 hover:text-brand-900">
        Keep the current time
      </a>
    </div>
  @elseif ($activeAppointment ?? null)
    <div class="mb-6 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-4 py-3 text-sm">
      <p class="font-semibold">You already have an active booking.</p>
      <p class="mt-1">
        {{ $activeAppointment->serviceType->name ?? 'Service' }} on
        {{ $activeAppointment->appointment_at->format('M d, Y \a\t g:i A') }}.
        Please cancel or complete this booking before scheduling another service.
      </p>
      <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
        <a href="{{ route('appointments') }}" class="inline-flex text-xs font-semibold text-amber-900 hover:text-amber-700">
          View appointment
        </a>
        @if ($activeAppointment->isCustomerReschedulable())
          <a href="{{ route('schedule', ['reschedule' => $activeAppointment->id]) }}" class="inline-flex text-xs font-semibold text-amber-900 hover:text-amber-700">
            Move it to another time
          </a>
        @endif
      </div>
    </div>
  @endif

  {{-- Booking form. In reschedule mode the same fields post to a different
       action: the server keeps the service and the fee from the record, so
       only the new time travels with the request. --}}
  <form method="POST"
        action="{{ $rescheduling ? route('appointments.reschedule', $rescheduling) : route('schedule.store') }}"
        id="booking-form">
    @csrf
    @if ($rescheduling)
      @method('PUT')
    @endif

    {{-- Hidden inputs populated by JS --}}
    <input type="hidden" name="service_type_id" id="hidden-service-type">
    <input type="hidden" name="appointment_at"  id="hidden-appointment-at">
    <input type="hidden" name="notes"           id="hidden-notes">

    <div id="booking-container" class="grid grid-cols-1 md:grid-cols-5 gap-6">

      {{-- Service first --}}
      <div class="md:col-span-5 customer-card p-5 sm:p-6">
        <div class="grid md:grid-cols-[1fr_1.25fr] gap-5 md:items-center">
          <div>
            <div class="flex items-center gap-2 mb-2">
              <span class="w-6 h-6 rounded-full bg-brand-700 text-white text-[10px] font-bold flex items-center justify-center">1</span>
              <p class="text-[10px] font-bold uppercase tracking-[.13em] text-brand-600">Choose a service</p>
            </div>
            <h2 class="font-display text-xl font-bold text-surface-900">What can we help with?</h2>
            <p class="mt-2 text-xs leading-5 text-surface-500">Starting fees are shown before you choose a visit. The team will confirm final scope and cost with you.</p>
          </div>
          <div>
            <label for="service-type-select" class="block text-xs font-bold text-surface-700 mb-2">Landscaping service</label>
            {{-- Locked while rescheduling: this form moves an existing booking,
                 it does not turn it into a different service. --}}
            <select id="service-type-select" @disabled($rescheduling !== null)
              class="w-full border border-surface-200 rounded-xl px-3.5 py-2.5 text-sm text-surface-700 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100 transition-colors disabled:bg-surface-50 disabled:text-surface-500">
              @forelse (($serviceTypes ?? []) as $st)
                <option value="{{ $st->id }}"
                  @selected($rescheduling
                    ? (int) $rescheduling->service_type_id === (int) $st->id
                    : (string) request('service') === (string) $st->id)>
                  {{ $st->name }} - from PHP {{ number_format((float) $st->default_fee, 0) }}
                </option>
              @empty
                <option value="">No services available</option>
              @endforelse
            </select>
            @if ($rescheduling)
              <p class="mt-2 text-[11px] text-surface-400">The service stays as booked. Cancel this visit if you need a different one.</p>
            @endif
            @if(empty($serviceTypes) || count($serviceTypes) === 0)
              <div class="rounded-lg border border-amber-100 bg-amber-50 text-amber-700 text-xs px-3 py-2 mt-3">
                No service types are available right now. Please check again later.
              </div>
            @endif
          </div>
        </div>
      </div>

      {{-- Calendar --}}
      <div class="md:col-span-3 customer-card p-5">
        <div class="flex items-center gap-2 mb-4">
          <span class="w-6 h-6 rounded-full bg-brand-50 text-brand-700 text-[10px] font-bold flex items-center justify-center">2</span>
          <h3 class="text-sm font-bold text-surface-900">Select a date</h3>
        </div>
        <div class="border border-surface-100 rounded-lg overflow-hidden">

          {{-- Month nav --}}
          <div class="flex justify-between items-center px-4 py-3 bg-surface-50 border-b border-surface-100">
            <button type="button" onclick="prevMonth()" aria-label="Previous month" class="w-9 h-9 flex items-center justify-center hover:bg-surface-100 rounded-lg text-surface-500 transition-colors">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            <span class="text-xs font-semibold text-surface-700" id="cal-month-label"></span>
            <button type="button" onclick="nextMonth()" aria-label="Next month" class="w-9 h-9 flex items-center justify-center hover:bg-surface-100 rounded-lg text-surface-500 transition-colors">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
          </div>

          {{-- Day-of-week headers --}}
          <div class="grid grid-cols-7 gap-0.5 px-3 pt-3 text-center text-[10px] text-surface-400 font-medium">
            <div>Su</div><div>Mo</div><div>Tu</div><div>We</div><div>Th</div><div>Fr</div><div>Sa</div>
          </div>

          {{-- Day cells rendered by JS --}}
          <div class="grid grid-cols-7 gap-1 p-3" id="cal-grid" role="grid" aria-label="Choose an appointment date"></div>
        </div>
        <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-[11px] text-surface-500">
          <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded bg-brand-700"></span>Selected</span>
          <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded border-2 border-brand-600"></span>Today</span>
          <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded bg-surface-200"></span>Unavailable</span>
        </div>
        <p class="mt-3 text-[11px] leading-5 text-surface-400">Appointments must be booked at least 24 hours in advance.</p>
      </div>

      {{-- Time & Details --}}
      <div class="md:col-span-2 space-y-5">

        {{-- Time slots --}}
        <div class="customer-card p-5">
          <div class="flex items-center gap-2 mb-4">
            <span class="w-6 h-6 rounded-full bg-brand-50 text-brand-700 text-[10px] font-bold flex items-center justify-center">3</span>
            <h3 class="text-sm font-bold text-surface-900">Select a time</h3>
          </div>
          <div class="grid grid-cols-2 gap-2" id="time-slots">
            @foreach (\App\Models\Appointment::SLOT_TIMES as $t)
              <button type="button"
                class="time-slot min-h-[44px] border border-surface-200 py-2 rounded-xl text-xs font-bold text-surface-600"
                data-time="{{ $t }}"
                aria-pressed="false"
                onclick="selectTime(this)">
                {{ \Carbon\Carbon::createFromFormat('H:i', $t)->format('h:i A') }}
              </button>
            @endforeach
            <button type="button" disabled
              class="time-slot min-h-[44px] border border-surface-100 py-2 rounded-xl text-xs font-medium text-surface-350 bg-surface-50 cursor-not-allowed">
              05:30 PM
            </button>
          </div>
          <p id="time-slots-hint" class="mt-2 text-xs text-amber-700 hidden" role="status"></p>
        </div>

        {{-- Notes --}}
        <div class="customer-card p-5">
          <label for="notes-field" class="block text-sm font-bold text-surface-900 mb-1">Project notes <span class="font-normal text-surface-400">(optional)</span></label>
          <p class="text-[11px] text-surface-400 mb-3">Tell us about your space, goals, or anything we should prepare for. <span class="font-semibold text-surface-500">Need more than one service on this visit?</span> Name the extras here - the team confirms the combined scope and total before your visit.</p>
          <textarea id="notes-field"
            placeholder="For example: front garden, partial shade, easy-care plants. Also need lawn care on the same visit."
            class="w-full border border-surface-200 rounded-xl px-3.5 py-3 text-sm text-surface-700 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-100 h-24 resize-none transition-colors"></textarea>
        </div>

        {{-- Submit --}}
        <div id="selection-summary" class="text-xs text-surface-500 text-center hidden" role="status" aria-live="polite">
          <span id="summary-text"></span>
        </div>

        <button type="button" onclick="submitBooking()" id="booking-submit-btn"
          @disabled($activeAppointment ?? null)
          class="customer-action w-full min-h-[48px] bg-brand-700 hover:bg-brand-800 text-white font-bold py-3 text-sm shadow-soft disabled:opacity-60 disabled:cursor-not-allowed">
          @if ($rescheduling)
            Confirm New Time
          @elseif ($activeAppointment ?? null)
            Booking Limit Reached
          @else
            Confirm Booking
          @endif
        </button>
      </div>
    </div>
  </form>

  <section class="mt-8 rounded-[1.3rem] border border-brand-100 bg-brand-50 p-5 sm:p-6">
    <p class="text-[10px] font-bold uppercase tracking-[.15em] text-brand-600">After you submit</p>
    <h2 class="mt-2 font-display text-xl font-bold text-brand-950">Know what happens next.</h2>
    <div class="mt-5 grid gap-4 sm:grid-cols-3">
      @foreach([
        ['1', 'Booking recorded', 'Your appointment appears immediately in Appointments.'],
        ['2', 'Team review', 'Ferosa checks the visit details and updates the booking status.'],
        ['3', 'Stay informed', 'Follow email, Notifications, and Messages for changes or reminders.'],
      ] as [$number, $title, $copy])
        <div class="rounded-xl border border-brand-100 bg-white/80 p-4"><span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-700 text-[10px] font-bold text-white">{{ $number }}</span><h3 class="mt-3 text-sm font-bold text-brand-950">{{ $title }}</h3><p class="mt-1 text-xs leading-5 text-brand-800/70">{{ $copy }}</p></div>
      @endforeach
    </div>
    <p class="mt-4 text-xs leading-5 text-brand-800/75">The displayed fee is a starting amount. Final scope and cost may be confirmed after Ferosa reviews your space and requirements.</p>
  </section>

</main>

@include('partials.mobile-bottom-customer')
@endsection

@section('scripts')
<script>
  const SCHEDULE_AVAILABILITY_URL = @json(route('schedule.availability'));
  // Declared here rather than beside IS_RESCHEDULING further down: the first
  // availability fetch runs during init, before that line has been evaluated.
  const RESCHEDULING_ID = @json($rescheduling?->id);

  // ── Calendar state ────────────────────────────────────────────────────────
  const MONTHS = ['January','February','March','April','May','June',
                  'July','August','September','October','November','December'];

  const today = new Date();
  today.setHours(0,0,0,0);
  const minimumBookingAt = new Date();
  minimumBookingAt.setHours(minimumBookingAt.getHours() + 24);

  let viewYear  = today.getFullYear();
  let viewMonth = today.getMonth();   // 0-based

  let selectedDate = null;  // Date object
  let selectedTime = null;  // '09:00'

  function formatDateYmd(d) {
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
  }

  /** Normalize API/local times to HH:MM for Set lookup */
  function normalizeHi(t) {
    if (!t || typeof t !== 'string') return '';
    const p = t.trim().split(':');
    const h = String(parseInt(p[0], 10)).padStart(2, '0');
    const m = String(parseInt(p[1] ?? '0', 10)).padStart(2, '0');
    return `${h}:${m}`;
  }

  function slotDateTime(date, time) {
    const [h, m] = time.split(':').map(Number);
    return new Date(date.getFullYear(), date.getMonth(), date.getDate(), h, m, 0, 0);
  }

  function isSlotAllowed(date, time) {
    return slotDateTime(date, time) >= minimumBookingAt;
  }

  // Days no crew is dispatched, from Appointment::CLOSED_WEEKDAYS. The server
  // rejects them too - this only keeps the customer from picking one and being
  // told off for it.
  const CLOSED_WEEKDAYS = @json(\App\Models\Appointment::CLOSED_WEEKDAYS);

  function isDateBookable(date) {
    if (CLOSED_WEEKDAYS.includes(date.getDay())) return false;
    const slots = Array.from(document.querySelectorAll('.time-slot[data-time]'));
    return slots.some(btn => isSlotAllowed(date, btn.dataset.time));
  }

  function renderCalendar() {
    const label = document.getElementById('cal-month-label');
    label.textContent = MONTHS[viewMonth] + ' ' + viewYear;

    const grid = document.getElementById('cal-grid');
    grid.innerHTML = '';

    const firstDay = new Date(viewYear, viewMonth, 1).getDay(); // 0=Sun
    const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();

    // Empty leading cells
    for (let i = 0; i < firstDay; i++) {
      const blank = document.createElement('div');
      blank.className = 'cal-day empty py-1.5 text-center text-xs';
      grid.appendChild(blank);
    }

    // Day cells
    for (let d = 1; d <= daysInMonth; d++) {
      const date = new Date(viewYear, viewMonth, d);
      const isPast = date < today || !isDateBookable(date);
      const isToday = date.getTime() === today.getTime();
      const isSelected = selectedDate && date.getTime() === selectedDate.getTime();

      const cell = document.createElement('button');
      cell.type = 'button';
      cell.textContent = d;
      cell.className = 'cal-day py-1.5 text-center text-xs rounded-lg cursor-pointer select-none';
      cell.setAttribute('role', 'gridcell');
      cell.setAttribute('aria-label', date.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }));
      cell.setAttribute('aria-pressed', isSelected ? 'true' : 'false');

      if (isPast) {
        cell.classList.add('past');
        cell.disabled = true;
        cell.setAttribute('aria-disabled', 'true');
        // A closed day looks the same as a past one, so say which it is
        // rather than leaving the customer to guess why it is greyed out.
        if (CLOSED_WEEKDAYS.includes(date.getDay())) {
          const dayName = date.toLocaleDateString(undefined, { weekday: 'long' });
          cell.title = `No visits on ${dayName}`;
          cell.setAttribute('aria-label', `${cell.getAttribute('aria-label')} - no visits on ${dayName}`);
        }
      } else {
        if (isToday) cell.classList.add('today');
        if (isSelected) cell.classList.add('selected');
        cell.addEventListener('click', () => pickDate(date));
      }

      grid.appendChild(cell);
    }

    updateSummary();
  }

  function pickDate(date) {
    selectedDate = date;
    renderCalendar();
    refreshTimeSlotAvailability();
  }

  function prevMonth() {
    if (viewMonth === 0) { viewMonth = 11; viewYear--; }
    else { viewMonth--; }
    renderCalendar();
  }

  function nextMonth() {
    if (viewMonth === 11) { viewMonth = 0; viewYear++; }
    else { viewMonth++; }
    renderCalendar();
  }

  // ── Time slots ────────────────────────────────────────────────────────────
  function selectTime(btn) {
    if (!btn || btn.disabled || btn.classList.contains('booked')) return;
    document.querySelectorAll('.time-slot[data-time]').forEach(t => {
      t.classList.remove('selected');
      t.setAttribute('aria-pressed', 'false');
    });
    btn.classList.add('selected');
    btn.setAttribute('aria-pressed', 'true');
    selectedTime = btn.dataset.time;
    updateSummary();
  }

  function setTimeSlotsHint(message) {
    const el = document.getElementById('time-slots-hint');
    if (!el) return;
    if (message) {
      el.textContent = message;
      el.classList.remove('hidden');
    } else {
      el.textContent = '';
      el.classList.add('hidden');
    }
  }

  function applyBookedTimes(bookedList) {
    const booked = new Set((bookedList || []).map(normalizeHi));
    document.querySelectorAll('.time-slot[data-time]').forEach(btn => {
      const t = normalizeHi(btn.dataset.time);
      const isTooSoon = selectedDate && !isSlotAllowed(selectedDate, t);
      if (booked.has(t) || isTooSoon) {
        btn.disabled = true;
        btn.classList.add('booked');
        btn.classList.remove('selected');
        if (selectedTime && normalizeHi(selectedTime) === t) selectedTime = null;
      } else {
        btn.disabled = false;
        btn.classList.remove('booked');
      }
      btn.setAttribute('aria-pressed', btn.classList.contains('selected') ? 'true' : 'false');
    });

    const pickable = Array.from(document.querySelectorAll('.time-slot[data-time]:not(:disabled)'));
    setTimeSlotsHint(pickable.length === 0 && selectedDate
      ? 'No eligible times are available. Appointments must be booked at least 24 hours in advance.'
      : '');

    const stillSelected = document.querySelector('.time-slot[data-time].selected:not(:disabled)');
    if (stillSelected) {
      selectedTime = stillSelected.dataset.time;
      updateSummary();
      return;
    }
    if (pickable.length) {
      pickable[0].classList.add('selected');
      pickable[0].setAttribute('aria-pressed', 'true');
      selectedTime = pickable[0].dataset.time;
    } else {
      selectedTime = null;
    }
    updateSummary();
  }

  async function refreshTimeSlotAvailability() {
    const serviceTypeId = document.getElementById('service-type-select')?.value;
    if (!selectedDate || !serviceTypeId) {
      document.querySelectorAll('.time-slot[data-time]').forEach(btn => {
        btn.disabled = false;
        btn.classList.remove('booked');
      });
      setTimeSlotsHint('');
      const first = document.querySelector('.time-slot[data-time]:not(:disabled)');
      if (first && !document.querySelector('.time-slot[data-time].selected')) {
        first.classList.add('selected');
        selectedTime = first.dataset.time;
      }
      updateSummary();
      return;
    }

    const params = new URLSearchParams({
      service_type_id: serviceTypeId,
      date: formatDateYmd(selectedDate),
    });
    // The visit being moved does not block its own slot.
    if (RESCHEDULING_ID) params.set('exclude_appointment_id', RESCHEDULING_ID);
    try {
      const res = await fetch(`${SCHEDULE_AVAILABILITY_URL}?${params}`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (!res.ok) throw new Error('availability failed');
      const data = await res.json();
      applyBookedTimes(data.booked_times);
    } catch (e) {
      console.error(e);
      setTimeSlotsHint('Could not load availability. You can still try to book; the server will reject double bookings.');
      document.querySelectorAll('.time-slot[data-time]').forEach(btn => {
        btn.disabled = false;
        btn.classList.remove('booked');
      });
      updateSummary();
    }
  }

  // Auto-select first available time slot (before date is chosen, all slots stay selectable)
  (function () {
    const first = document.querySelector('.time-slot[data-time]:not(:disabled)');
    if (first) {
      first.classList.add('selected');
      selectedTime = first.dataset.time;
    }
  })();

  // ── Progress ──────────────────────────────────────────────────────────────
  // Step 1 is complete as soon as a service is chosen (one is preselected), so
  // the strip normally opens on step 2 rather than pretending nothing is done.
  function updateStepper() {
    const hasDate = Boolean(selectedDate);
    const done = [
      Boolean(document.getElementById('service-type-select')?.value),
      hasDate,
      // A time slot is preselected on load as a convenience, before any date
      // exists to book it on. Counting that as a finished step put a tick on
      // "Time" while "Date" was still the open step, so the strip claimed the
      // customer had done something they had not, in the wrong order.
      hasDate && Boolean(selectedTime),
      false,
    ];
    // "Confirm" is reached only once the three choices above are made.
    done[3] = done[0] && done[1] && done[2];

    const firstOpen = done.findIndex(isDone => !isDone);

    document.querySelectorAll('.booking-step').forEach((step, index) => {
      const isDone = done[index] && index !== 3;
      const isActive = index === firstOpen || (index === 3 && done[3]);

      step.classList.toggle('is-done', isDone);
      step.classList.toggle('is-active', isActive && !isDone);
      step.setAttribute('aria-current', isActive && !isDone ? 'step' : 'false');

      const marker = step.querySelector('.step-marker');
      if (marker) marker.textContent = isDone ? '✓' : String(index + 1);

      const state = step.querySelector('.step-state');
      if (state) state.textContent = isDone ? 'completed' : (isActive ? 'current step' : 'not started');
    });
  }

  // ── Summary ───────────────────────────────────────────────────────────────
  function updateSummary() {
    const sum = document.getElementById('selection-summary');
    const txt = document.getElementById('summary-text');
    if (selectedDate && selectedTime) {
      const fmtDate = selectedDate.toLocaleDateString('en-US', { weekday:'short', month:'short', day:'numeric', year:'numeric' });
      const [h, m]  = selectedTime.split(':');
      const ampm    = +h >= 12 ? 'PM' : 'AM';
      const hh      = +h > 12 ? +h - 12 : (+h === 0 ? 12 : +h);
      txt.textContent = `📅 ${fmtDate} at ${hh}:${m} ${ampm}`;
      sum.classList.remove('hidden');
    } else {
      sum.classList.add('hidden');
    }
    updateStepper();
  }

  // ── Submit ────────────────────────────────────────────────────────────────
  const IS_RESCHEDULING = @json($rescheduling !== null);

  function submitBooking() {
    if (!IS_RESCHEDULING && @json((bool) ($activeAppointment ?? null))) {
      alert('You already have an active booking. Please cancel or complete it before booking another service.');
      return;
    }

    if (!selectedDate) { alert('Please select a date.'); return; }
    if (!selectedTime)  { alert('No time slot is available. Please choose another date or service.'); return; }

    const serviceTypeId = document.getElementById('service-type-select').value;
    if (!serviceTypeId) { alert('Please select a service type.'); return; }

    // Build appointment_at as "YYYY-MM-DD HH:MM:00"
    const yyyy = selectedDate.getFullYear();
    const mm   = String(selectedDate.getMonth() + 1).padStart(2, '0');
    const dd   = String(selectedDate.getDate()).padStart(2, '0');
    const [hh, min] = selectedTime.split(':');
    const appointmentAt = `${yyyy}-${mm}-${dd} ${hh}:${min}:00`;
    const appointmentDate = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), selectedDate.getDate(), Number(hh), Number(min), 0, 0);
    if (appointmentDate < minimumBookingAt) {
      alert('Appointments must be scheduled at least 24 hours in advance.');
      return;
    }

    document.getElementById('hidden-service-type').value   = serviceTypeId;
    document.getElementById('hidden-appointment-at').value = appointmentAt;
    document.getElementById('hidden-notes').value          = document.getElementById('notes-field').value;

    const btn = document.getElementById('booking-submit-btn');
    btn.disabled = true;
    btn.dataset.loading = 'true';
    const busyLabel = IS_RESCHEDULING ? 'Moving...' : 'Booking...';
    btn.innerHTML = '<span class="inline-block w-3.5 h-3.5 border-2 border-current border-r-transparent rounded-full animate-spin"></span><span>' + busyLabel + '</span>';
    document.getElementById('booking-form').submit();
  }

  // ── Init ──────────────────────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    renderCalendar();
    updateStepper();
    document.getElementById('service-type-select')?.addEventListener('change', () => {
      refreshTimeSlotAvailability();
      updateStepper();
    });
  });
</script>
@endsection
