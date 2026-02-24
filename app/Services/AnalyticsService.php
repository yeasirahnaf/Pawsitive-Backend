<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Sales metrics for a given date range.
     */
    public function getSales(string $from, string $to): array
    {
        $rows = DB::select(
            "SELECT
                DATE_TRUNC('day', created_at) AS date,
                COUNT(*)                       AS total_orders,
                SUM(total)                     AS revenue
            FROM orders
            WHERE created_at BETWEEN ? AND ?
              AND status NOT IN ('cancelled')
            GROUP BY 1
            ORDER BY 1",
            [$from, $to]
        );

        return [
            'total_orders'  => array_sum(array_column($rows, 'total_orders')),
            'total_revenue' => array_sum(array_column($rows, 'revenue')),
            'by_day'        => $rows,
        ];
    }

    /**
     * Inventory snapshot by status.
     */
    public function getInventory(): array
    {
        $rows = DB::select(
            "SELECT status, COUNT(*) AS count FROM pets WHERE deleted_at IS NULL GROUP BY status"
        );

        $map = ['available' => 0, 'reserved' => 0, 'sold' => 0];
        foreach ($rows as $row) {
            $map[$row->status] = (int) $row->count;
        }

        return $map;
    }

    /**
     * Customer metrics.
     */
    public function getCustomers(): array
    {
        $total       = DB::scalar("SELECT COUNT(*) FROM users WHERE role = 'customer'");
        $withOrders  = DB::scalar("SELECT COUNT(DISTINCT user_id) FROM orders WHERE user_id IS NOT NULL");
        $guestOrders = DB::scalar("SELECT COUNT(*) FROM orders WHERE user_id IS NULL");

        return [
            'total_customers'   => (int) $total,
            'customers_ordered' => (int) $withOrders,
            'guest_orders'      => (int) $guestOrders,
        ];
    }
}
