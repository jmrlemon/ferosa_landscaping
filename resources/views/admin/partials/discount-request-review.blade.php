@if($discountRequest)
  <section class="mt-4 rounded-lg border border-amber-200 bg-amber-50/70 p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-wider text-amber-900">Customer discount request</p>
        <p class="mt-1 text-sm font-semibold text-surface-900">{{ $discountRequest->beneficiaryLabel() }}</p>
        <p class="mt-1 text-[11px] text-surface-600">
          Submitted {{ optional($discountRequest->created_at)->format('M d, Y h:i A') }}
          @if($discountRequest->requestedBy) by {{ $discountRequest->requestedBy->name }} @endif
        </p>
      </div>
      <span class="rounded-full bg-white px-2.5 py-1 text-xs font-bold uppercase text-amber-900">{{ str_replace('_', ' ', $discountRequest->status) }}</span>
    </div>

    @if($discountRequest->status === \App\Models\DiscountRequest::STATUS_PENDING)
      <p class="mt-3 text-xs leading-5 text-surface-700">The customer’s declaration does not change the total. Verify the ID and the eligible goods or service before applying a discount.</p>
      @if($isAdmin)
        <a href="{{ route('admin.discount-requests.evidence', $discountRequest) }}" target="_blank" rel="noopener"
           class="mt-3 inline-flex rounded-lg border border-amber-300 bg-white px-3 py-2 text-xs font-semibold text-amber-900 hover:bg-amber-100">
          View uploaded ID image
        </a>

        @if($discountsEnabled && count($eligibleDiscountSchemes) > 0)
          <form method="POST" action="{{ route('admin.discount-requests.approve', $discountRequest) }}" class="mt-4 rounded-lg border border-brand-200 bg-white p-3">
            @csrf
            <p class="text-xs font-bold text-brand-900">Verify and apply</p>
            <p class="mt-1 text-[11px] leading-4 text-surface-600">Also check the original ID when the order is delivered or the service is performed. Senior Citizen and PWD benefits cannot be stacked.</p>
            <label class="mt-3 block text-xs font-semibold text-surface-700">Applicable benefit *
              <select name="scheme" required class="mt-1.5 w-full rounded-lg border border-surface-200 px-3 py-2 text-sm font-normal outline-none focus:border-brand-500">
                @foreach($eligibleDiscountSchemes as $scheme)
                  <option value="{{ $scheme }}">
                    {{ $scheme === \App\Services\PhilippineDiscountCalculator::SCHEME_STATUTORY_20 ? '20% + VAT exemption' : '5% BNPC discount' }}
                  </option>
                @endforeach
              </select>
            </label>
            <label class="mt-3 flex items-start gap-2 text-xs leading-5 text-surface-700">
              <input type="checkbox" name="eligibility_confirmed" value="1" required class="mt-0.5 h-4 w-4 rounded border-surface-300 text-brand-600 focus:ring-brand-500">
              I verified the customer’s ID and confirmed the selected goods or service qualify.
            </label>
            <button type="submit" class="mt-3 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Approve request and recalculate total</button>
          </form>
        @elseif(! $canApplyDiscount)
          <p class="mt-3 rounded-lg border border-amber-200 bg-white px-3 py-2 text-xs text-amber-900">
            This order or appointment is no longer eligible for a discount change. Reject the request to close it; no payment or fulfilment can proceed while it remains pending.
          </p>
        @else
          <p class="mt-3 rounded-lg border border-amber-200 bg-white px-3 py-2 text-xs text-amber-900">
            Discount application is unavailable because the applicable scheme or eligible lines are not enabled.
          </p>
        @endif

        <form method="POST" action="{{ route('admin.discount-requests.reject', $discountRequest) }}" class="mt-3 flex flex-col gap-2 sm:flex-row">
          @csrf
          <label class="sr-only" for="discount-rejection-reason-{{ $discountRequest->id }}">Reason for rejection</label>
          <input id="discount-rejection-reason-{{ $discountRequest->id }}" name="rejection_reason" required maxlength="255" placeholder="Reason if the request cannot be approved"
                 class="w-full rounded-lg border border-surface-200 px-3 py-2 text-sm outline-none focus:border-red-400">
          <button type="submit" class="shrink-0 rounded-lg border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Reject request</button>
        </form>
      @else
        <p class="mt-3 text-xs text-amber-900">Your request is waiting for admin review. The invoice total will update only if it is approved.</p>
      @endif
    @elseif($discountRequest->status === \App\Models\DiscountRequest::STATUS_REJECTED)
      <p class="mt-3 text-xs leading-5 text-surface-700">
        Reviewed {{ optional($discountRequest->reviewed_at)->format('M d, Y h:i A') }}
        @if($discountRequest->rejection_reason) · {{ $discountRequest->rejection_reason }} @endif
      </p>
    @elseif($discountRequest->status === \App\Models\DiscountRequest::STATUS_APPROVED)
      <p class="mt-3 text-xs text-surface-700">
        Verified by {{ $discountRequest->reviewedBy?->name ?? 'admin' }} on {{ optional($discountRequest->reviewed_at)->format('M d, Y h:i A') }}.
      </p>
    @endif
  </section>
@endif
