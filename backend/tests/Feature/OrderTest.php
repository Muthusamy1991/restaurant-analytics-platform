<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::create([
            'name' => 'Restaurant Demo',
            'public_key' => 'pk_test_order_key',
            'server_key' => 'sk_test_order_key',
        ]);

        config([
            'services.analytics.endpoint' => 'http://analytics.test',
            'services.analytics.server_key' => 'sk_test_order_key',
        ]);
    }

    public function test_order_is_created_with_server_calculated_total(): void
    {
        Http::fake([
            'analytics.test/api/v1/events' => Http::response([
                'message' => 'Event accepted.',
                'duplicate' => false,
            ], 201),
        ]);

        $response = $this->postJson('/api/v1/orders', [
            'items' => [
                ['item_id' => 'item-101', 'quantity' => 2],
                ['item_id' => 'item-103', 'quantity' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Order created successfully.')
            ->assertJsonPath('order.item_count', 3)
            ->assertJsonPath('order.total_amount', 59700)
            ->assertJsonPath('order.currency', 'INR')
            ->assertJsonPath('order.status', 'created');

        $this->assertDatabaseHas('orders', [
            'project_id' => $this->project->id,
            'item_count' => 3,
            'total_amount' => 59700,
            'currency' => 'INR',
        ]);

        $this->assertSame(1, Order::count());

        Http::assertSent(function ($request) {
            return str_ends_with(
                $request->url(),
                '/api/v1/events'
            )
                && $request->hasHeader(
                    'X-Server-Key',
                    'sk_test_order_key'
                )
                && $request['event_name'] === 'order_created'
                && $request['event_source'] === 'server';
        });
    }

    public function test_order_rejects_unknown_menu_items(): void
    {
        $response = $this->postJson('/api/v1/orders', [
            'items' => [
                ['item_id' => 'unknown-item', 'quantity' => 1],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'One or more menu items are invalid.'
            );

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_rejects_empty_item_list(): void
    {
        $response = $this->postJson('/api/v1/orders', [
            'items' => [],
        ]);

        $response->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
    }
}
