<?php

declare(strict_types=1);

namespace RestaurantAnalytics;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class Analytics
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $serverKey,
    ) {
        if ($this->endpoint === '' || $this->serverKey === '') {
            throw new RuntimeException(
                'Analytics endpoint and server key are required.'
            );
        }
    }

    public function track(
        string $eventName,
        array $properties = [],
        ?string $eventId = null,
        ?string $visitorId = null,
        ?string $userId = null,
    ): array {
        $payload = [
            'event_id' => $eventId ?? (string) Str::uuid(),
            'event_name' => $eventName,
            'event_source' => 'server',
            'visitor_id' => $visitorId,
            'user_id' => $userId,
            'occurred_at' => now()->toISOString(),
            'properties' => $properties,
        ];

        $response = Http::timeout(3)
            ->acceptJson()
            ->withHeaders([
                'X-Server-Key' => $this->serverKey,
            ])
            ->post(
                rtrim($this->endpoint, '/') . '/api/v1/events',
                $payload
            );

        if (!$response->successful()) {
            throw new RuntimeException(
                'Analytics request failed with HTTP ' . $response->status()
            );
        }

        return $response->json() ?? [];
    }
}
