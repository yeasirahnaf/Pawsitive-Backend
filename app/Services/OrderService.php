<?php

namespace App\Services;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Delivery;
use App\Exceptions\ValidationException;
use App\Exceptions\BusinessLogicException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Create an order from the caller's active cart in a single atomic transaction.
     */
    public function create(array $data, ?string $userId, string $sessionId): Order
    {
        return DB::transaction(function () use ($data, $userId, $sessionId) {
            $cartItems = CartItem::with('pet')
                ->when(
                    $userId,
                    fn ($q) => $q->where('user_id', $userId),
                    fn ($q) => $q->whereNull('user_id')->where('session_id', $sessionId)
                )
                ->get();

            if ($cartItems->isEmpty()) {
                throw new ValidationException(
                    'Your cart is empty.',
                    ['cart' => ['Your cart is empty.']]
                );
            }

            CartItem::whereIn('id', $cartItems->pluck('id'))
                ->update(['locked_until' => now()->addMinutes(15)]);

            $address = Address::create([
                'address_line' => $data['address_line'],
                'city'         => $data['city'] ?? null,
                'area'         => $data['area'] ?? null,
            ]);

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

            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id'             => $order->id,
                    'pet_id'               => $item->pet->id,
                    'pet_name_snapshot'    => $item->pet->name,
                    'pet_breed_snapshot'   => $item->pet->breed,
                    'pet_species_snapshot' => $item->pet->species,
                    'price_snapshot'       => $item->pet->price,
                ]);

                $item->pet->update(['status' => 'sold']);
                $item->delete();
            }

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => 'pending',
                'changed_by' => $userId,
                'notes'      => 'Order placed.',
            ]);

            Delivery::create([
                'order_id' => $order->id,
                'status'   => 'pending',
            ]);

            $this->notifications->sendOrderConfirmation($order->load(['items', 'deliveryAddress', 'user', 'guestContact']));

            return $order;
        });
    }

    /**
     * Admin: update order status with history record.
     *
     * @throws \App\Exceptions\BusinessLogicException
     */
    public function updateStatus(Order $order, string $newStatus, string $adminId, ?string $notes = null, ?string $cancellationReason = null): Order
    {
        $allowed = $this->allowedTransitions($order->status);
        if (! in_array($newStatus, $allowed)) {
            throw new BusinessLogicException(
                "Cannot transition from '{$order->status}' to '{$newStatus}'.",
                ['status' => ["Cannot transition from '{$order->status}' to '{$newStatus}'."]]
            );
        }

        DB::transaction(function () use ($order, $newStatus, $adminId, $notes, $cancellationReason) {
            $update = ['status' => $newStatus];

            if ($newStatus === 'cancelled') {
                $update['cancellation_reason'] = $cancellationReason;
                $update['cancelled_at']        = now();

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
