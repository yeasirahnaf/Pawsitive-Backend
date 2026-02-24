<?php

namespace App\Http\Controllers;

use App\Services\GeolocationService;
use App\Services\PetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PetController extends Controller
{
    public function __construct(
        private PetService         $pets,
        private GeolocationService $geo,
    ) {}

    /**
     * GET /api/v1/pets
     * Spec query params: q, species[], breed, age_min, age_max, gender, size[],
     *                    color, price_min, price_max, behaviour[], latitude, longitude,
     *                    radius_km, sort_by, sort_order, page, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search'     => $request->input('q'),                   // spec: q
            'species'    => $request->input('species'),             // array-capable
            'breed'      => $request->input('breed'),
            'gender'     => $request->input('gender'),
            'size'       => $request->input('size'),                // array-capable
            'color'      => $request->input('color'),
            'min_price'  => $request->input('price_min'),          // spec: price_min
            'max_price'  => $request->input('price_max'),          // spec: price_max
            'min_age'    => $request->input('age_min'),            // spec: age_min
            'max_age'    => $request->input('age_max'),            // spec: age_max
            'behaviour'  => $request->input('behaviour'),          // spec: behaviour[]
            'lat'        => $request->input('latitude'),           // spec: latitude
            'lng'        => $request->input('longitude'),          // spec: longitude
            'radius_km'  => $request->input('radius_km'),
            'sort_by'    => $request->input('sort_by'),
            'sort_dir'   => $request->input('sort_order', 'desc'), // spec: sort_order
            'per_page'   => $request->integer('per_page', 12),    // spec default: 12
        ];

        // Auto-detect location from IP if lat/lng not provided
        if (empty($filters['lat']) || empty($filters['lng'])) {
            $detected = $this->geo->detectFromIp($request->ip());
            if ($detected) {
                $filters['lat'] = $filters['lat'] ?? $detected['lat'];
                $filters['lng'] = $filters['lng'] ?? $detected['lng'];
            }
        }

        $paginated = $this->pets->list($filters);

        return response()->json([
            'success' => true,
            'data'    => $paginated->items(),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/pets/{id} — public pet detail.
     */
    public function show(string $id): JsonResponse
    {
        $pet = $this->pets->findOrFail($id);

        return response()->json(['success' => true, 'data' => $pet]);
    }
}
