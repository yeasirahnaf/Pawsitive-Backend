<?php

namespace App\Services;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Delivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Create an order from the caller's active cart in a single atomic transaction.
     */
    public function create(array $data, ?string $userId, string $sessionId): Order
    {
        return DB::transaction(function () use ($data, $userId, $sessionId) {
            // ── 1. Load cart items ───────────────────────────────────────────
            $cartItems = CartItem::with('pet')
                ->when(
                    $userId,
                    // Authenticated: scope strictly to this user
                    fn ($q) => $q->where('user_id', $userId),
                    // Guest: scope strictly to session (must not belong to any user)
                    fn ($q) => $q->whereNull('user_id')->where('session_id', $sessionId)
                )
                ->get();

            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages(['cart' => ['Your cart is empty.']]);
            }

            // ── 2. Refresh locks inside the transaction ───────────────────────
            // Extend the lock for all items being ordered so they cannot expire
            // between now and when the pet status is updated to 'sold'.
            // The real double-booking guard is pet.status='reserved'; the
            // locked_until field is a UI reservation hint, not a hard gate.
            CartItem::whereIn('id', $cartItems->pluck('id'))
                ->update(['locked_until' => now()->addMinutes(15)]);

            // ── 3. Resolve / create delivery address ─────────────────────────
            $address = Address::create([
                'address_line' => $data['address_line'],
                'city'         => $data['city'] ?? null,
                'area'         => $data['area'] ?? null,
            ]);

            // ── 5. Calculate subtotal ─────────────────────────────────────────────
            $subtotal = $cartItems->sum(fn ($item) => $item->pet->price);

            // ── 6. Create the order ───────────────────────────────────────────────
            $order = Order::create([
                'order_number'        => $this->generateOrderNumber(),
                'user_id'             => $userId,
                'delivery_address_id' => $address->id,
                'subtotal'            => $subtotal,
                'delivery_fee'        => $data['delivery_fee'] ?? 0,
                'payment_method'      => $data['payment_method'],
                'status'              => 'pending',
                'notes'               => $data['notes'] ?? null,
            ]);

            // ── 7. Create order items (with snapshots) ───────────────────────
            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id'             => $order->id,
                    'pet_id'               => $item->pet->id,
                    'pet_name_snapshot'    => $item->pet->name,
                    'pet_breed_snapshot'   => $item->pet->breed,
                    'pet_species_snapshot' => $item->pet->species,
                    'price_snapshot'       => $item->pet->price,
                ]);

                // Mark pet as sold
                $item->pet->update(['status' => 'sold']);
                $item->delete();
            }

            // ── 8. Create initial status history entry ───────────────────────
            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => 'pending',
                'changed_by' => $userId,
                'notes'      => 'Order placed.',
            ]);

            // ── 9. Create delivery record ────────────────────────────────────
            Delivery::create([
                'order_id' => $order->id,
                'status'   => 'pending',
            ]);

            // ── 10. Send confirmation email (synchronous) ────────────────────
            $this->notifications->sendOrderConfirmation($order->load(['items', 'deliveryAddress', 'user', 'guestContact']));

            return $order;
        });
    }

    /**
     * Admin: update order status with history record.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateStatus(Order $order, string $newStatus, string $adminId, ?string $notes = null, ?string $cancellationReason = null): Order
    {
        // Guard invalid transitions
        $allowed = $this->allowedTransitions($order->status);
        if (! in_array($newStatus, $allowed)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot transition from '{$order->status}' to '{$newStatus}'."],
            ]);
        }

        DB::transaction(function () use ($order, $newStatus, $adminId, $notes, $cancellationReason) {
            $update = ['status' => $newStatus];

            if ($newStatus === 'cancelled') {
                $update['cancellation_reason'] = $cancellationReason;
                $update['cancelled_at']        = now();

                // Release pets back to available
                foreach ($order->items as $item) {
                    if ($item->pet) {
                        $item->pet->update(['status' => 'available']);
                    }
                }
            }

            if ($newStatus === 'delivered') {
                $update['delivered_at'] = now();
            }

            $order->update($update);

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => $newStatus,
                'changed_by' => $adminId,
                'notes'      => $notes,
            ]);
        });

        return $order->fresh(['items', 'statusHistory', 'delivery']);
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-' . strtoupper(Str::random(6));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }

    private function allowedTransitions(string $current): array
    {
        return match ($current) {
            'pending'          => ['confirmed', 'cancelled'],
            'confirmed'        => ['out_for_delivery', 'cancelled'],
            'out_for_delivery' => ['delivered', 'cancelled'],
            default            => [],
        };
    }
}
