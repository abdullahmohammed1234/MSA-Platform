<?php

namespace App\Pm\Controllers;

use App\Http\Controllers\Controller;
use App\Pm\Models\PmProject;
use App\Pm\Models\PmTask;
use App\Pm\Services\PmExportService;
use App\Pm\Services\ProjectAccessService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PmExportController extends Controller
{
    public function __construct(
        protected PmExportService $exportService,
        protected ProjectAccessService $accessService
    ) {}

    /**
     * Export project reports (progress, tasks list, or workload).
     */
    public function exportProject(Request $request, string $uuid): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $project = PmProject::where('uuid', $uuid)->firstOrFail();

        // Option B: Zero Executive Bypass
        if (!$this->accessService->canViewProject($user, $project)) {
            return response()->json([
                'message' => 'Unauthorized: You do not have permission to view or export this project under Option B policies.',
            ], 403);
        }

        $type = $request->query('type', 'progress');
        $format = strtolower($request->query('format', 'csv'));
        if (!in_array($format, ['csv', 'xlsx', 'pdf'], true)) {
            $format = 'csv';
        }

        if ($type === 'tasks') {
            $query = $project->tasks()->with(['assignee', 'taskList', 'project']);

            if ($request->filled('status')) {
                $query->where('status', $request->query('status'));
            }
            if ($request->filled('priority')) {
                $query->where('priority', $request->query('priority'));
            }
            if ($request->filled('assignee_id')) {
                $query->where('assignee_id', $request->query('assignee_id'));
            }

            $totalCount = (clone $query)->count();
            $limit = 1000;
            $tasks = $query->orderBy('due_date')->limit($limit)->get();
            $result = $this->exportService->exportTasks($tasks, $user, $format, "Tasks - {$project->name}", $totalCount, $limit);
        } elseif ($type === 'workload') {
            $startDate = $request->query('start_date', now()->toDateString());
            $endDate = $request->query('end_date', now()->addDays(28)->toDateString());
            $result = $this->exportService->exportProjectWorkload($project, $user, $startDate, $endDate, $format);
        } else {
            // Default: progress
            $result = $this->exportService->exportProjectProgress($project, $user, $format);
        }

        return response()->streamDownload(function () use ($result) {
            echo $result['content'];
        }, $result['filename'], [
            'Content-Type' => $result['contentType'],
            'Content-Disposition' => 'attachment; filename="' . $result['filename'] . '"',
        ]);
    }

    /**
     * Export accessible tasks cross-project.
     */
    public function exportTasks(Request $request): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $format = strtolower($request->query('format', 'csv'));
        if (!in_array($format, ['csv', 'xlsx', 'pdf'], true)) {
            $format = 'csv';
        }

        // Option B: strictly accessible projects
        $accessibleProjectIds = PmProject::accessibleTo($user)->pluck('id');

        $query = PmTask::whereIn('project_id', $accessibleProjectIds)
            ->with(['project', 'assignee', 'taskList']);

        if ($request->filled('project_uuid')) {
            $proj = PmProject::where('uuid', $request->query('project_uuid'))->first();
            if ($proj && $this->accessService->canViewProject($user, $proj)) {
                $query->where('project_id', $proj->id);
            } else {
                return response()->json([
                    'message' => 'Unauthorized: Cannot export tasks from this project.',
                ], 403);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->query('priority'));
        }
        if ($request->filled('assignee_id')) {
            $query->where('assignee_id', $request->query('assignee_id'));
        }

        $totalCount = (clone $query)->count();
        $limit = 1000;
        $tasks = $query->orderBy('due_date')->limit($limit)->get();
        $result = $this->exportService->exportTasks($tasks, $user, $format, 'All Accessible Tasks', $totalCount, $limit);

        return response()->streamDownload(function () use ($result) {
            echo $result['content'];
        }, $result['filename'], [
            'Content-Type' => $result['contentType'],
            'Content-Disposition' => 'attachment; filename="' . $result['filename'] . '"',
        ]);
    }
}
