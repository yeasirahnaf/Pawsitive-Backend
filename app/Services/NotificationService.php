<?php

namespace App\Services;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Send an order confirmation email to the buyer synchronously.
     * Called by OrderService::create() immediately after a successful purchase.
     */
    public function sendOrderConfirmation(Order $order): void
    {
        $email = $order->user?->email ?? $order->guestContact?->email;

        if (! $email) {
            return;
        }

        Mail::to($email)->send(new OrderConfirmationMail($order));
    }
}
