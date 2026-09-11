@extends('admin.layouts.workspace')

@section('title', 'Project Portfolio - Ferosa Admin')
@section('admin-section', 'projects')
@section('skip-label', 'Skip to project management')
@section('header-eyebrow', 'Trust content')
@section('header-title', 'Project Portfolio')

{{-- No page-specific header actions: the layout renders the shared
     messages / notifications / account cluster. "Add a project" and "View
     public portfolio" live in the hero section below, where there is room to
     label them properly. --}}

@section('content')
    @if(session('success'))
      <div class="mb-5 rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm font-semibold text-brand-800">{{ session('success') }}</div>
    @endif

    <section class="mb-6 overflow-hidden rounded-[1.5rem] bg-brand-950 px-6 py-7 text-white shadow-lg sm:px-8">
      <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="text-[10px] font-bold uppercase tracking-[.16em] text-brand-200">Trust content</p><h2 class="mt-2 text-3xl text-white">Publish work customers can believe.</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-brand-100/70">Use only genuine project details, real photos, and feedback you have permission to share. Drafts stay private until you publish them.</p></div>
        <div class="flex flex-shrink-0 flex-wrap items-center gap-2.5">
          <a href="{{ route('projects.index') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-white/25 px-5 py-3 text-sm font-bold text-white transition-colors hover:bg-white/10">View public portfolio</a>
          <a href="{{ route('admin.projects.create') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-bold text-brand-950">Add a project</a>
        </div>
      </div>
    </section>

    <form method="GET" action="{{ route('admin.projects.index') }}" class="mb-6 grid gap-3 rounded-xl border border-surface-200 bg-white p-4 sm:grid-cols-[1fr_180px_auto]">
      <input type="search" name="q" aria-label="Search projects" value="{{ $search }}" placeholder="Search title, service, or location" class="h-11 w-full rounded-xl border border-surface-200 px-3 text-sm outline-none focus:border-brand-500">
      <select name="status" aria-label="Filter projects by status" class="h-11 rounded-xl border border-surface-200 px-3 text-sm outline-none focus:border-brand-500">
        <option value="all" @selected($status === 'all')>All statuses</option>
        <option value="published" @selected($status === 'published')>Published</option>
        <option value="draft" @selected($status === 'draft')>Drafts</option>
      </select>
      <button class="rounded-xl bg-brand-700 px-5 py-2 text-sm font-bold text-white">Apply filters</button>
    </form>

    @if($projects->isEmpty())
      <section class="rounded-xl border border-dashed border-surface-300 bg-white px-6 py-14 text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-50 text-brand-600"><svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M4 20h16M6 20V9l6-5 6 5v11M9 20v-6h6v6"/></svg></div>
        <h2 class="mt-4 text-xl text-brand-950">No projects found</h2>
        <p class="mt-2 text-sm text-surface-500">Create a draft, add authentic photos, then publish it when it is ready.</p>
        <a href="{{ route('admin.projects.create') }}" class="mt-5 inline-flex rounded-xl bg-brand-700 px-5 py-3 text-sm font-bold text-white">Create first project</a>
      </section>
    @else
      <section class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @foreach($projects as $project)
          <article class="overflow-hidden rounded-xl border border-surface-200 bg-white shadow-sm">
            <div class="relative aspect-[16/9] bg-brand-50">
              @if($project->cover_image_url)<img src="{{ $project->cover_image_url }}" alt="" class="h-full w-full object-cover">@endif
              <div class="absolute left-3 top-3 flex gap-2">
                <span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ $project->is_published ? 'bg-brand-700 text-white' : 'bg-white text-surface-700' }}">{{ $project->is_published ? 'Published' : 'Draft' }}</span>
                @if($project->is_featured)<span class="rounded-full bg-amber-400 px-2.5 py-1 text-[10px] font-bold text-amber-950">Featured</span>@endif
              </div>
            </div>
            <div class="p-5">
              <p class="text-[10px] font-bold uppercase tracking-wider text-brand-600">{{ $project->service_name ?: 'Uncategorized' }} @if($project->location)&middot; {{ $project->location }}@endif</p>
              <h2 class="mt-2 text-xl text-brand-950">{{ $project->title }}</h2>
              <p class="mt-2 line-clamp-2 text-sm leading-6 text-surface-500">{{ $project->summary }}</p>
              <div class="mt-5 flex items-center gap-2 border-t border-surface-100 pt-4">
                <a href="{{ route('admin.projects.edit', $project) }}" class="flex-1 rounded-lg bg-brand-700 px-3 py-2 text-center text-xs font-bold text-white">Edit project</a>
                @if($project->is_published)<a href="{{ route('projects.show', $project) }}" class="rounded-lg border border-surface-200 px-3 py-2 text-xs font-bold text-surface-600">Preview</a>@endif
                <form method="POST" action="{{ route('admin.projects.destroy', $project) }}"
                      data-confirm-title="Delete this project permanently?"
                      data-confirm="&ldquo;{{ $project->title }}&rdquo; will be deleted for good. This cannot be undone."
                      data-confirm-action="Delete permanently">@csrf @method('DELETE')<button class="rounded-lg border border-red-100 px-3 py-2 text-xs font-bold text-red-600">Remove</button></form>
              </div>
            </div>
          </article>
        @endforeach
      </section>
      <div class="mt-7">{{ $projects->links() }}</div>
    @endif
@endsection
