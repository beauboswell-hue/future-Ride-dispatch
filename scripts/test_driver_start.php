<?php

use Fleetbase\FleetOps\Models\OrderConfig;
use Fleetbase\FleetOps\Models\Order;
use Fleetbase\Models\Company;

require __DIR__ . '/../api/vendor/autoload.php';
$app = require_once __DIR__ . '/../api/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing Driver Start Transition...\n";

$company = Company::where('name', 'Future Limo')->first() ?? Company::first();
$orderConfig = OrderConfig::where([
    'company_uuid' => $company->uuid,
    'key'          => 'transport',
])->first();

$startedActivity = $orderConfig->getStartedActivity();
echo "Started Activity Code: " . $startedActivity->code . "\n";
echo "Started Activity Status: " . $startedActivity->status . "\n";

if ($startedActivity->code !== 'enroute_pickup') {
    echo "❌ ERROR: Expected getStartedActivity() to return 'enroute_pickup', got: {$startedActivity->code}\n";
    exit(1);
}

// Create a mock order to test start transition
$order = new Order();
$order->company_uuid = $company->uuid;
$order->order_config_uuid = $orderConfig->uuid;
$order->status = 'dispatched';
$order->dispatched = true;
$order->started = false;
$order->save();

// Set order context
$orderConfig->setOrderContext($order);

// Simulate Driver Start logic
$order->started = true;
$order->started_at = now();
$order->updateActivity($startedActivity);

$order->refresh();
echo "Order Status after Driver Start: {$order->status}\n";
echo "Order Started flag: " . ($order->started ? 'TRUE' : 'FALSE') . "\n";

$allPassed = true;
if ($order->status === 'enroute_pickup' && $order->started) {
    echo "✓ Driver start successfully transitioned order from [dispatched] -> [enroute_pickup] with started=true!\n";
} else {
    echo "❌ Transition failed! Status: {$order->status}, Started: {$order->started}\n";
    $allPassed = false;
}

// Test subsequent transitions:
// enroute_pickup -> on_location
$next = $orderConfig->nextFirstActivity();
echo "Next activity from enroute_pickup: " . ($next ? $next->code : 'null') . "\n";
if ($next && $next->code === 'on_location') {
    echo "✓ Next after enroute_pickup is [on_location]!\n";
    $order->updateActivity($next);
    $order->refresh();
    echo "Order Status after on_location: {$order->status}\n";
} else {
    $allPassed = false;
}

// on_location -> pob
$next = $orderConfig->nextFirstActivity();
echo "Next activity from on_location: " . ($next ? $next->code : 'null') . "\n";
if ($next && $next->code === 'pob') {
    echo "✓ Next after on_location is [pob]!\n";
    $order->updateActivity($next);
    $order->refresh();
    echo "Order Status after pob: {$order->status}\n";
} else {
    $allPassed = false;
}

// pob -> completed
$next = $orderConfig->nextFirstActivity();
echo "Next activity from pob: " . ($next ? $next->code : 'null') . "\n";
if ($next && $next->code === 'completed') {
    echo "✓ Next after pob is [completed]!\n";
    $order->updateActivity($next);
    $order->refresh();
    echo "Order Status after completed: {$order->status}\n";
} else {
    $allPassed = false;
}

// Clean up test order
$order->delete();
echo "✓ Test order cleaned up successfully.\n";

if ($allPassed) {
    echo "\n🎉 DRIVER START & COMPLETE PIPELINE TRANSITIONS VERIFIED!\n";
} else {
    exit(1);
}
