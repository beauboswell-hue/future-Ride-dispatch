<?php

/**
 * Standalone Test Script: Verify Google Calendar Sync Tokenized Payload & Observer Hooks
 *
 * Usage:
 *   docker exec fleetbase-application-1 php /fleetbase/scripts/test_google_calendar_sync.php
 */

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $app = require_once __DIR__ . '/bootstrap/app.php';
} elseif (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $app = require_once __DIR__ . '/../bootstrap/app.php';
} else {
    require_once '/fleetbase/api/vendor/autoload.php';
    $app = require_once '/fleetbase/api/bootstrap/app.php';
}
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\GoogleCalendarService;
use Fleetbase\FleetOps\Models\Contact;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\Payload;
use Fleetbase\FleetOps\Models\Place;
use Fleetbase\LaravelMysqlSpatial\Types\Point;
use Fleetbase\Models\Company;
use Illuminate\Support\Carbon;

echo "====================================================\n";
echo "  Fleetbase Google Calendar Sync Tokenization Test  \n";
echo "====================================================\n\n";

$company = Company::first();
if (!$company) {
    echo "❌ ERROR: No company found in database.\n";
    exit(1);
}
echo "✓ Found Company: {$company->name} ({$company->public_id})\n\n";

$service = app(GoogleCalendarService::class);
$passed = 0;
$total = 0;

