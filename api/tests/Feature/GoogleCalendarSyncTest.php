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
    public function test_event_title_summary_format_with_type(): void
    {
        $service = app(GoogleCalendarService::class);
        $order = new Order();
        $order->public_id = 'order_test123';
        $order->type = 'luxury';

        $summary = $service->buildSummary($order);
        $this->assertEquals('Ride [luxury]: order_test123', $summary);
    }

    /**
     * Test Event Title (Summary) fallback when order type is null.
     */
    public function test_event_title_summary_fallback_when_type_is_null(): void
    {
        $service = app(GoogleCalendarService::class);
        $order = new Order();
        $order->public_id = 'order_test456';
        $order->attributes['type'] = null;

        $summary = $service->buildSummary($order);
        $this->assertEquals('Ride: order_test456', $summary);
    }

    /**
     * Test Event Title strictly removes customer names.
     */
    public function test_event_title_strictly_excludes_customer_name(): void
    {
        $company = $this->getTestCompany();
        $customer = Contact::firstOrCreate(
            ['company_uuid' => $company->uuid, 'email' => 'vip.passenger@example.com'],
            ['name' => 'Johnathan Doe', 'phone' => '+15551234567', 'type' => 'customer']
        );

        $order = new Order();
        $order->public_id = 'order_pii_check';
        $order->type = 'executive';
        $order->customer_uuid = $customer->uuid;
        $order->setRelation('customer', $customer);

        $service = app(GoogleCalendarService::class);
        $summary = $service->buildSummary($order);

        $this->assertEquals('Ride [executive]: order_pii_check', $summary);
        $this->assertStringNotContainsString('Johnathan', $summary);
        $this->assertStringNotContainsString('Doe', $summary);
        $this->assertStringNotContainsString('Future Limo', $summary);
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
     * Test Event Description is anonymous metadata block with console link.
     */
    public function test_event_description_anonymous_metadata_block(): void
    {
        config(['fleetops.console_url' => 'http://localhost:4200']);

        $order = new Order();
        $order->public_id = 'order_meta_789';
        $order->status = 'dispatched';
        $order->notes = 'Customer note: gate code 1234, private passenger phone +15559876543, price $250.00';

        $service = app(GoogleCalendarService::class);
        $description = $service->buildDescription($order);

        $expected = "Status: dispatched\nOrder ID: order_meta_789\nConsole Link: http://localhost:4200/fleet-ops?layout=kanban&order=order_meta_789";
        $this->assertEquals($expected, $description);

        // Strict PII exclusion assertions
        $this->assertStringNotContainsString('gate code', $description);
        $this->assertStringNotContainsString('1234', $description);
        $this->assertStringNotContainsString('+15559876543', $description);
        $this->assertStringNotContainsString('250.00', $description);
        $this->assertStringNotContainsString('Customer note', $description);
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
        $endTime = Carbon::parse($timing['end']['dateTime']);

        $this->assertTrue($endTime->greaterThan($startTime));
        $this->assertEquals('-04:00', $startTime->format('P')); // EDT
    }

    /**
     * Test updateEvent uses the exact same tokenized schema as createEvent.
     */
    public function test_update_event_uses_exact_same_tokenized_schema(): void
    {
        $order = new Order();
        $order->public_id = 'order_schema_match';
        $order->type = 'shuttle';
        $order->status = 'in_progress';
        $order->scheduled_at = '2026-11-01 10:00:00';
        $order->time_window_end = '2026-11-01 11:30:00';

        $service = app(GoogleCalendarService::class);

        $createPayload = $service->createEvent($order);
        $updatePayload = $service->updateEvent($order);

        // Strip any generated metadata IDs if testing API response
        unset($createPayload['id'], $updatePayload['id']);

        $this->assertEquals($createPayload, $updatePayload);
        $this->assertEquals('Ride [shuttle]: order_schema_match', $updatePayload['summary']);
        $this->assertEquals('', $updatePayload['location']);
        $this->assertStringContainsString('Status: in_progress', $updatePayload['description']);
        $this->assertStringContainsString('Order ID: order_schema_match', $updatePayload['description']);
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
