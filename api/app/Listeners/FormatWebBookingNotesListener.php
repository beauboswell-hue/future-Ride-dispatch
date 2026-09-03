<?php

namespace App\Listeners;

use App\Services\WebBookingNoteFormatter;
use Fleetbase\FleetOps\Models\Order;
use Illuminate\Support\Facades\Log;

class FormatWebBookingNotesListener
{
    /**
     * Handle the event.
     *
     * Accepts either an Order model directly, or an event containing an $order property.
     *
     * @param mixed $event
     * @return void
     */
    public function handle($event): void
    {
        $order = null;

        if ($event instanceof Order) {
            $order = $event;
        } elseif (is_object($event) && isset($event->order) && $event->order instanceof Order) {
            $order = $event->order;
        }

        if ($order) {
            try {
                WebBookingNoteFormatter::formatAndSave($order);
            } catch (\Throwable $e) {
                Log::error('FormatWebBookingNotesListener error: ' . $e->getMessage(), [
                    'order_id'  => $order->public_id ?? $order->uuid,
                    'exception' => $e,
                ]);
            }
        }
    }
}
