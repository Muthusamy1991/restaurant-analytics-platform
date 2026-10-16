<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RestaurantAnalytics\Analytics;
use Throwable;

class OrderController extends Controller
{
    private const MENU = [
        'item-101' => ['name' => 'Paneer Pizza', 'price' => 24900],
        'item-102' => ['name' => 'Veg Burger', 'price' => 14900],
        'item-103' => ['name' => 'Masala Dosa', 'price' => 9900],
        'item-104' => ['name' => 'Veg Biryani', 'price' => 17900],
        'item-105' => ['name' => 'Fresh Lime Soda', 'price' => 6900],
        'item-106' => ['name' => 'Chocolate Brownie', 'price' => 11900],
    ];

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.item_id' => ['required', 'string', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $orderItems = [];
        $itemCount = 0;
        $totalAmount = 0;

        foreach ($validated['items'] as $requestedItem) {
            $menuItem = self::MENU[$requestedItem['item_id']] ?? null;

            if (!$menuItem) {
                return response()->json([
                    'message' => 'One or more menu items are invalid.',
                ], 422);
            }

            $quantity = $requestedItem['quantity'];
            $lineTotal = $menuItem['price'] * $quantity;

            $orderItems[] = [
                'item_id' => $requestedItem['item_id'],
                'name' => $menuItem['name'],
                'quantity' => $quantity,
                'unit_price' => $menuItem['price'],
                'line_total' => $lineTotal,
            ];

            $itemCount += $quantity;
            $totalAmount += $lineTotal;
        }

        $project = Project::where('name', 'Restaurant Demo')->firstOrFail();

        $order = DB::transaction(function () use (
            $project,
            $orderItems,
            $itemCount,
            $totalAmount
        ) {
            return Order::create([
                'project_id' => $project->id,
                'order_number' => 'ORD-' . Str::upper(Str::random(12)),
                'status' => 'created',
                'items' => $orderItems,
                'item_count' => $itemCount,
                'total_amount' => $totalAmount,
                'currency' => 'INR',
            ]);
        });

        // The order is committed before analytics is attempted.
        // Analytics failure must not turn a successful order into a failed order.
        try {
            $analytics = new Analytics(
                (string) config('services.analytics.endpoint'),
                (string) config('services.analytics.server_key'),
            );

            $analytics->track(
                'order_created',
                [
                    'order_number' => $order->order_number,
                    'item_count' => $order->item_count,
                    'total_amount' => $order->total_amount,
                    'currency' => $order->currency,
                ],
                'order-' . $order->id,
            );
        } catch (Throwable $exception) {
            report($exception);
        }

        return response()->json([
            'message' => 'Order created successfully.',
            'order' => $order,
        ], 201);
    }
}
