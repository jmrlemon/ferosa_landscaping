@extends('layouts.customer')

@section('title', 'Feedback')

@section('content')
<div class="customer-page is-narrow space-y-6">

  {{-- Page header --}}
  <x-page-head
    kicker="Your experience"
    title="Feedback"
    sub="Rate completed orders and services — your notes help us improve every visit.">
    <x-slot:icon>
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="m12 3 2.9 5.9 6.1.9-4.5 4.3 1.1 6.1L12 17.3 6.4 20.2l1.1-6.1L3 9.8l6.1-.9L12 3Z"/>
      </svg>
    </x-slot:icon>
  </x-page-head>

  @if (session('status'))
    <x-alert type="success" class="reveal">{{ session('status') }}</x-alert>
  @endif

  {{-- ── Completed orders awaiting feedback ─────────────────────────── --}}
  @if ($deliveredOrders->isNotEmpty())
    <div class="bg-amber-50 border border-amber-200 rounded-xl overflow-hidden">
      <div class="px-5 py-3.5 border-b border-amber-200 flex items-center gap-2">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="text-amber-500 shrink-0">
          <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
        </svg>
        <h2 class="text-sm font-semibold text-amber-800">Orders Awaiting Your Review</h2>
      </div>
      <ul class="divide-y divide-amber-100">
        @foreach ($deliveredOrders as $order)
          <li class="px-4 sm:px-5 py-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
              <div class="min-w-0">
                <p class="text-sm font-medium text-surface-900">
                  Order <span class="font-mono text-brand-600">{{ $order->order_number }}</span>
                </p>
                <p class="text-xs text-surface-400 mt-0.5">
                  Completed · {{ optional($order->customer_confirmed_at ?? $order->updated_at)->format('M d, Y') }}
                  &nbsp;·&nbsp; &#8369;{{ number_format((float) $order->total_amount, 2) }}
                </p>
              </div>
              <button onclick="openFeedbackModal({{ $order->id }}, '{{ $order->order_number }}')"
                class="w-full sm:w-auto shrink-0 inline-flex items-center justify-center gap-1.5 bg-brand-700 hover:bg-brand-800 active:bg-brand-900 text-white text-sm sm:text-xs font-medium px-4 py-3 sm:py-1.5 rounded-xl sm:rounded-lg transition-colors touch-manipulation">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                Rate Now
              </button>
            </div>
          </li>
        @endforeach
      </ul>
    </div>
  @endif

  @if ($deliveredOrders->isEmpty() && $myFeedbacks->isEmpty())
    <div class="customer-empty">
      <div class="customer-empty-icon">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M21 15a4 4 0 0 1-4 4H7l-4 4V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>
      </div>
      <h2 class="text-sm font-semibold text-surface-900 mb-1">No feedback available</h2>
      <p class="text-surface-400 text-sm">Completed orders that are ready for review will appear here.</p>
    </div>
  @endif

  {{-- Past feedback --}}
  @if ($myFeedbacks->isNotEmpty())
    <div class="customer-card overflow-hidden">
      <div class="px-6 py-4 border-b border-surface-100">
        <h2 class="text-sm font-semibold text-surface-900">Your Past Feedback</h2>
      </div>
      <ul class="divide-y divide-surface-100">
        @foreach ($myFeedbacks as $fb)
          <li class="px-6 py-4 space-y-1">
            <div class="flex items-center justify-between">
              <span class="text-amber-400 text-lg tracking-tighter">
                {{ str_repeat('★', $fb->rating) }}<span class="text-surface-350">{{ str_repeat('★', 5 - $fb->rating) }}</span>
              </span>
              <span class="text-xs text-surface-400">{{ $fb->created_at->format('M d, Y') }}</span>
            </div>
            @if ($fb->order)
              <p class="text-xs text-surface-500">
                Order: <span class="font-mono font-medium text-brand-600">{{ $fb->order->order_number }}</span>
              </p>
            @elseif ($fb->product || $fb->serviceType)
              <p class="text-xs text-surface-500">
                About: <span class="font-medium text-surface-700">
                  {{ $fb->product ? $fb->product->name : $fb->serviceType->name }}
                </span>
              </p>
            @endif
            @if ($fb->comment)
              <p class="text-sm text-surface-600">{{ $fb->comment }}</p>
            @endif
          </li>
        @endforeach
      </ul>
    </div>
  @endif

</div>

