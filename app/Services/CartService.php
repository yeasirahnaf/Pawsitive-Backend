<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Pet;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CartService
{
    private const LOCK_MINUTES = 15;

    /**
     * Add a pet to the cart (creates a 15-min inventory lock).
     * Prevents double-locking the same pet.
     */
    public function addItem(string $petId, ?string $userId, string $sessionId): CartItem
    {
        $pet = Pet::findOrFail($petId);

        if (! $pet->isAvailable()) {
            throw ValidationException::withMessages([
                'pet_id' => ['This pet is not available.'],
            ]);
        }

        // Check for an existing unexpired lock by another user/session
        $existing = CartItem::where('pet_id', $petId)->first();

        if ($existing) {
            if (! $existing->isExpired()) {
                throw ValidationException::withMessages([
                    'pet_id' => ['This pet is already reserved by another customer.'],
                ]);
            }
            // Expired lock — take it over
            $existing->delete();
        }

        // Mark pet as reserved
        $pet->update(['status' => 'reserved']);

        return CartItem::create([
            'pet_id'       => $petId,
            'user_id'      => $userId,
            // Authenticated users own their items via user_id; never store the
            // guest session_id alongside a user_id or items can appear in both
            // the guest cart and the logged-in user's cart simultaneously.
            'session_id'   => $userId ? null : $sessionId,
            'locked_until' => now()->addMinutes(self::LOCK_MINUTES),
        ]);
    }

    /**
     * Return all cart items for the given user or session (skips expired locks).
     */
    public function getCart(?string $userId, string $sessionId): Collection
    {
        $this->releaseExpiredLocks();

        // For authenticated users, extend the lock on every cart view so the
        // 15-minute window resets with each interaction rather than from the
        // moment the item was first added (which would cause spurious expiry).
        if ($userId) {
            CartItem::where('user_id', $userId)
                ->update(['locked_until' => now()->addMinutes(self::LOCK_MINUTES)]);
        }

        return CartItem::with('pet.thumbnail')
            ->when(
                $userId,
                // Authenticated: scope strictly to this user (ignore session header)
                fn ($q) => $q->where('user_id', $userId),
                // Guest: scope strictly to the session (user_id must be null)
                fn ($q) => $q->whereNull('user_id')->where('session_id', $sessionId)
            )
            ->get();
    }

    /**
     * Remove a pet from the cart and set it back to available.
     * @deprecated Use removeItemById() — kept for internal compatibility.
     */
    public function removeItem(string $petId, ?string $userId, string $sessionId): void
    {
        $item = CartItem::where('pet_id', $petId)
            ->when(
                $userId,
                fn ($q) => $q->where('user_id', $userId),
                fn ($q) => $q->whereNull('user_id')->where('session_id', $sessionId)
            )
            ->firstOrFail();

        $item->pet->update(['status' => 'available']);
        $item->delete();
    }

    /**
     * Remove a cart item by its own CartItem UUID (spec: DELETE /cart/items/{id}).
     */
    public function removeItemById(string $cartItemId, ?string $userId, string $sessionId): void
    {
        $item = CartItem::where('id', $cartItemId)
            ->when(
                $userId,
                fn ($q) => $q->where('user_id', $userId),
                fn ($q) => $q->whereNull('user_id')->where('session_id', $sessionId)
            )
            ->firstOrFail();

        $item->pet->update(['status' => 'available']);
        $item->delete();
    }

    /**
     * Merge guest session cart into an authenticated user's cart after login.
     */
    public function mergeGuestCart(string $sessionId, string $userId): void
    {
        CartItem::where('session_id', $sessionId)
            ->whereNull('user_id')
            ->update([
                'user_id'      => $userId,
                'session_id'   => null,
                // Reset the lock timer from the moment of merge so the user
                // has a full 15 minutes from login — not from when they added
                // the item as a guest (which may have been much earlier).
                'locked_until' => now()->addMinutes(self::LOCK_MINUTES),
            ]);
    }

    /**
     * Release all expired locks — called by ExpireCartLocks job and on cart reads.
     */
    public function releaseExpiredLocks(): int
    {
        $expired = CartItem::where('locked_until', '<', now())->get();

        foreach ($expired as $item) {
            $item->pet?->update(['status' => 'available']);
            $item->delete();
        }

        return $expired->count();
    }
}
