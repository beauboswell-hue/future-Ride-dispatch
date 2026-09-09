<?php

namespace Fleetbase\FleetOps\Support;

use Fleetbase\FleetOps\Models\OrderConfig;
use Fleetbase\Models\Company;
use Illuminate\Support\Str;

class FleetOps
{
    /**
     * Creates or retrieves an existing transport configuration for a given company.
     *
     * This method attempts to find a transport configuration (`OrderConfig`) for the specified company.
     * If such a configuration exists, it is returned. Otherwise, a new configuration is created with default values,
     * implementing the standard 1-to-1 Limo Anywhere dispatch pipeline.
     *
     * @param Company $company the company for which the transport configuration is being created or retrieved
     *
     * @return OrderConfig the transport configuration associated with the specified company
     */
    public static function createTransportConfig(Company $company): OrderConfig
    {
        return OrderConfig::firstOrCreate(
            [
                'company_uuid' => $company->uuid,
                'key'          => 'transport',
                'namespace'    => 'system:order-config:transport',
            ],
            [
                'name'         => 'Transport',
                'key'          => 'transport',
                'namespace'    => 'system:order-config:transport',
                'description'  => 'Standard 1-to-1 Limo Anywhere dispatch pipeline',
                'core_service' => 1,
                'status'       => 'private',
                'version'      => '0.0.1',
                'tags'         => ['transport', 'delivery', 'limo'],
                'entities'     => [],
                'meta'         => [],
                'flow'         => [
                    'created' => [
                        'key'         => 'created',
                        'code'        => 'created',
                        'color'       => '#6B7280',
                        'logic'       => [],
                        'events'      => [],
                        'status'      => 'Created',
                        'actions'     => [],
                        'details'     => 'Unassigned / Quoted',
                        'options'     => [],
                        'complete'    => false,
                        'entities'    => [],
                        'sequence'    => 0,
                        'activities'  => ['dispatched', 'canceled'],
                        'internalId'  => Str::uuid(),
                        'pod_method'  => 'scan',
                        'require_pod' => false,
                    ],
                    'dispatched' => [
                        'key'         => 'dispatched',
                        'code'        => 'dispatched',
                        'color'       => '#2563EB',
                        'logic'       => [],
                        'events'      => [],
                        'status'      => 'Dispatched',
                        'actions'     => [],
                        'details'     => 'Assigned to chauffeur',
                        'options'     => [],
                        'complete'    => false,
                        'entities'    => [],
                        'sequence'    => 1,
                        'activities'  => ['enroute_pickup', 'created', 'canceled'],
                        'internalId'  => Str::uuid(),
                        'pod_method'  => 'scan',
                        'require_pod' => false,
                    ],
                    'enroute_pickup' => [
                        'key'         => 'enroute_pickup',
                        'code'        => 'enroute_pickup',
                        'color'       => '#EAB308',
                        'logic'       => [],
                        'events'      => [],
                        'status'      => 'En Route',
                        'actions'     => [],
                        'details'     => 'En route to pickup',
                        'options'     => [],
                        'complete'    => false,
                        'entities'    => [],
                        'sequence'    => 2,
                        'activities'  => ['on_location', 'dispatched', 'canceled'],
                        'internalId'  => Str::uuid(),
                        'pod_method'  => 'scan',
                        'require_pod' => false,
                    ],
                    'on_location' => [
                        'key'         => 'on_location',
                        'code'        => 'on_location',
                        'color'       => '#9333EA',
                        'logic'       => [],
                        'events'      => [],
                        'status'      => 'On Location',
                        'actions'     => [],
                        'details'     => 'Arrived at pickup / waiting',
                        'options'     => [],
                        'complete'    => false,
                        'entities'    => [],
                        'sequence'    => 3,
                        'activities'  => ['pob', 'enroute_pickup', 'canceled'],
                        'internalId'  => Str::uuid(),
                        'pod_method'  => 'scan',
                        'require_pod' => false,
                    ],
                    'pob' => [
                        'key'         => 'pob',
                        'code'        => 'pob',
                        'color'       => '#F97316',
                        'logic'       => [],
                        'events'      => [],
                        'status'      => 'Passenger On Board',
                        'actions'     => [],
                        'details'     => 'Trip in progress',
                        'options'     => [],
                        'complete'    => false,
                        'entities'    => [],
                        'sequence'    => 4,
                        'activities'  => ['completed', 'on_location', 'canceled'],
                        'internalId'  => Str::uuid(),
                        'pod_method'  => 'scan',
                        'require_pod' => false,
                    ],
                    'completed' => [
                        'key'         => 'completed',
                        'code'        => 'completed',
                        'color'       => '#16A34A',
                        'logic'       => [],
                        'events'      => [],
                        'status'      => 'Completed',
                        'actions'     => [],
                        'details'     => 'Dropoff finished / closed',
                        'options'     => [],
                        'complete'    => true,
                        'entities'    => [],
                        'sequence'    => 5,
                        'activities'  => [],
                        'internalId'  => Str::uuid(),
                        'pod_method'  => 'scan',
                        'require_pod' => false,
                    ],
                    'canceled' => [
                        'key'         => 'canceled',
                        'code'        => 'canceled',
                        'color'       => '#DC2626',
                        'logic'       => [],
                        'events'      => [],
                        'status'      => 'Canceled',
                        'actions'     => [],
                        'details'     => 'Voided / cancelled',
                        'options'     => [],
                        'complete'    => false,
                        'entities'    => [],
                        'sequence'    => 6,
                        'activities'  => [],
                        'internalId'  => Str::uuid(),
                        'pod_method'  => 'scan',
                        'require_pod' => false,
                    ],
                ],
            ]
        );
    }
}
