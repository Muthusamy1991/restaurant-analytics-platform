<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\Project;
use App\Models\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EventIngestionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $eventSource = $request->input('event_source');

        if (!in_array($eventSource, ['browser', 'server'], true)) {
            return response()->json([
                'message' => 'Invalid event source.',
            ], 422);
        }

        if ($eventSource === 'browser') {
            $projectKey = $request->header('X-Project-Key');

            if (!is_string($projectKey) || $projectKey === '') {
                return response()->json([
                    'message' => 'Missing project tracking key.',
                ], 401);
            }

            $project = Project::where('public_key', $projectKey)->first();
        } else {
            $serverKey = $request->header('X-Server-Key');

            if (!is_string($serverKey) || $serverKey === '') {
                return response()->json([
                    'message' => 'Missing server tracking key.',
                ], 401);
            }

            $project = Project::where('server_key', $serverKey)->first();
        }

        if (!$project) {
            return response()->json([
                'message' => 'Invalid tracking credentials.',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'event_id' => ['required', 'string', 'max:64'],
            'event_name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z][a-zA-Z0-9_.-]*$/',
            ],
            'event_source' => [
                'required',
                Rule::in(['browser', 'server']),
            ],
            'visitor_id' => ['nullable', 'string', 'max:64'],
            'session_id' => ['nullable', 'string', 'max:64'],
            'user_id' => ['nullable', 'string', 'max:64'],
            'page_url' => ['nullable', 'url', 'max:2048'],
            'properties' => ['nullable', 'array', 'max:50'],
            'occurred_at' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid event payload.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        try {
            $result = DB::transaction(function () use ($data, $project) {
                if (!empty($data['visitor_id'])) {
                    Visitor::firstOrCreate([
                        'project_id' => $project->id,
                        'visitor_id' => $data['visitor_id'],
                    ]);
                }

                $event = AnalyticsEvent::firstOrCreate(
                    [
                        'project_id' => $project->id,
                        'event_id' => $data['event_id'],
                    ],
                    [
                        'visitor_id' => $data['visitor_id'] ?? null,
                        'session_id' => $data['session_id'] ?? null,
                        'user_id' => $data['user_id'] ?? null,
                        'event_name' => $data['event_name'],
                        'event_source' => $data['event_source'],
                        'page_url' => $data['page_url'] ?? null,
                        'properties' => $data['properties'] ?? null,
                        'occurred_at' => $data['occurred_at'],
                        'received_at' => now(),
                    ]
                );

                return [
                    'event' => $event,
                    'created' => $event->wasRecentlyCreated,
                ];
            });
        } catch (\Illuminate\Database\QueryException $exception) {
            $existing = AnalyticsEvent::where('project_id', $project->id)
                ->where('event_id', $data['event_id'])
                ->first();

            if (!$existing) {
                throw $exception;
            }

            $result = [
                'event' => $existing,
                'created' => false,
            ];
        }

        return response()->json([
            'message' => $result['created']
                ? 'Event accepted.'
                : 'Event already received.',
            'event_id' => $result['event']->event_id,
            'duplicate' => !$result['created'],
        ], $result['created'] ? 201 : 200);
    }
}
