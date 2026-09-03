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
        $orderId = $request->input('order_id') ?? $request->input('id') ?? $request->input('order');

        if ($orderId) {
            $order = Order::where('public_id', $orderId)
                ->orWhere('uuid', $orderId)
                ->first();

            if (!$order) {
                return response()->json(['error' => 'Order not found.'], 404);
            }

            WebBookingNoteFormatter::formatAndSave($order, true);

            return response()->json([
                'status'  => 'success',
                'message' => 'Web booking notes generated successfully.',
                'order'   => [
                    'id'    => $order->public_id,
                    'notes' => $order->notes,
                ],
            ]);
        }

        return response()->json([
            'status'  => 'received',
            'message' => 'Web booking payload processed.',
        ]);
    }
}
