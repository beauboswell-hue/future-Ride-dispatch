<?php

namespace App\Observers;

use App\Services\WebBookingNoteFormatter;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\Place;
use Fleetbase\Services\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Internal dispatch alert recipients.
     *
     * @var array<string, string>
     */
    public const DISPATCH_RECIPIENTS = [
        '+12394652364' => 'Beau Boswell',
        '+18088407370' => 'Tom Brennan',
    ];

    /**
     * Outbound SMS sender phone number.
     *
     * @var string
     */
    public const FROM_PHONE_NUMBER = '+18888669741';

    /**
     * Handle the Order "saving" event.
     * Prevents console updates or partial payloads from blowing away existing booking notes and metadata.
     * Normalizes passenger_count, vehicle_selection, and notes in order meta.
     *
     * @param Order $order
     * @return void
     */
    public function saving(Order $order): void
    {
        // 1. Prevent wiping out existing notes
        $origNotes = $order->getOriginal('notes');
        if (!empty($origNotes) && (empty($order->notes) || trim((string)$order->notes) === '')) {
            $reqNotes = request() ? request()->input('notes') : null;
            if ($reqNotes === null || $reqNotes === '') {
                $order->notes = $origNotes;
            }
        }

        // 2. Prevent wiping out metadata (e.g. when console sends meta: {"_index_resource": true})
        $origMeta = $order->getOriginal('meta');
        if (is_string($origMeta)) {
            $origMeta = json_decode($origMeta, true) ?? [];
        }
        $currentMeta = $order->meta ?? [];
        if (is_string($currentMeta)) {
            $currentMeta = json_decode($currentMeta, true) ?? [];
        }

        if (is_array($origMeta) && !empty($origMeta)) {
            $preserveKeys = [
                'vehicle_type', 'vehicle_selection', 'vehicle',
                'passengers', 'passenger_count', 'pax',
                'child_seats', 'seats',
                'passenger_name', 'passenger_phone', 'passenger_email',
                'source', 'source_form', 'notes', 'special_instructions', 'comments',
                'description', 'flight_number', 'sms_consent'
            ];
            foreach ($preserveKeys as $k) {
                if (isset($origMeta[$k]) && (!isset($currentMeta[$k]) || $currentMeta[$k] === null || $currentMeta[$k] === '' || $currentMeta[$k] === 'N/A')) {
                    $currentMeta[$k] = $origMeta[$k];
                }
            }
        }

        // 3. Normalize aliases in metadata
        $vehicle = $currentMeta['vehicle_selection'] ?? $currentMeta['vehicle_type'] ?? $currentMeta['vehicle'] ?? null;
        if (!empty($vehicle) && $vehicle !== 'N/A') {
            $currentMeta['vehicle_selection'] = $vehicle;
            $currentMeta['vehicle_type'] = $vehicle;
            $currentMeta['vehicle'] = $vehicle;
        }

        $pax = $currentMeta['passenger_count'] ?? $currentMeta['passengers'] ?? $currentMeta['pax'] ?? null;
        if ($pax !== null && $pax !== '' && $pax !== 'N/A') {
            $currentMeta['passenger_count'] = $pax;
            $currentMeta['passengers'] = $pax;
            $currentMeta['pax'] = $pax;
        }

        $seats = $currentMeta['child_seats'] ?? $currentMeta['seats'] ?? null;
        if ($seats !== null && $seats !== '' && $seats !== 'N/A') {
            $currentMeta['child_seats'] = $seats;
            $currentMeta['seats'] = $seats;
        }

        $notesVal = $currentMeta['notes'] ?? $currentMeta['special_instructions'] ?? $currentMeta['comments'] ?? null;
        if (empty($notesVal) && !empty($order->notes) && !str_contains($order->notes, 'PASSENGER:') && !str_contains($order->notes, 'Future Limo Dispatch')) {
            $notesVal = $order->notes;
        }
        if (!empty($notesVal)) {
            $currentMeta['notes'] = $notesVal;
            $currentMeta['special_instructions'] = $notesVal;
        }

        $order->meta = $currentMeta;
    }

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

        // 1. Dispatch Team Alert
        $this->sendDispatchAlerts($order);

        // 2. Customer Booking Confirmation
        $this->sendCustomerConfirmation($order);
    }

    /**
     * Send internal dispatch alert SMS to dispatch team members.
     * Each transmission is isolated in its own try/catch block.
     *
     * @param Order $order
     * @return void
     */
    public function sendDispatchAlerts(Order $order): void
    {
        $orderId = $order->public_id ?? $order->internal_id ?? $order->uuid;
        $message = "Future Limo Alert: A new booking request has been received. Please check the dispatch console.";

        foreach (self::DISPATCH_RECIPIENTS as $phone => $name) {
            try {
                $sms = app(SmsService::class);
                $sms->send($phone, $message, [
                    'from' => self::FROM_PHONE_NUMBER,
                ]);
                Log::info("Order SMS dispatch alert sent to {$name} ({$phone})", [
                    'order_id' => $orderId,
                ]);
            } catch (\Throwable $e) {
                Log::error("Order SMS dispatch alert failed to {$name} ({$phone}): " . $e->getMessage(), [
                    'order_id'  => $orderId,
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * Send customer booking confirmation acknowledgment SMS.
     * Wrapped in its own try/catch block so delivery errors do not block order creation.
     *
     * @param Order $order
     * @return void
     */
    public function sendCustomerConfirmation(Order $order): void
    {
        $orderId = $order->public_id ?? $order->internal_id ?? $order->uuid;

        try {
            $rawPhone = $this->resolveCustomerPhone($order);
            $customerPhone = $this->sanitizePhoneNumber($rawPhone);

            if (!$customerPhone) {
                Log::info('No valid customer phone number resolved; skipping customer booking confirmation SMS', [
                    'order_id'  => $orderId,
                    'raw_phone' => $rawPhone,
                ]);
                return;
            }

            $firstName = $this->resolveCustomerFirstName($order);
            $message = "Hello {$firstName}, thank you for choosing Future Limo! We have received your booking request (Order #{$orderId}). Our dispatch team will call you shortly to confirm your details. Reply HELP for help or STOP to unsubscribe.";

            $sms = app(SmsService::class);
            $sms->send($customerPhone, $message, [
                'from' => self::FROM_PHONE_NUMBER,
            ]);

            Log::info("Customer booking confirmation SMS sent to {$customerPhone}", [
                'order_id'   => $orderId,
                'first_name' => $firstName,
            ]);
        } catch (\Throwable $e) {
            Log::error('Customer booking confirmation SMS failed: ' . $e->getMessage(), [
                'order_id'  => $orderId,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Resolve the client/customer phone number from the order.
     * Checks $order->customer->phone, pickup place phone, and order metadata.
     *
     * @param Order $order
     * @return string|null
     */
    public function resolveCustomerPhone(Order $order): ?string
    {
        // 1. Check $order->customer->phone
        if ($order->customer_uuid && (!$order->relationLoaded('customer') || $order->customer === null)) {
            try {
                $order->load('customer');
            } catch (\Throwable $e) {
                // Ignore relation load failure
            }
        }

        $phone = $order->customer?->phone ?? $order->customer_phone;
        if (!empty($phone) && !in_array(strtolower(trim($phone)), ['n/a', 'none', 'null', 'undefined'], true)) {
            return trim($phone);
        }

        // 2. Check pickup place phone
        if (!$order->relationLoaded('payload')) {
            try {
                $order->loadMissing('payload.pickup');
            } catch (\Throwable $e) {
                // Ignore relation load failure
            }
        }

        $pickupPhone = $order->payload?->pickup?->phone;
        if (!empty($pickupPhone) && !in_array(strtolower(trim($pickupPhone)), ['n/a', 'none', 'null', 'undefined'], true)) {
            return trim($pickupPhone);
        }

        if (!empty($order->pickup_uuid)) {
            try {
                $pickupPlace = Place::where('uuid', $order->pickup_uuid)->first();
                if ($pickupPlace && !empty($pickupPlace->phone) && !in_array(strtolower(trim($pickupPlace->phone)), ['n/a', 'none', 'null', 'undefined'], true)) {
                    return trim($pickupPlace->phone);
                }
            } catch (\Throwable $e) {
                // Ignore place query failure
            }
        }

        // 3. Fallback to order metadata
        $meta = $order->meta ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?? [];
        }

        $metaPhone = data_get($meta, 'passenger_phone') 
            ?? data_get($meta, 'customer_phone') 
            ?? data_get($meta, 'customer.phone') 
            ?? data_get($meta, 'phone');

        if (!empty($metaPhone) && !in_array(strtolower(trim($metaPhone)), ['n/a', 'none', 'null', 'undefined'], true)) {
            return trim($metaPhone);
        }

        return null;
    }

    /**
     * Resolve customer first name for greeting personalization.
     *
     * @param Order $order
     * @return string
     */
    public function resolveCustomerFirstName(Order $order): string
    {
        $fullName = null;

        // 1. From customer relation or accessor
        if ($order->customer_uuid && (!$order->relationLoaded('customer') || $order->customer === null)) {
            try {
                $order->load('customer');
            } catch (\Throwable $e) {
                // Ignore relation load failure
            }
        }

        if ($order->customer && !empty($order->customer->name)) {
            $fullName = $order->customer->name;
        } elseif (!empty($order->customer_name)) {
            $fullName = $order->customer_name;
        }

        // 2. From metadata
        if (empty($fullName) || in_array(strtolower(trim($fullName)), ['n/a', 'none', 'null', 'undefined'], true)) {
            $meta = $order->meta ?? [];
            if (is_string($meta)) {
                $meta = json_decode($meta, true) ?? [];
            }
            $fullName = data_get($meta, 'passenger_name') 
                ?? data_get($meta, 'customer_name') 
                ?? data_get($meta, 'customer.name') 
                ?? data_get($meta, 'name');
        }

        // 3. From pickup place name (if not a generic label)
        if (empty($fullName) || in_array(strtolower(trim($fullName)), ['n/a', 'none', 'null', 'undefined'], true)) {
            $pickup = $order->payload?->pickup;
            if ($pickup && !empty($pickup->name)) {
                $extracted = trim(explode(' (', $pickup->name)[0]);
                if (!preg_match('/^(pickup|dropoff)/i', $extracted)) {
                    $fullName = $extracted;
                }
            }
        }

        if (!empty($fullName) && !in_array(strtolower(trim($fullName)), ['n/a', 'none', 'null', 'undefined'], true)) {
            $parts = preg_split('/\s+/', trim($fullName));
            if (!empty($parts[0])) {
                $honorifics = ['mr', 'mr.', 'mrs', 'mrs.', 'ms', 'ms.', 'miss', 'dr', 'dr.', 'prof', 'prof.'];
                if (in_array(strtolower($parts[0]), $honorifics, true) && !empty($parts[1])) {
                    return $parts[1];
                }
                return $parts[0];
            }
        }

        return 'there';
    }

    /**
     * Sanitize phone numbers to standard E.164 format (+1XXXXXXXXXX).
     *
     * @param string|null $phone
     * @return string|null
     */
    public function sanitizePhoneNumber(?string $phone): ?string
    {
        if (empty($phone) || in_array(strtolower(trim($phone)), ['n/a', 'none', 'null', 'undefined'], true)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        // 10-digit US number (e.g. 2394652364)
        if (strlen($digits) === 10) {
            return '+1' . $digits;
        }

        // 11-digit US number starting with 1 (e.g. 12394652364)
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return '+' . $digits;
        }

        // If explicitly formatted as international E.164 (+ followed by 10-15 digits)
        if (str_starts_with(trim($phone), '+') && strlen($digits) >= 10 && strlen($digits) <= 15) {
            return '+' . $digits;
        }

        return null;
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
