<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingRequest;
use App\Http\Traits\ApiResponse;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    use ApiResponse;

    public function __construct(private SettingsService $settings) {}

    public function index(): JsonResponse
    {
        return $this->success($this->settings->all());
    }

    public function update(UpdateSettingRequest $request, string $key): JsonResponse
    {
        $setting = $this->settings->set(
            $key,
            $request->validated('value'),
            $request->user()->id
        );

        return $this->success($setting);
    }

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
                return $this->validationError(
                    "Invalid value for setting '{$key}': " . $e->getMessage()
                );
            }
        }

        return $this->success($updated);
    }
}
