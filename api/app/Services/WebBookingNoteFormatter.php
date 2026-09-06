<?php

namespace App\Services;

use Fleetbase\FleetOps\Models\Contact;
use Fleetbase\FleetOps\Models\Driver;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\Payload;
use Fleetbase\FleetOps\Models\Place;
use Fleetbase\FleetOps\Models\Vehicle;
use Fleetbase\LaravelMysqlSpatial\Types\Point;
use Fleetbase\Models\User;
use Fleetbase\Support\Timezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class WebBookingNoteFormatter
{
    /**
     * Format and persist notes for a web booking order.
     *
     * @param Order $order
     * @param bool  $force
     * @return bool Returns true if formatted and saved, false otherwise
     */
    public static function formatAndSave(Order $order, bool $force = false): bool
    {
        // Guard against infinite recursive update loops & repeated formatting
        if (str_contains($order->notes ?? '', 'Future Limo Dispatch')) {
            return false;
        }

        // Only format if this order is from a web booking source (unless forced)
        if (!$force && !static::isWebBooking($order)) {
            return false;
        }

        if ($order->exists) {
            $order->refresh();
        }

        $formattedNotes = static::generateMarkdownNote($order);

        // Assign formatted markdown block to notes attribute
        $order->notes = $formattedNotes;

        // If meta was missing 'source' but request metadata has it, persist it too
        $meta = $order->meta ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?? [];
        }
        if (empty($meta['source']) && request()) {
            $reqSource = request()->input('metadata.source') ?? request()->input('meta.source');
            if (!empty($reqSource)) {
                $meta['source'] = $reqSource;
                $order->meta = $meta;
            }
        }

        // Persist quietly to prevent firing updating/updated events recursively
        $order->saveQuietly();

        return true;
    }

    /**
     * Determine if an order is from a web booking request.
     *
     * @param Order $order
     * @return bool
     */
    public static function isWebBooking(Order $order): bool
    {
        $meta = $order->meta ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?? [];
        }

        $reqMeta = [];
        if (request()) {
            $reqMeta = request()->input('metadata') ?? request()->input('meta') ?? [];
            if (!is_array($reqMeta)) {
                $reqMeta = [];
            }
        }

        // Check explicit flag
        if (!empty($meta['is_web_booking']) || !empty($reqMeta['is_web_booking'])) {
            return true;
        }

        // Check source attribute in meta or request
        $source = data_get($meta, 'source')
            ?? data_get($reqMeta, 'source')
            ?? data_get($meta, 'source_form')
            ?? data_get($reqMeta, 'source_form')
            ?? data_get($meta, 'booking_source')
            ?? data_get($reqMeta, 'booking_source');

        if (!empty($source)) {
            return true;
        }

        // Check initial order notes for keywords
        $notes = $order->getOriginal('notes') ?: $order->notes;
        if ($notes && preg_match('/web\s*booking|website\s*form|online\s*booking|homepage\s*form/i', $notes)) {
            return true;
        }

        // Check request route or URL
        if (request() && (request()->is('*booking*') || request()->is('*webhook*'))) {
            return true;
        }

        return false;
    }

    /**
     * Generate the markdown note string for the order.
     *
     * @param Order $order
     * @return string
     */
    /**
     * Generate the markdown note string for the order.
     *
     * @param Order $order
     * @return string
     */
    public static function generateMarkdownNote(Order $order): string
    {
        $customerInfo = static::extractCustomerInfo($order);
        $timingInfo = static::extractTimingInfo($order, $customerInfo['timezone_raw']);
        $locationInfo = static::extractLocationInfo($order);
        $assignmentInfo = static::extractAssignmentInfo($order);

        $scheduledLocalFormatted = $timingInfo['local'];
        $scheduledUtcFormatted = str_replace(' UTC', '', $timingInfo['utc']);

        $passengerName = $customerInfo['name'];
        $passengerPhone = static::formatPhone($customerInfo['phone']);
        $passengerEmail = $customerInfo['email'];

        $cleanPickupAddress = static::sanitizeAddress($locationInfo['pickup']);
        $cleanDropoffAddress = static::sanitizeAddress($locationInfo['dropoff']);

        $status = !empty($order->status) ? $order->status : 'created';
        $driverName = $assignmentInfo['driver'];
        $vehicleName = $assignmentInfo['vehicle'];

        $orderPublicId = $order->public_id ?? $order->uuid ?? 'N/A';
        $orderInternalId = $order->id ?? 'N/A';
        $trackingNumber = $order->tracking_number ?? 'N/A';

        $lines = [
            "🕒 Scheduled: {$scheduledLocalFormatted} ({$scheduledUtcFormatted} UTC)",
            "Future Limo Dispatch",
            "────────────────────────────────────────",
            "👤 Passenger: {$passengerName}",
            "📞 Phone: {$passengerPhone}",
            "✉️ Email: {$passengerEmail}",
            "",
            "📍 Pickup: {$cleanPickupAddress}",
            "🏁 Dropoff: {$cleanDropoffAddress}",
            "",
            "🚗 Status: {$status} | Driver: {$driverName} | Vehicle: {$vehicleName}",
            "────────────────────────────────────────",
            "🆔 Order ID: {$orderPublicId}",
            "🔢 Internal ID: {$orderInternalId}",
            "📦 Tracking #: {$trackingNumber}",
            "────────────────────────────────────────",
        ];

        return implode("\n", $lines);
    }

    /**
     * Format phone numbers nicely as standard US numbers.
     *
     * @param string|null $phone
     * @return string
     */
    public static function formatPhone(?string $phone): string
    {
        if (empty($phone) || $phone === 'N/A') {
            return 'N/A';
        }

        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        $length = strlen($cleaned);

        if ($length === 10) {
            return '(' . substr($cleaned, 0, 3) . ') ' . substr($cleaned, 3, 3) . '-' . substr($cleaned, 6);
        } elseif ($length === 11 && $cleaned[0] === '1') {
            return '+1 (' . substr($cleaned, 1, 3) . ') ' . substr($cleaned, 4, 3) . '-' . substr($cleaned, 7);
        } elseif ($length > 10) {
            return '+' . $cleaned;
        }

        return $phone;
    }

    /**
     * Sanitize pickup and dropoff addresses.
     *
     * Strips "PICKUP LOCATION - ", "DROPOFF LOCATION - " and trailing ", UNITED STATES".
     *
     * @param string|null $address
     * @return string
     */
    public static function sanitizeAddress(?string $address): string
    {
        if (empty($address) || $address === 'N/A') {
            return 'N/A';
        }

        // Strip prefix
        $address = preg_replace('/^(pickup|dropoff)\s*location\s*-\s*/i', '', $address);
        
        // Strip trailing ", UNITED STATES" (preserving coordinates and warning indicators)
        $address = preg_replace('/,\s*united states\s*(\s*\(coordinates:.*?\))?(\s*⚠️.*)?$/i', '$1$2', $address);
        $address = preg_replace('/,\s*united states\s*$/i', '', $address);

        return trim($address);
    }

    /**
     * Extract customer contact info (null-safe).
     *
     * @param Order $order
     * @return array
     */
    protected static function extractCustomerInfo(Order $order): array
    {
        if (!$order->relationLoaded('customer') && $order->customer_uuid) {
            $order->load('customer');
        }

        $customer = $order->customer;

        $reqCustomer = [];
        if (request()) {
            $reqCustomer = request()->input('customer') ?? [];
            if (!is_array($reqCustomer)) {
                $reqCustomer = [];
            }
        }

        $meta = $order->meta ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?? [];
        }

        // Customer Name
        $name = $customer?->name
            ?? data_get($reqCustomer, 'name')
            ?? data_get($meta, 'customer.name')
            ?? data_get($meta, 'name');

        // Customer Phone
        $phone = $customer?->phone
            ?? data_get($reqCustomer, 'phone')
            ?? data_get($meta, 'customer.phone')
            ?? data_get($meta, 'phone');

        // Customer Email
        $email = $customer?->email
            ?? data_get($reqCustomer, 'email')
            ?? data_get($meta, 'customer.email')
            ?? data_get($meta, 'email');

        // Customer Username
        $username = null;
        if ($customer instanceof User) {
            $username = $customer->username;
        } elseif ($customer instanceof Contact || $customer instanceof \Fleetbase\Models\Contact) {
            $username = $customer->user?->username
                ?? $customer->anyUser?->username
                ?? $customer->username
                ?? data_get($customer->meta, 'username');
        }
        if (!$username) {
            $username = data_get($reqCustomer, 'username')
                ?? data_get($meta, 'customer.username')
                ?? data_get($meta, 'username');
        }

        // Customer Timezone
        $timezone = null;
        if ($customer instanceof User) {
            $timezone = $customer->timezone;
        } elseif ($customer instanceof Contact || $customer instanceof \Fleetbase\Models\Contact) {
            $timezone = $customer->user?->timezone
                ?? $customer->anyUser?->timezone
                ?? $customer->timezone
                ?? data_get($customer->meta, 'timezone');
        }
        if (!$timezone) {
            $timezone = data_get($reqCustomer, 'timezone')
                ?? data_get($meta, 'customer.timezone')
                ?? data_get($meta, 'timezone');
        }

        $clean = function ($value) {
            if ($value === null) {
                return 'N/A';
            }
            $trimmed = trim((string) $value);
            return ($trimmed === '' || $trimmed === 'N/A') ? 'N/A' : $trimmed;
        };

        return [
            'name'         => $clean($name),
            'phone'        => $clean($phone),
            'email'        => $clean($email),
            'username'     => $clean($username),
            'timezone'     => $clean($timezone),
            'timezone_raw' => (!empty($timezone) && $clean($timezone) !== 'N/A') ? trim((string) $timezone) : null,
        ];
    }

    /**
     * Extract timing info converted into UTC, local pickup timezone, and Eastern Time.
     *
     * @param Order       $order
     * @param string|null $customerTz
     * @return array
     */
    protected static function extractTimingInfo(Order $order, ?string $customerTz = null): array
    {
        $scheduledAt = $order->scheduled_at
            ?? (request() ? request()->input('scheduled_at') : null)
            ?? data_get($order->meta, 'scheduled_at');

        if (empty($scheduledAt)) {
            return [
                'utc'     => 'N/A',
                'local'   => 'N/A',
                'eastern' => 'N/A',
            ];
        }

        try {
            $carbon = Carbon::parse($scheduledAt);

            // UTC
            $utc = $carbon->copy()->setTimezone('UTC')->format('Y-m-d H:i:s T');

            // Resolve local pickup timezone
            $localTz = static::resolveLocalPickupTimezone($order, $customerTz);
            $local = $carbon->copy()->setTimezone($localTz)->format('Y-m-d H:i:s T');

            // Eastern Time
            $eastern = $carbon->copy()->setTimezone('America/New_York')->format('Y-m-d H:i:s T');

            return [
                'utc'     => $utc,
                'local'   => $local,
                'eastern' => $eastern,
            ];
        } catch (\Throwable $e) {
            return [
                'utc'     => 'N/A',
                'local'   => 'N/A',
                'eastern' => 'N/A',
            ];
        }
    }

    /**
     * Resolve the local timezone for the pickup location.
     *
     * @param Order       $order
     * @param string|null $customerTz
     * @return string
     */
    protected static function resolveLocalPickupTimezone(Order $order, ?string $customerTz = null): string
    {
        $candidates = [];

        // 1. Pickup place timezone
        $pickup = $order->payload?->pickup ?? $order->payload?->getPickupOrCurrentWaypoint();
        if ($pickup) {
            $candidates[] = data_get($pickup, 'timezone');
            $candidates[] = data_get($pickup, 'meta.timezone');
        }

        // 2. Order meta pickup timezone
        $meta = $order->meta ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?? [];
        }
        $candidates[] = data_get($meta, 'pickup_timezone');
        $candidates[] = data_get($meta, 'pickup_tz');

        // 3. Customer timezone
        if ($customerTz) {
            $candidates[] = $customerTz;
        }

        // 4. Request input timezone
        if (request()) {
            $candidates[] = request()->input('payload.pickup.timezone');
            $candidates[] = request()->input('timezone');
        }

        // 5. Place province / state heuristic (US states)
        $province = strtoupper(trim((string) ($pickup?->province ?? data_get(request()?->input('payload'), 'pickup.province', ''))));
        $stateTzMap = [
            'NY' => 'America/New_York',
            'FL' => 'America/New_York',
            'NJ' => 'America/New_York',
            'MA' => 'America/New_York',
            'PA' => 'America/New_York',
            'CA' => 'America/Los_Angeles',
            'WA' => 'America/Los_Angeles',
            'IL' => 'America/Chicago',
            'TX' => 'America/Chicago',
            'CO' => 'America/Denver',
        ];
        if (isset($stateTzMap[$province])) {
            $candidates[] = $stateTzMap[$province];
        }

        // 6. Company timezone
        $candidates[] = data_get($order->company, 'timezone');

        // 7. Default to Eastern Time
        $candidates[] = 'America/New_York';

        $validIdentifiers = \DateTimeZone::listIdentifiers();
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                $candidate = trim($candidate);
                if (in_array($candidate, $validIdentifiers, true)) {
                    return $candidate;
                }
            }
        }

        return 'America/New_York';
    }

    /**
     * Extract pickup and dropoff locations & coordinates, with match detection.
     *
     * @param Order $order
     * @return array
     */
    protected static function extractLocationInfo(Order $order): array
    {
        $order->loadMissing(['payload.pickup', 'payload.dropoff', 'payload.waypoints']);
        $payload = $order->payload;

        $pickup = $payload?->pickup ?? $payload?->getPickupOrCurrentWaypoint();
        $dropoff = $payload?->dropoff ?? $payload?->getDropoffOrLastWaypoint();

        $pickupData = static::parsePlace($pickup, 'pickup');
        $dropoffData = static::parsePlace($dropoff, 'dropoff');

        // Check if pickup and dropoff match
        $isMatch = false;

        // 1. Matching Place UUIDs
        if ($pickup && $dropoff && $pickup->uuid && $dropoff->uuid && $pickup->uuid === $dropoff->uuid) {
            $isMatch = true;
        } elseif (!empty($payload?->pickup_uuid) && !empty($payload?->dropoff_uuid) && $payload->pickup_uuid === $payload->dropoff_uuid) {
            $isMatch = true;
        }
        // 2. Matching normalized address strings
        elseif (!empty($pickupData['address_clean']) && !empty($dropoffData['address_clean']) && strcasecmp($pickupData['address_clean'], $dropoffData['address_clean']) === 0) {
            $isMatch = true;
        }
        // 3. Matching coordinates (within 0.0001 degrees)
        elseif ($pickupData['lat'] !== null && $pickupData['lng'] !== null && $dropoffData['lat'] !== null && $dropoffData['lng'] !== null) {
            if (abs($pickupData['lat'] - $dropoffData['lat']) < 0.0001 && abs($pickupData['lng'] - $dropoffData['lng']) < 0.0001) {
                $isMatch = true;
            }
        }

        $dropoffDisplay = $dropoffData['display'];
        if ($isMatch) {
            $dropoffDisplay .= ' ⚠️ [WARNING: Same as pickup location]';
        }

        return [
            'pickup'   => $pickupData['display'],
            'dropoff'  => $dropoffDisplay,
            'is_match' => $isMatch,
        ];
    }

    /**
     * Parse a Place model or request input into address and coordinates.
     *
     * @param Place|null $place
     * @param string     $type  'pickup' or 'dropoff'
     * @return array
     */
    protected static function parsePlace(?Place $place, string $type): array
    {
        $address = $place?->address ?? $place?->name ?? $place?->street1;

        if (empty($address) && request()) {
            $reqPayload = request()->input('payload');
            $address = data_get($reqPayload, "{$type}.address")
                ?? data_get($reqPayload, "{$type}.street1")
                ?? data_get($reqPayload, "{$type}.name");
        }

        // Coordinates
        $lat = null;
        $lng = null;

        if ($place && $place->location instanceof Point) {
            $lat = $place->location->getLat();
            $lng = $place->location->getLng();
        } elseif ($place && is_array($place->location) && isset($place->location['coordinates'])) {
            $coords = $place->location['coordinates'];
            $lng = $coords[0] ?? null;
            $lat = $coords[1] ?? null;
        } elseif (request()) {
            $reqCoords = data_get(request()->input('payload'), "{$type}.location.coordinates");
            if (is_array($reqCoords) && count($reqCoords) >= 2) {
                $lng = $reqCoords[0] ?? null;
                $lat = $reqCoords[1] ?? null;
            }
        }

        $addressStr = !empty($address) ? trim((string) $address) : '';
        $coordStr = ($lat !== null && $lng !== null && !($lat == 0 && $lng == 0))
            ? sprintf('%.6f, %.6f', (float) $lat, (float) $lng)
            : null;

        if ($addressStr !== '' && $coordStr !== null) {
            $display = "{$addressStr} (Coordinates: {$coordStr})";
        } elseif ($addressStr !== '') {
            $display = $addressStr;
        } elseif ($coordStr !== null) {
            $display = "Coordinates: {$coordStr}";
        } else {
            $display = 'N/A';
        }

        return [
            'address'       => $addressStr,
            'address_clean' => strtolower(preg_replace('/[^a-z0-9]/i', '', $addressStr)),
            'lat'           => $lat !== null ? (float) $lat : null,
            'lng'           => $lng !== null ? (float) $lng : null,
            'display'       => $display,
        ];
    }

    /**
     * Extract assigned driver and vehicle names.
     *
     * @param Order $order
     * @return array
     */
    protected static function extractAssignmentInfo(Order $order): array
    {
        $order->loadMissing(['driverAssigned', 'vehicleAssigned']);

        $driverName = $order->driver?->name
            ?? $order->driverAssigned?->name
            ?? (request() ? request()->input('driver_name') : null);

        if (empty($driverName) || trim((string) $driverName) === '' || trim((string) $driverName) === 'None Assigned') {
            $driverName = 'None Assigned';
        } else {
            $driverName = trim((string) $driverName);
        }

        $vehicleName = $order->vehicle?->display_name
            ?? $order->vehicle?->name
            ?? $order->vehicleAssigned?->display_name
            ?? $order->vehicleAssigned?->name
            ?? (request() ? request()->input('vehicle_name') : null);

        if (empty($vehicleName) || trim((string) $vehicleName) === '' || trim((string) $vehicleName) === 'None Assigned') {
            $vehicleName = 'None Assigned';
        } else {
            $vehicleName = trim((string) $vehicleName);
        }

        return [
            'driver'  => $driverName,
            'vehicle' => $vehicleName,
        ];
    }

    /**
     * Extract booking source, operational tags, or user submission comments.
     *
     * @param Order $order
     * @return string
     */
    protected static function extractBookingSource(Order $order): string
    {
        $meta = $order->meta ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?? [];
        }

        $reqMeta = [];
        if (request()) {
            $reqMeta = request()->input('metadata') ?? request()->input('meta') ?? [];
            if (!is_array($reqMeta)) {
                $reqMeta = [];
            }
        }

        // Source
        $source = data_get($meta, 'source')
            ?? data_get($reqMeta, 'source')
            ?? data_get($meta, 'source_form')
            ?? data_get($reqMeta, 'source_form')
            ?? data_get($meta, 'booking_source')
            ?? data_get($reqMeta, 'booking_source');

        // Original notes / user comments
        $originalNotes = $order->getOriginal('notes') ?: $order->notes;
        if ($originalNotes && str_starts_with(trim($originalNotes), '### 👤 Contact Information')) {
            $originalNotes = null;
        }

        $userComments = data_get($meta, 'comments')
            ?? data_get($reqMeta, 'comments')
            ?? data_get($meta, 'user_comments')
            ?? data_get($reqMeta, 'user_comments');

        if (empty($originalNotes) && !empty($userComments)) {
            $originalNotes = $userComments;
        }

        // Combine source and notes
        $parts = [];
        if (!empty($source)) {
            $parts[] = trim((string) $source);
        }
        if (!empty($originalNotes) && (empty($source) || strcasecmp(trim((string) $source), trim((string) $originalNotes)) !== 0)) {
            $parts[] = trim((string) $originalNotes);
        }

        $result = !empty($parts) ? implode(' - ', $parts) : 'Website Form';

        // Operational tags
        $tags = data_get($meta, 'tags')
            ?? data_get($reqMeta, 'tags')
            ?? data_get($meta, 'operational_tags')
            ?? data_get($reqMeta, 'operational_tags');

        if (!empty($tags)) {
            $tagsStr = is_array($tags) ? implode(', ', $tags) : (string) $tags;
            if (trim($tagsStr) !== '') {
                $result .= " [Tags: {$tagsStr}]";
            }
        }

        return $result;
    }
}
