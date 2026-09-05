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

        try {
            if (class_exists(\App\Services\GoogleCalendarService::class)) {
                app(\App\Services\GoogleCalendarService::class)->createEvent($order);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to sync order to Google Calendar on create: ' . $e->getMessage(), [
                'order_id'  => $order->public_id ?? $order->uuid,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Handle the Order "updated" event.
     *
     * @param Order $order
     * @return void
     */
    public function updated(Order $order): void
    {
        try {
            if (class_exists(\App\Services\GoogleCalendarService::class)) {
                app(\App\Services\GoogleCalendarService::class)->updateEvent($order);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to sync order to Google Calendar on update: ' . $e->getMessage(), [
                'order_id'  => $order->public_id ?? $order->uuid,
                'exception' => $e,
            ]);
        }
    }
}
