<?php

use Fleetbase\FleetOps\Models\OrderConfig;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\FleetOps\Models\Waypoint;
use Fleetbase\Models\Company;
use Fleetbase\Models\User;
use Illuminate\Http\Request;

require __DIR__ . '/../api/vendor/autoload.php';
$app = require_once __DIR__ . '/../api/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=================================================================\n";
echo "       VERIFYING LIMO ANYWHERE DISPATCH PIPELINE INTEGRATION     \n";
echo "=================================================================\n\n";

$company = Company::where('name', 'Future Limo')->first() ?? Company::first();
echo "1. Active Company: {$company->name} ({$company->uuid})\n";

// 1. Verify OrderConfig
$orderConfig = OrderConfig::where([
    'company_uuid' => $company->uuid,
    'key'          => 'transport',
])->first();

if (!$orderConfig) {
    echo "❌ ERROR: No transport OrderConfig found for company!\n";
    exit(1);
}

echo "2. Active Transport OrderConfig: {$orderConfig->name} ({$orderConfig->uuid})\n";
echo "   Description: {$orderConfig->description}\n\n";

$expectedStages = [
    0 => ['key' => 'created',        'status' => 'Created',             'color' => '#6B7280', 'next' => ['dispatched', 'canceled']],
    1 => ['key' => 'dispatched',     'status' => 'Dispatched',          'color' => '#2563EB', 'next' => ['enroute_pickup', 'created', 'canceled']],
    2 => ['key' => 'enroute_pickup', 'status' => 'En Route',             'color' => '#EAB308', 'next' => ['on_location', 'dispatched', 'canceled']],
    3 => ['key' => 'on_location',    'status' => 'On Location',         'color' => '#9333EA', 'next' => ['pob', 'enroute_pickup', 'canceled']],
    4 => ['key' => 'pob',            'status' => 'Passenger On Board',  'color' => '#F97316', 'next' => ['completed', 'on_location', 'canceled']],
    5 => ['key' => 'completed',      'status' => 'Completed',           'color' => '#16A34A', 'next' => []],
    6 => ['key' => 'canceled',       'status' => 'Canceled',            'color' => '#DC2626', 'next' => []],
];

echo "3. Verifying Flow Activities:\n";
$activities = $orderConfig->activities();
$allPassed = true;

foreach ($expectedStages as $seq => $expected) {
    $activity = $activities->firstWhere('code', $expected['key']);
    if (!$activity) {
        echo "   ❌ Missing stage {$expected['key']}\n";
        $allPassed = false;
        continue;
    }

    $colorMatch = strtoupper($activity->color) === strtoupper($expected['color']);
    $statusMatch = $activity->status === $expected['status'];
    $seqMatch = (int) $activity->sequence === $seq;

    if ($colorMatch && $statusMatch && $seqMatch) {
        echo "   ✓ Stage {$seq}: {$expected['key']} | Title: '{$activity->status}' | Color: {$activity->color} | Next: [" . implode(', ', (array)$activity->activities) . "]\n";
    } else {
        echo "   ❌ Stage {$seq}: mismatch! (color: {$activity->color} vs {$expected['color']}, status: '{$activity->status}' vs '{$expected['status']}', seq: {$activity->sequence} vs {$seq})\n";
        $allPassed = false;
    }
}

// 4. Test Controller Statuses Endpoint
echo "\n4. Testing OrderController@statuses Endpoint:\n";
$user = User::where('company_uuid', $company->uuid)->first();
$req = new Request(['order_config_uuid' => $orderConfig->uuid]);
$req->setUserResolver(fn() => $user);
$controller = app(Fleetbase\FleetOps\Http\Controllers\Internal\v1\OrderController::class);
$statusesResponse = $controller->statuses($req);
$statusesData = (array) $statusesResponse->getData();

echo "   Statuses returned: " . json_encode($statusesData) . "\n";
$expectedCodes = array_column($expectedStages, 'key');

if ($statusesData === $expectedCodes) {
    echo "   ✓ Endpoint returned EXACT 7 stages in perfect pipeline order!\n";
} else {
    echo "   ❌ Endpoint returned different order or elements!\n";
    $allPassed = false;
}

// 5. Test Transition Pipeline (nextActivity)
echo "\n5. Testing 1-to-1 Dispatch Progression via nextActivity():\n";
$dummyOrder = new Order(['company_uuid' => $company->uuid, 'order_config_uuid' => $orderConfig->uuid]);
$orderConfig->setOrderContext($dummyOrder);

$transitionTests = [
    'created'        => 'dispatched',
    'dispatched'     => 'enroute_pickup',
    'enroute_pickup' => 'on_location',
    'on_location'    => 'pob',
    'pob'            => 'completed',
];

foreach ($transitionTests as $current => $expectedNext) {
    $dummyOrder->status = $current;
    $nextFirst = $orderConfig->nextFirstActivity();
    $nextAll = $orderConfig->nextActivity()->pluck('code')->toArray();

    if ($nextFirst && $nextFirst->code === $expectedNext) {
        echo "   ✓ From [{$current}] -> nextFirst is [{$nextFirst->code}], all options: [" . implode(', ', $nextAll) . "]\n";
    } else {
        $actual = $nextFirst ? $nextFirst->code : 'null';
        echo "   ❌ From [{$current}] -> expected [{$expectedNext}], got [{$actual}]\n";
        $allPassed = false;
    }
}

// 6. Verify existing order data counts
echo "\n6. Verifying Existing Order Data Integrity:\n";
$totalOrders = Order::query()->count();
echo "   Total Orders in DB: {$totalOrders}\n";
$statusCounts = Order::query()->select('status', \DB::raw('count(*) as count'))->groupBy('status')->pluck('count', 'status')->toArray();
foreach ($statusCounts as $st => $cnt) {
    echo "   - Status [{$st}]: {$cnt} orders\n";
}

if ($allPassed) {
    echo "\n=================================================================\n";
    echo "  🎉 ALL TESTS PASSED! LIMO ANYWHERE DISPATCH PIPELINE IS ACTIVE \n";
    echo "=================================================================\n";
} else {
    echo "\n❌ Some checks failed.\n";
    exit(1);
}