{{-- ── Feedback Modal ─────────────────────────────────────────────── --}}
<div id="feedback-modal" class="fixed inset-0 z-50 hidden flex items-end sm:items-center justify-center sm:p-4">
  <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeFeedbackModal()"></div>
  <div class="relative bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl shadow-2xl animate-modal-up sm:animate-fade-in">
    {{-- drag handle mobile --}}
    <div class="flex justify-center pt-3 pb-1 sm:hidden">
      <div class="w-10 h-1 bg-surface-200 rounded-full"></div>
    </div>
    <div class="px-4 sm:px-6 pt-2 sm:pt-5 pb-5 sm:pb-6">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-base font-semibold text-surface-900">Rate Your Order</h3>
          <p class="text-xs text-surface-350 mt-0.5">Order <span id="modal-order-number" class="font-mono text-brand-600 font-semibold"></span></p>
        </div>
        <button type="button" aria-label="Close dialog" onclick="closeFeedbackModal()" class="p-1.5 text-surface-400 hover:text-surface-600 transition-colors rounded-lg">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <form method="POST" action="{{ route('feedback.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="order_id" id="modal-order-id">
        <div>
          <label class="block text-xs font-medium text-surface-600 mb-3">Rating <span class="text-red-500">*</span></label>
          <div class="flex justify-between sm:justify-start sm:gap-3" id="modal-star-group">
            @for ($i = 1; $i <= 5; $i++)
              <button type="button" data-value="{{ $i }}"
                class="modal-star text-5xl sm:text-4xl text-surface-350 hover:text-amber-400 active:scale-90 transition-all focus:outline-none leading-none touch-manipulation"
                aria-label="{{ $i }} star">&#9733;</button>
            @endfor
          </div>
          <input type="hidden" name="rating" id="modal-rating-input" value="">
        </div>
        <div>
          <label for="feedback-comment" class="block text-xs font-medium text-surface-600 mb-1">Comment <span class="text-surface-400">(optional)</span></label>
          <textarea id="feedback-comment" name="comment" rows="3"
            class="w-full border border-surface-200 rounded-xl px-3 py-2.5 text-sm text-surface-700 outline-none focus:border-brand-500 resize-none transition-all"
            placeholder="Tell us about your experience..."></textarea>
        </div>
        <div class="flex flex-col-reverse sm:flex-row gap-2 sm:gap-3 pt-1">
          <button type="button" onclick="closeFeedbackModal()"
            class="py-3 sm:py-2.5 sm:px-4 rounded-xl border border-surface-200 text-sm font-medium text-surface-500 hover:bg-surface-50 transition-colors touch-manipulation">
            Cancel
          </button>
          <button type="submit" data-loading-label="Submitting..."
            class="flex-1 bg-surface-900 hover:bg-surface-800 active:bg-surface-700 text-white text-sm font-medium py-3 sm:py-2.5 rounded-xl transition-colors touch-manipulation">
            Submit Feedback
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
@keyframes fade-in  { from { opacity:0; transform:scale(.95); }    to { opacity:1; transform:scale(1); } }
@keyframes modal-up { from { transform:translateY(100%); opacity:0; } to { transform:translateY(0); opacity:1; } }
.animate-fade-in  { animation: fade-in  .18s ease-out; }
.animate-modal-up { animation: modal-up .25s cubic-bezier(.32,1,.5,1); }
</style>

@include('partials.mobile-bottom-customer')
@endsection

@section('scripts')
<script>
  // ── Modal ──────────────────────────────────────────────────────────────
  const feedbackModal    = document.getElementById('feedback-modal');
  const modalOrderId     = document.getElementById('modal-order-id');
  const modalOrderNum    = document.getElementById('modal-order-number');
  const modalRatingInput = document.getElementById('modal-rating-input');
  const modalStars       = document.querySelectorAll('.modal-star');
  let modalSelected = 0;

  function paintModal(upTo) {
    modalStars.forEach((s, i) => {
      s.classList.toggle('text-amber-400', i < upTo);
      s.classList.toggle('text-surface-350',  i >= upTo);
    });
  }

  modalStars.forEach((btn, idx) => {
    btn.addEventListener('mouseenter', () => paintModal(idx + 1));
    btn.addEventListener('mouseleave', () => paintModal(modalSelected));
    btn.addEventListener('click', () => {
      modalSelected = idx + 1;
      modalRatingInput.value = modalSelected;
      paintModal(modalSelected);
    });
  });

  function openFeedbackModal(orderId, orderNum) {
    modalOrderId.value         = orderId;
    modalOrderNum.textContent  = orderNum;
    modalSelected              = 0;
    modalRatingInput.value     = '';
    paintModal(0);
    feedbackModal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }

  function closeFeedbackModal() {
    feedbackModal.classList.add('hidden');
    document.body.style.overflow = '';
  }

  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeFeedbackModal(); });
</script>
@endsection
