<?php

namespace App\Services;

use App\Models\Pet;
use App\Models\PetImage;
use App\Exceptions\BusinessLogicException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaService
{
    private const DISK           = 'public';
    private const DIRECTORY      = 'pets';

    /**
     * Store uploaded images for a pet in storage/app/public/pets/{pet_id}/.
     * The first image is automatically set as thumbnail if none exists yet.
     */
    public function storeImages(Pet $pet, array $files): array
    {
        $stored        = [];
        $hasThumbnail  = $pet->images()->where('is_thumbnail', true)->exists();
        $nextSortOrder = $pet->images()->max('sort_order') + 1;

        foreach ($files as $file) {
            $path = $file->store(self::DIRECTORY . '/' . $pet->id, self::DISK);

            $isThumb = ! $hasThumbnail;

            $image = PetImage::create([
                'pet_id'          => $pet->id,
                'file_path'       => $path,
                'file_name'       => $file->getClientOriginalName(),
                'mime_type'       => $file->getMimeType(),
                'file_size_bytes' => $file->getSize(),
                'is_thumbnail'    => $isThumb,
                'sort_order'      => $nextSortOrder++,
            ]);

            if ($isThumb) {
                $hasThumbnail = true;
            }

            $stored[] = $image;
        }

        return $stored;
    }

    /**
     * Set a specific image as the pet's thumbnail.
     * Clears is_thumbnail from all other images first.
     */
    public function setThumbnail(Pet $pet, string $imageId): PetImage
    {
        $image = PetImage::where('id', $imageId)->where('pet_id', $pet->id)->firstOrFail();

        $pet->images()->where('is_thumbnail', true)->update(['is_thumbnail' => false]);

        $image->update(['is_thumbnail' => true]);

        return $image->fresh();
    }

    /**
     * Delete an image from disk and the database.
     * Guards against removing the only thumbnail (must reassign first).
     *
     * @throws \App\Exceptions\BusinessLogicException
     */
    public function deleteImage(Pet $pet, string $imageId): void
    {
        $image = PetImage::where('id', $imageId)->where('pet_id', $pet->id)->firstOrFail();

        if ($image->is_thumbnail && $pet->images()->count() > 1) {
            throw new BusinessLogicException(
                'Cannot delete the thumbnail.',
                ['image_id' => ['Cannot delete the thumbnail. Set another image as thumbnail first.']]
            );
        }

        Storage::disk(self::DISK)->delete($image->file_path);
        $image->delete();
    }
}
