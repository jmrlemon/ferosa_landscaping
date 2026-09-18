@extends('admin.layouts.workspace')

@section('title', 'Return Claims - Ferosa Admin')
@section('admin-section', 'returns')
@section('skip-label', 'Skip to return claims')
@section('header-eyebrow', 'Ordering & delivery')
@section('header-title', 'Return & replacement claims')

@section('content')
    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3 rounded-xl border border-surface-200 bg-white p-4">
      <label class="text-sm font-semibold">Status
        <select name="status" class="mt-1 block rounded-lg border-surface-300 text-sm">
          <option value="">All claims</option>
          @foreach(\App\Models\ReturnRequest::STATUSES as $status)
            <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
          @endforeach
        </select>
      </label>
      <button class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Filter</button>
    </form>

    <section class="overflow-hidden rounded-xl border border-surface-200 bg-white shadow-sm">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-surface-200 text-left text-sm">
          <thead class="bg-surface-50 text-xs uppercase tracking-wide text-surface-500">
            <tr><th class="px-5 py-3">Claim</th><th class="px-5 py-3">Customer</th><th class="px-5 py-3">Order</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Age</th><th class="px-5 py-3"><span class="sr-only">Open</span></th></tr>
          </thead>
          <tbody class="divide-y divide-surface-100">
            @forelse($claims as $claim)
              <tr>
                <td class="px-5 py-4 font-bold text-brand-950">{{ $claim->claim_number }}</td>
                <td class="px-5 py-4">{{ $claim->user->name }}</td>
                <td class="px-5 py-4">{{ $claim->order->order_number }}</td>
                <td class="px-5 py-4"><span class="badge badge-neutral">{{ ucfirst(str_replace('_', ' ', $claim->status)) }}</span></td>
                <td class="px-5 py-4 text-surface-600">{{ optional($claim->submitted_at)->diffForHumans() }}</td>
                <td class="px-5 py-4 text-right"><a href="{{ route('admin.returns.show', $claim) }}" class="font-semibold text-brand-700 hover:text-brand-900">Review</a></td>
              </tr>
            @empty
              <tr><td colspan="6" class="px-5 py-12 text-center text-surface-500">No return claims match this filter.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
    <div class="mt-5">{{ $claims->links() }}</div>
@endsection
