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
     * If such a configuration exists, it's returned. Otherwise, a new configuration is created with default values,
     * such as the company UUID, key, core service flag, status, version, tags, and predefined workflow steps.
     * These steps include 'created', 'enroute', 'started', 'completed', and 'dispatched', each with specific attributes.
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
                'description'  => 'Default order configuration for transport',
                'core_service' => 1,
                'status'       => 'private',
                'version'      => '0.0.1',
                'tags'         => ['transport', 'delivery'],
                'entities'     => [],
                'meta'         => [],
                'flow'         => [
                    'created' => [
                        'key'         => 'created',
                        'code'        => 'created',
                        'name'        => 'Created',
                        'color'       => '#3b82f6',
                        'logic'       => [],
                        'events'      => [],
                        'status'      => 'Created',
                        'actions'     => [],
                        'details'     => 'New order was created.',
                        'options'     => [],
                        'complete'    => false,
                        'entities'    => [],
                        'sequence'    => 1,
                        'activities'  => ['dispatched', 'canceled'],
                        'internalId'  => Str::uuid(),
                        'pod_method'  => 'scan',
                        'require_pod' => false,
                    ],
                    'dispatched' => [
                        'key'         => 'dispatched',
                        'code'        => 'dispatched',
                        'name'        => 'Dispatched',
                        'color'       => '#0284c7',
                        'logic'       => [],
                        'events'      => [],
                        'status'      => 'Dispatched',
                        'actions'     => [],
                        'details'     => 'Order has been dispatched.',
                        'options'     => [],
                        'complete'    => false,
                        'entities'    => [],
                        'sequence'    => 2,
                        'activities'  => ['en_route', 'created', 'canceled'],
                        'internalId'  => Str::uuid(),
                        'pod_method'  => 'scan',
                        'require_pod' => false,
                    ],
                    'en_route' => [
                        'key'         => 'en_route',
                        'code'        => 'en_route',
                        'name'        => 'En Route',
                        'color'       => '#f97316',
                        'logic'       => [],
                        'events'      => [],
                        'status'      => 'En Route',
                        'actions'     => [],
                        'details'     => 'En Route.',
                        'options'     => [],
                        'complete'    => false,
                        'entities'    => [],
                        'sequence'    => 3,
                        'activities'  => ['on_location', 'dispatched', 'canceled'],
                        'internalId'  => Str::uuid(),
                        'pod_method'  => 'scan',
                        'require_pod' => false,
                    ],
                    'on_location' => [
                        'key'         => 'on_location',
                        'code'        => 'on_location',
                        'name'        => 'On Location',
                        'color'       => '#8b5cf6',
                        'logic'       => [],
                        'events'      => [],
                        'status'      => 'On Location',
                        'actions'     => [],
                        'details'     => 'On Location.',
                        'options'     => [],
                        'complete'    => false,
                        'entities'    => [],
                        'sequence'    => 4,
                        'activities'  => ['passenger_on_board', 'en_route', 'canceled'],
                        'internalId'  => Str::uuid(),
                        'pod_method'  => 'scan',
                        'require_pod' => false,
                    ],
                    'passenger_on_board' => [
                        'key'         => 'passenger_on_board',
                        'code'        => 'passenger_on_board',
                        'name'        => 'Passenger on Board',
                        'color'       => '#06b6d4',
                        'logic'       => [],
                        'events'      => [],
                        'status'      => 'Passenger on Board',
                        'actions'     => [],
                        'details'     => 'Passenger on Board.',
                        'options'     => [],
                        'complete'    => false,
                        'entities'    => [],
                        'sequence'    => 5,
                        'activities'  => ['completed', 'on_location', 'canceled'],
                        'internalId'  => Str::uuid(),
                        'pod_method'  => 'scan',
                        'require_pod' => false,
                    ],
                    'completed' => [
                        'key'         => 'completed',
                        'code'        => 'completed',
                        'name'        => 'Completed',
                        'color'       => '#22c55e',
                        'logic'       => [],
                        'events'      => [],
                        'status'      => 'Completed',
                        'actions'     => [],
                        'details'     => 'Completed.',
                        'options'     => [],
                        'complete'    => true,
                        'entities'    => [],
                        'sequence'    => 6,
                        'activities'  => [],
                        'internalId'  => Str::uuid(),
                        'pod_method'  => 'scan',
                        'require_pod' => false,
                    ],
                    'canceled' => [
                        'key'         => 'canceled',
                        'code'        => 'canceled',
                        'name'        => 'Canceled',
                        'color'       => '#ef4444',
                        'logic'       => [],
                        'events'      => [],
                        'status'      => 'Canceled',
                        'actions'     => [],
                        'details'     => 'Canceled.',
                        'options'     => [],
                        'complete'    => false,
                        'entities'    => [],
                        'sequence'    => 7,
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
