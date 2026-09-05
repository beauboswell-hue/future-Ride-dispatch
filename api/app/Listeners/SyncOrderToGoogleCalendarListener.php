<?php

namespace App\Listeners;

use App\Services\GoogleCalendarService;
use Fleetbase\FleetOps\Models\Order;
use Illuminate\Support\Facades\Log;

class SyncOrderToGoogleCalendarListener
{
    /**
     * Handle the order event.
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
                if ($order->wasRecentlyCreated) {
                    app(GoogleCalendarService::class)->createEvent($order);
                } else {
                    app(GoogleCalendarService::class)->updateEvent($order);
                }
            } catch (\Throwable $e) {
                Log::error('SyncOrderToGoogleCalendarListener error: ' . $e->getMessage(), [
                    'order_id'  => $order->public_id ?? $order->uuid,
                    'exception' => $e,
                ]);
            }
        }
    }
}
