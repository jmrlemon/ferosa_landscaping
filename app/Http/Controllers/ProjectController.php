<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $service = trim((string) $request->get('service', ''));

        $projects = Project::query()
            ->published()
            ->when($service !== '', fn ($query) => $query->where('service_name', $service))
            ->orderByDesc('is_featured')
            ->orderByDesc('completed_at')
            ->latest('id')
            ->paginate(9)
            ->withQueryString();

        $services = Project::query()
            ->published()
            ->whereNotNull('service_name')
            ->where('service_name', '!=', '')
            ->distinct()
            ->orderBy('service_name')
            ->pluck('service_name');

        return view('projects.index', [
            'projects' => $projects,
            'services' => $services,
            'selectedService' => $service,
            'businessProfile' => AppSetting::getBusinessProfile(),
        ]);
    }

    public function show(Project $project): View
    {
        abort_unless($project->is_published, 404);

        $relatedProjects = Project::query()
            ->published()
            ->whereKeyNot($project->getKey())
            ->when($project->service_name, fn ($query) => $query->where('service_name', $project->service_name))
            ->orderByDesc('is_featured')
            ->orderByDesc('completed_at')
            ->limit(3)
            ->get();

        return view('projects.show', [
            'project' => $project,
            'relatedProjects' => $relatedProjects,
            'businessProfile' => AppSetting::getBusinessProfile(),
        ]);
    }
}
