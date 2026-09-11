@extends('admin.layouts.workspace')

@section('title', $product->name.' stock - Ferosa Admin')
@section('admin-section', 'products')
@section('skip-label', 'Skip to stock history')
@section('header-eyebrow', 'Inventory')
@section('header-title', $product->name)

@section('content')
  @if(session('status'))
    <div class="mb-5 rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm font-semibold text-brand-800">{{ session('status') }}</div>
  @endif
  @if($errors->any())
    <div class="mb-5 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
      <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="mb-6">
    <a href="{{ route('admin.products.edit', $product) }}" class="text-xs font-semibold text-brand-700 hover:underline">&larr; Back to {{ $product->name }}</a>
    <h2 class="mt-2 text-3xl text-brand-950">Stock history</h2>
    <p class="mt-1 text-sm text-surface-500">{{ ucfirst($product->category) }} · &#8369;{{ number_format((float) $product->price, 2) }}</p>
  </div>

  @include('admin.partials.stock-panel', [
    'product' => $product,
    'movements' => $movements,
    'showAllLink' => true,
  ])
@endsection
