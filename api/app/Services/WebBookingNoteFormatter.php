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
     * Cache of the current request data.
     *
     * @var array|null
     */
    private static ?array $requestData = null;

    /**
     * Set the current request data cache.
     *
     * @param array|null $data
     * @return void
     */
    public static function setRequestData(?array $data): void
    {
        self::$requestData = $data;
    }

    /**
     * Get the cached request data.
     *
     * @return array|null
     */
    public static function getRequestData(): ?array
    {
        return self::$requestData;
    }

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
        if (!$force && (str_contains($order->notes ?? '', 'PASSENGER:') || str_contains($order->notes ?? '', 'Future Limo Dispatch'))) {
            if ($order->customer_uuid) {
                return false;
            }
        }

        // Only format if this order is from a web booking source (unless forced)
        if (!$force && !static::isWebBooking($order)) {
            if ($order->customer_uuid) {
                return false;
            }
        }

        if ($order->exists) {
            $order->refresh();
        }

        // 1. Ingest and extract metadata
        $meta = $order->meta ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?? [];
        }

        $order->loadMissing('payload');
        $payloadMeta = $order->payload?->meta ?? [];
        if (is_string($payloadMeta)) {
            $payloadMeta = json_decode($payloadMeta, true) ?? [];
        }

        $isValidVal = function ($v) {
            if ($v === null || $v === '') {
                return false;
            }
            if (is_string($v)) {
                $clean = strtolower(trim($v));
                return !in_array($clean, ['n/a', 'none', '--', 'null', 'undefined']);
            }
            return true;
        };

        $getVal = function ($keys) use ($meta, $payloadMeta, $order, $isValidVal) {
            $reqData = self::getRequestData();
            foreach ((array)$keys as $k) {
                if ($reqData) {
                    $v = data_get($reqData, $k)
                        ?? data_get($reqData, "metadata.{$k}")
                        ?? data_get($reqData, "meta.{$k}")
                        ?? data_get($reqData, "payload.meta.{$k}")
                        ?? data_get($reqData, "payload.customer.{$k}")
                        ?? data_get($reqData, "payload.pickup.{$k}")
                        ?? data_get($reqData, "payload.dropoff.{$k}");
                    if ($isValidVal($v)) {
                        return $v;
                    }
                }
                if (request()) {
                    $v = request()->input($k) 
                        ?? request()->input("metadata.{$k}") 
                        ?? request()->input("meta.{$k}")
                        ?? request()->input("payload.meta.{$k}")
                        ?? request()->input("payload.customer.{$k}")
                        ?? request()->input("payload.pickup.{$k}")
                        ?? request()->input("payload.dropoff.{$k}");
                    if ($isValidVal($v)) {
                        return $v;
                    }
                }
                $v = data_get($meta, $k) 
                    ?? data_get($meta, "metadata.{$k}") 
                    ?? data_get($meta, "meta.{$k}")
                    ?? data_get($meta, "payload.meta.{$k}")
                    ?? data_get($payloadMeta, $k)
                    ?? data_get($payloadMeta, "metadata.{$k}")
                    ?? data_get($payloadMeta, "meta.{$k}")
                    ?? data_get($order->payload, "pickup.{$k}")
                    ?? data_get($order->payload, "dropoff.{$k}")
                    ?? data_get($order->payload, "customer.{$k}")
                    ?? data_get($order->customer, $k);
                if ($isValidVal($v)) {
                    return $v;
                }
            }
            return null;
        };

        $vehicleType = $getVal(['vehicle_type', 'vehicle', 'carChoice', 'car_choice', 'vehicle_name', 'car_type', 'fleet']);
        $passengers = $getVal(['passengers', 'passenger_count', 'pax', 'passengers_count', 'num_passengers']);
        $childSeats = $getVal(['child_seats', 'child_seats_count', 'car_seats', 'seats', 'childSeats']);
        $passengerName = $getVal(['passenger_name', 'customer.name', 'customer_name', 'name']);
        $passengerPhone = $getVal(['passenger_phone', 'customer.phone', 'customer_phone', 'phone']);
        $passengerEmail = $getVal(['email', 'passenger_email', 'customer_email', 'billing_email', 'user_email', 'contact_email', 'customer.email']);

        // Fallbacks from original notes or description text patterns
        $origNotes = $order->getOriginal('notes') ?: $order->notes;
        $origDescription = data_get($meta, 'description') ?: '';

        if (empty($vehicleType) || $vehicleType === 'N/A') {
            if ($origNotes && preg_match('/(?:Car|Vehicle):\s*([^|\n\r]+)/i', $origNotes, $matches)) {
                $vehicleType = trim($matches[1]);
            } elseif ($origDescription && preg_match('/(?:Car|Vehicle):\s*([^|\n\r]+)/i', $origDescription, $matches)) {
                $vehicleType = trim($matches[1]);
            }
        }
        if (empty($vehicleType) || $vehicleType === 'N/A') {
            $vehicleType = 'Sedan';
        }

        if ($passengers === null || $passengers === '' || $passengers === 'N/A') {
            if ($origNotes && preg_match('/(?:Pax|Passengers?|Passenger Count):\s*(\d+)/i', $origNotes, $matches)) {
                $passengers = (int)$matches[1];
            } elseif ($origDescription && preg_match('/(?:Pax|Passengers?|Passenger Count):\s*(\d+)/i', $origDescription, $matches)) {
                $passengers = (int)$matches[1];
            }
        }
        if ($passengers === null || $passengers === '' || $passengers === 'N/A') {
            $passengers = 1;
        }

        if ($childSeats === null || $childSeats === '' || $childSeats === 'N/A') {
            if ($origNotes && preg_match('/(?:Child\s*Seats?|Car\s*Seats?|Seats?):\s*(\d+)/i', $origNotes, $matches)) {
                $childSeats = (int)$matches[1];
            } elseif ($origDescription && preg_match('/(?:Child\s*Seats?|Car\s*Seats?|Seats?):\s*(\d+)/i', $origDescription, $matches)) {
                $childSeats = (int)$matches[1];
            }
        }
        if ($childSeats === null || $childSeats === '' || $childSeats === 'N/A') {
            $childSeats = 0;
        }

        // Fallbacks if not provided in request or metadata
        if (empty($passengerName)) {
            $order->loadMissing('payload.pickup');
            $pickup = $order->payload?->pickup;
            if ($pickup && $pickup->name) {
                $extractedName = trim(explode(' (', $pickup->name)[0]);
                if (strcasecmp($extractedName, 'pickup') !== 0 && strcasecmp($extractedName, 'pickup place') !== 0 && strcasecmp($extractedName, 'pickup location') !== 0) {
                    $passengerName = $extractedName;
                }
            }
        }

        if (empty($passengerPhone)) {
            $order->loadMissing('payload.pickup');
            $pickup = $order->payload?->pickup;
            if ($pickup && $pickup->phone) {
                $passengerPhone = $pickup->phone;
            }
        }

        if (!$order->relationLoaded('customer') && $order->customer_uuid) {
            $order->load('customer');
        }
        $customer = $order->customer;
        if ($customer) {
            $passengerName = $passengerName ?: $customer->name;
            $passengerPhone = $passengerPhone ?: $customer->phone;
            $passengerEmail = $passengerEmail ?: $customer->email;
        }

        // Normalize / Fallbacks
        $meta['vehicle_type'] = $vehicleType ?: (data_get($meta, 'vehicle_type') ?: 'N/A');
        $meta['passengers'] = $passengers !== null ? $passengers : (data_get($meta, 'passengers') ?: 'N/A');
        $meta['child_seats'] = $childSeats !== null ? $childSeats : (data_get($meta, 'child_seats') ?: 'N/A');
        $meta['passenger_name'] = $passengerName ?: (data_get($meta, 'passenger_name') ?: 'N/A');
        $meta['passenger_phone'] = $passengerPhone ?: (data_get($meta, 'passenger_phone') ?: 'N/A');
        $meta['passenger_email'] = $passengerEmail ?: (data_get($meta, 'passenger_email') ?: 'N/A');

        // Make sure source is present
        if (empty($meta['source'])) {
            $meta['source'] = $getVal(['source', 'source_form', 'booking_source']) ?: 'Website Form';
        }

        $order->meta = $meta;

        // 2. Customer & Contact Binding
        $customer = $order->customer;
        $email = $meta['passenger_email'] !== 'N/A' ? $meta['passenger_email'] : null;
        $phone = $meta['passenger_phone'] !== 'N/A' ? $meta['passenger_phone'] : null;
        $name = $meta['passenger_name'] !== 'N/A' ? $meta['passenger_name'] : null;

        if (!$customer && $email) {
            $customer = Contact::where('company_uuid', $order->company_uuid)
                ->where('email', $email)
                ->first();
        }
        if (!$customer && $phone) {
            $customer = Contact::where('company_uuid', $order->company_uuid)
                ->where('phone', $phone)
                ->first();
        }
        if (!$customer && $name) {
            $customer = Contact::create([
                'company_uuid' => $order->company_uuid,
                'name'         => $name,
                'phone'        => $phone,
                'email'        => $email,
                'type'         => 'customer',
            ]);
        }

        if ($customer) {
            $order->customer_uuid = $customer->uuid;
            $order->customer_type = get_class($customer);

            $contactUpdated = false;
            if ($name && $customer->name !== $name) {
                $customer->name = $name;
                $contactUpdated = true;
            }
            if ($phone && $customer->phone !== $phone) {
                $customer->phone = $phone;
                $contactUpdated = true;
            }
            if ($email && $customer->email !== $email) {
                $customer->email = $email;
                $contactUpdated = true;
            }
            if ($contactUpdated) {
                $customer->save();
            }
        }

        // 3. Pickup Place Binding
        $order->loadMissing('payload.pickup');
        $pickupPlace = $order->payload?->pickup;
        if ($pickupPlace) {
            if ($phone) {
                $pickupPlace->phone = $phone;
            }
            
            $placeMeta = $pickupPlace->meta ?? [];
            if (is_string($placeMeta)) {
                $placeMeta = json_decode($placeMeta, true) ?? [];
            }
            $placeMeta['passenger_name'] = $name ?: 'N/A';
            $placeMeta['passenger_phone'] = $phone ?: 'N/A';
            $placeMeta['passenger_email'] = $email ?: 'N/A';
            $pickupPlace->meta = $placeMeta;

            $pickupPlace->saveQuietly();
        }

        // 4. Generate & Save notes / description
        $formattedNotes = static::generateMarkdownNote($order);
        $order->notes = $formattedNotes;

        // Persist description in JSON metadata since there is no description database column
        $meta = $order->meta ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?? [];
        }
        $meta['description'] = $formattedNotes;
        $order->meta = $meta;

        // Set description as an in-memory attribute
        $order->setAttribute('description', $formattedNotes);

        // Temporarily remove description from Eloquent attributes to avoid SQL column not found error
        $rawAttributes = $order->getAttributes();
        if (array_key_exists('description', $rawAttributes)) {
            unset($rawAttributes['description']);
            $order->setRawAttributes($rawAttributes);
        }

        // Persist quietly to prevent firing updating/updated events recursively
        $order->saveQuietly();

        // Restore description attribute on the in-memory model
        $order->setAttribute('description', $formattedNotes);

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
        $reqData = self::getRequestData();
        if ($reqData) {
            $reqMeta = data_get($reqData, 'metadata') ?? data_get($reqData, 'meta') ?? [];
        }
        if (empty($reqMeta) && request()) {
            $reqMeta = request()->input('metadata') ?? request()->input('meta') ?? [];
        }
        if (!is_array($reqMeta)) {
            $reqMeta = [];
        }

        // 1. Explicit flag
        if (!empty($meta['is_web_booking']) || !empty($reqMeta['is_web_booking'])) {
            return true;
        }

        // 2. Booking source attributes
        $source = data_get($meta, 'source')
            ?? data_get($reqMeta, 'source')
            ?? data_get($meta, 'source_form')
            ?? data_get($reqMeta, 'source_form')
            ?? data_get($meta, 'booking_source')
            ?? data_get($reqMeta, 'booking_source');

        if (!empty($source)) {
            return true;
        }

        // 3. Transport check
        if ($order->type === 'transport' || (request() && request()->input('type') === 'transport') || ($reqData && data_get($reqData, 'type') === 'transport')) {
            return true;
        }

        // 4. Metadata indicator check
        $indicators = [
            'vehicle_type', 'vehicle', 'carChoice', 'car_choice', 'vehicle_name', 'car_type', 'fleet',
            'passengers', 'passenger_count', 'pax', 'passengers_count', 'num_passengers',
            'child_seats', 'child_seats_count', 'car_seats', 'seats', 'childSeats',
            'passenger_name', 'customer_name', 'passenger_phone', 'customer_phone', 'passenger_email', 'customer_email'
        ];

        foreach ($indicators as $ind) {
            if (data_get($meta, $ind) !== null || data_get($reqMeta, $ind) !== null) {
                return true;
            }
            if ($reqData && (data_get($reqData, $ind) !== null || data_get($reqData, "meta.{$ind}") !== null || data_get($reqData, "payload.meta.{$ind}") !== null)) {
                return true;
            }
            if (request() && (request()->input($ind) !== null || request()->input("meta.{$ind}") !== null || request()->input("payload.meta.{$ind}") !== null)) {
                return true;
            }
        }

        // 5. Keyword in notes fallback
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
    public static function generateMarkdownNote(Order $order): string
    {
        $meta = $order->meta ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?? [];
        }

        $passengerName = data_get($meta, 'passenger_name') ?? 'N/A';
        $passengerPhone = static::formatPhone(data_get($meta, 'passenger_phone'));
        $passengerEmail = data_get($meta, 'passenger_email') ?? 'N/A';
        $vehicleType = data_get($meta, 'vehicle_type') ?? 'N/A';
        $passengers = data_get($meta, 'passengers') ?? 'N/A';
        $childSeats = data_get($meta, 'child_seats') ?? 'N/A';

        $locationInfo = static::extractLocationInfo($order);
        $cleanPickupAddress = static::sanitizeAddress($locationInfo['pickup']);
        $cleanDropoffAddress = static::sanitizeAddress($locationInfo['dropoff']);

        $lines = [
            "PASSENGER: {$passengerName}" . ($passengerPhone && $passengerPhone !== 'N/A' ? " ({$passengerPhone})" : " (N/A)"),
            "EMAIL: {$passengerEmail}",
            "VEHICLE: {$vehicleType}",
            "PASSENGERS: {$passengers}",
            "CHILD SEATS: {$childSeats}",
            "PICKUP: {$cleanPickupAddress}",
            "DROPOFF: {$cleanDropoffAddress}",
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
        $reqData = self::getRequestData();
        if ($reqData) {
            $reqCustomer = data_get($reqData, 'customer') ?? [];
        }
        if (empty($reqCustomer) && request()) {
            $reqCustomer = request()->input('customer') ?? [];
        }
        if (!is_array($reqCustomer)) {
            $reqCustomer = [];
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
            ?? (self::getRequestData() ? data_get(self::getRequestData(), 'scheduled_at') : null)
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
        $reqData = self::getRequestData();
        if ($reqData) {
            $candidates[] = data_get($reqData, 'payload.pickup.timezone');
            $candidates[] = data_get($reqData, 'timezone');
        }
        if (request()) {
            $candidates[] = request()->input('payload.pickup.timezone');
            $candidates[] = request()->input('timezone');
        }

        // 5. Place province / state heuristic (US states)
        $reqProvince = $reqData ? data_get($reqData, 'payload.pickup.province') : null;
        if (empty($reqProvince) && request()) {
            $reqProvince = data_get(request()->input('payload'), 'pickup.province');
        }
        $province = strtoupper(trim((string) ($pickup?->province ?? $reqProvince ?? '')));
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
            $dropoffDisplay .= ' ⚠️ [WARNING: Same as pickup location - Identical coordinates/addresses]';
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

        if (empty($address)) {
            $reqData = self::getRequestData();
            if ($reqData) {
                $reqPayload = data_get($reqData, 'payload');
                $address = data_get($reqPayload, "{$type}.address")
                    ?? data_get($reqPayload, "{$type}.street1")
                    ?? data_get($reqPayload, "{$type}.name");
            }
            if (empty($address) && request()) {
                $reqPayload = request()->input('payload');
                $address = data_get($reqPayload, "{$type}.address")
                    ?? data_get($reqPayload, "{$type}.street1")
                    ?? data_get($reqPayload, "{$type}.name");
            }
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
        } else {
            $reqData = self::getRequestData();
            $reqCoords = null;
            if ($reqData) {
                $reqCoords = data_get($reqData, "payload.{$type}.location.coordinates");
            }
            if (empty($reqCoords) && request()) {
                $reqCoords = data_get(request()->input('payload'), "{$type}.location.coordinates");
            }
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
            ?? (self::getRequestData() ? data_get(self::getRequestData(), 'driver_name') : null)
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
            ?? (self::getRequestData() ? data_get(self::getRequestData(), 'vehicle_name') : null)
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
        $reqData = self::getRequestData();
        if ($reqData) {
            $reqMeta = data_get($reqData, 'metadata') ?? data_get($reqData, 'meta') ?? [];
        }
        if (empty($reqMeta) && request()) {
            $reqMeta = request()->input('metadata') ?? request()->input('meta') ?? [];
        }
        if (!is_array($reqMeta)) {
            $reqMeta = [];
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
