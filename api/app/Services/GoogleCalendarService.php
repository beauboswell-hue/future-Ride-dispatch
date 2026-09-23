<?php

namespace App\Services;

use Fleetbase\FleetOps\Models\Order;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event as GoogleCalendarEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Twilio\Rest\Client as TwilioClient;

class GoogleCalendarService
{
    /**
     * Google API Client instance.
     *
     * @var GoogleClient|null
     */
    protected ?GoogleClient $client = null;

    /**
     * Track synchronized order actions within current request to prevent duplicate syncs.
     *
     * @var array<string, bool>
     */
    protected array $syncedInRequest = [];

    /**
     * Target timezone for all Google Calendar sync events.
     */
    public const TIMEZONE = 'America/New_York';

    /**
     * Construct the full tokenized event payload for Google Calendar sync.
     * Zero Personally Identifiable Information (PII) is included in this payload.
     *
     * @param Order $order
     * @return array
     */
    public function buildEventPayload(Order $order): array
    {
        $timing = $this->buildTiming($order);

        return [
            'summary'     => $this->buildSummary($order),
            'location'    => $this->buildLocation($order),
            'description' => $this->buildDescription($order),
            'start'       => $timing['start'],
            'end'         => $timing['end'],
        ];
    }

    /**
     * Convert passenger name to initials.
     *
     * @param string|null $name
     * @return string
     */
    public function getInitials(?string $name): string
    {
        if (empty($name) || strtolower($name) === 'n/a' || strtolower($name) === 'none') {
            return 'N.A.';
        }
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $parts = explode(' ', $name);
        $initials = '';
        foreach ($parts as $part) {
            $char = substr($part, 0, 1);
            if ($char !== '') {
                $initials .= strtoupper($char) . '.';
            }
        }
        return $initials ?: 'N.A.';
    }

    /**
     * Parse and untokenize booking data from order.
     *
     * @param Order $order
     * @return array
     */
    public function getBookingData(Order $order): array
    {
        $meta = $order->meta ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?? [];
        }

        $name = data_get($meta, 'passenger_name') ?? data_get($meta, 'name');
        $phone = data_get($meta, 'passenger_phone') ?? data_get($meta, 'phone');
        $email = data_get($meta, 'passenger_email') ?? data_get($meta, 'email');

        if ($order->relationLoaded('customer') && $order->customer) {
            $name = $name ?: $order->customer->name;
            $phone = $phone ?: $order->customer->phone;
            $email = $email ?: $order->customer->email;
        } elseif ($order->customer_uuid) {
            $customer = \Fleetbase\FleetOps\Models\Contact::find($order->customer_uuid);
            if ($customer) {
                $name = $name ?: $customer->name;
                $phone = $phone ?: $customer->phone;
                $email = $email ?: $customer->email;
            }
        }

        $name = $name ?: 'N/A';
        $phone = $phone ?: 'N/A';
        $email = $email ?: 'N/A';

        $vehicleType = data_get($meta, 'vehicle_type') ?? 'N/A';
        $childSeats = data_get($meta, 'child_seats') ?? 'N/A';

        $order->loadMissing(['payload.pickup', 'payload.dropoff']);
        $pickupPlace = $order->payload?->pickup;
        $dropoffPlace = $order->payload?->dropoff;

        $pickupAddress = $pickupPlace ? ($pickupPlace->address ?? $pickupPlace->name ?? $pickupPlace->street1 ?? 'N/A') : 'N/A';
        $dropoffAddress = $dropoffPlace ? ($dropoffPlace->address ?? $dropoffPlace->name ?? $dropoffPlace->street1 ?? 'N/A') : 'N/A';

        if (class_exists(\App\Services\WebBookingNoteFormatter::class)) {
            $pickupAddress = \App\Services\WebBookingNoteFormatter::sanitizeAddress($pickupAddress);
            $dropoffAddress = \App\Services\WebBookingNoteFormatter::sanitizeAddress($dropoffAddress);
        } else {
            $pickupAddress = preg_replace('/,\s*united states\s*$/i', '', $pickupAddress);
            $dropoffAddress = preg_replace('/,\s*united states\s*$/i', '', $dropoffAddress);
        }

        $startRaw = $order->scheduled_at
            ?? $order->time_window_start
            ?? $order->started_at
            ?? $order->created_at
            ?? now();

