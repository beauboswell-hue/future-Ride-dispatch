<?php

namespace App\Http\Controllers;

use App\Services\WebBookingNoteFormatter;
use Fleetbase\FleetOps\Http\Controllers\Api\v1\OrderController as BaseOrderController;
use Illuminate\Http\Request;

class OverriddenOrderController extends BaseOrderController
{
    /**
     * Sanitizes incoming payload places to avoid passenger names or corrupted tags in place names.
     *
     * @param Request $request
     * @return void
     */
    protected function sanitizeIncomingPayloadPlaces(Request $request)
    {
        $payload = $request->input('payload');
        if (!is_array($payload)) {
            return;
        }

        $passengerName = $request->input('payload.customer.name') ?? $request->input('payload.meta.passenger_name');

        $sanitizePlace = function (&$place, $type) use ($passengerName) {
            if (!is_array($place)) {
                return;
            }
            $name = isset($place['name']) ? trim($place['name']) : '';
            $street1 = isset($place['street1']) ? trim($place['street1']) : '';

            $isCorrupted = false;
            if ($name !== '') {
                if (stripos($name, '(PICKUP)') !== false || stripos($name, '(DROPOFF)') !== false) {
                    $isCorrupted = true;
                }
                if ($passengerName && stripos($name, $passengerName) !== false) {
                    $isCorrupted = true;
                }
            }

            if ($isCorrupted || $name === '') {
                if ($street1 !== '') {
                    $place['name'] = $street1;
                } else {
                    $place['name'] = $type === 'pickup' ? 'Pickup Location' : 'Dropoff Location';
                }
            }
        };

        if (isset($payload['pickup'])) {
            $pickup = $payload['pickup'];
            $sanitizePlace($pickup, 'pickup');
            $payload['pickup'] = $pickup;
        }

        if (isset($payload['dropoff'])) {
            $dropoff = $payload['dropoff'];
            $sanitizePlace($dropoff, 'dropoff');
            $payload['dropoff'] = $dropoff;
        }

        if (isset($payload['return'])) {
            $return = $payload['return'];
            $sanitizePlace($return, 'dropoff');
            $payload['return'] = $return;
        }

        $request->merge(['payload' => $payload]);
    }

    /**
     * Override order creation input mapping to support fallback booking note/instructions ingestion.
     *
     * @param Request $request
     * @return array
     */
    protected function orderCreateInputFromRequest(Request $request): array
    {
        $this->sanitizeIncomingPayloadPlaces($request);
        WebBookingNoteFormatter::setRequestData($request->all());

        $input = parent::orderCreateInputFromRequest($request);

        if (empty($input['notes'])) {
            $input['notes'] = $request->input('meta.notes') 
                ?? $request->input('meta.special_instructions') 
                ?? $request->input('special_instructions');
        }

        $input = $this->mergePayloadMetadata($request, $input);

        return $input;
    }

    /**
     * Override order update input mapping to support fallback booking note/instructions ingestion.
     *
     * @param Request $request
     * @return array
     */
    protected function orderUpdateInputFromRequest(Request $request): array
    {
        $this->sanitizeIncomingPayloadPlaces($request);
        WebBookingNoteFormatter::setRequestData($request->all());

        $id = $request->route('order') ?? $request->input('id') ?? $request->route('id');
        $order = null;
        if ($id) {
            $order = \Fleetbase\FleetOps\Models\Order::find($id);
        }

        $input = parent::orderUpdateInputFromRequest($request);

        if (empty($input['notes'])) {
            $input['notes'] = $request->input('meta.notes') 
                ?? $request->input('meta.special_instructions') 
                ?? $request->input('special_instructions');
        }

        // If notes is empty/null, but notes were not explicitly sent in the request, preserve existing notes!
        if (empty($input['notes']) && $order) {
            if (!$request->has('notes') && !$request->has('meta.notes') && !$request->has('meta.special_instructions') && !$request->has('special_instructions')) {
                $input['notes'] = $order->notes;
            }
        }

        $input = $this->mergePayloadMetadata($request, $input, $order);

        return $input;
    }

    /**
     * Extract metadata fields from incoming payload.meta and merge them into input's meta.
     *
     * @param Request $request
     * @param array $input
     * @param mixed $order
     * @return array
     */
    protected function mergePayloadMetadata(Request $request, array $input, $order = null): array
    {
        $existingMeta = [];
        if ($order) {
            $existingMeta = $order->meta ?? [];
            if (is_string($existingMeta)) {
                $existingMeta = json_decode($existingMeta, true) ?? [];
            }
        }

        $meta = $input['meta'] ?? $request->input('meta') ?? [];
        if (is_string($meta)) {
            $meta = json_decode($meta, true) ?? [];
        }

        $meta = array_merge($existingMeta, $meta);

        $payloadMeta = $request->input('payload.meta') ?? [];
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

        $getVal = function ($keys) use ($request, $payloadMeta, $isValidVal) {
            foreach ($keys as $k) {
                $v = $request->input($k)
                    ?? $request->input("meta.{$k}")
                    ?? $request->input("payload.meta.{$k}")
                    ?? $request->input("payload.{$k}")
                    ?? $request->input("payload.customer.{$k}")
                    ?? $request->input("payload.pickup.{$k}")
                    ?? $request->input("payload.dropoff.{$k}")
                    ?? data_get($payloadMeta, $k);
                if ($isValidVal($v)) {
                    return $v;
                }
            }
            return null;
        };

        $vehicleType = $getVal(['vehicle_type', 'vehicle', 'carChoice', 'car_choice', 'vehicle_name', 'car_type', 'fleet', 'vehicleChoice']);
        $passengers = $getVal(['passengers', 'passenger_count', 'pax', 'passengers_count', 'num_passengers', 'pax_count', 'paxCount']);
        $childSeats = $getVal(['child_seats', 'child_seats_count', 'car_seats', 'seats', 'childSeats', 'childSeatsCount', 'booster_seats', 'booster_seats_count']);

        if ($vehicleType) {
            $meta['vehicle_type'] = $vehicleType;
        }
        if ($passengers !== null && $passengers !== '') {
            $meta['passengers'] = $passengers;
        }
        if ($childSeats !== null && $childSeats !== '') {
            $meta['child_seats'] = $childSeats;
        }

        // Also merge passenger details if present in payload
        $passengerName = $request->input('payload.customer.name') ?? $request->input('payload.meta.passenger_name') ?? $getVal(['passenger_name', 'customer_name', 'name']);
        $passengerPhone = $request->input('payload.customer.phone') ?? $request->input('payload.meta.passenger_phone') ?? $getVal(['passenger_phone', 'customer_phone', 'phone']);
        $passengerEmail = $request->input('payload.customer.email') ?? $request->input('payload.meta.passenger_email') ?? $getVal(['email', 'passenger_email', 'customer_email', 'billing_email', 'user_email', 'contact_email', 'customer.email']);

        if ($passengerName) {
            $meta['passenger_name'] = $passengerName;
        }
        if ($passengerPhone) {
            $meta['passenger_phone'] = $passengerPhone;
        }
        if ($passengerEmail) {
            $meta['passenger_email'] = $passengerEmail;
        }

        $input['meta'] = $meta;

        return $input;
    }
}
