@php($isEdit = $project->exists)
@extends('admin.layouts.workspace')

@section('title', ($isEdit ? 'Edit Project' : 'Add Project').' - Ferosa Admin')
@section('admin-section', 'projects')
@section('skip-label', 'Skip to project form')
@section('header-eyebrow', 'Trust content')
@section('header-title', $isEdit ? 'Edit Project' : 'Add Project')

@section('header-actions')
  <a href="{{ route('admin.projects.index') }}" class="rounded-lg border border-surface-200 px-3 py-2 text-xs font-bold text-surface-600">Back to portfolio</a>
@endsection

@section('content')
    @if(session('success'))<div class="mb-5 rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm font-semibold text-brand-800">{{ session('success') }}</div>@endif
    @if($errors->any())
      <div class="mb-5 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700"><p class="font-bold">Please review these details:</p><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="mb-6"><p class="text-[10px] font-bold uppercase tracking-[.16em] text-brand-600">Authentic proof</p><h2 class="mt-2 text-3xl text-brand-950">{{ $isEdit ? 'Refine the project story.' : 'Document completed work.' }}</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-surface-500">Keep facts specific and only publish client feedback or photos you are allowed to share.</p></div>

    <form id="project-form" method="POST" action="{{ $isEdit ? route('admin.projects.update', $project) : route('admin.projects.store') }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-[1fr_330px]">
      @csrf
      @if($isEdit) @method('PUT') @endif
      <div class="space-y-6">
        <section class="rounded-xl border border-surface-200 bg-white p-5 sm:p-6">
          <div class="mb-5"><h3 class="font-bold text-surface-900">Project story</h3><p class="mt-1 text-xs text-surface-400">The title and summary appear on the public portfolio.</p></div>
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-semibold text-surface-700 sm:col-span-2">Project title *<input name="title" required maxlength="160" value="{{ old('title', $project->title) }}" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500"></label>
            <label class="block text-sm font-semibold text-surface-700">Service type<input name="service_name" maxlength="120" value="{{ old('service_name', $project->service_name) }}" placeholder="e.g. Garden redesign" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500"></label>
            <label class="block text-sm font-semibold text-surface-700">Location<input name="location" maxlength="160" value="{{ old('location', $project->location) }}" placeholder="e.g. Orani, Bataan" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500"></label>
            <label class="block text-sm font-semibold text-surface-700 sm:col-span-2">Summary *<textarea name="summary" required maxlength="5000" rows="7" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal leading-6 outline-none focus:border-brand-500">{{ old('summary', $project->summary) }}</textarea></label>
            <label class="block text-sm font-semibold text-surface-700">Completion date<input type="date" name="completed_at" max="{{ now()->toDateString() }}" value="{{ old('completed_at', $project->completed_at?->toDateString()) }}" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500"></label>
            <label class="block text-sm font-semibold text-surface-700">Project duration<input name="duration_label" maxlength="80" value="{{ old('duration_label', $project->duration_label) }}" placeholder="e.g. 3 weeks" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500"></label>
          </div>
        </section>

        <section class="rounded-xl border border-surface-200 bg-white p-5 sm:p-6">
          <div class="mb-5"><h3 class="font-bold text-surface-900">Project images</h3><p class="mt-1 text-xs text-surface-400">JPG, PNG, or WebP. Maximum 8 MB per image.</p></div>
          <div class="grid gap-4 md:grid-cols-3">
            @foreach([['cover_image', 'Cover image', $project->cover_image_url], ['before_image', 'Before image', $project->before_image_url], ['after_image', 'After image', $project->after_image_url]] as [$name, $label, $current])
              <label class="block rounded-xl border border-surface-200 p-3 text-sm font-semibold text-surface-700">
                <span>{{ $label }}</span>
                <div class="mt-3 flex aspect-[4/3] items-center justify-center overflow-hidden rounded-lg bg-brand-50">@if($current)<img src="{{ $current }}" alt="" class="h-full w-full object-cover">@else<svg class="h-8 w-8 text-brand-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="m3 16 5-5 4 4 3-3 6 6M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z"/></svg>@endif</div>
                <input type="file" name="{{ $name }}" accept="image/jpeg,image/png,image/webp" class="mt-3 w-full text-xs font-normal text-surface-500 file:mr-2 file:rounded-lg file:border-0 file:bg-surface-100 file:px-3 file:py-2 file:text-xs file:font-bold">
              </label>
            @endforeach
          </div>
        </section>

        <section class="rounded-xl border border-surface-200 bg-white p-5 sm:p-6">
          <div class="mb-5"><h3 class="font-bold text-surface-900">Client feedback</h3><p class="mt-1 text-xs text-surface-400">Optional. Add only feedback the client has allowed you to publish.</p></div>
          <div class="grid gap-4 sm:grid-cols-[1fr_150px]">
            <label class="block text-sm font-semibold text-surface-700">Quote<textarea name="client_quote" maxlength="1500" rows="4" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500">{{ old('client_quote', $project->client_quote) }}</textarea></label>
            <label class="block text-sm font-semibold text-surface-700">Rating<select name="rating" class="mt-2 w-full rounded-xl border border-surface-200 px-3 py-2 text-base font-normal outline-none focus:border-brand-500"><option value="">Not shown</option>@for($rating = 5; $rating >= 1; $rating--)<option value="{{ $rating }}" @selected((string) old('rating', $project->rating) === (string) $rating)>{{ $rating }} / 5</option>@endfor</select></label>
          </div>
        </section>
      </div>

      <aside class="h-fit space-y-4 xl:sticky xl:top-5">
        <section class="rounded-xl border border-surface-200 bg-white p-5">
          <h3 class="font-bold text-surface-900">Publishing</h3>
          <label class="mt-4 flex items-start gap-3 rounded-xl border border-surface-200 p-3"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $project->is_published)) class="mt-0.5 h-4 w-4 rounded border-surface-300 text-brand-600"><span><span class="block text-sm font-bold text-surface-800">Publish to customers</span><span class="mt-1 block text-xs leading-5 text-surface-500">Leave off until every detail is ready.</span></span></label>
          <label class="mt-3 flex items-start gap-3 rounded-xl border border-surface-200 p-3"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $project->is_featured)) class="mt-0.5 h-4 w-4 rounded border-surface-300 text-brand-600"><span><span class="block text-sm font-bold text-surface-800">Feature on home</span><span class="mt-1 block text-xs leading-5 text-surface-500">Featured projects appear on the customer home page.</span></span></label>
          <button class="mt-5 w-full rounded-xl bg-brand-700 px-4 py-3 text-sm font-bold text-white">{{ $isEdit ? 'Save project changes' : 'Save project' }}</button>
          <a href="{{ route('admin.projects.index') }}" class="mt-2 flex w-full justify-center rounded-xl border border-surface-200 px-4 py-3 text-sm font-bold text-surface-600">Cancel</a>
        </section>
        <div class="rounded-xl border border-amber-100 bg-amber-50 p-4 text-xs leading-5 text-amber-900"><strong>Before publishing:</strong> confirm photo permission, spelling, location details, and any client quote.</div>
      </aside>
    </form>
@endsection
