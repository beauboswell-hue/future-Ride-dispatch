<?php

namespace App\Console\Commands;

use App\Services\WebBookingNoteFormatter;
use Fleetbase\FleetOps\Models\Contact;
use Fleetbase\FleetOps\Models\Driver;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\Payload;
use Fleetbase\FleetOps\Models\Place;
use Fleetbase\FleetOps\Models\Vehicle;
use Fleetbase\LaravelMysqlSpatial\Types\Point;
use Fleetbase\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TestWebBookingNotes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fleetbase:test-web-booking 
                            {--matching-locations : Test with identical pickup and dropoff to verify warning flag}
                            {--with-driver : Test with assigned driver and vehicle}
                            {--without-schedule : Test with unscheduled order}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate an incoming web booking and verify automated order notes formatting';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('====================================================');
        $this->info('  Simulating Incoming Fleetbase Web Booking Request ');
        $this->info('====================================================');

        // 1. Resolve Company
        $company = Company::first();
        if (!$company) {
            $this->error('No company found in database to attach order to.');
            return 1;
        }
        $this->line("• Using Company: {$company->name} ({$company->public_id})");

        // 2. Setup Customer
        $customer = Contact::where('company_uuid', $company->uuid)
            ->where('type', 'customer')
            ->first();

        if (!$customer) {
            $customer = Contact::create([
                'company_uuid' => $company->uuid,
                'name'         => 'Jane Doe',
                'phone'        => '+1 212-555-0199',
                'email'        => 'jane.doe@example.com',
                'type'         => 'customer',
                'meta'         => [
                    'username' => 'janedoe_web',
                    'timezone' => 'America/New_York',
                ],
            ]);
        }
        $this->line("• Customer: {$customer->name} ({$customer->email})");

        // 3. Setup Locations
        $pickup = Place::create([
            'company_uuid' => $company->uuid,
            'name'         => 'The Plaza Hotel',
            'street1'      => '768 5th Ave',
            'city'         => 'New York',
            'province'     => 'NY',
            'postal_code'  => '10019',
            'country'      => 'US',
            'location'     => new Point(40.7645, -73.9744),
        ]);
        $this->line("• Pickup: {$pickup->address} [40.764500, -73.974400]");

        $isMatching = $this->option('matching-locations');
        if ($isMatching) {
            $dropoff = $pickup;
            $this->warn('• Dropoff: [MATCHING PICKUP LOCATION FOR TEST]');
        } else {
            $dropoff = Place::create([
                'company_uuid' => $company->uuid,
                'name'         => 'John F. Kennedy International Airport (JFK)',
                'street1'      => 'Queens, NY 11430',
                'city'         => 'New York',
                'province'     => 'NY',
                'postal_code'  => '11430',
                'country'      => 'US',
                'location'     => new Point(40.6413, -73.7781),
            ]);
            $this->line("• Dropoff: {$dropoff->address} [40.641300, -73.778100]");
        }

        // 4. Create Payload
        $payload = Payload::create([
            'company_uuid' => $company->uuid,
            'pickup_uuid'  => $pickup->uuid,
            'dropoff_uuid' => $dropoff->uuid,
        ]);

        // 5. Driver & Vehicle assignment (optional)
        $driverId = null;
        $vehicleId = null;
        if ($this->option('with-driver')) {
            $driver = Driver::where('company_uuid', $company->uuid)->first();
            if (!$driver) {
                $driver = Driver::create([
                    'company_uuid' => $company->uuid,
                    'name'         => 'Michael Schumacher',
                    'phone'        => '+1 555-987-6543',
                    'type'         => 'driver',
                    'status'       => 'active',
                ]);
            }
            $driverId = $driver->uuid;

            $vehicle = Vehicle::where('company_uuid', $company->uuid)->first();
            if (!$vehicle) {
                $vehicle = Vehicle::create([
                    'company_uuid' => $company->uuid,
                    'name'         => 'Cadillac Escalade ESV',
                    'make'         => 'Cadillac',
                    'model'        => 'Escalade ESV',
                    'year'         => 2024,
                    'plate_number' => 'LIMO-01',
                    'status'       => 'active',
                ]);
            }
            $vehicleId = $vehicle->uuid;
            $this->line("• Driver: {$driver->name}, Vehicle: {$vehicle->name}");
        } else {
            $this->line('• Driver & Vehicle: None Assigned (Testing null-safety)');
        }

        // 6. Timing & Dates
        $scheduledAt = $this->option('without-schedule')
            ? null
            : Carbon::now()->addDays(2)->setHour(14)->setMinute(30)->setSecond(0);

        if ($scheduledAt) {
            $this->line("• Scheduled Time: {$scheduledAt->toIso8601String()}");
        } else {
            $this->line('• Scheduled Time: None (Testing null-safety)');
        }

        // 7. Assemble Order Creation Data (as submitted by web form)
        $orderData = [
            'company_uuid'          => $company->uuid,
            'customer_uuid'         => $customer->uuid,
            'customer_type'         => 'Fleetbase\\FleetOps\\Models\\Contact',
            'payload_uuid'          => $payload->uuid,
            'driver_assigned_uuid'  => $driverId,
            'vehicle_assigned_uuid' => $vehicleId,
            'scheduled_at'          => $scheduledAt,
            'status'                => 'created',
            'type'                  => 'transport',
            'notes'                 => 'Web Booking via Homepage Form',
            'meta'                  => [
                'source'           => 'Website Form',
                'sms_consent'      => 1,
                'tags'             => ['web-booking', 'VIP'],
                'pickup_timezone'  => 'America/New_York',
            ],
        ];

        $this->info("\n--- Creating Order (Triggering Order::created event) ---");
        $order = Order::create($orderData);

        $this->info("Order successfully created! Public ID: {$order->public_id}");

        // 8. Refresh order from DB to verify persisted notes
        $order->refresh();

        $this->info("\n====================================================");
        $this->info("  RENDERED ORDER NOTES (Stored in DB order.notes)   ");
        $this->info("====================================================");
        $this->line($order->notes);
        $this->info("====================================================\n");

        // 9. Verify Required Elements in Notes
        $checks = [
            'Header: Future Limo Dispatch' => str_contains($order->notes, 'Future Limo Dispatch'),
            'Scheduled Time'              => str_contains($order->notes, '🕒 Scheduled:'),
            'Passenger Name'              => str_contains($order->notes, '👤 Passenger:'),
            'Passenger Phone'             => str_contains($order->notes, '📞 Phone:'),
            'Passenger Email'             => str_contains($order->notes, '✉️ Email:'),
            'Locations: Pickup'           => str_contains($order->notes, '📍 Pickup:'),
            'Locations: Dropoff'          => str_contains($order->notes, '🏁 Dropoff:'),
            'Status & Assignment'         => str_contains($order->notes, '🚗 Status:'),
            'Order ID'                    => str_contains($order->notes, '🆔 Order ID:'),
            'Internal ID'                 => str_contains($order->notes, '🔢 Internal ID:'),
            'Tracking Number'             => str_contains($order->notes, '📦 Tracking #:'),
        ];

        if ($isMatching) {
            $checks['Warning Flag on Dropoff'] = str_contains($order->notes, '⚠️') || str_contains($order->notes, 'WARNING');
        }

        $allPassed = true;
        foreach ($checks as $name => $passed) {
            if ($passed) {
                $this->line("  ✔ {$name}: PASSED");
            } else {
                $this->error("  ✖ {$name}: FAILED");
                $allPassed = false;
            }
        }

        if ($allPassed) {
            $this->info("\n🎉 All verifications passed successfully!");
            return 0;
        } else {
            $this->error("\n❌ Some verifications failed.");
            return 1;
        }
    }
}
