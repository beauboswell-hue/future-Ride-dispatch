<?php

namespace App\Observers;

use App\Services\WebBookingNoteFormatter;
use Fleetbase\FleetOps\Models\Order;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     *
     * @param Order $order
     * @return void
     */
    public function created(Order $order): void
    {
        try {
            WebBookingNoteFormatter::formatAndSave($order);
        } catch (\Throwable $e) {
            Log::error('Failed to format web booking note for order: ' . $e->getMessage(), [
                'order_id'  => $order->public_id ?? $order->uuid,
                'exception' => $e,
            ]);
        }
    }
}
