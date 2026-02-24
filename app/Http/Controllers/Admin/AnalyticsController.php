<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(private AnalyticsService $analytics) {}

    public function sales(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $data = $this->analytics->getSales($request->input('from'), $request->input('to'));

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function inventory(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->analytics->getInventory(),
        ]);
    }

    public function customers(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->analytics->getCustomers(),
        ]);
    }
}
