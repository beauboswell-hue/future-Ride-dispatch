<?php

namespace Tests\Feature;

use App\Services\GoogleCalendarService;
use Fleetbase\FleetOps\Models\Contact;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\Payload;
use Fleetbase\FleetOps\Models\Place;
use Fleetbase\LaravelMysqlSpatial\Types\Point;
use Fleetbase\Models\Company;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GoogleCalendarSyncTest extends TestCase
{
    /**
     * Helper to get or create a test company.
     */
    protected function getTestCompany(): Company
    {
        $company = Company::first();
        $this->assertNotNull($company, 'A company must exist in the database for testing.');
        return $company;
    }

    /**
     * Test Event Title (Summary) formatting with order type.
     */
    /**
     * Test Event Title (Summary) formatting.
     */
    public function test_event_title_summary_format(): void
    {
        $service = app(GoogleCalendarService::class);
        $order = new Order();
        $order->public_id = 'order_test123';
        $order->scheduled_at = '2026-12-25 15:00:00';
        $order->meta = [
            'passenger_name' => 'John Doe',
            'vehicle_type'   => 'luxury',
        ];

        $summary = $service->buildSummary($order);
        $this->assertStringStartsWith('Ride: J.D. - ', $summary);
    }

    /**
     * Test Event Title (Summary) fallback when passenger name is null.
     */
    public function test_event_title_summary_fallback_when_name_is_null(): void
    {
        $service = app(GoogleCalendarService::class);
        $order = new Order();
        $order->public_id = 'order_test456';
        $order->meta = [];

        $summary = $service->buildSummary($order);
        $this->assertStringStartsWith('Ride: N.A. - ', $summary);
    }

    /**
     * Test Event Title contains passenger initials instead of full name.
     */
    public function test_event_title_contains_passenger_initials(): void
    {
        $company = $this->getTestCompany();
        $customer = Contact::firstOrCreate(
            ['company_uuid' => $company->uuid, 'email' => 'vip.passenger@example.com'],
            ['name' => 'Johnathan Doe', 'phone' => '+15551234567', 'type' => 'customer']
        );

        $order = new Order();
        $order->public_id = 'order_pii_check';
        $order->customer_uuid = $customer->uuid;
        $order->setRelation('customer', $customer);

        $service = app(GoogleCalendarService::class);
        $summary = $service->buildSummary($order);

        $this->assertStringStartsWith('Ride: J.D. - ', $summary);
        $this->assertStringNotContainsString('Johnathan', $summary);
        $this->assertStringNotContainsString('Doe', $summary);
    }

    /**
     * Test Event Location is empty string and contains no PII or coordinates.
     */
    public function test_event_location_is_empty_string(): void
    {
        $company = $this->getTestCompany();
        $place = new Place([
            'company_uuid' => $company->uuid,
            'name'         => '123 Confidential St',
            'street1'      => '123 Confidential St',
            'city'         => 'New York',
            'province'     => 'NY',
            'postal_code'  => '10001',
            'location'     => new Point(40.7128, -74.0060),
        ]);

        $order = new Order();
        $order->public_id = 'order_loc_test';
        $order->setRelation('payload', new Payload(['pickup' => $place]));

        $service = app(GoogleCalendarService::class);
        $location = $service->buildLocation($order);

        $this->assertSame('', $location);
        $this->assertStringNotContainsString('123 Confidential St', $location);
        $this->assertStringNotContainsString('New York', $location);
        $this->assertStringNotContainsString('40.7128', $location);
    }

    /**
     * Test Event Description matches untokenized layout with full details.
     */
    public function test_event_description_untokenized_layout(): void
    {
        $order = new Order();
        $order->public_id = 'order_meta_789';
        $order->meta = [
            'passenger_name' => 'John Doe',
            'passenger_phone' => '+15551234567',
            'passenger_email' => 'vip.passenger@example.com',
            'vehicle_type' => 'sedan',
            'child_seats' => '2 Booster Seats',
        ];

        $service = app(GoogleCalendarService::class);
        $description = $service->buildDescription($order);

        $this->assertStringContainsString('Customer Initials: J.D.', $description);
        $this->assertStringContainsString('Phone: +15551234567', $description);
        $this->assertStringContainsString('Email: vip.passenger@example.com', $description);
        $this->assertStringContainsString('Vehicle: sedan', $description);
        $this->assertStringContainsString('Child Seats: 2 Booster Seats', $description);
    }

    /**
     * Test Timing preserves scheduled start and end in America/New_York timezone.
     */
    public function test_timing_preserves_scheduled_times_in_eastern_timezone(): void
    {
        $order = new Order();
        $order->scheduled_at = '2026-10-15 14:00:00';
        $order->time_window_end = '2026-10-15 16:30:00';

        $service = app(GoogleCalendarService::class);
        $timing = $service->buildTiming($order);

        $this->assertArrayHasKey('start', $timing);
        $this->assertArrayHasKey('end', $timing);

        $this->assertEquals('America/New_York', $timing['start']['timeZone']);
        $this->assertEquals('America/New_York', $timing['end']['timeZone']);

        $startTime = Carbon::parse($timing['start']['dateTime']);
        $this->assertEquals('2026-10-15 14:00:00', $startTime->format('Y-m-d H:i:s'));
    }

    /**
     * Test updateEvent uses the exact same schema as createEvent.
     */
    public function test_update_event_uses_exact_same_schema(): void
    {
        $order = new Order();
        $order->public_id = 'order_schema_match';
        $order->scheduled_at = '2026-11-01 10:00:00';
        $order->meta = [
            'passenger_name' => 'Alice Smith',
            'vehicle_type' => 'shuttle',
        ];

        $service = app(GoogleCalendarService::class);

        $createPayload = $service->createEvent($order);
        $updatePayload = $service->updateEvent($order);

        // Strip any generated metadata IDs if testing API response
        unset($createPayload['id'], $updatePayload['id']);

        $this->assertEquals($createPayload, $updatePayload);
        $this->assertStringStartsWith('Ride: A.S. - ', $updatePayload['summary']);
        $this->assertEquals('', $updatePayload['location']);
        $this->assertStringContainsString('Customer Initials: A.S.', $updatePayload['description']);
    }

    /**
     * Test order creation and update triggers Google Calendar sync without breaking workflows.
     */
    public function test_order_creation_and_update_lifecycle(): void
    {
        $company = $this->getTestCompany();

        // Create order
        $order = Order::create([
            'company_uuid' => $company->uuid,
            'type'         => 'hourly',
            'status'       => 'created',
            'scheduled_at' => now()->addDay(),
        ]);

        $this->assertNotNull($order->id);
        $this->assertNotNull($order->public_id);

        // Update order status (rescheduled/modified)
        $order->status = 'dispatched';
        $order->scheduled_at = now()->addDays(2);
        $order->save();

        $this->assertEquals('dispatched', $order->status);
    }
}
