<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeliveryService
{
    /**
     * Update delivery status and timestamps.
     */
    public function updateStatus(Delivery $delivery, array $data): Delivery
    {
        $update = array_filter([
            'status'         => $data['status'] ?? null,
            'scheduled_date' => $data['scheduled_date'] ?? null,
            'notes'          => $data['notes'] ?? null,
        ], fn ($v) => $v !== null);

        if (isset($data['status'])) {
            if ($data['status'] === 'dispatched' && ! $delivery->dispatched_at) {
                $update['dispatched_at'] = now();
            }
            if ($data['status'] === 'delivered' && ! $delivery->delivered_at) {
                $update['delivered_at'] = now();
                // Also mark the parent order as delivered
                $delivery->order->update(['status' => 'delivered', 'delivered_at' => now()]);
            }
        }

        $delivery->update($update);

        return $delivery->fresh();
    }

    /**
     * Return a calendar view: deliveries grouped by date for a given month.
     * Returns array keyed by date (Y-m-d) => collection of deliveries.
     */
    public function getCalendar(int $year, int $month): Collection
    {
        $from = sprintf('%d-%02d-01', $year, $month);
        $to   = date('Y-m-t', strtotime($from));

        return Delivery::with(['order.deliveryAddress', 'order.items'])
            ->whereBetween('scheduled_date', [$from, $to])
            ->orderBy('scheduled_date')
            ->get()
            ->groupBy(fn ($d) => (string) $d->scheduled_date);
    }
}
