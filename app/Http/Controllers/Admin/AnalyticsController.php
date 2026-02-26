<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(private AnalyticsService $analytics) {}

    public function sales(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $data = $this->analytics->getSales($request->input('from'), $request->input('to'));

        return $this->success($data);
    }

    public function inventory(): JsonResponse
    {
        return $this->success($this->analytics->getInventory());
    }

    public function customers(): JsonResponse
    {
        return $this->success($this->analytics->getCustomers());
    }
}
