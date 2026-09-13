<?php

namespace App\Console\Commands;

use Fleetbase\FleetOps\Models\Place;
use Fleetbase\FleetOps\Support\LiveCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOrphanedPlaces extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fleetbase:cleanup-orphaned-places {--force : Perform the actual soft-delete update} {--company= : Optional company UUID filter}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely soft-delete orphaned ad-hoc order places that have no active orders or organization references';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $force = (bool) $this->option('force');
        $companyUuid = $this->option('company');

        $query = Place::whereNull('deleted_at')
            // Not referenced as pickup, dropoff, or return by any active, non-terminal order
            ->whereNotIn('uuid', function ($sub) {
                $sub->select('pay.pickup_uuid')
                    ->from('orders as o')
                    ->join('payloads as pay', 'o.payload_uuid', '=', 'pay.uuid')
                    ->whereNull('o.deleted_at')
                    ->whereNotIn('o.status', ['completed', 'canceled', 'expired'])
                    ->whereNotNull('pay.pickup_uuid');
            })
            ->whereNotIn('uuid', function ($sub) {
                $sub->select('pay.dropoff_uuid')
                    ->from('orders as o')
                    ->join('payloads as pay', 'o.payload_uuid', '=', 'pay.uuid')
                    ->whereNull('o.deleted_at')
                    ->whereNotIn('o.status', ['completed', 'canceled', 'expired'])
                    ->whereNotNull('pay.dropoff_uuid');
            })
            ->whereNotIn('uuid', function ($sub) {
                $sub->select('pay.return_uuid')
                    ->from('orders as o')
                    ->join('payloads as pay', 'o.payload_uuid', '=', 'pay.uuid')
                    ->whereNull('o.deleted_at')
                    ->whereNotIn('o.status', ['completed', 'canceled', 'expired'])
                    ->whereNotNull('pay.return_uuid');
            })
            // Not referenced as a waypoint by any active, non-terminal order
            ->whereNotIn('uuid', function ($sub) {
                $sub->select('wp.place_uuid')
                    ->from('orders as o')
                    ->join('payloads as pay', 'o.payload_uuid', '=', 'pay.uuid')
                    ->join('waypoints as wp', 'wp.payload_uuid', '=', 'pay.uuid')
                    ->whereNull('o.deleted_at')
                    ->whereNotIn('o.status', ['completed', 'canceled', 'expired'])
                    ->whereNull('wp.deleted_at')
                    ->whereNotNull('wp.place_uuid');
            })
            // Must not be an organizational landmark / entity reference
            ->whereNull('owner_uuid')
            ->whereNotIn('uuid', function ($sub) {
                $sub->select('place_uuid')->from('companies')->whereNotNull('place_uuid');
            })
            ->whereNotIn('uuid', function ($sub) {
                $sub->select('place_uuid')->from('contacts')->whereNotNull('place_uuid');
            })
            ->whereNotIn('uuid', function ($sub) {
                $sub->select('place_uuid')->from('vendors')->whereNotNull('place_uuid');
            })
            ->whereNotIn('uuid', function ($sub) {
                $sub->select('current_place_uuid')->from('assets')->whereNotNull('current_place_uuid');
            });

        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }

        $candidates = $query->get(['id', 'uuid', 'public_id', 'name', 'street1', 'city', 'company_uuid', 'created_at']);
        $count = $candidates->count();

        $this->info("Found {$count} orphaned place(s) eligible for cleanup.");

        if ($count === 0) {
            $this->info('No orphaned places found. Database is already clean.');
            return 0;
        }

        $headers = ['ID', 'Public ID', 'Name', 'Street1', 'City', 'Company UUID'];
        $rows = $candidates->take(15)->map(function ($p) {
            return [
                $p->id,
                $p->public_id,
                $p->name ?? 'NULL',
                $p->street1 ?? 'NULL',
                $p->city ?? 'NULL',
                $p->company_uuid,
            ];
        })->toArray();

        $this->table($headers, $rows);

        if ($count > 15) {
            $this->comment("... and " . ($count - 15) . " more places.");
        }

        if (!$force) {
            $this->warn('DRY RUN: No places were modified. Pass --force to execute soft-delete.');
            return 0;
        }

        $now = now();
        $updated = DB::table('places')
            ->whereIn('id', $candidates->pluck('id'))
            ->update(['deleted_at' => $now]);

        $this->info("Successfully soft-deleted {$updated} orphaned place(s).");

        // Invalidate live cache
        if (class_exists(LiveCacheService::class)) {
            LiveCacheService::invalidateMultiple(['orders', 'routes', 'coordinates', 'places']);
            $this->info('Invalidated FleetOps live cache.');
        }

        return 0;
    }
}
