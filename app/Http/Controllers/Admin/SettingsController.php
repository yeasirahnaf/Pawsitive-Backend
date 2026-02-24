<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingRequest;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->settings->all(),
        ]);
    }

    public function update(UpdateSettingRequest $request, string $key): JsonResponse
    {
        $setting = $this->settings->set(
            $key,
            $request->validated('value'),
            $request->user()->id
        );

        return response()->json(['success' => true, 'data' => $setting]);
    }

    /**
     * PUT /api/v1/admin/settings
     * Spec: bulk update all settings in a single { key: value, ... } body.
     */
    public function updateAll(Request $request): JsonResponse
    {
        $request->validate([
            '*' => ['string'],
        ]);

        $updated = [];
        foreach ($request->all() as $key => $value) {
            try {
                $updated[$key] = $this->settings->set((string) $key, (string) $value, $request->user()->id)->typedValue();
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'error'   => "Invalid value for setting '{$key}': " . $e->getMessage(),
                ], 422);
            }
        }

        return response()->json(['success' => true, 'data' => $updated]);
    }
}
