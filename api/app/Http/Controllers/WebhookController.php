<?php

namespace App\Http\Controllers;

use App\Services\WebBookingNoteFormatter;
use Fleetbase\FleetOps\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle an incoming Twilio SMS webhook.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function handleTwilioSms(Request $request)
    {
        // Capture all incoming input (form data) and raw body content
        $input = $request->all();
        $rawBody = $request->getContent();

        // Log the incoming payload
        Log::info('Incoming Twilio SMS webhook received', [
            'input' => $input,
            'raw_body' => $rawBody,
        ]);

        // Return empty TwiML response as expected by Twilio to acknowledge the request
        return response('<Response></Response>', 200)
            ->header('Content-Type', 'text/xml');
    }

    /**
     * Handle an incoming web booking webhook or public booking endpoint.
     *
     * Formats the web booking details into the order's notes attribute.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleWebBooking(Request $request)
    {
        // Cache current request data in formatter
        WebBookingNoteFormatter::setRequestData($request->all());

        $orderId = $request->input('order_id')
            ?? $request->input('id')
            ?? $request->input('order')
            ?? $request->input('public_id')
            ?? $request->input('order.id')
            ?? $request->input('order.public_id');

        if ($orderId) {
            $order = Order::where('public_id', $orderId)
                ->orWhere('uuid', $orderId)
                ->first();

            if (!$order) {
                return response()->json(['error' => 'Order not found.'], 404);
            }

            // Ingest incoming passenger, vehicle, child seats, notes
            $meta = $order->meta ?? [];
            if (is_string($meta)) {
                $meta = json_decode($meta, true) ?? [];
            }

            $reqMeta = $request->input('meta') ?? $request->input('payload.meta') ?? [];
            if (is_string($reqMeta)) {
                $reqMeta = json_decode($reqMeta, true) ?? [];
            }
            if (is_array($reqMeta)) {
                $meta = array_merge($meta, $reqMeta);
            }

            $v = $request->input('vehicle_selection')
                ?? $request->input('vehicle_type')
                ?? $request->input('vehicle')
                ?? $request->input('selected_vehicle')
                ?? $meta['vehicle_selection']
                ?? $meta['vehicle_type']
                ?? $meta['vehicle']
                ?? null;

            if ($v) {
                $meta['vehicle_selection'] = $v;
                $meta['vehicle_type'] = $v;
                $meta['vehicle'] = $v;
            }

            $p = $request->input('passenger_count')
                ?? $request->input('passengers')
                ?? $request->input('pax')
                ?? $meta['passenger_count']
                ?? $meta['passengers']
                ?? $meta['pax']
                ?? null;

            if ($p !== null) {
                $meta['passenger_count'] = is_numeric($p) ? (int)$p : $p;
                $meta['passengers'] = is_numeric($p) ? (int)$p : $p;
                $meta['pax'] = is_numeric($p) ? (int)$p : $p;
            }

            $s = $request->input('child_seats')
                ?? $request->input('seats')
                ?? $request->input('child_seat_count')
                ?? $meta['child_seats']
                ?? $meta['seats']
                ?? null;

            if ($s !== null) {
                $meta['child_seats'] = is_numeric($s) ? (int)$s : $s;
                $meta['seats'] = is_numeric($s) ? (int)$s : $s;
            }

            $n = $request->input('notes')
                ?? $request->input('comments')
                ?? $request->input('special_instructions')
                ?? $request->input('booking_notes')
                ?? $meta['notes']
                ?? $meta['comments']
                ?? $meta['special_instructions']
                ?? null;

            if ($n) {
                $meta['notes'] = $n;
                if (empty($order->notes)) {
                    $order->notes = $n;
                }
            }

            $order->meta = $meta;
            $order->saveQuietly();

            WebBookingNoteFormatter::formatAndSave($order, true);
            $order->refresh();

            return response()->json([
                'status'  => 'success',
                'message' => 'Web booking notes generated successfully.',
                'order'   => [
                    'id'    => $order->public_id,
                    'notes' => $order->notes,
                    'meta'  => $order->meta,
                ],
            ]);
        }

        return response()->json([
            'status'  => 'received',
            'message' => 'Web booking payload processed.',
        ]);
    }
}