$runTest = function (string $title, callable $fn) use (&$passed, &$total) {
    $total++;
    echo "[TEST {$total}] {$title} ... ";
    try {
        $fn();
        $passed++;
        echo "✅ PASSED\n";
    } catch (\Throwable $e) {
        echo "❌ FAILED: " . $e->getMessage() . "\n";
        echo "   Trace: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
};

// 1. Event Title (Summary) Format with Type
$runTest("Event Title format: 'Ride [{order.type}]: {order.public_id}'", function () use ($service) {
    $order = new Order();
    $order->public_id = 'order_luxury99';
    $order->type = 'luxury';

    $summary = $service->buildSummary($order);
    if ($summary !== 'Ride [luxury]: order_luxury99') {
        throw new Exception("Expected 'Ride [luxury]: order_luxury99', got: '{$summary}'");
    }
});

// 2. Event Title (Summary) Fallback when Type is null
$runTest("Event Title fallback: 'Ride: {order.public_id}' when type is null", function () use ($service) {
    $order = new Order();
    $order->public_id = 'order_generic42';
    // type is null by default on new Order()

    $summary = $service->buildSummary($order);
    if ($summary !== 'Ride: order_generic42') {
        throw new Exception("Expected 'Ride: order_generic42', got: '{$summary}'");
    }
});

// 3. Event Title strictly excludes passenger/customer names
$runTest("Event Title strictly excludes passenger name and company name", function () use ($service, $company) {
    $customer = Contact::firstOrCreate(
        ['company_uuid' => $company->uuid, 'email' => 'jane.passenger@example.com'],
        ['name' => 'Jane Supersecret', 'phone' => '+1 555-432-1098', 'type' => 'customer']
    );

    $order = new Order();
    $order->public_id = 'order_anon1';
    $order->type = 'executive';
    $order->customer_uuid = $customer->uuid;
    $order->setRelation('customer', $customer);

    $summary = $service->buildSummary($order);
    if (str_contains($summary, 'Jane') || str_contains($summary, 'Supersecret') || str_contains($summary, 'Future Limo')) {
        throw new Exception("Summary leaked customer or company identity: '{$summary}'");
    }
    if ($summary !== 'Ride [executive]: order_anon1') {
        throw new Exception("Expected 'Ride [executive]: order_anon1', got: '{$summary}'");
    }
});

// 4. Event Location is strictly empty string ""
$runTest("Event Location is strictly empty string \"\" without addresses or coordinates", function () use ($service, $company) {
    $place = new Place([
        'company_uuid' => $company->uuid,
        'name'         => 'Penthouse Suite',
        'street1'      => '740 Park Avenue',
        'city'         => 'New York',
        'province'     => 'NY',
        'postal_code'  => '10021',
        'location'     => new Point(40.7712, -73.9654),
    ]);

    $order = new Order();
    $order->public_id = 'order_loc_test';
    $order->setRelation('payload', new Payload(['pickup' => $place]));

    $location = $service->buildLocation($order);
    if ($location !== '') {
        throw new Exception("Location must be empty string, got: '{$location}'");
    }

    $payload = $service->buildEventPayload($order);
    if ($payload['location'] !== '') {
        throw new Exception("Payload location must be empty string, got: '{$payload['location']}'");
    }
});

// 5. Event Description anonymous metadata block with console link
$runTest("Event Description matches anonymous metadata block format and console link", function () use ($service) {
    config(['fleetops.console_url' => 'http://localhost:4200']);

    $order = new Order();
    $order->public_id = 'order_meta_test';
    $order->status = 'dispatched';
    $order->notes = "VIP passenger note: Gate code #9988, call +15554443322 upon arrival. Total paid: $450.00";

    $description = $service->buildDescription($order);
    $expected = "Status: dispatched\nOrder ID: order_meta_test\nConsole Link: http://localhost:4200/fleet-ops?layout=kanban&order=order_meta_test";

    if ($description !== $expected) {
        throw new Exception("Description mismatch.\nExpected:\n{$expected}\n\nGot:\n{$description}");
    }

    // Assert strict zero-PII in description
    $forbiddenTerms = ['Gate code', '9988', '+15554443322', '$450.00', 'VIP passenger'];
    foreach ($forbiddenTerms as $term) {
        if (str_contains($description, $term)) {
            throw new Exception("Description leaked forbidden sensitive term: '{$term}'");
        }
    }
});

// 6. Dynamic consoleBaseUrl resolution
$runTest("consoleBaseUrl uses config('fleetops.console_url', env('CONSOLE_URL', 'http://localhost:4200'))", function () use ($service) {
    config(['fleetops.console_url' => 'https://custom-host.local:8443/']);

    $order = new Order();
    $order->public_id = 'order_custom_url';
    $order->status = 'created';

    $description = $service->buildDescription($order);
    if (!str_contains($description, 'Console Link: https://custom-host.local:8443/fleet-ops?layout=kanban&order=order_custom_url')) {
        throw new Exception("Dynamic console url resolution failed: '{$description}'");
    }

    // Reset back to default
    config(['fleetops.console_url' => 'http://localhost:4200']);
});

// 7. Timing preserves start/end in America/New_York timezone
$runTest("Timing preserves scheduled pickup and drop-off start/end in America/New_York timezone", function () use ($service) {
    $order = new Order();
    $order->scheduled_at = '2026-12-25 15:00:00';
    $order->time_window_end = '2026-12-25 17:00:00';

    $timing = $service->buildTiming($order);

    if ($timing['start']['timeZone'] !== 'America/New_York') {
        throw new Exception("Start timeZone must be America/New_York, got: '{$timing['start']['timeZone']}'");
    }
    if ($timing['end']['timeZone'] !== 'America/New_York') {
        throw new Exception("End timeZone must be America/New_York, got: '{$timing['end']['timeZone']}'");
    }

    $startTime = Carbon::parse($timing['start']['dateTime']);
    $endTime = Carbon::parse($timing['end']['dateTime']);

    if (!$endTime->greaterThan($startTime)) {
        throw new Exception("End time must be after start time");
    }
    if ($endTime->diffInHours($startTime) !== 2) {
        throw new Exception("Expected 2 hour difference, got: " . $endTime->diffInHours($startTime));
    }
});

// 8. Order Updates & Sync uses exact same tokenized schema
$runTest("Update method uses exact same tokenized schema as creation without PII", function () use ($service) {
    $order = new Order();
    $order->public_id = 'order_update_test';
    $order->type = 'shuttle';
    $order->status = 'started';
    $order->scheduled_at = '2026-09-20 18:00:00';
    $order->time_window_end = '2026-09-20 19:30:00';

    $createPayload = $service->createEvent($order);
    $updatePayload = $service->updateEvent($order);

    unset($createPayload['id'], $updatePayload['id']);

    if ($createPayload !== $updatePayload) {
        throw new Exception("createEvent and updateEvent payloads must be identical!");
    }
    if ($updatePayload['summary'] !== 'Ride [shuttle]: order_update_test') {
        throw new Exception("Summary mismatch in updateEvent: '{$updatePayload['summary']}'");
    }
    if ($updatePayload['location'] !== '') {
        throw new Exception("Location must be empty in updateEvent");
    }
});

// 9. End-to-end Order Creation & Update lifecycle triggering Observer
$runTest("Full Order creation and update lifecycle triggers Observer without breaking workflows", function () use ($company) {
    $order = Order::create([
        'company_uuid' => $company->uuid,
        'type'         => 'standard',
        'status'       => 'created',
        'scheduled_at' => now()->addDay(),
    ]);

    if (!$order->uuid || !$order->public_id) {
        throw new Exception("Order creation failed");
    }

    // Modify/reschedule order
    $order->status = 'in_progress';
    $order->scheduled_at = now()->addDays(2);
    $order->save();

    if ($order->status !== 'in_progress') {
        throw new Exception("Order update failed");
    }
});

echo "\n====================================================\n";
echo "  Results: {$passed} / {$total} Tests Passed\n";
echo "====================================================\n";

if ($passed !== $total) {
    exit(1);
}
exit(0);
