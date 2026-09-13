<?php

namespace App\Http\Controllers;

use Fleetbase\FleetOps\Http\Controllers\Internal\v1\LiveController as BaseLiveController;
use Fleetbase\FleetOps\Http\Filter\PlaceFilter;
use Fleetbase\FleetOps\Http\Resources\v1\Index\Place as PlaceIndexResource;
use Fleetbase\FleetOps\Models\Place;
use Fleetbase\FleetOps\Support\LiveCacheService;
use Illuminate\Http\Request;

class OverriddenLiveController extends BaseLiveController
{
    /**
     * Get places based on filters for the current company.
     * Filters out orphaned or ad-hoc pickup/dropoff places from completed, canceled, or deleted orders.
     *
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function places(Request $request)
    {
        $cacheParams = $request->only(['query', 'type', 'country', 'limit', 'bounds']);

        return LiveCacheService::remember('places', $cacheParams, function () use ($request) {
            // Query places for current company
            $query = Place::where(['company_uuid' => session('company')])
                ->filter(new PlaceFilter($request))
                ->applyDirectivesForPermissions('fleet-ops list place');

            // Filter out places with invalid coordinates
            $query->whereNotNull('location')
                ->whereRaw('
                    ST_Y(location) BETWEEN -90 AND 90
                    AND ST_X(location) BETWEEN -180 AND 180
                    AND NOT (ST_X(location) = 0 AND ST_Y(location) = 0)
                ');

            // Apply spatial filtering if bounds are provided
            $bounds = $request->input('bounds');
            if ($bounds && is_array($bounds) && count($bounds) === 4) {
                [$south, $west, $north, $east] = array_map('floatval', array_values($bounds));

                $query->whereRaw(
                    'ST_Y(location) BETWEEN ? AND ? AND ST_X(location) BETWEEN ? AND ?',
                    [$south, $north, $west, $east]
                );
            }

            // Filter out places associated only with completed, canceled, expired, or deleted orders.
            // A place will be displayed on the live map IF:
            // 1. It is attached to an active, non-terminal order (via pickup, dropoff, return, or waypoints), OR
            // 2. It is explicitly saved as an organization landmark / contact / vendor / company / asset, OR
            // 3. It is a standalone place not created as an order payload stop.
            $query->where(function ($q) {
                // 1a. Payload pickup / dropoff / return for active, non-terminal orders
                $q->whereExists(function ($sub) {
                    $sub->selectRaw(1)
                        ->from('payloads')
                        ->join('orders', 'orders.payload_uuid', '=', 'payloads.uuid')
                        ->whereRaw('(payloads.pickup_uuid = places.uuid OR payloads.dropoff_uuid = places.uuid OR payloads.return_uuid = places.uuid)')
                        ->whereNull('orders.deleted_at')
                        ->whereNotIn('orders.status', ['completed', 'canceled', 'expired']);
                })
                // 1b. Waypoints for active, non-terminal orders
                ->orWhereExists(function ($sub) {
                    $sub->selectRaw(1)
                        ->from('waypoints')
                        ->join('payloads', 'waypoints.payload_uuid', '=', 'payloads.uuid')
                        ->join('orders', 'orders.payload_uuid', '=', 'payloads.uuid')
                        ->whereColumn('waypoints.place_uuid', 'places.uuid')
                        ->whereNull('waypoints.deleted_at')
                        ->whereNull('orders.deleted_at')
                        ->whereNotIn('orders.status', ['completed', 'canceled', 'expired']);
                })
                // 2. Reusable organization landmark / contact / vendor / company / asset
                ->orWhereNotNull('places.owner_uuid')
                ->orWhereExists(function ($sub) {
                    $sub->selectRaw(1)->from('companies')->whereColumn('companies.place_uuid', 'places.uuid');
                })
                ->orWhereExists(function ($sub) {
                    $sub->selectRaw(1)->from('contacts')->whereColumn('contacts.place_uuid', 'places.uuid');
                })
                ->orWhereExists(function ($sub) {
                    $sub->selectRaw(1)->from('vendors')->whereColumn('vendors.place_uuid', 'places.uuid');
                })
                ->orWhereExists(function ($sub) {
                    $sub->selectRaw(1)->from('assets')->whereColumn('assets.current_place_uuid', 'places.uuid');
                })
                // 3. Standalone places (created without payloads)
                ->orWhere(function ($standalone) {
                    $standalone->whereNotExists(function ($sub) {
                        $sub->selectRaw(1)
                            ->from('payloads')
                            ->whereRaw('(payloads.pickup_uuid = places.uuid OR payloads.dropoff_uuid = places.uuid OR payloads.return_uuid = places.uuid)');
                    })
                    ->whereNotExists(function ($sub) {
                        $sub->selectRaw(1)
                            ->from('waypoints')
                            ->whereColumn('waypoints.place_uuid', 'places.uuid');
                    });
                });
            });

            $limit = (int) $request->input('limit', 500);
            if ($limit > 0) {
                $query->limit(min($limit, 1000));
            }

            $query->orderByDesc('updated_at')->orderByDesc('id');

            $places = $query->get();

            return PlaceIndexResource::collection($places);
        });
    }
}
