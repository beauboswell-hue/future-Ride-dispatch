<?php

namespace App\Jobs;

use App\Services\GoogleCalendarService;
use Fleetbase\FleetOps\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncOrderToGoogleCalendar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The order instance to sync.
     *
     * @var Order
     */
    public Order $order;

    /**
     * The action to perform ('create' or 'update').
     *
     * @var string
     */
    public string $action;

    /**
     * Create a new job instance.
     *
     * @param Order  $order
     * @param string $action 'create' or 'update'
     */
    public function __construct(Order $order, string $action = 'create')
    {
        $this->order = $order;
        $this->action = $action;
    }

    /**
     * Execute the job.
     *
     * @param GoogleCalendarService $calendarService
     * @return void
     */
    public function handle(GoogleCalendarService $calendarService): void
    {
        try {
            if ($this->action === 'update') {
                $calendarService->updateEvent($this->order);
            } else {
                $calendarService->createEvent($this->order);
            }
        } catch (\Throwable $e) {
            Log::error('SyncOrderToGoogleCalendar job failed: ' . $e->getMessage(), [
                'order_id'  => $this->order->public_id ?? $this->order->uuid,
                'action'    => $this->action,
                'exception' => $e,
            ]);
        }
    }
}
