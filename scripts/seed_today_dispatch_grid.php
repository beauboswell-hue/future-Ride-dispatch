<?php

use Fleetbase\FleetOps\Models\Contact;
use Fleetbase\FleetOps\Models\Driver;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\OrderConfig;
use Fleetbase\FleetOps\Models\Payload;
use Fleetbase\FleetOps\Models\Place;
use Fleetbase\FleetOps\Models\Vehicle;
use Fleetbase\LaravelMysqlSpatial\Types\Point;
use Fleetbase\Models\Company;
use Illuminate\Support\Carbon;

require_once __DIR__ . '/../api/vendor/autoload.php';
$app = require_once __DIR__ . '/../api/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=================================================================\n";
echo "    SEEDING REALISTIC TODAY RESERVATIONS (2026-09-09)            \n";
echo "=================================================================\n\n";

$company = Company::where('name', 'Future Limo')->first() ?? Company::first();
if (!$company) {
    echo "❌ ERROR: No company found in database!\n";
    exit(1);
}
echo "1. Active Company: {$company->name} ({$company->uuid})\n";

$orderConfig = OrderConfig::where([
    'company_uuid' => $company->uuid,
    'key'          => 'transport',
])->first();

if (!$orderConfig) {
    $orderConfig = OrderConfig::where('company_uuid', $company->uuid)->first();
}
echo "2. Order Config: " . ($orderConfig ? "{$orderConfig->name} ({$orderConfig->uuid})" : "Default") . "\n";

// Fetch available drivers
$drivers = Driver::with('user')->where('company_uuid', $company->uuid)->get();
$beau = $drivers->first(fn($d) => str_contains($d->name, 'Beau'));
$noel = $drivers->first(fn($d) => str_contains($d->name, 'Noel'));
$tom  = $drivers->first(fn($d) => str_contains($d->name, 'Tom'));
$tb   = $drivers->first(fn($d) => str_contains($d->name, 'T. B'));
$fallbackDriver = $drivers->first();

echo "3. Available Drivers:\n";
echo "   - Beau Boswell: " . ($beau ? $beau->uuid : "None") . "\n";
echo "   - Noel Walker: " . ($noel ? $noel->uuid : "None") . "\n";
echo "   - Tom Brennan: " . ($tom ? $tom->uuid : "None") . "\n";

// Move any existing orders from today to yesterday so Today cleanly contains the 7 canonical test reservations
$existingCount = Order::where('company_uuid', $company->uuid)
    ->whereDate('scheduled_at', '2026-09-09')
    ->update(['scheduled_at' => Carbon::parse('2026-09-08 12:00:00', 'UTC')]);
echo "4. Relocated {$existingCount} existing orders to 2026-09-08.\n\n";

