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

        $this->invalidateLiveCache();
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

        $this->invalidateLiveCache();
    }

    /**
     * Handle the Order "deleted" event.
     *
     * @param Order $order
     * @return void
     */
    public function deleted(Order $order): void
    {
        try {
            $this->cleanupOrderPlaces($order);
        } catch (\Throwable $e) {
            Log::error('Failed to clean up places on order delete: ' . $e->getMessage(), [
                'order_id'  => $order->public_id ?? $order->uuid,
                'exception' => $e,
            ]);
        }

        $this->invalidateLiveCache();
    }

    /**
     * Invalidate all live map caches when orders are created, updated, or deleted.
     */
    protected function invalidateLiveCache(): void
    {
        try {
            if (class_exists(\Fleetbase\FleetOps\Support\LiveCacheService::class)) {
                \Fleetbase\FleetOps\Support\LiveCacheService::invalidateMultiple(['orders', 'routes', 'coordinates', 'places']);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to invalidate live cache: ' . $e->getMessage());
        }
    }

    /**
     * Soft-delete ad-hoc places tied to a deleted order if they are not reusable landmarks
     * or referenced by any other active (non-deleted) order.
     *
     * @param Order $order
     * @return void
     */
    protected function cleanupOrderPlaces(Order $order): void
    {
        $payload = $order->payload;
        if (!$payload) {
            return;
        }

        $placeUuids = array_filter([
            $payload->pickup_uuid,
            $payload->dropoff_uuid,
            $payload->return_uuid,
        ]);

        if (empty($placeUuids)) {
            return;
        }

        foreach ($placeUuids as $placeUuid) {
            // Check if place is a reusable landmark / contact / vendor / company / asset
            $isReusable = \Illuminate\Support\Facades\DB::table('places')
                ->where('uuid', $placeUuid)
                ->where(function ($q) use ($placeUuid) {
                    $q->whereNotNull('owner_uuid')
                        ->orWhereExists(function ($sub) use ($placeUuid) {
                            $sub->selectRaw(1)->from('companies')->where('place_uuid', $placeUuid);
                        })
                        ->orWhereExists(function ($sub) use ($placeUuid) {
                            $sub->selectRaw(1)->from('contacts')->where('place_uuid', $placeUuid);
                        })
                        ->orWhereExists(function ($sub) use ($placeUuid) {
                            $sub->selectRaw(1)->from('vendors')->where('place_uuid', $placeUuid);
                        })
                        ->orWhereExists(function ($sub) use ($placeUuid) {
                            $sub->selectRaw(1)->from('assets')->where('current_place_uuid', $placeUuid);
                        });
                })
                ->exists();

            if ($isReusable) {
                continue;
            }

            // Check if place is referenced by any other active (non-deleted) order
            $isUsedByOtherActiveOrder = \Illuminate\Support\Facades\DB::table('orders')
                ->join('payloads', 'orders.payload_uuid', '=', 'payloads.uuid')
                ->where('orders.uuid', '!=', $order->uuid)
                ->whereNull('orders.deleted_at')
                ->whereRaw('(payloads.pickup_uuid = ? OR payloads.dropoff_uuid = ? OR payloads.return_uuid = ?)', [$placeUuid, $placeUuid, $placeUuid])
                ->exists();

            if ($isUsedByOtherActiveOrder) {
                continue;
            }

            // Soft-delete the place
            \Fleetbase\FleetOps\Models\Place::where('uuid', $placeUuid)->delete();
        }
    }
}
