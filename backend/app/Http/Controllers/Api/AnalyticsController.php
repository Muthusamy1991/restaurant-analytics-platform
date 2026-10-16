<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AnalyticsController extends Controller
{
    private function resolveProject(Request $request): ?Project
    {
        $key = $request->header('X-Project-Key');

        if (!is_string($key) || $key === '') {
            return null;
        }

        return Project::where('public_key', $key)->first();
    }

    private function dateRange(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : now()->subDays(6)->startOfDay();

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }

    private function eventsQuery(
        Project $project,
        Carbon $from,
        Carbon $to
    ) {
        return AnalyticsEvent::query()
            ->where('project_id', $project->id)
            ->whereBetween('occurred_at', [$from, $to]);
    }

    public function overview(Request $request): JsonResponse
    {
        $project = $this->resolveProject($request);

        if (!$project) {
            return response()->json([
                'message' => 'Invalid or missing project key.',
            ], 401);
        }

        [$from, $to] = $this->dateRange($request);

        $query = $this->eventsQuery($project, $from, $to);

        return response()->json([
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'total_events' => (clone $query)->count(),
            'page_views' => (clone $query)
                ->where('event_name', 'page_view')
                ->count(),
            'unique_visitors' => (clone $query)
                ->whereNotNull('visitor_id')
                ->distinct()
                ->count('visitor_id'),
            'orders_created' => (clone $query)
                ->where('event_name', 'order_created')
                ->count(),
        ]);
    }

    
    public function trends(Request $request): JsonResponse
    {
        $project = $this->resolveProject($request);

        if (!$project) {
            return response()->json([
                'message' => 'Invalid or missing project key.',
            ], 401);
        }

        [$from, $to] = $this->dateRange($request);

        $rows = $this->eventsQuery($project, $from, $to)
            ->selectRaw('DATE(occurred_at) as date')
            ->selectRaw('COUNT(*) as total_events')
            ->selectRaw(
                "SUM(CASE WHEN event_name = 'page_view' THEN 1 ELSE 0 END) as page_views"
            )
            ->groupByRaw('DATE(occurred_at)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $data = [];
        $current = $from->copy()->startOfDay();
        $last = $to->copy()->startOfDay();

        while ($current->lte($last)) {
            $date = $current->toDateString();
            $row = $rows->get($date);

            $data[] = [
                'date' => $date,
                'total_events' => (int) ($row->total_events ?? 0),
                'page_views' => (int) ($row->page_views ?? 0),
            ];

            $current->addDay();
        }

        return response()->json([
            'data' => $data,
        ]);
    }


    public function pages(Request $request): JsonResponse
    {
        $project = $this->resolveProject($request);

        if (!$project) {
            return response()->json([
                'message' => 'Invalid or missing project key.',
            ], 401);
        }

        [$from, $to] = $this->dateRange($request);

        $rows = $this->eventsQuery($project, $from, $to)
            ->where('event_name', 'page_view')
            ->whereNotNull('page_url')
            ->select('page_url')
            ->selectRaw('COUNT(*) as views')
            ->groupBy('page_url')
            ->orderByDesc('views')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $rows,
        ]);
    }

    public function recentEvents(Request $request): JsonResponse
    {
        $project = $this->resolveProject($request);

        if (!$project) {
            return response()->json([
                'message' => 'Invalid or missing project key.',
            ], 401);
        }

        [$from, $to] = $this->dateRange($request);

        $perPage = min(
            max((int) $request->query('per_page', 20), 1),
            100
        );

        $events = $this->eventsQuery($project, $from, $to)
            ->orderByDesc('occurred_at')
            ->paginate($perPage);

        return response()->json($events);
    }
}