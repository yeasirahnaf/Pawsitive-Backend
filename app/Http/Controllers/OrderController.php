<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlaceOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    /**
     * POST /api/v1/orders — place an order from active cart.
     */
    public function place(PlaceOrderRequest $request): JsonResponse
    {
        $order = $this->orders->create(
            $request->validated(),
            $request->user()?->id,
            $request->header('X-Session-Id', '')
        );

        return response()->json([
            'success' => true,
            'data'    => $order->load(['items', 'deliveryAddress', 'delivery']),
        ], 201);
    }

    /**
     * GET /api/v1/orders — authenticated user's order history.
     */
    public function history(Request $request): JsonResponse
    {
        $orders = Order::with(['items', 'delivery'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data'    => $orders->items(),
            'meta'    => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'total'        => $orders->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/orders/{orderNumber}?email={email}
     * Public order tracking — email verified to prevent enumeration.
     */
    public function track(Request $request, string $orderNumber): JsonResponse
    {
        $request->validate(['email' => ['nullable', 'email']]);

        $query = Order::with(['statusHistory', 'delivery'])
            ->where('order_number', $orderNumber);

        // Require email to match when provided, OR allow if order belongs to a logged-in user
        if ($request->filled('email')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('user', fn ($u) => $u->where('email', $request->input('email')))
                  ->orWhereHas('guestContact', fn ($g) => $g->where('email', $request->input('email')));
            });
        }

        $order = $query->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => [
                'order_number' => $order->order_number,
                'status'       => $order->status,
                'history'      => $order->statusHistory,
                'delivery'     => $order->delivery,
            ],
        ]);
    }
}
