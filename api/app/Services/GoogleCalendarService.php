<?php

namespace App\Services;

use Fleetbase\FleetOps\Models\Order;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event as GoogleCalendarEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

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
     * Build tokenized event title (Summary).
     * Format: "#{internal_id} ({fleetbase_order_id})"
     *
     * @param Order $order
     * @return string
     */
    public function buildSummary(Order $order): string
    {
        $internalId = $order->id ?? 'N/A';
        $fleetbaseOrderId = $order->public_id ?? $order->uuid ?? 'N/A';

        $meta = $order->meta ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?? [];
        }

        $carChoice = data_get($meta, 'vehicle_type') ?? 'N/A';
        $pax = data_get($meta, 'passengers') ?? 'N/A';
        $childSeats = data_get($meta, 'child_seats') ?? 'N/A';

        return "#{$internalId} ({$fleetbaseOrderId}) - Vehicle: {$carChoice}, Pax: {$pax}, Child Seats: {$childSeats}";
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
     * Build anonymous event description.
     * Strictly tokenized with zero names, phone numbers, emails, addresses, or coordinates.
     *
     * @param Order $order
     * @return string
     */
    public function buildDescription(Order $order): string
    {
        $internalId = $order->id ?? 'N/A';
        $fleetbaseOrderId = $order->public_id ?? $order->uuid ?? 'N/A';

        // Pickup / start time resolution
        $startRaw = $order->scheduled_at
            ?? $order->time_window_start
            ?? $order->started_at
            ?? $order->created_at
            ?? now();

        try {
            $start = Carbon::parse($startRaw)->setTimezone(self::TIMEZONE);
            $pickupTime = $start->format('Y-m-d H:i:s T');
        } catch (\Throwable $e) {
            $pickupTime = 'N/A';
        }

        $meta = $order->meta ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?? [];
        }

        $carChoice = data_get($meta, 'vehicle_type') ?? 'N/A';
        $pax = data_get($meta, 'passengers') ?? 'N/A';
        $childSeats = data_get($meta, 'child_seats') ?? 'N/A';

        return implode("\n", [
            "Order: #{$internalId}",
            "Time: {$pickupTime}",
            "Fleetbase ID: {$fleetbaseOrderId}",
            "Vehicle: {$carChoice}",
            "Pax: {$pax}",
            "Child Seats: {$childSeats}",
            "",
            "https://console.futurelimo.website/fleet-ops/orders?id={$fleetbaseOrderId}",
        ]);
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
            $start = Carbon::parse($startRaw)->setTimezone($tz);
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
