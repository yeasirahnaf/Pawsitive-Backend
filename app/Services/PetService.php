<?php

namespace App\Services;

use App\Models\Pet;
use App\Models\PetBehaviour;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PetService
{
    public function __construct(private MediaService $media) {}

    /**
     * List pets with filtering, search, and pagination (public storefront).
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = Pet::with(['thumbnail', 'behaviours'])
            ->whereNull('deleted_at');

        // Status filter — default to available for public
        $query->where('status', $filters['status'] ?? 'available');

        if (! empty($filters['species'])) {
            $query->where('species', $filters['species']);
        }
        if (! empty($filters['breed'])) {
            $query->where('breed', $filters['breed']);
        }
        if (! empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }
        if (! empty($filters['size'])) {
            $query->where('size', $filters['size']);
        }
        if (! empty($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }
        if (! empty($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }
        if (! empty($filters['min_age'])) {
            $query->where('age_months', '>=', $filters['min_age']);
        }
        if (! empty($filters['max_age'])) {
            $query->where('age_months', '<=', $filters['max_age']);
        }
        if (! empty($filters['behaviour'])) {
            $query->whereHas('behaviours', fn ($q) => $q->where('behaviour', $filters['behaviour']));
        }

        // Full-text trigram search
        if (! empty($filters['search'])) {
            $query->whereRaw("name % ?", [$filters['search']]);
        }

        // Nearby filter using PostGIS ST_DWithin
        if (! empty($filters['lat']) && ! empty($filters['lng']) && ! empty($filters['radius_km'])) {
            $query->whereRaw(
                "ST_DWithin(geo_point, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)",
                [$filters['lng'], $filters['lat'], $filters['radius_km'] * 1000]
            );
        }

        $sortBy  = in_array($filters['sort_by'] ?? '', ['price', 'age_months', 'created_at']) ? $filters['sort_by'] : 'created_at';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDir)->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Admin list — all statuses, includes soft-deleted toggle.
     */
    public function adminList(array $filters): LengthAwarePaginator
    {
        $query = Pet::with(['thumbnail', 'behaviours']);

        if (! empty($filters['include_deleted'])) {
            $query->withTrashed();
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 20);
    }

    public function findOrFail(string $id): Pet
    {
        return Pet::with(['images', 'behaviours'])->findOrFail($id);
    }

    /**
     * Create a pet with optional behaviours and images.
     */
    public function create(array $data, array $images = []): Pet
    {
        return DB::transaction(function () use ($data, $images) {
            $pet = Pet::create($data);

            if (! empty($data['behaviours'])) {
                $this->syncBehaviours($pet, $data['behaviours']);
            }

            $pet->syncGeoPoint();

            if (! empty($images)) {
                $this->media->storeImages($pet, $images);
            }

            return $pet->fresh(['images', 'behaviours', 'thumbnail']);
        });
    }

    /**
     * Update a pet (PATCH semantics).
     */
    public function update(Pet $pet, array $data, array $images = []): Pet
    {
        return DB::transaction(function () use ($pet, $data, $images) {
            $pet->update($data);

            if (array_key_exists('behaviours', $data)) {
                $this->syncBehaviours($pet, $data['behaviours'] ?? []);
            }

            // Re-sync geo_point if lat/lng changed
            if (isset($data['latitude']) || isset($data['longitude'])) {
                $pet->refresh();
                $pet->syncGeoPoint();
            }

            if (! empty($images)) {
                $this->media->storeImages($pet, $images);
            }

            return $pet->fresh(['images', 'behaviours', 'thumbnail']);
        });
    }

    /**
     * Soft-delete. Guards against deleting pets with active cart locks or pending orders.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function delete(Pet $pet): void
    {
        if ($pet->cartItem()->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'id' => ['Cannot delete a pet that is currently in a cart.'],
            ]);
        }

        if ($pet->orderItems()->whereHas('order', fn ($q) => $q->whereNotIn('status', ['delivered', 'cancelled']))->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'id' => ['Cannot delete a pet with an active order.'],
            ]);
        }

        $pet->delete();
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function syncBehaviours(Pet $pet, array $behaviours): void
    {
        $pet->behaviours()->delete();

        foreach (array_unique($behaviours) as $behaviour) {
            PetBehaviour::create(['pet_id' => $pet->id, 'behaviour' => trim($behaviour)]);
        }
    }
}
