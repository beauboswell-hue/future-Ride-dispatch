<?php

use Fleetbase\FleetOps\Models\Contact;
use Fleetbase\FleetOps\Models\Driver;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\OrderConfig;
use Fleetbase\FleetOps\Models\Payload;
use Fleetbase\FleetOps\Models\Place;
use Fleetbase\LaravelMysqlSpatial\Types\Point;
use Fleetbase\Models\Company;
use Illuminate\Support\Carbon;

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=================================================================\n";
echo "    SEEDING COMPREHENSIVE LIVERY RESERVATIONS (2026-09-09)       \n";
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

// Move any existing orders from today to yesterday so Today cleanly contains our 28 reservations
$existingCount = Order::where('company_uuid', $company->uuid)
    ->whereDate('scheduled_at', '2026-09-09')
    ->update(['scheduled_at' => Carbon::parse('2026-09-08 12:00:00', 'UTC')]);
echo "4. Relocated {$existingCount} existing orders to 2026-09-08.\n\n";

$reservations = [
    // --- 1. COMPLETED RUNS ---
    [
        'label'        => 'Completed - 05:00 AM',
        'status'       => 'completed',
        'scheduled_ny' => '2026-09-09 05:00:00',
        'customer'     => [
            'name'  => 'Alexander Wright',
            'phone' => '+1 (239) 555-0101',
            'email' => 'alex.wright@vanguard.test',
        ],
        'pickup'       => [
            'name'     => 'RSW Airport - Terminal Access Rd',
            'street1'  => 'Terminal Access Rd, Fort Myers, FL 33913',
            'city'     => 'Fort Myers',
            'province' => 'FL',
            'postal'   => '33913',
            'lat'      => 26.5362,
            'lng'      => -81.7551,
        ],
        'dropoff'      => [
            'name'     => 'Naples Grande Beach Resort',
            'street1'  => '475 Seagate Dr, Naples, FL 34103',
            'city'     => 'Naples',
            'province' => 'FL',
            'postal'   => '34103',
            'lat'      => 26.1983,
            'lng'      => -81.8055,
        ],
        'driver'       => $noel ?? $fallbackDriver,
        'started'      => true,
        'dispatched'   => true,
        'notes'        => "Early morning airport arrival. Client arriving on DL1045. Meet & greet inside the terminal. [Tags: airport-transfer, vip]",
    ],
    [
        'label'        => 'Completed - 06:30 AM',
        'status'       => 'completed',
        'scheduled_ny' => '2026-09-09 06:30:00',
        'customer'     => [
            'name'  => 'Beatrice Vance',
            'phone' => '+1 (305) 555-0102',
            'email' => 'beatrice.vance@vancecap.test',
        ],
        'pickup'       => [
            'name'     => 'The Ritz-Carlton, Naples',
            'street1'  => '280 Vanderbilt Beach Rd, Naples, FL 34108',
            'city'     => 'Naples',
            'province' => 'FL',
            'postal'   => '34108',
            'lat'      => 26.2568,
            'lng'      => -81.8211,
        ],
        'dropoff'      => [
            'name'     => 'RSW Airport - Terminal Access Rd',
            'street1'  => 'Terminal Access Rd, Fort Myers, FL 33913',
            'city'     => 'Fort Myers',
            'province' => 'FL',
            'postal'   => '33913',
            'lat'      => 26.5362,
            'lng'      => -81.7551,
        ],
        'driver'       => $tom ?? $fallbackDriver,
        'started'      => true,
        'dispatched'   => true,
        'notes'        => "Executive departure to RSW. Prefers bottled water and morning paper. [Tags: corporate, airport-transfer]",
    ],
    [
        'label'        => 'Completed - 08:00 AM',
        'status'       => 'completed',
        'scheduled_ny' => '2026-09-09 08:00:00',
        'customer'     => [
            'name'  => 'Charles Dupont',
            'phone' => '+1 (212) 555-0103',
            'email' => 'charles.dupont@lux-travel.test',
        ],
        'pickup'       => [
            'name'     => 'Naples Grande Beach Resort',
            'street1'  => '475 Seagate Dr, Naples, FL 34103',
            'city'     => 'Naples',
            'province' => 'FL',
            'postal'   => '34103',
            'lat'      => 26.1983,
            'lng'      => -81.8055,
        ],
        'dropoff'      => [
            'name'     => 'Fifth Avenue South',
            'street1'  => '701 5th Ave S, Naples, FL 34102',
            'city'     => 'Naples',
            'province' => 'FL',
            'postal'   => '34102',
            'lat'      => 26.1420,
            'lng'      => -81.7948,
        ],
        'driver'       => $beau ?? $fallbackDriver,
        'started'      => true,
        'dispatched'   => true,
        'notes'        => "Local hotel hop for shopping on 5th Avenue South. [Tags: hotel-hop]",
    ],
    [
        'label'        => 'Completed - 09:30 AM',
        'status'       => 'completed',
        'scheduled_ny' => '2026-09-09 09:30:00',
        'customer'     => [
            'name'  => 'Diana Prince',
            'phone' => '+1 (239) 555-0104',
            'email' => 'diana.prince@waynecorp.test',
        ],
        'pickup'       => [
            'name'     => 'Fort Myers Downtown',
            'street1'  => '2200 First St, Fort Myers, FL 33901',
            'city'     => 'Fort Myers',
            'province' => 'FL',
            'postal'   => '33901',
            'lat'      => 26.6406,
            'lng'      => -81.8723,
        ],
        'dropoff'      => [
            'name'     => 'Marco Island Marriott',
            'street1'  => '400 S Collier Blvd, Marco Island, FL 34145',
            'city'     => 'Marco Island',
            'province' => 'FL',
            'postal'   => '34145',
            'lat'      => 25.9135,
            'lng'      => -81.7281,
        ],
        'driver'       => $noel ?? $fallbackDriver,
        'started'      => true,
        'dispatched'   => true,
        'notes'        => "Corporate travel between regional offices. [Tags: corporate]",
    ],

    // --- 2. CANCELED RUNS ---
    [
        'label'        => 'Canceled - 07:15 AM',
        'status'       => 'canceled',
        'scheduled_ny' => '2026-09-09 07:15:00',
        'customer'     => [
            'name'  => 'Ethan Hunt',
            'phone' => '+1 (305) 555-0105',
            'email' => 'ethan.hunt@imf-global.test',
        ],
        'pickup'       => [
            'name'     => 'Hyatt Regency Coconut Point',
            'street1'  => '5001 Coconut Rd, Bonita Springs, FL 34134',
            'city'     => 'Bonita Springs',
            'province' => 'FL',
            'postal'   => '34134',
            'lat'      => 26.3905,
            'lng'      => -81.8540,
        ],
        'dropoff'      => [
            'name'     => 'RSW Airport - Terminal Access Rd',
            'street1'  => 'Terminal Access Rd, Fort Myers, FL 33913',
            'city'     => 'Fort Myers',
            'province' => 'FL',
            'postal'   => '33913',
            'lat'      => 26.5362,
            'lng'      => -81.7551,
        ],
        'driver'       => null,
        'started'      => false,
        'dispatched'   => false,
        'notes'        => "Canceled by passenger due to meeting reschedule. [Tags: canceled]",
    ],
    [
        'label'        => 'Canceled - 10:45 AM',
        'status'       => 'canceled',
        'scheduled_ny' => '2026-09-09 10:45:00',
        'customer'     => [
            'name'  => 'Fiona Gallagher',
            'phone' => '+1 (212) 555-0106',
            'email' => 'fiona.g@southside.test',
        ],
        'pickup'       => [
            'name'     => 'Grand Central Terminal',
            'street1'  => '89 E 42nd St, New York, NY 10017',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '10017',
            'lat'      => 40.7527,
            'lng'      => -73.9772,
        ],
        'dropoff'      => [
            'name'     => 'JFK Airport - Terminal 4',
            'street1'  => 'Terminal 4 Arrivals, Queens, NY 11430',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '11430',
            'lat'      => 40.6437,
            'lng'      => -73.7820,
        ],
        'driver'       => null,
        'started'      => false,
        'dispatched'   => false,
        'notes'        => "Flight cancelled. Reservation rescheduled for tomorrow. [Tags: canceled]",
    ],
    [
        'label'        => 'Canceled - 02:00 PM',
        'status'       => 'canceled',
        'scheduled_ny' => '2026-09-09 14:00:00',
        'customer'     => [
            'name'  => 'George Clooney',
            'phone' => '+1 (239) 555-0107',
            'email' => 'george.c@casamigos.test',
        ],
        'pickup'       => [
            'name'     => 'LaGuardia Airport',
            'street1'  => 'Queens, NY 11371',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '11371',
            'lat'      => 40.7769,
            'lng'      => -73.8740,
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
        'driver'       => $tom ?? $fallbackDriver,
        'started'      => false,
        'dispatched'   => false,
        'notes'        => "No-show at terminal after 30 minutes of wait. [Tags: canceled, no-show]",
    ],
    [
        'label'        => 'Canceled - 07:15 PM',
        'status'       => 'canceled',
        'scheduled_ny' => '2026-09-09 19:15:00',
        'customer'     => [
            'name'  => 'Helena Rostova',
            'phone' => '+1 (305) 555-0108',
            'email' => 'helena.r@rostov-arts.test',
        ],
        'pickup'       => [
            'name'     => 'Fifth Avenue South',
            'street1'  => '701 5th Ave S, Naples, FL 34102',
            'city'     => 'Naples',
            'province' => 'FL',
            'postal'   => '34102',
            'lat'      => 26.1420,
            'lng'      => -81.7948,
        ],
        'dropoff'      => [
            'name'     => 'Ritz-Carlton Golf Resort',
            'street1'  => '2600 Tiburon Dr, Naples, FL 34109',
            'city'     => 'Naples',
            'province' => 'FL',
            'postal'   => '34109',
            'lat'      => 26.2425,
            'lng'      => -81.7681,
        ],
        'driver'       => null,
        'started'      => false,
        'dispatched'   => false,
        'notes'        => "Canceled in advance by assistant. [Tags: canceled]",
    ],

    // --- 3. PASSENGER ON BOARD (POB) RUNS ---
    [
        'label'        => 'POB - 11:15 AM',
        'status'       => 'pob',
        'scheduled_ny' => '2026-09-09 11:15:00',
        'customer'     => [
            'name'  => 'Ian Malcolm',
            'phone' => '+1 (212) 555-0109',
            'email' => 'ian.malcolm@jurassic.test',
        ],
        'pickup'       => [
            'name'     => 'Marco Island Residence',
            'street1'  => '1200 S Barfield Dr, Marco Island, FL 34145',
            'city'     => 'Marco Island',
            'province' => 'FL',
            'postal'   => '34145',
            'lat'      => 25.9250,
            'lng'      => -81.7110,
        ],
        'dropoff'      => [
            'name'     => 'Naples Airport (APF)',
            'street1'  => '160 Aviation Dr N, Naples, FL 34104',
            'city'     => 'Naples',
            'province' => 'FL',
            'postal'   => '34104',
            'lat'      => 26.1524,
            'lng'      => -81.7753,
        ],
        'driver'       => $beau ?? $fallbackDriver,
        'started'      => true,
        'dispatched'   => true,
        'notes'        => "Private jet departure. Client on board, traveling via Collier Blvd. [Tags: vip, private-jet]",
    ],
    [
        'label'        => 'POB - 12:00 PM',
        'status'       => 'pob',
        'scheduled_ny' => '2026-09-09 12:00:00',
        'customer'     => [
            'name'  => 'Julia Roberts',
            'phone' => '+1 (239) 555-0110',
            'email' => 'julia.r@prettywoman.test',
        ],
        'pickup'       => [
            'name'     => 'The Ritz-Carlton, Naples',
            'street1'  => '280 Vanderbilt Beach Rd, Naples, FL 34108',
            'city'     => 'Naples',
            'province' => 'FL',
            'postal'   => '34108',
            'lat'      => 26.2568,
            'lng'      => -81.8211,
        ],
        'dropoff'      => [
            'name'     => 'Fifth Avenue South',
            'street1'  => '701 5th Ave S, Naples, FL 34102',
            'city'     => 'Naples',
            'province' => 'FL',
            'postal'   => '34102',
            'lat'      => 26.1420,
            'lng'      => -81.7948,
        ],
        'driver'       => $noel ?? $fallbackDriver,
        'started'      => true,
        'dispatched'   => true,
        'notes'        => "Lunch appointment on 5th Ave. Passenger on board. [Tags: hotel-hop]",
    ],
    [
        'label'        => 'POB - 01:30 PM',
        'status'       => 'pob',
        'scheduled_ny' => '2026-09-09 13:30:00',
        'customer'     => [
            'name'  => 'Kevin Hart',
            'phone' => '+1 (305) 555-0111',
            'email' => 'kevin.hart@lolcom.test',
        ],
        'pickup'       => [
            'name'     => 'Naples Grande Beach Resort',
            'street1'  => '475 Seagate Dr, Naples, FL 34103',
            'city'     => 'Naples',
            'province' => 'FL',
            'postal'   => '34103',
            'lat'      => 26.1983,
            'lng'      => -81.8055,
        ],
        'dropoff'      => [
            'name'     => 'Mercato, Naples',
            'street1'  => '9115 Strada Pl, Naples, FL 34108',
            'city'     => 'Naples',
            'province' => 'FL',
            'postal'   => '34108',
            'lat'      => 26.2520,
            'lng'      => -81.7870,
        ],
        'driver'       => $tom ?? $fallbackDriver,
        'started'      => true,
        'dispatched'   => true,
        'notes'        => "Corporate lunch. Passenger in vehicle. [Tags: corporate]",
    ],
    [
        'label'        => 'POB - 03:00 PM',
        'status'       => 'pob',
        'scheduled_ny' => '2026-09-09 15:00:00',
        'customer'     => [
            'name'  => 'Laura Croft',
            'phone' => '+1 (212) 555-0112',
            'email' => 'lara.croft@tomb-raider.test',
        ],
        'pickup'       => [
            'name'     => 'RSW Airport - Terminal Access Rd',
            'street1'  => 'Terminal Access Rd, Fort Myers, FL 33913',
            'city'     => 'Fort Myers',
            'province' => 'FL',
            'postal'   => '33913',
            'lat'      => 26.5362,
            'lng'      => -81.7551,
        ],
        'dropoff'      => [
            'name'     => 'Sanibel Island Resort',
            'street1'  => '1451 Middle Gulf Dr, Sanibel, FL 33957',
            'city'     => 'Sanibel',
            'province' => 'FL',
            'postal'   => '33957',
            'lat'      => 26.4350,
            'lng'      => -82.0720,
        ],
        'driver'       => $beau ?? $fallbackDriver,
        'started'      => true,
        'dispatched'   => true,
        'notes'        => "Vacation arrival. En route to Sanibel Island. [Tags: airport-transfer, vip]",
    ],

    // --- 4. ON LOCATION RUNS ---
    [
        'label'        => 'On Location - 03:45 PM',
        'status'       => 'on_location',
        'scheduled_ny' => '2026-09-09 15:45:00',
        'customer'     => [
            'name'  => 'Marcus Aurelius',
            'phone' => '+1 (239) 555-0113',
            'email' => 'marcus.aurelius@rome-corp.test',
        ],
        'pickup'       => [
            'name'     => 'Tribeca Loft',
            'street1'  => '100 Hudson St, New York, NY 10013',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '10013',
            'lat'      => 40.7198,
            'lng'      => -74.0090,
        ],
        'dropoff'      => [
            'name'     => 'JFK Airport - Terminal 8',
            'street1'  => 'Terminal 8, Queens, NY 11430',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '11430',
            'lat'      => 40.6437,
            'lng'      => -73.7820,
        ],
        'driver'       => $noel ?? $fallbackDriver,
        'started'      => true,
        'dispatched'   => true,
        'notes'        => "Chauffeur on location waiting curbside. [Tags: corporate, airport-transfer]",
    ],
    [
        'label'        => 'On Location - 04:30 PM',
        'status'       => 'on_location',
        'scheduled_ny' => '2026-09-09 16:30:00',
        'customer'     => [
            'name'  => 'Natalie Portman',
            'phone' => '+1 (305) 555-0114',
            'email' => 'natalie.p@blackswan.test',
        ],
        'pickup'       => [
            'name'     => 'The Pierre NY',
            'street1'  => '2 E 61st St, New York, NY 10065',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '10065',
            'lat'      => 40.7654,
            'lng'      => -73.9715,
        ],
        'dropoff'      => [
            'name'     => 'LaGuardia Airport - Terminal B',
            'street1'  => 'Terminal B, Queens, NY 11371',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '11371',
            'lat'      => 40.7769,
            'lng'      => -73.8740,
        ],
        'driver'       => $tom ?? $fallbackDriver,
        'started'      => true,
        'dispatched'   => true,
        'notes'        => "Driver staged outside main lobby entrance. [Tags: vip, hotel-hop]",
    ],
    [
        'label'        => 'On Location - 05:00 PM',
        'status'       => 'on_location',
        'scheduled_ny' => '2026-09-09 17:00:00',
        'customer'     => [
            'name'  => 'Oliver Queen',
            'phone' => '+1 (212) 555-0115',
            'email' => 'oliver.queen@queen-ind.test',
        ],
        'pickup'       => [
            'name'     => 'Newark Liberty Airport - Terminal C',
            'street1'  => '3 Brewster Rd, Newark, NJ 07114',
            'city'     => 'Newark',
            'province' => 'NJ',
            'postal'   => '07114',
            'lat'      => 40.6895,
            'lng'      => -74.1745,
        ],
        'dropoff'      => [
            'name'     => 'Midtown Manhattan',
            'street1'  => '1211 Avenue of the Americas, New York, NY 10036',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '10036',
            'lat'      => 40.7580,
            'lng'      => -73.9820,
        ],
        'driver'       => $beau ?? $fallbackDriver,
        'started'      => true,
        'dispatched'   => true,
        'notes'        => "Chauffeur waiting at Terminal C arrivals with sign. [Tags: airport-transfer, corporate]",
    ],
    [
        'label'        => 'On Location - 05:30 PM',
        'status'       => 'on_location',
        'scheduled_ny' => '2026-09-09 17:30:00',
        'customer'     => [
            'name'  => 'Penelope Cruz',
            'phone' => '+1 (239) 555-0116',
            'email' => 'penelope.c@madrid.test',
        ],
        'pickup'       => [
            'name'     => 'Grand Central Terminal',
            'street1'  => '89 E 42nd St, New York, NY 10017',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '10017',
            'lat'      => 40.7527,
            'lng'      => -73.9772,
        ],
        'dropoff'      => [
            'name'     => 'Wall Street NYSE',
            'street1'  => '11 Wall St, New York, NY 10005',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '10005',
            'lat'      => 40.7069,
            'lng'      => -74.0113,
        ],
        'driver'       => $noel ?? $fallbackDriver,
        'started'      => true,
        'dispatched'   => true,
        'notes'        => "Driver staging on 42nd street outside terminal. [Tags: corporate]",
    ],

    // --- 5. EN ROUTE PICKUP RUNS ---
    [
        'label'        => 'En Route - 06:00 PM',
        'status'       => 'enroute_pickup',
        'scheduled_ny' => '2026-09-09 18:00:00',
        'customer'     => [
            'name'  => 'Quentin Tarantino',
            'phone' => '+1 (305) 555-0117',
            'email' => 'q.tarantino@pulp.test',
        ],
        'pickup'       => [
            'name'     => 'Brooklyn Heights',
            'street1'  => '150 Montague St, Brooklyn, NY 11201',
            'city'     => 'Brooklyn',
            'province' => 'NY',
            'postal'   => '11201',
            'lat'      => 40.6950,
            'lng'      => -73.9950,
        ],
        'dropoff'      => [
            'name'     => 'JFK Airport - Terminal 1',
            'street1'  => 'Terminal 1, Queens, NY 11430',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '11430',
            'lat'      => 40.6437,
            'lng'      => -73.7820,
        ],
        'driver'       => $tom ?? $fallbackDriver,
        'started'      => true,
        'dispatched'   => true,
        'notes'        => "Chauffeur en route to Brooklyn pickup. [Tags: vip]",
    ],
    [
        'label'        => 'En Route - 06:30 PM',
        'status'       => 'enroute_pickup',
        'scheduled_ny' => '2026-09-09 18:30:00',
        'customer'     => [
            'name'  => 'Rachel Green',
            'phone' => '+1 (212) 555-0118',
            'email' => 'rachel.green@ralphlauren.test',
        ],
        'pickup'       => [
            'name'     => 'Central Park South',
            'street1'  => '59 Central Park S, New York, NY 10019',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '10019',
            'lat'      => 40.7650,
            'lng'      => -73.9760,
        ],
        'dropoff'      => [
            'name'     => 'LaGuardia Airport - Terminal C',
            'street1'  => 'Terminal C, Queens, NY 11371',
            'city'     => 'New York',
            'province' => 'NY',
            'postal'   => '11371',
            'lat'      => 40.7769,
            'lng'      => -73.8740,
        ],
        'driver'       => $beau ?? $fallbackDriver,
        'started'      => true,
        'dispatched'   => true,
        'notes'        => "Chauffeur heading to Midtown pickup. [Tags: corporate]",
    ],
    [
        'label'        => 'En Route - 07:00 PM',
        'status'       => 'enroute_pickup',
        'scheduled_ny' => '2026-09-09 19:00:00',
        'customer'     => [
            'name'  => 'Samuel L. Jackson',
            'phone' => '+1 (239) 555-0119',
            'email' => 'sam.jackson@shield.test',
        ],
        'pickup'       => [
            'name'     => 'Fifth Avenue South',
            'street1'  => '701 5th Ave S, Naples, FL 34102',
            'city'     => 'Naples',
            'province' => 'FL',
            'postal'   => '34102',
            'lat'      => 26.1420,
            'lng'      => -81.7948,
        ],
        'dropoff'      => [
            'name'     => 'RSW Airport - Terminal Access Rd',
            'street1'  => 'Terminal Access Rd, Fort Myers, FL 33913',
            'city'     => 'Fort Myers',
            'province' => 'FL',
            'postal'   => '33913',
            'lat'      => 26.5362,
            'lng'      => -81.7551,
        ],
        'driver'       => $noel ?? $fallbackDriver,
        'started'      => true,
        'dispatched'   => true,
        'notes'        => "Dinner drop-off. Chauffeur en route. [Tags: vip]",
    ],
    [
        'label'        => 'En Route - 07:45 PM',
        'status'       => 'enroute_pickup',
        'scheduled_ny' => '2026-09-09 19:45:00',
        'customer'     => [
            'name'  => 'Taylor Swift',
            'phone' => '+1 (305) 555-0120',
            'email' => 'taylor.swift@eras.test',
        ],
        'pickup'       => [
            'name'     => 'Fort Myers Airport',
            'street1'  => 'Terminal Access Rd, Fort Myers, FL 33913',
            'city'     => 'Fort Myers',
            'province' => 'FL',
            'postal'   => '33913',
            'lat'      => 26.5362,
            'lng'      => -81.7551,
        ],
        'dropoff'      => [
            'name'     => 'Marco Island Marriott',
            'street1'  => '400 S Collier Blvd, Marco Island, FL 34145',
            'city'     => 'Marco Island',
            'province' => 'FL',
            'postal'   => '34145',
            'lat'      => 25.9135,
            'lng'      => -81.7281,
        ],
        'driver'       => $tom ?? $fallbackDriver,
        'started'      => true,
        'dispatched'   => true,
        'notes'        => "Private travel en route to pickup. [Tags: vip, private-jet]",
    ],

    // --- 6. DISPATCHED RUNS ---
    [
        'label'        => 'Dispatched - 08:00 PM',
        'status'       => 'dispatched',
        'scheduled_ny' => '2026-09-09 20:00:00',
        'customer'     => [
            'name'  => 'Victor Frankenstein',
            'phone' => '+1 (212) 555-0121',
            'email' => 'victor.f@prometheus.test',
        ],
        'pickup'       => [
            'name'     => 'Naples Beach Hotel',
            'street1'  => '851 Gulf Shore Blvd N, Naples, FL 34102',
            'city'     => 'Naples',
            'province' => 'FL',
            'postal'   => '34102',
            'lat'      => 26.1550,
            'lng'      => -81.8080,
        ],
        'dropoff'      => [
            'name'     => 'Fort Myers Downtown',
            'street1'  => '2200 First St, Fort Myers, FL 33901',
            'city'     => 'Fort Myers',
            'province' => 'FL',
            'postal'   => '33901',
            'lat'      => 26.6406,
            'lng'      => -81.8723,
        ],
        'driver'       => $beau ?? $fallbackDriver,
        'started'      => false,
        'dispatched'   => true,
        'notes'        => "Corporate event return trip. [Tags: corporate]",
    ],
    [
        'label'        => 'Dispatched - 08:30 PM',
        'status'       => 'dispatched',
        'scheduled_ny' => '2026-09-09 20:30:00',
        'customer'     => [
            'name'  => 'Wendy Darling',
            'phone' => '+1 (239) 555-0122',
            'email' => 'wendy.d@neverland.test',
        ],
        'pickup'       => [
            'name'     => 'Marco Island Residence',
            'street1'  => '240 S Collier Blvd, Marco Island, FL 34145',
            'city'     => 'Marco Island',
            'province' => 'FL',
            'postal'   => '34145',
            'lat'      => 25.9220,
            'lng'      => -81.7290,
        ],
        'dropoff'      => [
            'name'     => 'Naples Grande Beach Resort',
            'street1'  => '475 Seagate Dr, Naples, FL 34103',
            'city'     => 'Naples',
            'province' => 'FL',
            'postal'   => '34103',
            'lat'      => 26.1983,
            'lng'      => -81.8055,
        ],
        'driver'       => $noel ?? $fallbackDriver,
        'started'      => false,
        'dispatched'   => true,
        'notes'        => "Assigned, awaiting chauffeur login. [Tags: hotel-hop]",
    ],
    [
        'label'        => 'Dispatched - 09:15 PM',
        'status'       => 'dispatched',
        'scheduled_ny' => '2026-09-09 21:15:00',
        'customer'     => [
            'name'  => 'Xavier Charles',
            'phone' => '+1 (305) 555-0123',
            'email' => 'charles.xavier@mutant.test',
        ],
        'pickup'       => [
            'name'     => 'Ritz-Carlton Golf Resort',
            'street1'  => '2600 Tiburon Dr, Naples, FL 34109',
            'city'     => 'Naples',
            'province' => 'FL',
            'postal'   => '34109',
            'lat'      => 26.2425,
            'lng'      => -81.7681,
        ],
        'dropoff'      => [
            'name'     => 'RSW Airport - Terminal Access Rd',
            'street1'  => 'Terminal Access Rd, Fort Myers, FL 33913',
            'city'     => 'Fort Myers',
            'province' => 'FL',
            'postal'   => '33913',
            'lat'      => 26.5362,
            'lng'      => -81.7551,
        ],
        'driver'       => $tom ?? $fallbackDriver,
        'started'      => false,
        'dispatched'   => true,
        'notes'        => "Corporate travel, dispatched to driver Brennan. [Tags: corporate]",
    ],
    [
        'label'        => 'Dispatched - 09:45 PM',
        'status'       => 'dispatched',
        'scheduled_ny' => '2026-09-09 21:45:00',
        'customer'     => [
            'name'  => 'Yvonne Strahovski',
            'phone' => '+1 (212) 555-0124',
            'email' => 'yvonne.s@cia-agents.test',
        ],
        'pickup'       => [
            'name'     => 'Mercato, Naples',
            'street1'  => '9115 Strada Pl, Naples, FL 34108',
            'city'     => 'Naples',
            'province' => 'FL',
            'postal'   => '34108',
            'lat'      => 26.2520,
            'lng'      => -81.7870,
        ],
        'dropoff'      => [
            'name'     => 'Fifth Avenue South',
            'street1'  => '701 5th Ave S, Naples, FL 34102',
            'city'     => 'Naples',
            'province' => 'FL',
            'postal'   => '34102',
            'lat'      => 26.1420,
            'lng'      => -81.7948,
        ],
        'driver'       => $beau ?? $fallbackDriver,
        'started'      => false,
        'dispatched'   => true,
        'notes'        => "Scheduled dinner run. [Tags: hotel-hop]",
    ],

    // --- 7. CREATED (UNASSIGNED) RUNS ---
    [
        'label'        => 'Created - 10:00 AM',
        'status'       => 'created',
        'scheduled_ny' => '2026-09-09 10:00:00',
        'customer'     => [
            'name'  => 'Zachary Levi',
            'phone' => '+1 (239) 555-0125',
            'email' => 'zach.levi@nerdherd.test',
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
            'name'     => 'Brooklyn Heights',
            'street1'  => '150 Montague St, Brooklyn, NY 11201',
            'city'     => 'Brooklyn',
            'province' => 'NY',
            'postal'   => '11201',
            'lat'      => 40.6950,
            'lng'      => -73.9950,
        ],
        'driver'       => null,
        'started'      => false,
        'dispatched'   => false,
        'notes'        => "New web reservation, awaiting dispatcher review. [Tags: web-booking]",
    ],
    [
        'label'        => 'Created - 12:30 PM',
        'status'       => 'created',
        'scheduled_ny' => '2026-09-09 12:30:00',
        'customer'     => [
            'name'  => 'Alice Johnson',
            'phone' => '+1 (305) 555-0126',
            'email' => 'alice.j@wonderland.test',
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
            'name'     => 'Newark Liberty Airport - Terminal C',
            'street1'  => '3 Brewster Rd, Newark, NJ 07114',
            'city'     => 'Newark',
            'province' => 'NJ',
            'postal'   => '07114',
            'lat'      => 40.6895,
            'lng'      => -74.1745,
        ],
        'driver'       => null,
        'started'      => false,
        'dispatched'   => false,
        'notes'        => "High priority corporate request, unassigned. [Tags: corporate]",
    ],
    [
        'label'        => 'Created - 10:30 PM',
        'status'       => 'created',
        'scheduled_ny' => '2026-09-09 22:30:00',
        'customer'     => [
            'name'  => 'Bob Builder',
            'phone' => '+1 (212) 555-0127',
            'email' => 'bob.builder@construct.test',
        ],
        'pickup'       => [
            'name'     => 'Naples Grande Beach Resort',
            'street1'  => '475 Seagate Dr, Naples, FL 34103',
            'city'     => 'Naples',
            'province' => 'FL',
            'postal'   => '34103',
            'lat'      => 26.1983,
            'lng'      => -81.8055,
        ],
        'dropoff'      => [
            'name'     => 'Fifth Avenue South',
            'street1'  => '701 5th Ave S, Naples, FL 34102',
            'city'     => 'Naples',
            'province' => 'FL',
            'postal'   => '34102',
            'lat'      => 26.1420,
            'lng'      => -81.7948,
        ],
        'driver'       => null,
        'started'      => false,
        'dispatched'   => false,
        'notes'        => "Late night unassigned transfer request. [Tags: hotel-hop]",
    ],
    [
        'label'        => 'Created - 11:30 PM',
        'status'       => 'created',
        'scheduled_ny' => '2026-09-09 23:30:00',
        'customer'     => [
            'name'  => 'Claire Redfield',
            'phone' => '+1 (239) 555-0128',
            'email' => 'claire.r@umbrella.test',
        ],
        'pickup'       => [
            'name'     => 'RSW Airport - Terminal Access Rd',
            'street1'  => 'Terminal Access Rd, Fort Myers, FL 33913',
            'city'     => 'Fort Myers',
            'province' => 'FL',
            'postal'   => '33913',
            'lat'      => 26.5362,
            'lng'      => -81.7551,
        ],
        'dropoff'      => [
            'name'     => 'The Ritz-Carlton, Naples',
            'street1'  => '280 Vanderbilt Beach Rd, Naples, FL 34108',
            'city'     => 'Naples',
            'province' => 'FL',
            'postal'   => '34108',
            'lat'      => 26.2568,
            'lng'      => -81.8211,
        ],
        'driver'       => null,
        'started'      => false,
        'dispatched'   => false,
        'notes'        => "Red-eye arrival, flight AA1024. Meet & Greet requested. [Tags: airport-transfer]",
    ],
];

echo "5. Creating 28 Comprehensive Today Reservations:\n";

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
        'created_at'           => now()->subMinutes(60 - $idx),
        'updated_at'           => now(),
    ]);

    \DB::table('orders')->where('uuid', $order->uuid)->update(['status' => $r['status']]);

    $driverName = $r['driver'] ? $r['driver']->name : 'Unassigned';
    echo "   ✓ {$r['label']}: Order {$order->public_id} | Status: {$r['status']} | Driver: {$driverName}\n";
}

echo "\n✓ All 28 reservations created successfully!\n";
