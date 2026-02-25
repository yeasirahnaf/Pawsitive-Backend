<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['user', 'guestContact', 'items', 'delivery'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $result = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $result->items(),
            'meta'    => [
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'total'        => $result->total(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $order = Order::with(['user', 'guestContact', 'items', 'statusHistory', 'delivery', 'deliveryAddress'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $order]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, string $id): JsonResponse
    {
        $order    = Order::with('items.pet')->findOrFail($id);
        $data     = $request->validated();

        $updated = $this->orders->updateStatus(
            $order,
            $data['status'],
            $request->user()->id,
            $data['notes'] ?? null,
            $data['cancellation_reason'] ?? null,
        );

        return response()->json(['success' => true, 'data' => $updated]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:1000'],
        ]);

        $order   = Order::with('items.pet')->findOrFail($id);
        $updated = $this->orders->updateStatus(
            $order,
            'cancelled',
            $request->user()->id,
            null,
            $request->input('cancellation_reason'),
        );

        return response()->json(['success' => true, 'data' => $updated]);
    }
}
