<?php

namespace App\Http\Controllers;

use App\Services\GeolocationService;
use App\Services\PetService;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PetController extends Controller
{
    use ApiResponse;

    public function __construct(
        private PetService         $pets,
        private GeolocationService $geo,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search'     => $request->input('q'),
            'species'    => $request->input('species'),
            'breed'      => $request->input('breed'),
            'gender'     => $request->input('gender'),
            'size'       => $request->input('size'),
            'color'      => $request->input('color'),
            'min_price'  => $request->input('price_min'),
            'max_price'  => $request->input('price_max'),
            'min_age'    => $request->input('age_min'),
            'max_age'    => $request->input('age_max'),
            'behaviour'  => $request->input('behaviour'),
            'lat'        => $request->input('latitude'),
            'lng'        => $request->input('longitude'),
            'radius_km'  => $request->input('radius_km'),
            'sort_by'    => $request->input('sort_by'),
            'sort_dir'   => $request->input('sort_order', 'desc'),
            'per_page'   => $request->integer('per_page', 12),
        ];

        if (empty($filters['lat']) || empty($filters['lng'])) {
            $detected = $this->geo->detectFromIp($request->ip());
            if ($detected) {
                $filters['lat'] = $filters['lat'] ?? $detected['lat'];
                $filters['lng'] = $filters['lng'] ?? $detected['lng'];
            }
        }

        $paginated = $this->pets->list($filters);

        return $this->paginated($paginated);
    }

    public function show(string $id): JsonResponse
    {
        $pet = $this->pets->findOrFail($id);

        return $this->success($pet);
    }
}
