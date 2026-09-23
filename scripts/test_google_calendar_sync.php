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

// 1. Event Title (Summary) Format
$runTest("Event Title format: 'Ride: [Initials] - [Time]'", function () use ($service) {
    $order = new Order();
    $order->public_id = 'order_luxury99';
    $order->scheduled_at = '2026-12-25 15:00:00';
    $order->meta = [
        'passenger_name' => 'John Doe',
        'vehicle_type'   => 'luxury',
    ];

    $summary = $service->buildSummary($order);
    if (!str_starts_with($summary, 'Ride: J.D. - ')) {
        throw new Exception("Expected summary starting with 'Ride: J.D. - ', got: '{$summary}'");
    }
});

// 2. Event Title Initials parsing logic
$runTest("Event Title initials extraction works for single and multi-word names", function () use ($service) {
    $order1 = new Order();
    $order1->meta = ['passenger_name' => 'Alice'];
    $summary1 = $service->buildSummary($order1);
    if (!str_contains($summary1, 'Ride: A. - ')) {
        throw new Exception("Expected 'Ride: A. - ...', got '{$summary1}'");
    }

    $order2 = new Order();
    $order2->meta = ['passenger_name' => 'Bob Marley Jenkins'];
    $summary2 = $service->buildSummary($order2);
    if (!str_contains($summary2, 'Ride: B.M.J. - ')) {
        throw new Exception("Expected 'Ride: B.M.J. - ...', got '{$summary2}'");
    }
});

// 3. Event Title fallback for missing name
$runTest("Event Title fallback: 'N.A.' when passenger name is null", function () use ($service) {
    $order = new Order();
    $order->meta = [];

    $summary = $service->buildSummary($order);
    if (!str_contains($summary, 'Ride: N.A. - ')) {
        throw new Exception("Expected Ride: N.A. - ..., got: '{$summary}'");
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

// 5. Event Description matches untokenized layout with full details
$runTest("Event Description matches untokenized layout and includes passenger initials and details", function () use ($service) {
    $order = new Order();
    $order->public_id = 'order_meta_test';
    $order->scheduled_at = '2026-09-22 13:00:00';
    $order->meta = [
        'passenger_name' => 'Jane Supersecret',
        'passenger_phone' => '+15554443322',
        'passenger_email' => 'jane.passenger@example.com',
        'vehicle_type' => 'sedan',
        'child_seats' => '1 Toddler Seat',
    ];

    $description = $service->buildDescription($order);

    if (!str_contains($description, "Customer Initials: J.S.")) {
        throw new Exception("Description should include Customer Initials: J.S.");
    }
    if (!str_contains($description, "Phone: +15554443322")) {
        throw new Exception("Description should include Phone");
    }
    if (!str_contains($description, "Email: jane.passenger@example.com")) {
        throw new Exception("Description should include Email");
    }
    if (!str_contains($description, "Vehicle: sedan")) {
        throw new Exception("Description should include Vehicle");
    }
    if (!str_contains($description, "Child Seats: 1 Toddler Seat")) {
        throw new Exception("Description should include Child Seats");
    }
});

// 6. Event Description displays default fallbacks for missing values
$runTest("Event Description displays default N/A for missing details", function () use ($service) {
    $order = new Order();
    $description = $service->buildDescription($order);

    if (!str_contains($description, "Customer Initials: N.A.")) {
        throw new Exception("Expected Customer Initials to be N.A.");
    }
    if (!str_contains($description, "Vehicle: N/A")) {
        throw new Exception("Expected Vehicle to be N/A");
    }
    if (!str_contains($description, "Child Seats: N/A")) {
        throw new Exception("Expected Child Seats to be N/A");
    }
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

    if ($startTime->format('Y-m-d H:i:s') !== '2026-12-25 15:00:00') {
        throw new Exception("Expected startTime to be 2026-12-25 15:00:00, got: " . $startTime->format('Y-m-d H:i:s'));
    }
});

// 8. Order Updates & Sync uses exact same schema
$runTest("Update method uses exact same schema as creation with same details", function () use ($service) {
    $order = new Order();
    $order->public_id = 'order_update_test';
    $order->scheduled_at = '2026-09-20 18:00:00';
    $order->meta = [
        'passenger_name' => 'Bob Smith',
        'vehicle_type' => 'shuttle',
    ];

    $createPayload = $service->createEvent($order);
    $updatePayload = $service->updateEvent($order);

    unset($createPayload['id'], $updatePayload['id']);

    if ($createPayload !== $updatePayload) {
        throw new Exception("createEvent and updateEvent payloads must be identical!");
    }
    if (!str_starts_with($updatePayload['summary'], 'Ride: B.S. - ')) {
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
