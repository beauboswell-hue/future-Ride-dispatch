<?php

use Fleetbase\FleetOps\Models\OrderConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $configs = OrderConfig::where('key', 'transport')->get();

        foreach ($configs as $config) {
            $currentFlow = is_array($config->flow) ? $config->flow : json_decode($config->flow ?? '[]', true);

            $newFlow = [
                'created' => [
                    'key'         => 'created',
                    'code'        => 'created',
                    'name'        => 'Created',
                    'status'      => 'Created',
                    'color'       => data_get($currentFlow, 'created.color', '#6B7280'),
                    'logic'       => data_get($currentFlow, 'created.logic', []),
                    'events'      => data_get($currentFlow, 'created.events', []),
                    'actions'     => data_get($currentFlow, 'created.actions', []),
                    'details'     => data_get($currentFlow, 'created.details', 'Unassigned / Quoted'),
                    'options'     => data_get($currentFlow, 'created.options', []),
                    'complete'    => false,
                    'entities'    => data_get($currentFlow, 'created.entities', []),
                    'sequence'    => 0,
                    'activities'  => ['dispatched', 'canceled'],
                    'internalId'  => data_get($currentFlow, 'created.internalId', (string) Str::uuid()),
                    'pod_method'  => data_get($currentFlow, 'created.pod_method', 'scan'),
                    'require_pod' => data_get($currentFlow, 'created.require_pod', false),
                ],
                'dispatched' => [
                    'key'         => 'dispatched',
                    'code'        => 'dispatched',
                    'name'        => 'Dispatched',
                    'status'      => 'Dispatched',
                    'color'       => data_get($currentFlow, 'dispatched.color', '#2563EB'),
                    'logic'       => data_get($currentFlow, 'dispatched.logic', []),
                    'events'      => data_get($currentFlow, 'dispatched.events', []),
                    'actions'     => data_get($currentFlow, 'dispatched.actions', []),
                    'details'     => data_get($currentFlow, 'dispatched.details', 'Assigned to chauffeur'),
                    'options'     => data_get($currentFlow, 'dispatched.options', []),
                    'complete'    => false,
                    'entities'    => data_get($currentFlow, 'dispatched.entities', []),
                    'sequence'    => 1,
                    'activities'  => ['enroute_pickup', 'created', 'canceled'],
                    'internalId'  => data_get($currentFlow, 'dispatched.internalId', (string) Str::uuid()),
                    'pod_method'  => data_get($currentFlow, 'dispatched.pod_method', 'scan'),
                    'require_pod' => data_get($currentFlow, 'dispatched.require_pod', false),
                ],
                'enroute_pickup' => [
                    'key'         => 'enroute_pickup',
                    'code'        => 'enroute_pickup',
                    'name'        => 'En Route',
                    'status'      => 'En Route',
                    'color'       => data_get($currentFlow, 'enroute_pickup.color', data_get($currentFlow, 'enroute.color', '#EAB308')),
                    'logic'       => data_get($currentFlow, 'enroute_pickup.logic', data_get($currentFlow, 'enroute.logic', [])),
                    'events'      => data_get($currentFlow, 'enroute_pickup.events', data_get($currentFlow, 'enroute.events', [])),
                    'actions'     => data_get($currentFlow, 'enroute_pickup.actions', data_get($currentFlow, 'enroute.actions', [])),
                    'details'     => data_get($currentFlow, 'enroute_pickup.details', data_get($currentFlow, 'enroute.details', 'En route to pickup')),
                    'options'     => data_get($currentFlow, 'enroute_pickup.options', data_get($currentFlow, 'enroute.options', [])),
                    'complete'    => false,
                    'entities'    => data_get($currentFlow, 'enroute_pickup.entities', data_get($currentFlow, 'enroute.entities', [])),
                    'sequence'    => 2,
                    'activities'  => ['on_location', 'dispatched', 'canceled'],
                    'internalId'  => data_get($currentFlow, 'enroute_pickup.internalId', data_get($currentFlow, 'enroute.internalId', (string) Str::uuid())),
                    'pod_method'  => data_get($currentFlow, 'enroute_pickup.pod_method', data_get($currentFlow, 'enroute.pod_method', 'scan')),
                    'require_pod' => data_get($currentFlow, 'enroute_pickup.require_pod', data_get($currentFlow, 'enroute.require_pod', false)),
                ],
                'on_location' => [
                    'key'         => 'on_location',
                    'code'        => 'on_location',
                    'name'        => 'On Location',
                    'status'      => 'On Location',
                    'color'       => data_get($currentFlow, 'on_location.color', '#9333EA'),
                    'logic'       => data_get($currentFlow, 'on_location.logic', []),
                    'events'      => data_get($currentFlow, 'on_location.events', []),
                    'actions'     => data_get($currentFlow, 'on_location.actions', []),
                    'details'     => data_get($currentFlow, 'on_location.details', 'Arrived at pickup / waiting'),
                    'options'     => data_get($currentFlow, 'on_location.options', []),
                    'complete'    => false,
                    'entities'    => data_get($currentFlow, 'on_location.entities', []),
                    'sequence'    => 3,
                    'activities'  => ['pob', 'enroute_pickup', 'canceled'],
                    'internalId'  => data_get($currentFlow, 'on_location.internalId', (string) Str::uuid()),
                    'pod_method'  => data_get($currentFlow, 'on_location.pod_method', 'scan'),
                    'require_pod' => data_get($currentFlow, 'on_location.require_pod', false),
                ],
                'pob' => [
                    'key'         => 'pob',
                    'code'        => 'pob',
                    'name'        => 'Passenger on Board',
                    'status'      => 'Passenger On Board',
                    'color'       => data_get($currentFlow, 'pob.color', '#F97316'),
                    'logic'       => data_get($currentFlow, 'pob.logic', []),
                    'events'      => data_get($currentFlow, 'pob.events', []),
                    'actions'     => data_get($currentFlow, 'pob.actions', []),
                    'details'     => data_get($currentFlow, 'pob.details', 'Trip in progress'),
                    'options'     => data_get($currentFlow, 'pob.options', []),
                    'complete'    => false,
                    'entities'    => data_get($currentFlow, 'pob.entities', []),
                    'sequence'    => 4,
                    'activities'  => ['completed', 'on_location', 'canceled'],
                    'internalId'  => data_get($currentFlow, 'pob.internalId', (string) Str::uuid()),
                    'pod_method'  => data_get($currentFlow, 'pob.pod_method', 'scan'),
                    'require_pod' => data_get($currentFlow, 'pob.require_pod', false),
                ],
                'completed' => [
                    'key'         => 'completed',
                    'code'        => 'completed',
                    'name'        => 'Completed',
                    'status'      => 'Completed',
                    'color'       => data_get($currentFlow, 'completed.color', '#16A34A'),
                    'logic'       => data_get($currentFlow, 'completed.logic', []),
                    'events'      => data_get($currentFlow, 'completed.events', []),
                    'actions'     => data_get($currentFlow, 'completed.actions', []),
                    'details'     => data_get($currentFlow, 'completed.details', 'Dropoff finished / closed'),
                    'options'     => data_get($currentFlow, 'completed.options', []),
                    'complete'    => true,
                    'entities'    => data_get($currentFlow, 'completed.entities', []),
                    'sequence'    => 5,
                    'activities'  => [],
                    'internalId'  => data_get($currentFlow, 'completed.internalId', (string) Str::uuid()),
                    'pod_method'  => data_get($currentFlow, 'completed.pod_method', 'scan'),
                    'require_pod' => data_get($currentFlow, 'completed.require_pod', false),
                ],
                'canceled' => [
                    'key'         => 'canceled',
                    'code'        => 'canceled',
                    'name'        => 'Canceled',
                    'status'      => 'Canceled',
                    'color'       => data_get($currentFlow, 'canceled.color', '#DC2626'),
                    'logic'       => data_get($currentFlow, 'canceled.logic', []),
                    'events'      => data_get($currentFlow, 'canceled.events', []),
                    'actions'     => data_get($currentFlow, 'canceled.actions', []),
                    'details'     => data_get($currentFlow, 'canceled.details', 'Voided / cancelled'),
                    'options'     => data_get($currentFlow, 'canceled.options', []),
                    'complete'    => false,
                    'entities'    => data_get($currentFlow, 'canceled.entities', []),
                    'sequence'    => 6,
                    'activities'  => [],
                    'internalId'  => data_get($currentFlow, 'canceled.internalId', (string) Str::uuid()),
                    'pod_method'  => data_get($currentFlow, 'canceled.pod_method', 'scan'),
                    'require_pod' => data_get($currentFlow, 'canceled.require_pod', false),
                ],
            ];

            $config->flow = $newFlow;
            $config->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $configs = OrderConfig::where('key', 'transport')->get();

        foreach ($configs as $config) {
            $currentFlow = is_array($config->flow) ? $config->flow : json_decode($config->flow ?? '[]', true);

            $rollbackFlow = [
                'created' => [
                    'key'         => 'created',
                    'code'        => 'created',
                    'name'        => 'Created',
                    'status'      => 'Created',
                    'color'       => data_get($currentFlow, 'created.color', '#6B7280'),
                    'logic'       => data_get($currentFlow, 'created.logic', []),
                    'events'      => data_get($currentFlow, 'created.events', []),
                    'actions'     => data_get($currentFlow, 'created.actions', []),
                    'details'     => data_get($currentFlow, 'created.details', 'Unassigned / Quoted'),
                    'options'     => data_get($currentFlow, 'created.options', []),
                    'complete'    => false,
                    'entities'    => data_get($currentFlow, 'created.entities', []),
                    'sequence'    => 0,
                    'activities'  => ['dispatched', 'canceled'],
                    'internalId'  => data_get($currentFlow, 'created.internalId', (string) Str::uuid()),
                    'pod_method'  => data_get($currentFlow, 'created.pod_method', 'scan'),
                    'require_pod' => data_get($currentFlow, 'created.require_pod', false),
                ],
                'dispatched' => [
                    'key'         => 'dispatched',
                    'code'        => 'dispatched',
                    'name'        => 'Dispatched',
                    'status'      => 'Dispatched',
                    'color'       => data_get($currentFlow, 'dispatched.color', '#2563EB'),
                    'logic'       => data_get($currentFlow, 'dispatched.logic', []),
                    'events'      => data_get($currentFlow, 'dispatched.events', []),
                    'actions'     => data_get($currentFlow, 'dispatched.actions', []),
                    'details'     => data_get($currentFlow, 'dispatched.details', 'Assigned to chauffeur'),
                    'options'     => data_get($currentFlow, 'dispatched.options', []),
                    'complete'    => false,
                    'entities'    => data_get($currentFlow, 'dispatched.entities', []),
                    'sequence'    => 1,
                    'activities'  => ['enroute'],
                    'internalId'  => data_get($currentFlow, 'dispatched.internalId', (string) Str::uuid()),
                    'pod_method'  => data_get($currentFlow, 'dispatched.pod_method', 'scan'),
                    'require_pod' => data_get($currentFlow, 'dispatched.require_pod', false),
                ],
                'enroute' => [
                    'key'         => 'enroute',
                    'code'        => 'enroute',
                    'name'        => 'En Route',
                    'status'      => 'En Route',
                    'color'       => data_get($currentFlow, 'enroute.color', '#EAB308'),
                    'logic'       => data_get($currentFlow, 'enroute.logic', []),
                    'events'      => data_get($currentFlow, 'enroute.events', []),
                    'actions'     => data_get($currentFlow, 'enroute.actions', []),
                    'details'     => data_get($currentFlow, 'enroute.details', 'En route to pickup'),
                    'options'     => data_get($currentFlow, 'enroute.options', []),
                    'complete'    => false,
                    'entities'    => data_get($currentFlow, 'enroute.entities', []),
                    'sequence'    => 2,
                    'activities'  => ['completed'],
                    'internalId'  => data_get($currentFlow, 'enroute.internalId', (string) Str::uuid()),
                    'pod_method'  => data_get($currentFlow, 'enroute.pod_method', 'scan'),
                    'require_pod' => data_get($currentFlow, 'enroute.require_pod', false),
                ],
                'completed' => [
                    'key'         => 'completed',
                    'code'        => 'completed',
                    'name'        => 'Completed',
                    'status'      => 'Completed',
                    'color'       => data_get($currentFlow, 'completed.color', '#16A34A'),
                    'logic'       => data_get($currentFlow, 'completed.logic', []),
                    'events'      => data_get($currentFlow, 'completed.events', []),
                    'actions'     => data_get($currentFlow, 'completed.actions', []),
                    'details'     => data_get($currentFlow, 'completed.details', 'Dropoff finished / closed'),
                    'options'     => data_get($currentFlow, 'completed.options', []),
                    'complete'    => true,
                    'entities'    => data_get($currentFlow, 'completed.entities', []),
                    'sequence'    => 3,
                    'activities'  => [],
                    'internalId'  => data_get($currentFlow, 'completed.internalId', (string) Str::uuid()),
                    'pod_method'  => data_get($currentFlow, 'completed.pod_method', 'scan'),
                    'require_pod' => data_get($currentFlow, 'completed.require_pod', false),
                ],
                'canceled' => [
                    'key'         => 'canceled',
                    'code'        => 'canceled',
                    'name'        => 'Canceled',
                    'status'      => 'Canceled',
                    'color'       => data_get($currentFlow, 'canceled.color', '#DC2626'),
                    'logic'       => data_get($currentFlow, 'canceled.logic', []),
                    'events'      => data_get($currentFlow, 'canceled.events', []),
                    'actions'     => data_get($currentFlow, 'canceled.actions', []),
                    'details'     => data_get($currentFlow, 'canceled.details', 'Voided / cancelled'),
                    'options'     => data_get($currentFlow, 'canceled.options', []),
                    'complete'    => false,
                    'entities'    => data_get($currentFlow, 'canceled.entities', []),
                    'sequence'    => 4,
                    'activities'  => [],
                    'internalId'  => data_get($currentFlow, 'canceled.internalId', (string) Str::uuid()),
                    'pod_method'  => data_get($currentFlow, 'canceled.pod_method', 'scan'),
                    'require_pod' => data_get($currentFlow, 'canceled.require_pod', false),
                ],
            ];

            $config->flow = $rollbackFlow;
            $config->save();
        }
    }
};
