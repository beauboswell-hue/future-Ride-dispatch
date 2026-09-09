import subprocess

# 1. Fetch OrderConfig.php from container
res = subprocess.run(["docker", "exec", "fleetbase-application-1", "cat", "/fleetbase/api/vendor/fleetbase/fleetops-api/server/src/Models/OrderConfig.php"], capture_output=True, text=True)
content = res.stdout

old_get_started = """    public function getStartedActivity()
    {
        $startedActivity = $this->activities()->firstWhere('code', 'started');
        if ($startedActivity) {
            return $startedActivity;
        }

        return new Activity([
            'key'      => 'order_started',
            'code'     => 'started',
            'status'   => 'Order started',
            'details'  => 'Order has started',
            'complete' => false,
        ], $this->flow);
    }"""

new_get_started = """    public function getStartedActivity()
    {
        $enrouteActivity = $this->activities()->firstWhere('code', 'enroute_pickup');
        if ($enrouteActivity) {
            return $enrouteActivity;
        }

        $startedActivity = $this->activities()->firstWhere('code', 'started');
        if ($startedActivity) {
            return $startedActivity;
        }

        return $this->nextFirstActivity() ?? new Activity([
            'key'      => 'enroute_pickup',
            'code'     => 'enroute_pickup',
            'status'   => 'En Route',
            'details'  => 'En route to pickup',
            'complete' => false,
        ], $this->flow);
    }"""

if old_get_started in content:
    content = content.replace(old_get_started, new_get_started)
    p = subprocess.Popen(["docker", "exec", "-i", "fleetbase-application-1", "tee", "/fleetbase/api/vendor/fleetbase/fleetops-api/server/src/Models/OrderConfig.php"], stdin=subprocess.PIPE, stdout=subprocess.DEVNULL)
    p.communicate(input=content.encode('utf-8'))
    print("Updated getStartedActivity in OrderConfig.php")
else:
    print("old_get_started not found in OrderConfig.php")

# 2. Update OrderController.php to explicitly reject 'started' from statuses
res = subprocess.run(["docker", "exec", "fleetbase-application-1", "cat", "/fleetbase/api/vendor/fleetbase/fleetops-api/server/src/Http/Controllers/Internal/v1/OrderController.php"], capture_output=True, text=True)
ctrl_content = res.stdout

old_statuses_merge = """if ($activityCodes->isNotEmpty()) {
            $result = $activityCodes
                ->merge($orderStatuses)
                ->unique()
                ->values();
        } else {
            $result = $orderStatuses
                ->unique()
                ->values();
        }"""

new_statuses_merge = """if ($activityCodes->isNotEmpty()) {
            $result = $activityCodes
                ->reject(fn ($code) => $code === 'started')
                ->merge($orderStatuses->reject(fn ($code) => $code === 'started'))
                ->unique()
                ->values();
        } else {
            $result = $orderStatuses
                ->reject(fn ($code) => $code === 'started')
                ->unique()
                ->values();
        }"""

if old_statuses_merge in ctrl_content:
    ctrl_content = ctrl_content.replace(old_statuses_merge, new_statuses_merge)
    p = subprocess.Popen(["docker", "exec", "-i", "fleetbase-application-1", "tee", "/fleetbase/api/vendor/fleetbase/fleetops-api/server/src/Http/Controllers/Internal/v1/OrderController.php"], stdin=subprocess.PIPE, stdout=subprocess.DEVNULL)
    p.communicate(input=ctrl_content.encode('utf-8'))
    print("Updated OrderController.php to reject 'started'")

# 3. Update OrderObserver.php
res = subprocess.run(["docker", "exec", "fleetbase-application-1", "cat", "/fleetbase/api/vendor/fleetbase/fleetops-api/server/src/Observers/OrderObserver.php"], capture_output=True, text=True)
obs_content = res.stdout

old_obs = "&& $order->status === 'started'"
new_obs = "&& in_array($order->status, ['started', 'enroute_pickup'])"

if old_obs in obs_content:
    obs_content = obs_content.replace(old_obs, new_obs)
    p = subprocess.Popen(["docker", "exec", "-i", "fleetbase-application-1", "tee", "/fleetbase/api/vendor/fleetbase/fleetops-api/server/src/Observers/OrderObserver.php"], stdin=subprocess.PIPE, stdout=subprocess.DEVNULL)
    p.communicate(input=obs_content.encode('utf-8'))
    print("Updated OrderObserver.php for enroute_pickup auto-start")
