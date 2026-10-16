<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use RestaurantAnalytics\Analytics;
use RuntimeException;
use Tests\TestCase;

class AnalyticsHelperTest extends TestCase
{
    public function test_track_sends_server_event_with_private_key(): void
    {
        Http::fake([
            'http://analytics.test/api/v1/events' => Http::response([
                'message' => 'Event accepted.',
                'duplicate' => false,
            ], 201),
        ]);

        $analytics = new Analytics(
            'http://analytics.test',
            'sk_test_helper_key'
        );

        $result = $analytics->track(
            'order_created',
            ['order_number' => 'ORD-HELPER'],
            'order-helper-1'
        );

        $this->assertSame('Event accepted.', $result['message']);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://analytics.test/api/v1/events'
                && $request->hasHeader('X-Server-Key', 'sk_test_helper_key')
                && $request['event_name'] === 'order_created'
                && $request['event_source'] === 'server'
                && $request['event_id'] === 'order-helper-1'
                && $request['properties']['order_number'] === 'ORD-HELPER';
        });
    }

    public function test_track_throws_when_endpoint_or_key_is_missing(): void
    {
        $this->expectException(RuntimeException::class);

        new Analytics('', '');
    }
}