// Define 7 Canonical Stage Reservations for Today (2026-09-09)
$reservations = [
    [
        'label'        => '1. Created (1:00 PM)',
        'status'       => 'created',
        'scheduled_ny' => '2026-09-09 13:00:00',
        'customer'     => [
            'name'  => 'Guest Passenger',
            'phone' => null,
            'email' => 'guest.passenger@futurelimo.test',
        ],
        'pickup'       => [
            'name'     => 'JFK Airport - Terminal 4',
            'street1'  => 'Terminal 4 Arrivals, Queens, NY 11430',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '11430',
            'lat'      => 40.6437,
            'lng'      => -73.7820,
        ],
        'dropoff'      => [
            'name'     => 'The Plaza Hotel',
            'street1'  => '768 5th Ave, New York, NY 10019',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '10019',
            'lat'      => 40.7645,
            'lng'      => -73.9744,
        ],
        'driver'       => null,
        'started'      => false,
        'dispatched'   => false,
        'notes'        => "Airport Pickup - Flight BA177\nMeet & Greet at Terminal 4 Baggage Claim [Tags: vip, airport-transfer, meet-greet]",
    ],
    [
        'label'        => '2. Dispatched (2:15 PM)',
        'status'       => 'dispatched',
        'scheduled_ny' => '2026-09-09 14:15:00',
        'customer'     => [
            'name'  => 'Sarah Jenkins',
            'phone' => '+1 (212) 555-0144',
            'email' => 'sarah.jenkins@morganholdings.test',
        ],
        'pickup'       => [
            'name'     => 'Midtown Financial Center',
            'street1'  => '200 West St, New York, NY 10282',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '10282',
            'lat'      => 40.7149,
            'lng'      => -74.0150,
        ],
        'dropoff'      => [
            'name'     => 'Grand Central Terminal',
            'street1'  => '89 E 42nd St, New York, NY 10017',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '10017',
            'lat'      => 40.7527,
            'lng'      => -73.9772,
        ],
        'driver'       => $beau ?? $fallbackDriver,
        'started'      => false,
        'dispatched'   => true,
        'notes'        => "Executive Town Car Transfer\nPassenger requires trunk space for 2 bags [Tags: corporate]",
    ],
    [
        'label'        => '3. En Route (3:30 PM)',
        'status'       => 'enroute_pickup',
        'scheduled_ny' => '2026-09-09 15:30:00',
        'customer'     => [
            'name'  => 'David Miller',
            'phone' => '+1 (212) 555-0182',
            'email' => 'david.miller@apextech.test',
        ],
        'pickup'       => [
            'name'     => 'Upper East Side Residence',
            'street1'  => '1048 5th Ave, New York, NY 10028',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '10028',
            'lat'      => 40.7812,
            'lng'      => -73.9620,
        ],
        'dropoff'      => [
            'name'     => 'LaGuardia Airport (LGA)',
            'street1'  => 'Queens, NY 11371',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '11371',
            'lat'      => 40.7769,
            'lng'      => -73.8740,
        ],
        'driver'       => $noel ?? $fallbackDriver,
        'started'      => true,
        'dispatched'   => true,
        'notes'        => "Flight Delta DL452 to Chicago\nDriver en route to pickup location [Tags: airport-transfer]",
    ],
    [
        'label'        => '4. On Location (4:45 PM)',
        'status'       => 'on_location',
        'scheduled_ny' => '2026-09-09 16:45:00',
        'customer'     => [
            'name'  => 'Elena Rostova',
            'phone' => '+1 (212) 555-0199',
            'email' => 'elena.rostova@luxuryarts.test',
        ],
        'pickup'       => [
            'name'     => 'SoHo House New York',
            'street1'  => '29-35 9th Ave, New York, NY 10014',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '10014',
            'lat'      => 40.7402,
            'lng'      => -74.0060,
        ],
        'dropoff'      => [
            'name'     => 'Newark Liberty Airport (EWR)',
            'street1'  => '3 Brewster Rd, Newark, NJ 07114',
            'city'     => 'Newark',
            'province' => 'NJ',
            'postal'   => '07114',
            'lat'      => 40.6895,
            'lng'      => -74.1745,
        ],
        'driver'       => $tom ?? $fallbackDriver,
        'started'      => true,
        'dispatched'   => true,
        'notes'        => "Chauffeur on location waiting curbside with hazard lights on [Tags: vip]",
    ],
    [
        'label'        => '5. POB - Passenger on Board (5:30 PM)',
        'status'       => 'pob',
        'scheduled_ny' => '2026-09-09 17:30:00',
        'customer'     => [
            'name'  => 'Marcus Vance',
            'phone' => '+1 (212) 555-0128',
            'email' => 'marcus.vance@vancecap.test',
        ],
        'pickup'       => [
            'name'     => 'Times Square Broadway',
            'street1'  => '1560 Broadway, New York, NY 10036',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '10036',
            'lat'      => 40.7589,
            'lng'      => -73.9851,
        ],
        'dropoff'      => [
            'name'     => 'Brooklyn Heights Promenade',
            'street1'  => 'Montague St, Brooklyn, NY 11201',
            'city'     => 'Brooklyn',
            'province' => 'NY',
            'postal'   => '11201',
            'lat'      => 40.6970,
            'lng'      => -73.9972,
        ],
        'driver'       => $beau ?? $fallbackDriver,
        'started'      => true,
        'dispatched'   => true,
        'notes'        => "Passenger on board, route via Manhattan Bridge [Tags: vip]",
    ],
    [
        'label'        => '6. Completed (6:15 PM)',
        'status'       => 'completed',
        'scheduled_ny' => '2026-09-09 18:15:00',
        'customer'     => [
            'name'  => 'Sophia Taylor',
            'phone' => '+1 (212) 555-0163',
            'email' => 'sophia.taylor@couture.test',
        ],
        'pickup'       => [
            'name'     => 'Empire State Building',
            'street1'  => '20 W 34th St, New York, NY 10001',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '10001',
            'lat'      => 40.7484,
            'lng'      => -73.9857,
        ],
        'dropoff'      => [
            'name'     => 'Central Park South Club',
            'street1'  => '180 Central Park S, New York, NY 10019',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '10019',
            'lat'      => 40.7663,
            'lng'      => -73.9790,
        ],
        'driver'       => $noel ?? $fallbackDriver,
        'started'      => true,
        'dispatched'   => true,
        'notes'        => "Reservation completed. Passenger signature received on driver app.",
    ],
    [
        'label'        => '7. Canceled (7:00 PM)',
        'status'       => 'canceled',
        'scheduled_ny' => '2026-09-09 19:00:00',
        'customer'     => [
            'name'  => 'Robert Sterling',
            'phone' => '+1 (212) 555-0111',
            'email' => 'robert.sterling@sterlinglaw.test',
        ],
        'pickup'       => [
            'name'     => 'New York Stock Exchange',
            'street1'  => '11 Wall St, New York, NY 10005',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '10005',
            'lat'      => 40.7069,
            'lng'      => -74.0113,
        ],
        'dropoff'      => [
            'name'     => 'Teterboro Executive Airport',
            'street1'  => '111 Industrial Ave, Teterboro, NJ 07608',
            'city'     => 'Teterboro',
            'province' => 'NJ',
            'postal'   => '07608',
            'lat'      => 40.8501,
            'lng'      => -74.0608,
        ],
        'driver'       => null,
        'started'      => false,
        'dispatched'   => false,
        'notes'        => "Trip canceled by passenger due to rescheduled private flight.",
    ],
];

