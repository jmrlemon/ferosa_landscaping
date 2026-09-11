<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminProjectController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->get('q', ''));
        $status = (string) $request->get('status', 'all');

        $projects = Project::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', '%'.$search.'%')
                        ->orWhere('location', 'like', '%'.$search.'%')
                        ->orWhere('service_name', 'like', '%'.$search.'%');
                });
            })
            ->when($status === 'published', fn ($query) => $query->where('is_published', true))
            ->when($status === 'draft', fn ($query) => $query->where('is_published', false))
            ->orderByDesc('is_featured')
            ->orderByDesc('completed_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.projects.index', compact('projects', 'search', 'status'));
    }

    public function create(): View
    {
        return view('admin.projects.form', ['project' => new Project]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['slug'] = $this->uniqueSlug($data['title']);
        $data = $this->storeImages($request, $data);

        $project = Project::query()->create($data);

        return redirect()
            ->route('admin.projects.edit', $project)
            ->with('success', $project->is_published ? 'Project published successfully.' : 'Project draft saved.');
    }

    public function edit(Project $project): View
    {
        return view('admin.projects.form', compact('project'));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validatedData($request, $project);

        if ($project->title !== $data['title']) {
            $data['slug'] = $this->uniqueSlug($data['title'], $project->id);
        }

        $data = $this->storeImages($request, $data, $project);
        $project->update($data);

        return redirect()
            ->route('admin.projects.edit', $project)
            ->with('success', $project->is_published ? 'Published project updated.' : 'Project draft updated.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        foreach (['cover_image_path', 'before_image_path', 'after_image_path'] as $field) {
            if ($project->{$field}) {
                Storage::disk('public')->delete($project->{$field});
            }
        }

        $project->delete();

        return redirect()->route('admin.projects.index')->with('success', 'Project removed.');
    }

    private function validatedData(Request $request, ?Project $project = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:160'],
            'service_name' => ['nullable', 'string', 'max:120'],
            'summary' => ['required', 'string', 'max:5000'],
            'completed_at' => ['nullable', 'date', 'before_or_equal:today'],
            'duration_label' => ['nullable', 'string', 'max:80'],
            'client_quote' => ['nullable', 'string', 'max:1500'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'before_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'after_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'is_featured' => ['nullable', Rule::in(['1'])],
            'is_published' => ['nullable', Rule::in(['1'])],
        ]);

        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_published'] = $request->boolean('is_published');

        return $validated;
    }

    private function storeImages(Request $request, array $data, ?Project $project = null): array
    {
        foreach (['cover_image', 'before_image', 'after_image'] as $input) {
            if (! $request->hasFile($input)) {
                continue;
            }

            $field = $input.'_path';

            if ($project?->{$field}) {
                Storage::disk('public')->delete($project->{$field});
            }

            $data[$field] = $request->file($input)->store('projects', 'public');
        }

        return $data;
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'project';
        $slug = $base;
        $suffix = 2;

        while (Project::query()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