        $pickupTimeFormatted = 'N/A';
        try {
            $start = Carbon::parse($startRaw)->shiftTimezone('America/New_York');
            $pickupTimeFormatted = $start->format('Y-m-d H:i:s T');
        } catch (\Throwable $e) {
            // fallback
        }

        return [
            'name'            => $name,
            'initials'        => $this->getInitials($name),
            'phone'           => $phone,
            'email'           => $email,
            'vehicle_type'    => $vehicleType,
            'child_seats'     => $childSeats,
            'pickup_address'  => $pickupAddress,
            'pickup_time'     => $pickupTimeFormatted,
            'dropoff_address' => $dropoffAddress,
        ];
    }

    /**
     * Build event title (Summary).
     * Format: "Ride: [Initials] - [Time]"
     *
     * @param Order $order
     * @return string
     */
    public function buildSummary(Order $order): string
    {
        $data = $this->getBookingData($order);
        
        $startRaw = $order->scheduled_at
            ?? $order->time_window_start
            ?? $order->started_at
            ?? $order->created_at
            ?? now();
            
        try {
            $start = Carbon::parse($startRaw)->shiftTimezone('America/New_York');
            $timeFormatted = $start->format('g:i A');
        } catch (\Throwable $e) {
            $timeFormatted = 'N/A';
        }

        return "Ride: {$data['initials']} - {$timeFormatted}";
    }

    /**
     * Build event location.
     * Set to empty string "" (strictly zero street addresses, unit numbers, coordinates, or zip codes).
     *
     * @param Order $order
     * @return string
     */
    public function buildLocation(Order $order): string
    {
        return '';
    }

    /**
     * Build full event description with full readable details.
     *
     * @param Order $order
     * @return string
     */
    public function buildDescription(Order $order): string
    {
        $data = $this->getBookingData($order);
        
        $consoleHost = env('CONSOLE_HOST') ?: env('CONSOLE_URL') ?: 'https://console.futurelimo.website';
        $consoleHost = rtrim($consoleHost, '/');
        $directLink = "{$consoleHost}/fleet-ops/{$order->public_id}";

        return implode("\n", [
            "Customer Initials: {$data['initials']}",
            "Phone: {$data['phone']}",
            "Email: {$data['email']}",
            "Vehicle: {$data['vehicle_type']}",
            "Child Seats: {$data['child_seats']}",
            "Pickup: {$data['pickup_address']} @ {$data['pickup_time']}",
            "Dropoff: {$data['dropoff_address']}",
            "",
            "Direct Order Link: {$directLink}",
        ]);
    }

    /**
     * Send Twilio SMS alert notification.
     *
     * @param Order $order
     * @return void
     */
    public function sendSMSNotification(Order $order): void
    {
        try {
            $data = $this->getBookingData($order);

            $smsBody = implode("\n", [
                "New Booking Alert",
                "Pax: {$data['initials']}",
                "Phone: {$data['phone']}",
                "Email: {$data['email']}",
                "Vehicle: {$data['vehicle_type']}",
                "Child Seats: {$data['child_seats']}",
                "From: {$data['pickup_address']} @ {$data['pickup_time']}",
                "To: {$data['dropoff_address']}",
            ]);

            $sid = env('TWILIO_SID') ?: config('twilio.twilio.connections.twilio.sid');
            $token = env('TWILIO_TOKEN') ?: config('twilio.twilio.connections.twilio.token');
            $from = env('TWILIO_FROM') ?: config('twilio.twilio.connections.twilio.from');

            if (empty($sid) || empty($token)) {
                Log::warning('GoogleCalendarService: Twilio credentials are not fully configured in environment.');
                return;
            }

            $client = new TwilioClient($sid, $token);
            $client->messages->create(
                '+12127965858',
                [
                    'from' => $from,
                    'body' => $smsBody,
                ]
            );

            Log::info('GoogleCalendarService: Twilio SMS alert sent successfully to Tom.');
        } catch (\Throwable $e) {
            Log::error('GoogleCalendarService: Failed to send Twilio SMS notification: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    /**
     * Send Email notification to Reservations.
     *
     * @param Order $order
     * @return void
     */
    public function sendEmailNotification(Order $order): void
    {
        try {
            $data = $this->getBookingData($order);
            $subject = "New Booking Received: {$data['initials']} - {$data['pickup_time']}";

            $mailHost = env('MAIL_HOST');
            $mailPort = env('MAIL_PORT');
            $mailUsername = env('MAIL_USERNAME');
            $mailPassword = env('MAIL_PASSWORD');

            if (empty($mailHost) || empty($mailPort) || empty($mailUsername) || empty($mailPassword) ||
                $mailHost === 'null' || $mailPort === 'null' || $mailUsername === 'null' || $mailPassword === 'null') {
                Log::warning('GoogleCalendarService: SMTP variables in .env are missing or empty. Skipping email notification.');
                return;
            }

            $emailBody = implode("\n", [
                "A new booking has been received. Below are the details:",
                "",
                "Passenger Name: {$data['name']}",
                "Passenger Initials: {$data['initials']}",
                "Phone Number: {$data['phone']}",
                "Email Address: {$data['email']}",
                "Vehicle Type: {$data['vehicle_type']}",
                "Child Seats: {$data['child_seats']}",
                "From (Pickup Address): {$data['pickup_address']}",
                "Pickup Time: {$data['pickup_time']}",
                "To (Dropoff Address): {$data['dropoff_address']}",
            ]);

            Mail::raw($emailBody, function ($message) use ($subject) {
                $message->to('reservations@future.limo')
                        ->subject($subject);

                $fromAddress = env('MAIL_FROM_ADDRESS') ?: config('mail.from.address');
                if ($fromAddress) {
                    $message->from($fromAddress, env('MAIL_FROM_NAME') ?: config('mail.from.name'));
                }
            });

            Log::info('GoogleCalendarService: Reservation email notification sent successfully.');
        } catch (\Throwable $e) {
            Log::error('GoogleCalendarService: Failed to send Email notification to Reservations: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    /**
     * Build event timing preserving only the scheduled pickup start/end timestamps
     * formatted with America/New_York timezone. Both start and end are set to the pickup time.
     *
     * @param Order $order
     * @return array
     */
    public function buildTiming(Order $order): array
    {
        $tz = self::TIMEZONE;

        // Pickup / start time resolution
        $startRaw = $order->scheduled_at
            ?? $order->time_window_start
            ?? $order->started_at
            ?? $order->created_at
            ?? now();

        try {
            $start = Carbon::parse($startRaw)->shiftTimezone($tz);
        } catch (\Throwable $e) {
            $start = Carbon::now($tz);
        }

        return [
            'start' => [
                'dateTime' => $start->toRfc3339String(),
                'timeZone' => $tz,
            ],
            'end' => [
                'dateTime' => $start->toRfc3339String(),
                'timeZone' => $tz,
            ],
        ];
    }

    /**
     * Synchronize a newly created order to Google Calendar.
     *
     * @param Order $order
     * @return array The tokenized payload sent (or prepared)
     */
    public function createEvent(Order $order): array
    {
        $orderKey = ($order->uuid ?? $order->public_id ?? $order->id ?? 'unknown') . '_create';
        if (isset($this->syncedInRequest[$orderKey])) {
            return $this->buildEventPayload($order);
        }
        $this->syncedInRequest[$orderKey] = true;

        $payload = $this->buildEventPayload($order);
        $calendarId = $this->getCalendarId($order);

        Log::info('GoogleCalendarService: Preparing tokenized create event for order', [
            'order_id'    => $order->public_id ?? $order->uuid,
            'calendar_id' => $calendarId,
            'payload'     => $payload,
        ]);

        // Send Twilio SMS and Email notifications as part of the three-way pipeline
        $this->sendSMSNotification($order);
        $this->sendEmailNotification($order);

        $calendarService = $this->getCalendarService();
        if ($calendarService) {
            try {
                $event = new GoogleCalendarEvent($payload);
                $createdEvent = $calendarService->events->insert($calendarId, $event);
                if ($createdEvent && !empty($createdEvent->getId())) {
                    $this->saveEventIdToOrder($order, $createdEvent->getId());
                    return array_merge($payload, ['id' => $createdEvent->getId()]);
                }
            } catch (\Throwable $e) {
                Log::error('GoogleCalendarService: API call failed on createEvent: ' . $e->getMessage(), [
                    'order_id'  => $order->public_id ?? $order->uuid,
                    'exception' => $e,
                ]);
            }
        }

        return $payload;
    }

    /**
     * Synchronize an updated or rescheduled order to Google Calendar.
     * Uses the exact same tokenized schema so edits do not re-introduce PII.
     *
     * @param Order $order
     * @return array The tokenized payload sent (or prepared)
     */
    public function updateEvent(Order $order): array
    {
        $orderKey = ($order->uuid ?? $order->public_id ?? $order->id ?? 'unknown') . '_update';
        if (isset($this->syncedInRequest[$orderKey])) {
            return $this->buildEventPayload($order);
        }
        $this->syncedInRequest[$orderKey] = true;

        // Strictly uses the exact same tokenized schema
        $payload = $this->buildEventPayload($order);
        $calendarId = $this->getCalendarId($order);

        Log::info('GoogleCalendarService: Preparing tokenized update event for order', [
            'order_id'    => $order->public_id ?? $order->uuid,
            'calendar_id' => $calendarId,
            'payload'     => $payload,
        ]);

        $calendarService = $this->getCalendarService();
        if ($calendarService) {
            $eventId = data_get($order->meta, 'google_calendar_event_id');
            if (!empty($eventId)) {
                try {
                    $event = new GoogleCalendarEvent($payload);
                    $updatedEvent = $calendarService->events->update($calendarId, $eventId, $event);
                    return array_merge($payload, ['id' => $updatedEvent->getId()]);
                } catch (\Throwable $e) {
                    Log::error('GoogleCalendarService: API call failed on updateEvent: ' . $e->getMessage(), [
                        'order_id'  => $order->public_id ?? $order->uuid,
                        'event_id'  => $eventId,
                        'exception' => $e,
                    ]);
                }
            } else {
                // If event was not previously created on Google Calendar, create it now
                return $this->createEvent($order);
            }
        }

        return $payload;
    }

    /**
     * Get Google Calendar ID for the sync.
     *
     * @param Order|null $order
     * @return string
     */
    public function getCalendarId(?Order $order = null): string
    {
        if ($order) {
            $orderCalId = data_get($order->meta, 'google_calendar_id');
            if (!empty($orderCalId)) {
                return trim((string) $orderCalId);
            }
        }

        return config(
            'fleetops.google_calendar_id',
            env('GOOGLE_CALENDAR_ID', 'd54fed8da80fdadb92965f6743dba4d0603596e9c3bf533788f81140fe829b64@group.calendar.google.com')
        );
    }

    /**
     * Get Google API Client instance if configured.
     *
     * @return GoogleClient|null
     */
    public function getClient(): ?GoogleClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $credentialsPath = config('fleetops.google_calendar_credentials_path')
            ?? config('services.google.credentials_path')
            ?? env('GOOGLE_APPLICATION_CREDENTIALS')
            ?? env('GOOGLE_CLOUD_KEY_FILE');

        $credentialsJson = config('fleetops.google_calendar_credentials_json')
            ?? config('services.google.service_account')
            ?? env('GOOGLE_SERVICE_ACCOUNT_JSON');

        try {
            if (!empty($credentialsJson)) {
                $authConfig = is_array($credentialsJson) ? $credentialsJson : json_decode($credentialsJson, true);
                if (!empty($authConfig)) {
                    $client = new GoogleClient();
                    $client->setApplicationName('Fleetbase');
                    $client->setScopes([GoogleCalendar::CALENDAR_EVENTS, GoogleCalendar::CALENDAR]);
                    $client->setAuthConfig($authConfig);
                    return $this->client = $client;
                }
            }

            if (!empty($credentialsPath) && file_exists($credentialsPath)) {
                $client = new GoogleClient();
                $client->setApplicationName('Fleetbase');
                $client->setScopes([GoogleCalendar::CALENDAR_EVENTS, GoogleCalendar::CALENDAR]);
                $client->setAuthConfig($credentialsPath);
                return $this->client = $client;
            }
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendarService: Unable to initialize Google Client: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Get Google Service Calendar instance if client is configured.
     *
     * @return GoogleCalendar|null
     */
    public function getCalendarService(): ?GoogleCalendar
    {
        $client = $this->getClient();
        if ($client) {
            return new GoogleCalendar($client);
        }

        return null;
    }

    /**
     * Persist synchronized event ID to order meta quietly.
     *
     * @param Order  $order
     * @param string $eventId
     * @return void
     */
    protected function saveEventIdToOrder(Order $order, string $eventId): void
    {
        try {
            $meta = $order->meta ?? [];
            if (is_string($meta)) {
                $meta = json_decode($meta, true) ?? [];
            }
            $meta['google_calendar_event_id'] = $eventId;
            $meta['google_calendar_synced_at'] = now()->toIso8601String();
            $order->meta = $meta;
            $order->saveQuietly();
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendarService: Failed to save event ID to order meta: ' . $e->getMessage());
        }
    }
}
