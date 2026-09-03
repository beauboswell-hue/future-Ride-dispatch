<?php

/**
 * Standalone Test Script: Simulate Web Booking and Verify Notes Formatting
 *
 * Usage:
 *   docker exec fleetbase-application-1 php /fleetbase/api/test_web_booking.php
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\WebBookingNoteFormatter;
use Fleetbase\FleetOps\Models\Contact;
use Fleetbase\FleetOps\Models\Driver;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\Payload;
use Fleetbase\FleetOps\Models\Place;
use Fleetbase\FleetOps\Models\Vehicle;
use Fleetbase\LaravelMysqlSpatial\Types\Point;
use Fleetbase\Models\Company;
use Illuminate\Support\Carbon;

echo "====================================================\n";
echo "  Fleetbase Web Booking Notes Simulation & Test     \n";
echo "====================================================\n\n";

$company = Company::first();
if (!$company) {
    echo "ERROR: No company found in DB.\n";
    exit(1);
}
echo "✓ Found Company: {$company->name} ({$company->public_id})\n";

// Test 1: Standard Web Booking with Different Locations
echo "\n--- [TEST 1] Standard Web Booking with Scheduled Time & Different Locations ---\n";

$customer = Contact::firstOrCreate(
    [
        'company_uuid' => $company->uuid,
        'email'        => 'alex.smith@example.com',
    ],
    [
        'name'         => 'Alex Smith',
        'phone'        => '+1 212-555-4321',
        'type'         => 'customer',
        'meta'         => [
            'username' => 'asmith',
            'timezone' => 'America/New_York',
        ],
    ]
);

$pickup1 = Place::create([
    'company_uuid' => $company->uuid,
    'name'         => 'Grand Central Terminal',
    'street1'      => '89 E 42nd St',
    'city'         => 'New York',
    'province'     => 'NY',
    'postal_code'  => '10017',
    'country'      => 'US',
    'location'     => new Point(40.7527, -73.9772),
]);

$dropoff1 = Place::create([
    'company_uuid' => $company->uuid,
    'name'         => 'LaGuardia Airport (LGA)',
    'street1'      => 'Queens, NY 11371',
    'city'         => 'New York',
    'province'     => 'NY',
    'postal_code'  => '11371',
    'country'      => 'US',
    'location'     => new Point(40.7769, -73.8740),
]);

$payload1 = Payload::create([
    'company_uuid' => $company->uuid,
    'pickup_uuid'  => $pickup1->uuid,
    'dropoff_uuid' => $dropoff1->uuid,
]);

$scheduledTime = Carbon::parse('2026-09-15 14:00:00', 'America/New_York');

$order1 = Order::create([
    'company_uuid'  => $company->uuid,
    'customer_uuid' => $customer->uuid,
    'customer_type' => 'Fleetbase\\FleetOps\\Models\\Contact',
    'payload_uuid'  => $payload1->uuid,
    'scheduled_at'  => $scheduledTime,
    'status'        => 'created',
    'type'          => 'transport',
    'notes'         => 'Web Booking via Homepage Form',
    'meta'          => [
        'source'          => 'Website Form',
        'sms_consent'     => 1,
        'tags'            => ['vip', 'airport-transfer'],
        'pickup_timezone' => 'America/New_York',
    ],
]);

$order1->refresh();

echo "\n>>> Order 1 Public ID: {$order1->public_id}\n";
echo ">>> Rendered order.notes:\n";
echo "----------------------------------------------------\n";
echo $order1->notes . "\n";
echo "----------------------------------------------------\n";

// Assertions for Test 1
$t1_checks = [
    'Header Contact Info'    => str_contains($order1->notes, '### 👤 Contact Information'),
    'Name'                   => str_contains($order1->notes, '• Name: Alex Smith'),
    'Phone'                  => str_contains($order1->notes, '• Phone:') && str_contains($order1->notes, '212'),
    'Email'                  => str_contains($order1->notes, '• Email: alex.smith@example.com'),
    'Username'               => str_contains($order1->notes, '• Account Username:'),
    'Timezone'               => str_contains($order1->notes, '• Account Timezone:'),
    'Header Ride Info'       => str_contains($order1->notes, "### 📅 Ride Information (Order {$order1->public_id})"),
    'Scheduled UTC'          => str_contains($order1->notes, '- UTC:'),
    'Scheduled Local'        => str_contains($order1->notes, '- Local Pickup Time:'),
    'Scheduled Eastern'      => str_contains($order1->notes, '- Eastern Time:'),
    'Pickup Location'        => str_contains($order1->notes, '- Pickup:'),
    'Dropoff Location'       => str_contains($order1->notes, '- Dropoff:'),
    'No Warning Flag'        => !str_contains($order1->notes, 'WARNING'),
    'Status'                 => str_contains($order1->notes, '• Status: created'),
    'Driver None Assigned'   => str_contains($order1->notes, '• Assigned Driver: None Assigned'),
    'Vehicle None Assigned'  => str_contains($order1->notes, '• Assigned Vehicle: None Assigned'),
    'Booking Notes / Source' => str_contains($order1->notes, '• Booking Notes / Source: Website Form - Web Booking via Homepage Form [Tags: vip, airport-transfer]'),
];

foreach ($t1_checks as $label => $passed) {
    echo ($passed ? "  [PASS] " : "  [FAIL] ") . $label . "\n";
    if (!$passed) {
        exit(1);
    }
}

// Test 2: Matching Pickup and Dropoff Locations (Warning Flag Verification)
echo "\n--- [TEST 2] Web Booking with MATCHING Pickup & Dropoff (Warning Flag Verification) ---\n";

$payload2 = Payload::create([
    'company_uuid' => $company->uuid,
    'pickup_uuid'  => $pickup1->uuid,
    'dropoff_uuid' => $pickup1->uuid, // MATCHING
]);

$order2 = Order::create([
    'company_uuid'  => $company->uuid,
    'customer_uuid' => $customer->uuid,
    'customer_type' => 'Fleetbase\\FleetOps\\Models\\Contact',
    'payload_uuid'  => $payload2->uuid,
    'status'        => 'created',
    'type'          => 'transport',
    'notes'         => 'Web Booking via Homepage Form',
    'meta'          => [
        'source' => 'Website Form',
    ],
]);

$order2->refresh();

echo "\n>>> Order 2 Public ID: {$order2->public_id}\n";
echo ">>> Rendered order.notes:\n";
echo "----------------------------------------------------\n";
echo $order2->notes . "\n";
echo "----------------------------------------------------\n";

$t2_checks = [
    'Warning in notes' => str_contains($order2->notes, 'WARNING') && (str_contains($order2->notes, 'identical') || str_contains($order2->notes, 'Matches pickup')),
    'Warning flag icon' => str_contains($order2->notes, '⚠️'),
];

foreach ($t2_checks as $label => $passed) {
    echo ($passed ? "  [PASS] " : "  [FAIL] ") . $label . "\n";
    if (!$passed) {
        exit(1);
    }
}

// Test 3: Null Safety - Missing Customer, Missing Schedule, Missing Dropoff Notes
echo "\n--- [TEST 3] Null Safety: Unassigned Driver/Vehicle, Missing Schedule, Null Customer ---\n";

$pickup3 = Place::create([
    'company_uuid' => $company->uuid,
    'street1'      => 'TBD Pickup Location',
    'country'      => 'US',
    'location'     => new Point(0, 0),
]);

$dropoff3 = Place::create([
    'company_uuid' => $company->uuid,
    'street1'      => 'TBD Dropoff Location',
    'country'      => 'US',
    'location'     => new Point(0, 0),
]);

$payload3 = Payload::create([
    'company_uuid' => $company->uuid,
    'pickup_uuid'  => $pickup3->uuid,
    'dropoff_uuid' => $dropoff3->uuid,
]);

$order3 = Order::create([
    'company_uuid' => $company->uuid,
    'payload_uuid' => $payload3->uuid,
    'notes'        => 'Web Booking via Homepage Form',
    'meta'         => [
        'source' => 'Website Form',
    ],
]);

$order3->refresh();

echo "\n>>> Order 3 Public ID: {$order3->public_id}\n";
echo ">>> Rendered order.notes:\n";
echo "----------------------------------------------------\n";
echo $order3->notes . "\n";
echo "----------------------------------------------------\n";

$t3_checks = [
    'Name N/A'          => str_contains($order3->notes, '• Name: N/A'),
    'Phone N/A'         => str_contains($order3->notes, '• Phone: N/A'),
    'Email N/A'         => str_contains($order3->notes, '• Email: N/A'),
    'Username N/A'      => str_contains($order3->notes, '• Account Username: N/A'),
    'Timezone N/A'      => str_contains($order3->notes, '• Account Timezone: N/A'),
    'Scheduled UTC N/A' => str_contains($order3->notes, '- UTC: N/A'),
    'Driver None'       => str_contains($order3->notes, '• Assigned Driver: None Assigned'),
    'Vehicle None'      => str_contains($order3->notes, '• Assigned Vehicle: None Assigned'),
];

foreach ($t3_checks as $label => $passed) {
    echo ($passed ? "  [PASS] " : "  [FAIL] ") . $label . "\n";
    if (!$passed) {
        exit(1);
    }
}

echo "\n====================================================\n";
echo "  🎉 ALL 3 TESTS PASSED SUCCESSFULLY!               \n";
echo "====================================================\n";
