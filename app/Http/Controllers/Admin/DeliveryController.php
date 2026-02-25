<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDeliveryRequest;
use App\Models\Delivery;
use App\Services\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(private DeliveryService $deliveries) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'month'  => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', 'string'],
        ]);

        if ($request->filled('month')) {
            [$year, $month] = explode('-', $request->input('month'));

            $calendar = $this->deliveries->getCalendar((int) $year, (int) $month);

            return response()->json(['success' => true, 'data' => $calendar]);
        }

        $result = Delivery::with(['order.deliveryAddress', 'order.items'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

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

    public function update(UpdateDeliveryRequest $request, string $id): JsonResponse
    {
        $delivery = Delivery::with('order')->findOrFail($id);
        $updated  = $this->deliveries->updateStatus($delivery, $request->validated());

        return response()->json(['success' => true, 'data' => $updated]);
    }
}
