<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlaceOrderRequest;
use App\Models\Order;
use App\Http\Traits\ApiResponse;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(private OrderService $orders) {}

    public function place(PlaceOrderRequest $request): JsonResponse
    {
        $order = $this->orders->create(
            $request->validated(),
            $request->user()?->id,
            $request->header('X-Session-Id', '')
        );

        return $this->created($order->load(['items', 'deliveryAddress', 'delivery']));
    }

    public function history(Request $request): JsonResponse
    {
        $orders = Order::with(['items', 'delivery'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return $this->paginated($orders);
    }

    public function track(Request $request, string $orderNumber): JsonResponse
    {
        $request->validate(['email' => ['nullable', 'email']]);

        $query = Order::with(['statusHistory', 'delivery', 'items.pet'])
            ->where('order_number', $orderNumber);

        if ($request->filled('email')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('user', fn ($u) => $u->where('email', $request->input('email')))
                  ->orWhereHas('guestContact', fn ($g) => $g->where('email', $request->input('email')));
            });
        }

        $order = $query->firstOrFail();

        return $this->success([
            'order_number' => $order->order_number,
            'status'       => $order->status,
            'created_at'   => $order->created_at,
            'history'      => $order->statusHistory,
            'delivery'     => $order->delivery,
            'items'        => $order->items,
        ]);
    }
}
