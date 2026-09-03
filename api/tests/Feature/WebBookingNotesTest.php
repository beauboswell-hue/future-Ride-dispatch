<?php

namespace Tests\Feature;

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
use Tests\TestCase;

class WebBookingNotesTest extends TestCase
{
    /**
     * Test web booking notes formatting on Order creation.
     */
    public function test_web_booking_notes_are_automatically_formatted_on_order_created(): void
    {
        $company = Company::first();
        $this->assertNotNull($company, 'Company required for testing');

        $customer = Contact::firstOrCreate(
            ['company_uuid' => $company->uuid, 'email' => 'test.booking@example.com'],
            [
                'name'  => 'Taylor Swift',
                'phone' => '+12125559999',
                'type'  => 'customer',
                'meta'  => ['username' => 'tswift', 'timezone' => 'America/New_York'],
            ]
        );

        $pickup = Place::create([
            'company_uuid' => $company->uuid,
            'name'         => 'Madison Square Garden',
            'street1'      => '4 Pennsylvania Plaza',
            'city'         => 'New York',
            'province'     => 'NY',
            'postal_code'  => '10001',
            'country'      => 'US',
            'location'     => new Point(40.7505, -73.9934),
        ]);

        $dropoff = Place::create([
            'company_uuid' => $company->uuid,
            'name'         => 'Barclays Center',
            'street1'      => '620 Atlantic Ave',
            'city'         => 'Brooklyn',
            'province'     => 'NY',
            'postal_code'  => '11217',
            'country'      => 'US',
            'location'     => new Point(40.6826, -73.9754),
        ]);

        $payload = Payload::create([
            'company_uuid' => $company->uuid,
            'pickup_uuid'  => $pickup->uuid,
            'dropoff_uuid' => $dropoff->uuid,
        ]);

        $order = Order::create([
            'company_uuid'  => $company->uuid,
            'customer_uuid' => $customer->uuid,
            'customer_type' => 'Fleetbase\\FleetOps\\Models\\Contact',
            'payload_uuid'  => $payload->uuid,
            'scheduled_at'  => Carbon::now()->addDay(),
            'status'        => 'created',
            'type'          => 'transport',
            'notes'         => 'Web Booking via Homepage Form',
            'meta'          => [
                'source'          => 'Website Form',
                'tags'            => ['vip'],
                'pickup_timezone' => 'America/New_York',
            ],
        ]);

        $order->refresh();

        $this->assertStringContainsString('### 👤 Contact Information', $order->notes);
        $this->assertStringContainsString('• Name: Taylor Swift', $order->notes);
        $this->assertStringContainsString('• Email: test.booking@example.com', $order->notes);
        $this->assertStringContainsString("### 📅 Ride Information (Order {$order->public_id})", $order->notes);
        $this->assertStringContainsString('- UTC:', $order->notes);
        $this->assertStringContainsString('- Local Pickup Time:', $order->notes);
        $this->assertStringContainsString('- Eastern Time:', $order->notes);
        $this->assertStringContainsString('- Pickup: MADISON SQUARE GARDEN', $order->notes);
        $this->assertStringContainsString('- Dropoff: BARCLAYS CENTER', $order->notes);
        $this->assertStringContainsString('• Status: created', $order->notes);
        $this->assertStringContainsString('• Assigned Driver: None Assigned', $order->notes);
        $this->assertStringContainsString('• Assigned Vehicle: None Assigned', $order->notes);
        $this->assertStringContainsString('• Booking Notes / Source: Website Form - Web Booking via Homepage Form [Tags: vip]', $order->notes);
    }

    /**
     * Test matching pickup and dropoff warning flag.
     */
    public function test_matching_locations_generate_warning_flag(): void
    {
        $company = Company::first();
        $pickup = Place::create([
            'company_uuid' => $company->uuid,
            'name'         => 'Same Spot',
            'street1'      => '123 Same St',
            'country'      => 'US',
            'location'     => new Point(40.7128, -74.0060),
        ]);

        $payload = Payload::create([
            'company_uuid' => $company->uuid,
            'pickup_uuid'  => $pickup->uuid,
            'dropoff_uuid' => $pickup->uuid,
        ]);

        $order = Order::create([
            'company_uuid' => $company->uuid,
            'payload_uuid' => $payload->uuid,
            'notes'        => 'Web Booking via Homepage Form',
            'meta'         => ['source' => 'Website Form'],
        ]);

        $order->refresh();

        $this->assertStringContainsString('⚠️', $order->notes);
        $this->assertStringContainsString('WARNING', $order->notes);
    }
}
