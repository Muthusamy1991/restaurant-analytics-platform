<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::create([
            'name' => 'Test Restaurant',
            'public_key' => 'pk_test_dashboard_key',
            'server_key' => 'sk_test_dashboard_key',
        ]);
    }

    
public function test_overview_endpoint_returns_analytics_metrics(): void
{
    $response = $this->withHeader(
        'X-Project-Key',
        $this->project->public_key
    )->getJson('/api/v1/analytics/overview');

    $response->assertOk()
        ->assertJsonStructure([
            'total_events',
            'page_views',
            'unique_visitors',
            'orders_created',
            'from',
            'to',
        ]);
}


    public function test_trends_endpoint_returns_selected_date_range(): void
    {
        $response = $this->withHeader(
            'X-Project-Key',
            $this->project->public_key
        )->getJson('/api/v1/analytics/trends?from=2026-10-10&to=2026-10-16');

        $response->assertOk();

        $dates = collect($response->json('data'))
            ->pluck('date')
            ->all();

        $this->assertSame([
            '2026-10-10',
            '2026-10-11',
            '2026-10-12',
            '2026-10-13',
            '2026-10-14',
            '2026-10-15',
            '2026-10-16',
        ], $dates);
    }

    public function test_dashboard_endpoint_requires_project_key(): void
    {
        $this->getJson('/api/v1/analytics/overview')
            ->assertUnauthorized();
    }

    public function test_pages_endpoint_returns_popular_pages(): void
    {
        $this->withHeader('X-Project-Key', $this->project->public_key)
            ->postJson('/api/v1/events', [
                'event_id' => 'page-1',
                'event_name' => 'page_view',
                'event_source' => 'browser',
                'visitor_id' => 'visitor-pages',
                'page_url' => 'http://localhost:3000/',
                'occurred_at' => now()->toIso8601String(),
            ])
            ->assertCreated();

        $response = $this->withHeader(
            'X-Project-Key',
            $this->project->public_key
        )->getJson('/api/v1/analytics/pages');

        $response->assertOk()
            ->assertJsonPath('data.0.page_url', 'http://localhost:3000/')
            ->assertJsonPath('data.0.views', 1);
    }
}
