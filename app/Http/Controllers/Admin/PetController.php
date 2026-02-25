<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePetRequest;
use App\Http\Requests\UpdatePetRequest;
use App\Models\Pet;
use App\Services\MediaService;
use App\Services\PetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PetController extends Controller
{
    public function __construct(
        private PetService   $pets,
        private MediaService $media,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'include_deleted', 'per_page']);
        $result  = $this->pets->adminList($filters);

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
        $pet = $this->pets->findOrFail($id);
        return response()->json(['success' => true, 'data' => $pet]);
    }

    public function store(StorePetRequest $request): JsonResponse
    {
        $data   = $request->validated();
        $images = $request->file('images', []);

        $pet = $this->pets->create($data, $images);

        return response()->json(['success' => true, 'data' => $pet], 201);
    }

    public function update(UpdatePetRequest $request, string $id): JsonResponse
    {
        $pet    = Pet::findOrFail($id);
        $data   = $request->validated();
        $images = $request->file('images', []);

        $pet = $this->pets->update($pet, $data, $images);

        return response()->json(['success' => true, 'data' => $pet]);
    }

    public function destroy(string $id): JsonResponse
    {
        $pet = Pet::findOrFail($id);
        $this->pets->delete($pet);

        return response()->json(['success' => true, 'message' => 'Pet soft-deleted.']);
    }

    public function uploadImages(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'images'   => ['required', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        $pet    = Pet::findOrFail($id);
        $stored = $this->media->storeImages($pet, $request->file('images'));

        return response()->json(['success' => true, 'data' => $stored], 201);
    }

    public function setThumbnail(string $petId, string $imageId): JsonResponse
    {
        $pet   = Pet::findOrFail($petId);
        $image = $this->media->setThumbnail($pet, $imageId);

        return response()->json(['success' => true, 'data' => $image]);
    }

    public function deleteImage(string $petId, string $imageId): JsonResponse
    {
        $pet = Pet::findOrFail($petId);
        $this->media->deleteImage($pet, $imageId);

        return response()->json(['success' => true, 'message' => 'Image deleted.']);
    }
}
