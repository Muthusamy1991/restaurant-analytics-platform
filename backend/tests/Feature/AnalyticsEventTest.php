<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsEventTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::create([
            'name' => 'Test Restaurant',
            'public_key' => 'pk_test_analytics_key',
            'server_key' => 'sk_test_analytics_key',
        ]);
    }

    public function test_browser_event_can_be_ingested(): void
    {
        $response = $this->withHeader(
            'X-Project-Key',
            $this->project->public_key
        )->postJson('/api/v1/events', [
            'event_id' => 'event-test-001',
            'event_name' => 'page_view',
            'event_source' => 'browser',
            'visitor_id' => 'visitor-test-001',
            'session_id' => 'session-test-001',
            'page_url' => 'http://localhost:3000/',
            'properties' => ['page' => 'home'],
            'occurred_at' => now()->toIso8601String(),
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('analytics_events', [
            'project_id' => $this->project->id,
            'event_id' => 'event-test-001',
            'event_name' => 'page_view',
            'event_source' => 'browser',
        ]);
    }

    public function test_browser_event_requires_valid_project_key(): void
    {
        $response = $this->postJson('/api/v1/events', [
            'event_id' => 'event-test-002',
            'event_name' => 'page_view',
            'event_source' => 'browser',
            'occurred_at' => now()->toIso8601String(),
        ]);

        $response->assertUnauthorized();
    }

    public function test_duplicate_event_is_not_stored_twice(): void
    {
        $payload = [
            'event_id' => 'event-test-003',
            'event_name' => 'page_view',
            'event_source' => 'browser',
            'visitor_id' => 'visitor-test-003',
            'occurred_at' => now()->toIso8601String(),
        ];

        $headers = [
            'X-Project-Key' => $this->project->public_key,
        ];

        $this->postJson('/api/v1/events', $payload, $headers)
            ->assertCreated();

        $this->postJson('/api/v1/events', $payload, $headers)
            ->assertOk();

        $this->assertSame(
            1,
            AnalyticsEvent::where('project_id', $this->project->id)
                ->where('event_id', 'event-test-003')
                ->count()
        );
    }

    public function test_server_event_requires_valid_server_key(): void
    {
        $response = $this->postJson('/api/v1/events', [
            'event_id' => 'event-test-004',
            'event_name' => 'order_created',
            'event_source' => 'server',
            'occurred_at' => now()->toIso8601String(),
        ]);

        $response->assertUnauthorized();
    }

    public function test_server_event_can_be_ingested_with_server_key(): void
    {
        $response = $this->withHeader(
            'X-Server-Key',
            $this->project->server_key
        )->postJson('/api/v1/events', [
            'event_id' => 'event-test-005',
            'event_name' => 'order_created',
            'event_source' => 'server',
            'properties' => [
                'order_number' => 'ORD-TEST',
            ],
            'occurred_at' => now()->toIso8601String(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('duplicate', false);

        $this->assertDatabaseHas('analytics_events', [
            'project_id' => $this->project->id,
            'event_id' => 'event-test-005',
            'event_name' => 'order_created',
            'event_source' => 'server',
        ]);
    }
}