echo "5. Creating 7 Canonical Stage Reservations for Today:\n";

foreach ($reservations as $idx => $r) {
    // 1. Customer
    $contact = Contact::firstOrCreate(
        [
            'company_uuid' => $company->uuid,
            'email'        => $r['customer']['email'],
        ],
        [
            'name'  => $r['customer']['name'],
            'phone' => $r['customer']['phone'],
            'type'  => 'customer',
        ]
    );
    if ($contact->name !== $r['customer']['name'] || $contact->phone !== $r['customer']['phone']) {
        $contact->update([
            'name'  => $r['customer']['name'],
            'phone' => $r['customer']['phone'],
        ]);
    }

    // 2. Pickup Place
    $pickup = Place::create([
        'company_uuid' => $company->uuid,
        'name'         => $r['pickup']['name'],
        'street1'      => $r['pickup']['street1'],
        'city'         => $r['pickup']['city'],
        'province'     => $r['pickup']['province'],
        'postal_code'  => $r['pickup']['postal'],
        'country'      => 'US',
        'location'     => new Point($r['pickup']['lat'], $r['pickup']['lng']),
    ]);

    // 3. Dropoff Place
    $dropoff = Place::create([
        'company_uuid' => $company->uuid,
        'name'         => $r['dropoff']['name'],
        'street1'      => $r['dropoff']['street1'],
        'city'         => $r['dropoff']['city'],
        'province'     => $r['dropoff']['province'],
        'postal_code'  => $r['dropoff']['postal'],
        'country'      => 'US',
        'location'     => new Point($r['dropoff']['lat'], $r['dropoff']['lng']),
    ]);

    // 4. Payload
    $payload = Payload::create([
        'company_uuid' => $company->uuid,
        'pickup_uuid'  => $pickup->uuid,
        'dropoff_uuid' => $dropoff->uuid,
    ]);

    // 5. Scheduled Time: convert NY local time to UTC for storage
    $scheduledUtc = Carbon::parse($r['scheduled_ny'], 'America/New_York')->setTimezone('UTC');

    // 6. Order Creation
    $order = Order::create([
        'company_uuid'         => $company->uuid,
        'customer_uuid'        => $contact->uuid,
        'customer_type'        => Contact::class,
        'payload_uuid'         => $payload->uuid,
        'order_config_uuid'    => $orderConfig ? $orderConfig->uuid : null,
        'type'                 => 'transport',
        'status'               => $r['status'],
        'driver_assigned_uuid' => $r['driver'] ? $r['driver']->uuid : null,
        'scheduled_at'         => $scheduledUtc,
        'dispatched'           => $r['dispatched'],
        'started'              => $r['started'],
        'notes'                => $r['notes'],
        'created_at'           => now()->subMinutes(10 - $idx),
        'updated_at'           => now(),
    ]);

    \DB::table('orders')->where('uuid', $order->uuid)->update(['status' => $r['status']]);

    $driverName = $r['driver'] ? $r['driver']->name : 'Unassigned';
    echo "   ✓ {$r['label']}: Order {$order->public_id} | Status: {$r['status']} | Driver: {$driverName}\n";
}

echo "\n✓ All 7 canonical reservations created successfully!\n";
