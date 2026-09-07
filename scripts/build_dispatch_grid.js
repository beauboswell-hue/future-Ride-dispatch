const fs = require('fs');
const path = require('path');

const compilerPath = require.resolve('ember-source/dist/ember-template-compiler.js', {
    paths: ['/home/wert/fleetbase/console']
});
const compiler = require(compilerPath);

console.log('--- Compiling Dispatch Grid HBS Templates ---');

// 1. Template for <DispatchGrid> component
const dispatchGridHbs = `<div class="dispatch-grid-wrapper flex flex-col h-full w-full bg-gray-950 text-gray-100 p-4 space-y-4">
    {{!-- Top Toolbar --}}
    <div class="dispatch-grid-toolbar flex flex-col md:flex-row items-start md:items-center justify-between gap-3 bg-gray-900/90 border border-gray-800 rounded-xl p-3 shadow-lg">
        <div class="flex items-center space-x-3">
            <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-blue-600/20 text-blue-400 border border-blue-500/30">
                <FaIcon @icon="table-cells" class="text-base" />
            </div>
            <div>
                <div class="flex items-center space-x-2">
                    <h2 class="text-base font-bold text-white tracking-wide">Dispatch Grid</h2>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-500/20 text-blue-300 border border-blue-500/30">
                        {{this.gridRows.length}} Orders
                    </span>
                </div>
                <p class="text-xs text-gray-400">Live operational manifest &amp; dispatch status matrix</p>
            </div>
        </div>

        {{!-- Search & Filters --}}
        <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
            <div class="relative flex-1 md:w-64">
                <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-gray-400">
                    <FaIcon @icon="search" class="text-xs" />
                </div>
                <Input
                    @type="text"
                    @value={{this.searchQuery}}
                    placeholder="Search passenger, driver, vehicle, ID..."
                    class="form-input form-input-sm w-full pl-8 bg-gray-950/80 border-gray-700 text-gray-100 placeholder-gray-500 text-xs rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                />
                {{#if this.searchQuery}}
                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 pr-2.5 flex items-center text-gray-400 hover:text-white text-xs"
                        {{on "click" this.clearSearch}}
                    >
                        <FaIcon @icon="times-circle" />
                    </button>
                {{/if}}
            </div>

            {{!-- Quick Status Filter Tabs --}}
            <div class="flex items-center bg-gray-950 p-0.5 rounded-lg border border-gray-800 text-xs">
                <button
                    type="button"
                    class="px-2.5 py-1 rounded-md transition-colors font-medium {{if (eq this.statusFilter 'all') 'bg-blue-600 text-white shadow' 'text-gray-400 hover:text-white'}}"
                    {{on "click" (fn this.setStatusFilter "all")}}
                >
                    All
                </button>
                <button
                    type="button"
                    class="px-2.5 py-1 rounded-md transition-colors font-medium {{if (eq this.statusFilter 'draft') 'bg-gray-700 text-white shadow' 'text-gray-400 hover:text-white'}}"
                    {{on "click" (fn this.setStatusFilter "draft")}}
                >
                    Draft
                </button>
                <button
                    type="button"
                    class="px-2.5 py-1 rounded-md transition-colors font-medium {{if (eq this.statusFilter 'dispatched') 'bg-blue-600 text-white shadow' 'text-gray-400 hover:text-white'}}"
                    {{on "click" (fn this.setStatusFilter "dispatched")}}
                >
                    Dispatched
                </button>
                <button
                    type="button"
                    class="px-2.5 py-1 rounded-md transition-colors font-medium {{if (eq this.statusFilter 'enroute') 'bg-amber-600 text-white shadow' 'text-gray-400 hover:text-white'}}"
                    {{on "click" (fn this.setStatusFilter "enroute")}}
                >
                    En Route
                </button>
                <button
                    type="button"
                    class="px-2.5 py-1 rounded-md transition-colors font-medium {{if (eq this.statusFilter 'arrived') 'bg-purple-600 text-white shadow' 'text-gray-400 hover:text-white'}}"
                    {{on "click" (fn this.setStatusFilter "arrived")}}
                >
                    On Location
                </button>
                <button
                    type="button"
                    class="px-2.5 py-1 rounded-md transition-colors font-medium {{if (eq this.statusFilter 'in_progress') 'bg-emerald-600 text-white shadow' 'text-gray-400 hover:text-white'}}"
                    {{on "click" (fn this.setStatusFilter "in_progress")}}
                >
                    POB
                </button>
                <button
                    type="button"
                    class="px-2.5 py-1 rounded-md transition-colors font-medium {{if (eq this.statusFilter 'conflict') 'bg-red-600 text-white shadow' 'text-gray-400 hover:text-white'}}"
                    {{on "click" (fn this.setStatusFilter "conflict")}}
                >
                    Conflicts
                </button>
            </div>
        </div>
    </div>

    {{!-- Table Container --}}
    <div class="dispatch-grid-table-container flex-1 overflow-x-auto overflow-y-auto rounded-xl border border-gray-800 bg-gray-900/60 shadow-xl custom-scrollbar">
        <table class="w-full text-left border-collapse min-w-[1100px]">
            <thead class="dispatch-grid-thead sticky top-0 z-10 bg-[#111827] border-b border-gray-700 shadow-md">
                <tr class="bg-[#111827] border-b border-gray-700">
                    <th class="dispatch-grid-th py-3.5 px-3.5 w-36 min-w-[140px] text-[#F9FAFB] font-bold text-xs uppercase tracking-wider bg-[#111827]">Order ID</th>
                    <th class="dispatch-grid-th py-3.5 px-3.5 w-52 min-w-[180px] text-[#F9FAFB] font-bold text-xs uppercase tracking-wider bg-[#111827]">Passenger</th>
                    <th class="dispatch-grid-th py-3.5 px-3.5 w-44 min-w-[160px] text-[#F9FAFB] font-bold text-xs uppercase tracking-wider bg-[#111827]">Vehicle</th>
                    <th class="dispatch-grid-th py-3.5 px-3.5 w-52 min-w-[190px] text-[#F9FAFB] font-bold text-xs uppercase tracking-wider bg-[#111827]">Chauffeur</th>
                    <th class="dispatch-grid-th py-3.5 px-3.5 min-w-[220px] text-[#F9FAFB] font-bold text-xs uppercase tracking-wider bg-[#111827]">Pickup</th>
                    <th class="dispatch-grid-th py-3.5 px-3.5 min-w-[220px] text-[#F9FAFB] font-bold text-xs uppercase tracking-wider bg-[#111827]">Dropoff</th>
                    <th class="dispatch-grid-th py-3.5 px-3.5 w-48 min-w-[170px] text-[#F9FAFB] font-bold text-xs uppercase tracking-wider bg-[#111827]">Flags &amp; Conflicts</th>
                    <th class="dispatch-grid-th py-3.5 px-2 w-12 text-center bg-[#111827]"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800/60 text-sm">
                {{#if this.gridRows.length}}
                    {{#each this.gridRows as |item|}}
                        <tr
                            class="dispatch-grid-row {{item.rowClass}} transition-colors duration-150 cursor-pointer select-none"
                            {{on "click" (fn this.onClickRow item.order)}}
                        >
                            {{!-- Column 1: Order ID --}}
                            <td class="py-3 px-3.5 align-middle">
                                <div class="flex flex-col">
                                    <div class="font-mono font-bold text-white text-xs tracking-tight flex items-center space-x-1.5">
                                        <span>{{item.orderInfo.id}}</span>
                                    </div>
                                    {{#if item.orderInfo.scheduledAt}}
                                        <div class="text-[11px] text-gray-300 mt-0.5 flex items-center space-x-1">
                                            <FaIcon @icon="clock" class="text-[10px] text-gray-400" />
                                            <span>{{item.orderInfo.scheduledAt}}</span>
                                        </div>
                                    {{/if}}
                                    <div class="mt-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold tracking-wide uppercase {{item.statusBadgeClass}}">
                                            {{item.orderInfo.statusTitle}}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            {{!-- Column 2: Passenger --}}
                            <td class="py-3 px-3.5 align-middle">
                                <div class="flex flex-col">
                                    <div class="font-semibold text-white text-sm">
                                        {{item.passenger.name}}
                                    </div>
                                    {{#if item.passenger.phone}}
                                        <div class="text-xs text-gray-300 mt-0.5 flex items-center space-x-1">
                                            <FaIcon @icon="phone" class="text-[10px] text-gray-400" />
                                            <span>{{item.passenger.phone}}</span>
                                        </div>
                                    {{/if}}
                                    {{#if item.passenger.note}}
                                        <div class="text-[11px] text-gray-400 italic truncate max-w-xs mt-0.5" title={{item.passenger.note}}>
                                            {{item.passenger.note}}
                                        </div>
                                    {{/if}}
                                </div>
                            </td>

                            {{!-- Column 3: Vehicle --}}
                            <td class="py-3 px-3.5 align-middle">
                                <div class="flex flex-col">
                                    {{#if item.vehicle.assigned}}
                                        <div class="font-medium text-white text-xs">
                                            {{item.vehicle.type}}
                                        </div>
                                        {{#if item.vehicle.plate}}
                                            <div class="mt-1 inline-flex items-center px-1.5 py-0.5 rounded bg-gray-800/90 text-gray-200 border border-gray-700 font-mono text-[10px] font-semibold tracking-wider w-fit">
                                                <FaIcon @icon="car" class="mr-1 text-[9px] text-gray-400" />
                                                {{item.vehicle.plate}}
                                            </div>
                                        {{/if}}
                                    {{else}}
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-gray-800/80 text-gray-400 border border-gray-700/60 w-fit">
                                            <FaIcon @icon="car" class="mr-1 text-gray-500 text-[10px]" />
                                            Unassigned
                                        </span>
                                    {{/if}}
                                </div>
                            </td>

                            {{!-- Column 4: Chauffeur --}}
                            <td class="py-3 px-3.5 align-middle">
                                <div class="flex items-center space-x-2.5">
                                    <img
                                        src={{item.driver.avatar_url}}
                                        alt={{item.driver.name}}
                                        width="32"
                                        height="32"
                                        loading="lazy"
                                        class="w-8 h-8 rounded-full flex-shrink-0 object-cover border border-gray-700 bg-gray-800 shadow-sm"
                                        onerror="this.onerror=null;this.src='/images/no-avatar.png';"
                                    />
                                    <div class="flex flex-col min-w-0 truncate">
                                        <div class="font-semibold text-white text-xs truncate">
                                            {{item.driver.name}}
                                        </div>
                                        <div class="inline-flex items-center space-x-1.5 mt-0.5">
                                            <span class="w-1.5 h-1.5 rounded-full {{item.driver.dotClass}} flex-shrink-0"></span>
                                            <span class="text-[11px] font-medium {{item.driver.statusClass}} truncate">{{item.driver.status}}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{!-- Column 5: Pickup --}}
                            <td class="py-3 px-3.5 align-middle">
                                <div class="flex items-start space-x-2">
                                    <div class="mt-0.5 shrink-0 text-emerald-400">
                                        <FaIcon @icon="map-marker-alt" class="text-xs" />
                                    </div>
                                    <div class="flex flex-col min-w-0">
                                        {{#if item.pickup.name}}
                                            <div class="font-semibold text-white text-xs truncate" title={{item.pickup.name}}>
                                                {{item.pickup.name}}
                                            </div>
                                        {{/if}}
                                        <div class="text-xs text-gray-300 line-clamp-2 leading-relaxed" title={{item.pickup.address}}>
                                            {{item.pickup.address}}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{!-- Column 6: Dropoff --}}
                            <td class="py-3 px-3.5 align-middle">
                                <div class="flex items-start space-x-2">
                                    <div class="mt-0.5 shrink-0 text-red-400">
                                        <FaIcon @icon="flag-checkered" class="text-xs" />
                                    </div>
                                    <div class="flex flex-col min-w-0">
                                        {{#if item.dropoff.name}}
                                            <div class="font-semibold text-white text-xs truncate" title={{item.dropoff.name}}>
                                                {{item.dropoff.name}}
                                            </div>
                                        {{/if}}
                                        <div class="text-xs text-gray-300 line-clamp-2 leading-relaxed" title={{item.dropoff.address}}>
                                            {{item.dropoff.address}}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{!-- Column 7: Flags & Conflicts --}}
                            <td class="py-3 px-3.5 align-middle">
                                <div class="flex flex-wrap gap-1 items-center">
                                    {{#if item.flags.length}}
                                        {{#each item.flags as |flag|}}
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold tracking-wide border {{flag.badgeClass}}">
                                                <FaIcon @icon={{flag.icon}} class="mr-1 text-[9px]" />
                                                {{flag.label}}
                                            </span>
                                        {{/each}}
                                    {{else}}
                                        <span class="text-xs text-gray-500 font-normal italic">
                                            None
                                        </span>
                                    {{/if}}
                                </div>
                            </td>

                            {{!-- Column 8: Actions --}}
                            <td class="py-3 px-2 align-middle text-center" {{on "click" this.stopEventPropagation}}>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-gray-800 text-gray-300 hover:text-white hover:bg-gray-700 transition-colors border border-gray-700 shadow-sm"
                                    title="View Order Details"
                                    {{on "click" (fn this.onClickRow item.order)}}
                                >
                                    <FaIcon @icon="eye" class="text-xs" />
                                </button>
                            </td>
                        </tr>
                    {{/each}}
                {{else}}
                    <tr>
                        <td colspan="8" class="py-16 text-center text-gray-400">
                            <div class="flex flex-col items-center justify-center space-y-3">
                                <div class="w-12 h-12 rounded-full bg-gray-900 border border-gray-800 flex items-center justify-center text-gray-500">
                                    <FaIcon @icon="table-cells" class="text-xl" />
                                </div>
                                <div class="text-sm font-semibold text-white">No active orders found in Dispatch Grid</div>
                                <div class="text-xs text-gray-400 max-w-sm">
                                    {{#if this.searchQuery}}
                                        No orders matching "{{this.searchQuery}}". Try clearing the search filter.
                                    {{else}}
                                        All orders are completed or no orders match the selected status filter.
                                    {{/if}}
                                </div>
                            </div>
                        </td>
                    </tr>
                {{/if}}
            </tbody>
        </table>
    </div>
</div>`;

const dispatchGridCompiled = compiler.precompile(dispatchGridHbs, {
    moduleName: '@fleetbase/fleetops-engine/components/dispatch-grid.hbs'
});
console.log('✓ dispatch-grid.hbs compiled');

// 2. Template for operations/dispatch-grid.hbs
const operationsDispatchGridHbs = `<Layout::Section::Header @title="Dispatch Grid">
    <div class="flex items-center space-x-2">
        <Button @text="Dashboard" @icon="home" @onClick={{transition-to "operations.orders.index"}} />
        <Button @text="New Order" @icon="plus" @type="primary" @onClick={{this.orderActions.transition.create}} />
    </div>
</Layout::Section::Header>
<Layout::Section::Body class="no-padding overflow-hidden h-full">
    <DispatchGrid @orders={{@model}} @onOrderClick={{fn this.orderActions.transition.view}} />
</Layout::Section::Body>
{{outlet}}`;

const operationsDispatchGridCompiled = compiler.precompile(operationsDispatchGridHbs, {
    moduleName: '@fleetbase/fleetops-engine/templates/operations/dispatch-grid.hbs'
});
console.log('✓ operations/dispatch-grid.hbs compiled');

// 2b. Template for operations/orders/index.hbs (Dashboard with Map, Tabular, Board, Grid, and outlet)
const operationsOrdersIndexHbs = `<MapContainer::Topbar class="next-topbar-{{this.layout}}">
    <MapContainer::ViewSwitch>
        <FaIcon @icon="layer-group" class="mr-2 text-gray-400 dark:text-gray-600" />
        <button type="button" id="ops-map-view-button" class="{{if (eq this.layout 'map') 'active'}}" {{on "click" (fn this.changeLayout "map")}}>{{t "common.map"}}</button>
        <button type="button" id="ops-table-view-button" class="{{if (eq this.layout 'table') 'active'}} flex flex-row items-center relative" {{on "click" (fn this.changeLayout "table")}}>
            <div class="mr-1.5">{{t "common.table"}}</div>
            <Badge
                class="flex items-center justify-center -mr-4"
                @spanClass="rounded-full items-center justify-center h-5 w-7"
                @hideStatusDot={{true}}
                @status="info"
            >{{@model.meta.total}}</Badge>
        </button>
        <button type="button" id="ops-kanban-view-button" class="{{if (eq this.layout 'kanban') 'active'}}" {{on "click" (fn this.changeLayout "kanban")}}>{{t "common.board"}}</button>
        <button type="button" id="ops-grid-view-button" class="{{if (eq this.layout 'grid') 'active'}} ops-grid-view-button" {{on "click" (fn this.changeLayout "grid")}}>Dispatch Grid</button>
    </MapContainer::ViewSwitch>
</MapContainer::Topbar>

{{#if (eq this.layout "map")}}
    <Map as |Map|>
        <Map.Container>
            <Map.Toolbar />
            <Map.Search />
            <Map.LeafletLiveMap />
            <Map.OrderList />
            <Map.Drawer />
        </Map.Container>
    </Map>
{{/if}}

{{#if (eq this.layout "table")}}
    <Layout::Resource::Tabular
        @resource="order"
        @title={{t "resource.orders"}}
        @searchQuery={{this.query}}
        @onSearch={{perform this.orderActions.controllerSearchTask this}}
        @bulkSearchValue={{this.bulk_query}}
        @onClearBulkSearch={{fn (mut this.bulk_query)}}
        @onSubmitBulkSearch={{fn (mut this.bulk_query)}}
        @bulkSearchPlaceholder={{t "prompt.order-bulk-search"}}
        @data={{@model}}
        @columns={{this.columns}}
        @page={{this.page}}
        @onPageChange={{fn (mut this.page)}}
        @setupTable={{fn (mut this.table)}}
        @actionButtons={{this.actionButtons}}
        @bulkActions={{this.bulkActions}}
        @filterPickerGridCols="3"
        @filterPickerWidth="700px"
        @controller={{this}}
        @tfootVerticalOffset="7"
        @tfootVerticalOffsetElements="#next-view-section-subheader,.next-table-wrapper > table > thead,.next-map-container-topbar"
    />
{{/if}}

{{#if (eq this.layout "kanban")}}
    <Layout::Section::Header
        @title={{t "menu.order-board"}}
        @subtitle={{@model.length}}
        @subtitleClass="text-xs text-center font-semibold ml-2 w-6 h-6 rounded-full bg-blue-100 text-blue-900 dark:bg-blue-900 dark:text-blue-100 flex items-center justify-center"
        @actionsWrapperClass="space-x-1"
    >
        <div class="flex flex-row items-center space-x-2">
            <div class="w-64">
                <div class="fleetbase-model-select fleetbase-power-select ember-model-select">
                    <ModelSelect
                        @modelName="order-config"
                        @selectedModel={{or this.orderConfig this.type}}
                        @placeholder={{t "order.fields.order-type-placeholder"}}
                        @triggerClass="form-select form-input order-board-type-filter"
                        @infiniteScroll={{false}}
                        @renderInPlace={{true}}
                        @allowClear={{true}}
                        @onChange={{fn (mut this.orderConfig)}}
                        @onChangeId={{fn (mut this.type)}}
                        as |orderConfig|
                    >
                        <div class="text-sm">
                            <div class="font-semibold normalize-in-trigger">{{orderConfig.name}}</div>
                            <div class="hide-from-trigger">{{n-a orderConfig.description}}</div>
                        </div>
                    </ModelSelect>
                </div>
            </div>
        </div>
    </Layout::Section::Header>
    <Layout::Section::Body>
        <Order::Kanban @orders={{@model}} @headerOffset={{160}} @orderConfig={{this.type}} />
    </Layout::Section::Body>
{{/if}}

{{#if (eq this.layout "grid")}}
    <div class="flex-1 overflow-hidden h-full">
        <DispatchGrid @orders={{@model}} @onOrderClick={{fn this.orderActions.transition.view}} />
    </div>
{{/if}}

{{outlet}}`;

const operationsOrdersIndexCompiled = compiler.precompile(operationsOrdersIndexHbs, {
    moduleName: '@fleetbase/fleetops-engine/templates/operations/orders/index.hbs'
});
console.log('✓ operations/orders/index.hbs compiled');

// 2c. Template for order/panel-header.hbs
const orderPanelHeaderHbs = `<div class="px-4 py-2">
    <div class="flex flex-1 flex-row items-start justify-between">
        <div class="flex flex-row space-x-3">
            <div class="pt-1">
                <div class="rounded-md bg-white p-1">
                    <img src={{concat "data:image/png;base64," @resource.tracking_number.qr_code}} class="w-12 h-12" alt={{@resource.public_id}} />
                </div>
            </div>
            <div class="flex flex-col">
                <div class="font-semibold">{{@resource.tracking}}</div>
                <div class="flex flex-row space-x-1">
                    <Badge @status={{@resource.status}} />
                    {{#if @resource.dispatched_at}}
                        <Badge @status="dispatched">{{concat "Dispatched at " @resource.dispatchedAt}}</Badge>
                    {{/if}}
                </div>
                <div class="mt-1">
                    {{#if this.formattedCreatedAt}}
                        <div class="text-xs text-gray-400 dark:text-gray-500">Date Created: {{this.formattedCreatedAt}}</div>
                    {{/if}}
                    {{#if this.formattedType}}
                        <div class="text-xs text-gray-400 dark:text-gray-500">Type: {{smart-humanize this.formattedType}}</div>
                    {{/if}}
                </div>
            </div>
        </div>
        <div class="next-view-header-right">
            <Layout::Resource::Panel::HeaderActions
                @resource={{@resource}}
                @saveTask={{@saveTask}}
                @saveOptions={{@saveOptions}}
                @saveDisabled={{@saveDisabled}}
                @pojoResource={{@pojoResource}}
                @authSchema={{@authSchema}}
                @actionButtons={{@actionButtons}}
                @onPressCancel={{@onPressCancel}}
            />
        </div>
    </div>
</div>`;

const orderPanelHeaderCompiled = compiler.precompile(orderPanelHeaderHbs, {
    moduleName: '@fleetbase/fleetops-engine/components/order/panel-header.hbs'
});
console.log('✓ order/panel-header.hbs compiled');


// Write source files in console/app & node_modules
fs.mkdirSync('/home/wert/fleetbase/console/app/components', { recursive: true });
fs.writeFileSync('/home/wert/fleetbase/console/app/components/dispatch-grid.hbs', dispatchGridHbs, 'utf-8');

fs.mkdirSync('/home/wert/fleetbase/console/app/templates/console', { recursive: true });
fs.writeFileSync('/home/wert/fleetbase/console/app/templates/console/dispatch-grid.hbs', `{{outlet}}`, 'utf-8');

fs.mkdirSync('/home/wert/fleetbase/console/app/routes/console', { recursive: true });
fs.writeFileSync('/home/wert/fleetbase/console/app/routes/console/dispatch-grid.js', `import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class ConsoleDispatchGridRoute extends Route {
    @service router;

    beforeModel() {
        return this.router.transitionTo('console.fleet-ops.operations.dispatch-grid');
    }
}
`, 'utf-8');

const nodeEngineDir = '/home/wert/fleetbase/console/node_modules/.pnpm/@fleetbase+fleetops-engine@0.6.49_b421113b8e787158f8d42852c5267750/node_modules/@fleetbase/fleetops-engine';
fs.mkdirSync(path.join(nodeEngineDir, 'addon/components'), { recursive: true });
fs.writeFileSync(path.join(nodeEngineDir, 'addon/components/dispatch-grid.hbs'), dispatchGridHbs, 'utf-8');
fs.mkdirSync(path.join(nodeEngineDir, 'addon/templates/operations'), { recursive: true });
fs.writeFileSync(path.join(nodeEngineDir, 'addon/templates/operations/dispatch-grid.hbs'), operationsDispatchGridHbs, 'utf-8');

fs.writeFileSync(path.join(nodeEngineDir, 'addon/templates/operations/orders/index.hbs'), operationsOrdersIndexHbs, 'utf-8');
fs.writeFileSync(path.join(nodeEngineDir, 'addon/components/order/panel-header.hbs'), orderPanelHeaderHbs, 'utf-8');
fs.writeFileSync(path.join(nodeEngineDir, 'addon/components/order/panel-header.js'), `import Component from '@glimmer/component';

export default class OrderPanelHeaderComponent extends Component {
    get formattedCreatedAt() {
        const val = this.args.resource?.createdAt;
        if (typeof val === 'string' && val.length > 0) {
            return val;
        }
        return null;
    }

    get formattedType() {
        const val = this.args.resource?.type;
        if (typeof val === 'string' && val.length > 0) {
            return val;
        }
        return null;
    }
}
`, 'utf-8');

fs.writeFileSync(path.join(nodeEngineDir, 'addon/routes/operations/dispatch-grid.js'), `import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class OperationsDispatchGridRoute extends Route {
    @service store;
    @service notifications;
    @service hostRouter;
    @service abilities;
    @service intl;

    queryParams = {
        page: { refreshModel: true },
        limit: { refreshModel: true },
        sort: { refreshModel: true },
        query: { refreshModel: true },
        status: { refreshModel: true },
    };

    beforeModel() {
        if (this.abilities.cannot('fleet-ops list order')) {
            this.notifications.warning(this.intl.t('common.unauthorized-access'));
            return this.hostRouter.transitionTo('console.fleet-ops');
        }
    }

    model(params) {
        return this.store.query('order', {
            sort: '-created_at',
            ...params,
        });
    }
}
`, 'utf-8');

fs.writeFileSync(path.join(nodeEngineDir, 'addon/controllers/operations/dispatch-grid.js'), `import Controller from '@ember/controller';
import { inject as service } from '@ember/service';

export default class OperationsDispatchGridController extends Controller {
    @service orderActions;
    @service orderSocketEvents;
    @service hostRouter;

    constructor() {
        super(...arguments);
        this.orderSocketEvents.startCompany();
    }
}
`, 'utf-8');

// Also update ember-ui smart-humanize
try {
    const nodeUiDir = fs.realpathSync('/home/wert/fleetbase/console/node_modules/@fleetbase/ember-ui');
    const smartHumanizePath = path.join(nodeUiDir, 'addon/utils/smart-humanize.js');
    let smartHumanizeSrc = fs.readFileSync(smartHumanizePath, 'utf-8');
    if (!smartHumanizeSrc.includes("typeof string === 'function'")) {
        smartHumanizeSrc = smartHumanizeSrc.replace(
            "if (typeOf(string) !== 'string') {",
            "if (typeof string === 'function' || typeOf(string) !== 'string') {"
        );
        fs.writeFileSync(smartHumanizePath, smartHumanizeSrc, 'utf-8');
        console.log('✓ Updated smart-humanize.js in ember-ui');
    }
} catch (e) {
    console.warn('Could not update ember-ui smart-humanize source:', e.message);
}


// Update node_modules addon/routes.js to include dispatch-grid
const engineRoutesPath = path.join(nodeEngineDir, 'addon/routes.js');
let engineRoutesSrc = fs.readFileSync(engineRoutesPath, 'utf-8');
if (!engineRoutesSrc.includes("'dispatch-grid'")) {
    engineRoutesSrc = engineRoutesSrc.replace("this.route('calendar');", "this.route('calendar');\n        this.route('dispatch-grid', function () {});");
    fs.writeFileSync(engineRoutesPath, engineRoutesSrc, 'utf-8');
    console.log('✓ Updated fleetops-engine addon/routes.js');
}

// 3. Build JS component definition for DispatchGrid
const dispatchGridComponentJs = `define("@fleetbase/fleetops-engine/components/dispatch-grid", [
  "exports",
  "@ember/component",
  "@glimmer/component",
  "@glimmer/tracking",
  "@ember/service",
  "@ember/object",
  "@ember/array",
  "@ember/template-factory"
], function (_exports, _component, _component2, _tracking, _service, _object, _array, _templateFactory) {
  "use strict";

  Object.defineProperty(_exports, "__esModule", {
    value: true
  });
  _exports.default = void 0;
  var _class, _descriptor, _descriptor2, _descriptor3;
  function _initializerDefineProperty(e, i, r, l) { r && Object.defineProperty(e, i, { enumerable: r.enumerable, configurable: r.configurable, writable: r.writable, value: r.initializer ? r.initializer.call(l) : void 0 }); }
  function _applyDecoratedDescriptor(i, e, r, n, l) { var a = {}; return Object.keys(n).forEach(function (i) { a[i] = n[i]; }), a.enumerable = !!a.enumerable, a.configurable = !!a.configurable, ("value" in a || a.initializer) && (a.writable = !0), a = r.slice().reverse().reduce(function (r, n) { return n(i, e, r) || r; }, a), l && void 0 !== a.initializer && (a.value = a.initializer ? a.initializer.call(l) : void 0, a.initializer = void 0), void 0 === a.initializer ? (Object.defineProperty(i, e, a), null) : a; }

  const __COLOCATED_TEMPLATE__ = (0, _templateFactory.createTemplateFactory)(${dispatchGridCompiled});

  const _driverAvatarCache = new Map();
  function _resolveDriverAvatar(d) {
    if (!d) return '/images/no-avatar.png';
    const key = d.id || d.public_id || d.name || 'unassigned';
    if (_driverAvatarCache.has(key)) {
      return _driverAvatarCache.get(key);
    }
    let url = d.avatar_url || d.photo_url || '/images/no-avatar.png';
    if (typeof url !== 'string' || !url.trim() || url.includes('localhost:8000') || url.includes('futurelimo.website/storage/uploads/fleetbase-optimized-avatar.png')) {
      url = '/images/no-avatar.png';
    }
    _driverAvatarCache.set(key, url);
    return url;
  }

  let DispatchGridComponent = _exports.default = (_class = class DispatchGridComponent extends _component2.default {
    constructor(...args) {
      super(...args);
      _initializerDefineProperty(this, "searchQuery", _descriptor, this);
      _initializerDefineProperty(this, "statusFilter", _descriptor2, this);
      _initializerDefineProperty(this, "orderActions", _descriptor3, this);
    }

    get orders() {
      const orders = this.args.orders ?? [];
      if ((0, _array.isArray)(orders)) {
        return orders;
      }
      return orders.toArray ? orders.toArray() : [];
    }

    get filteredOrders() {
      let list = this.orders;
      if (this.searchQuery && this.searchQuery.trim()) {
        const q = this.searchQuery.trim().toLowerCase();
        list = list.filter(order => {
          const id = (order.public_id || '').toLowerCase();
          const trk = (order.tracking || '').toLowerCase();
          const cust = (order.customer?.name || order.customer_name || '').toLowerCase();
          const drv = (order.driver_assigned?.name || order.driver_name || '').toLowerCase();
          const veh = (order.vehicle_assigned?.displayName || order.vehicle_assigned?.name || order.vehicle_assigned?.plate_number || '').toLowerCase();
          const p = (order.pickup_name || order.payload?.pickup?.address || '').toLowerCase();
          const d = (order.dropoff_name || order.payload?.dropoff?.address || '').toLowerCase();
          const n = (order.notes || '').toLowerCase();
          return id.includes(q) || trk.includes(q) || cust.includes(q) || drv.includes(q) || veh.includes(q) || p.includes(q) || d.includes(q) || n.includes(q);
        });
      }

      if (this.statusFilter && this.statusFilter !== 'all') {
        list = list.filter(order => {
          const st = order.status;
          if (this.statusFilter === 'draft') return st === 'created' || !order.has_driver_assigned;
          if (this.statusFilter === 'dispatched') return st === 'dispatched';
          if (this.statusFilter === 'enroute') return st === 'enroute_pickup';
          if (this.statusFilter === 'arrived') return st === 'on_location';
          if (this.statusFilter === 'in_progress') return st === 'pob';
          if (this.statusFilter === 'conflict') return this.hasConflict(order) || st === 'canceled';
          return true;
        });
      }

      return list;
    }

    hasConflict(order) {
      if (!order) return false;
      if (order.has_conflict || order.meta?.has_conflict || order.meta?.conflict) return true;
      const notes = (order.notes || '').toLowerCase();
      if (notes.includes('conflict') || notes.includes('⚠️') || notes.includes('warning: pickup and dropoff')) return true;
      return false;
    }

    getRowClass(order) {
      if (this.hasConflict(order) || order.status === 'canceled') {
        return 'dispatch-grid-row-conflict';
      }
      switch (order.status) {
        case 'pob':
          return 'dispatch-grid-row-in-progress';
        case 'on_location':
          return 'dispatch-grid-row-arrived';
        case 'enroute_pickup':
          return 'dispatch-grid-row-enroute';
        case 'dispatched':
          return 'dispatch-grid-row-dispatched';
        case 'created':
        default:
          return 'dispatch-grid-row-draft';
      }
    }

    getStatusBadgeClass(status) {
      switch (status) {
        case 'dispatched':
          return 'bg-blue-900/80 text-blue-200 border border-blue-500/60 shadow-sm';
        case 'enroute_pickup':
          return 'bg-amber-900/80 text-amber-200 border border-amber-500/60 shadow-sm';
        case 'on_location':
          return 'bg-purple-900/80 text-purple-200 border border-purple-500/60 shadow-sm';
        case 'pob':
          return 'bg-orange-900/80 text-orange-200 border border-orange-500/60 shadow-sm';
        case 'completed':
          return 'bg-emerald-900/80 text-emerald-200 border border-emerald-500/60 shadow-sm';
        case 'canceled':
          return 'bg-red-900/80 text-red-200 border border-red-500/60 shadow-sm';
        case 'created':
        default:
          return 'bg-gray-800 text-gray-200 border border-gray-600 shadow-sm';
      }
    }

    getOrderInfo(order) {
      const id = order.public_id || order.id || 'N/A';
      const internalId = order.internal_id || order.tracking || '';
      const scheduledAt = order.scheduled_at_formatted || order.scheduled_at || order.createdAtShort || '';
      const statusTitles = {
        created: 'Created',
        dispatched: 'Dispatched',
        enroute_pickup: 'En Route',
        on_location: 'On Location',
        pob: 'Passenger On Board',
        completed: 'Completed',
        canceled: 'Canceled'
      };
      const st = order.status || 'created';
      const statusTitle = statusTitles[st] || st;
      return { id, internalId, scheduledAt, status: st, statusTitle };
    }

    getPassengerInfo(order) {
      const name = order.customer?.name || order.customer_name || 'Guest Passenger';
      const phone = order.customer?.phone || order.customer_phone || '';
      let note = '';
      if (order.notes) {
        const lines = order.notes.split('\\n').filter(l => !l.includes('Coordinates:') && !l.includes('Locations:'));
        note = lines.slice(0, 2).join(' ').trim();
      }
      return { name, phone, note };
    }

    getVehicleInfo(order) {
      const v = order.vehicle_assigned;
      if (!v && !order.meta?.vehicle) {
        return { assigned: false, type: 'Unassigned', plate: null };
      }
      const type = v?.type || v?.name || order.meta?.vehicle_type || 'Executive Vehicle';
      const plate = v?.plate_number || v?.displayName || order.meta?.plate || null;
      return { assigned: true, type, plate };
    }

    getDriverInfo(order) {
      const d = order.driver_assigned;
      if (!d && !order.driver_name) {
        return {
          assigned: false,
          name: 'Unassigned',
          avatar_url: '/images/no-avatar.png',
          avatarUrl: '/images/no-avatar.png',
          photoUrl: '/images/no-avatar.png',
          status: 'Unassigned',
          dotClass: 'bg-gray-500',
          statusClass: 'text-gray-400 font-normal italic'
        };
      }
      const name = d?.name || order.driver_name || 'Assigned Driver';
      const avatar_url = _resolveDriverAvatar(d);

      let status = 'Online';
      let dotClass = 'bg-emerald-500';
      let statusClass = 'text-emerald-400 font-medium';

      if (order.status === 'enroute_pickup') {
        status = 'En Route';
        dotClass = 'bg-amber-400';
        statusClass = 'text-amber-300 font-medium';
      } else if (order.status === 'on_location') {
        status = 'On Location';
        dotClass = 'bg-purple-400';
        statusClass = 'text-purple-300 font-medium';
      } else if (order.status === 'pob') {
        status = 'In Progress';
        dotClass = 'bg-emerald-400 animate-pulse';
        statusClass = 'text-emerald-300 font-medium';
      } else if (d?.online === false) {
        status = 'Offline';
        dotClass = 'bg-gray-500';
        statusClass = 'text-gray-400 font-medium';
      }

      return {
        assigned: true,
        name,
        avatar_url,
        avatarUrl: avatar_url,
        photoUrl: avatar_url,
        status,
        dotClass,
        statusClass
      };
    }

    getPickupInfo(order) {
      const p = order.payload?.pickup;
      const name = p?.name || order.pickup_name || '';
      const address = p?.address || p?.street1 || 'No pickup specified';
      return { name, address };
    }

    getDropoffInfo(order) {
      const d = order.payload?.dropoff;
      const name = d?.name || order.dropoff_name || '';
      const address = d?.address || d?.street1 || 'No dropoff specified';
      return { name, address };
    }

    getFlags(order) {
      const flags = [];
      const notes = (order.notes || '').toLowerCase();
      const meta = order.meta || {};

      if (this.hasConflict(order)) {
        flags.push({
          type: 'conflict',
          label: 'Conflict Alert',
          icon: 'exclamation-triangle',
          badgeClass: 'bg-red-900/80 text-red-200 border-red-500/60 shadow-sm'
        });
      }

      if (notes.includes('meet') || notes.includes('greet') || meta.meet_and_greet || notes.includes('inside pickup')) {
        flags.push({
          type: 'meet_greet',
          label: 'Meet & Greet',
          icon: 'user-tag',
          badgeClass: 'bg-amber-900/80 text-amber-200 border-amber-500/60 shadow-sm'
        });
      }

      if (notes.includes('vip') || meta.vip || notes.includes('priority: high')) {
        flags.push({
          type: 'vip',
          label: 'VIP',
          icon: 'crown',
          badgeClass: 'bg-purple-900/80 text-purple-200 border-purple-500/60 shadow-sm'
        });
      }

      if (notes.includes('airport') || notes.includes('flight') || meta.airport || notes.includes('lga') || notes.includes('jfk') || notes.includes('ewr')) {
        flags.push({
          type: 'airport',
          label: 'Airport Transfer',
          icon: 'plane',
          badgeClass: 'bg-blue-900/80 text-blue-200 border-blue-500/60 shadow-sm'
        });
      }

      const tagMatch = (order.notes || '').match(/\\[Tags:\\s*([^\\]]+)\\]/i);
      if (tagMatch && tagMatch[1]) {
        const rawTags = tagMatch[1].split(',').map(t => t.trim().toLowerCase());
        for (const t of rawTags) {
          if (t === 'vip' || t === 'airport-transfer' || t === 'meet-greet') continue;
          flags.push({
            type: 'tag',
            label: t.charAt(0).toUpperCase() + t.slice(1),
            icon: 'tag',
            badgeClass: 'bg-gray-800 text-gray-200 border-gray-600 shadow-sm'
          });
        }
      }

      return flags;
    }

    get gridRows() {
      return this.filteredOrders.map(order => ({
        order,
        rowClass: this.getRowClass(order),
        statusBadgeClass: this.getStatusBadgeClass(order.status),
        orderInfo: this.getOrderInfo(order),
        passenger: this.getPassengerInfo(order),
        vehicle: this.getVehicleInfo(order),
        driver: this.getDriverInfo(order),
        pickup: this.getPickupInfo(order),
        dropoff: this.getDropoffInfo(order),
        flags: this.getFlags(order)
      }));
    }

    setStatusFilter = (filter) => {
      this.statusFilter = filter;
    };

    clearSearch = () => {
      this.searchQuery = '';
    };

    onClickRow = (order) => {
      if (typeof this.args.onOrderClick === 'function') {
        this.args.onOrderClick(order);
      } else if (this.orderActions?.transition?.view) {
        this.orderActions.transition.view(order);
      }
    };

    stopEventPropagation = (e) => {
      e?.stopPropagation?.();
    };
  }, _descriptor = _applyDecoratedDescriptor(_class.prototype, "searchQuery", [_tracking.tracked], {
    configurable: true,
    enumerable: true,
    writable: true,
    initializer: function () {
      return '';
    }
  }), _descriptor2 = _applyDecoratedDescriptor(_class.prototype, "statusFilter", [_tracking.tracked], {
    configurable: true,
    enumerable: true,
    writable: true,
    initializer: function () {
      return 'all';
    }
  }), _descriptor3 = _applyDecoratedDescriptor(_class.prototype, "orderActions", [_service.inject], {
    configurable: true,
    enumerable: true,
    writable: true,
    initializer: null
  }), _class);

  (0, _component.setComponentTemplate)(__COLOCATED_TEMPLATE__, DispatchGridComponent);
});
`;

// 4. Route and Controller JS for fleetops-engine
const operationsDispatchGridRouteJs = `define("@fleetbase/fleetops-engine/routes/operations/dispatch-grid", ["exports", "@ember/routing/route", "@ember/service"], function (_exports, _route, _service) {
  "use strict";

  Object.defineProperty(_exports, "__esModule", {
    value: true
  });
  _exports.default = void 0;
  var _class, _descriptor, _descriptor2, _descriptor3, _descriptor4, _descriptor5;
  function _initializerDefineProperty(e, i, r, l) { r && Object.defineProperty(e, i, { enumerable: r.enumerable, configurable: r.configurable, writable: r.writable, value: r.initializer ? r.initializer.call(l) : void 0 }); }
  function _applyDecoratedDescriptor(i, e, r, n, l) { var a = {}; return Object.keys(n).forEach(function (i) { a[i] = n[i]; }), a.enumerable = !!a.enumerable, a.configurable = !!a.configurable, ("value" in a || a.initializer) && (a.writable = !0), a = r.slice().reverse().reduce(function (r, n) { return n(i, e, r) || r; }, a), l && void 0 !== a.initializer && (a.value = a.initializer ? a.initializer.call(l) : void 0, a.initializer = void 0), void 0 === a.initializer ? (Object.defineProperty(i, e, a), null) : a; }

  let OperationsDispatchGridRoute = _exports.default = (_class = class OperationsDispatchGridRoute extends _route.default {
    constructor(...args) {
      super(...args);
      _initializerDefineProperty(this, "store", _descriptor, this);
      _initializerDefineProperty(this, "notifications", _descriptor2, this);
      _initializerDefineProperty(this, "hostRouter", _descriptor3, this);
      _initializerDefineProperty(this, "abilities", _descriptor4, this);
      _initializerDefineProperty(this, "intl", _descriptor5, this);
    }
    beforeModel() {
      if (this.abilities.cannot('fleet-ops list order')) {
        this.notifications.warning(this.intl.t('common.unauthorized-access'));
        return this.hostRouter.transitionTo('console.fleet-ops');
      }
    }
    model(params) {
      return this.store.query('order', {
        sort: '-created_at',
        ...params
      });
    }
  }, _descriptor = _applyDecoratedDescriptor(_class.prototype, "store", [_service.inject], {
    configurable: true,
    enumerable: true,
    writable: true,
    initializer: null
  }), _descriptor2 = _applyDecoratedDescriptor(_class.prototype, "notifications", [_service.inject], {
    configurable: true,
    enumerable: true,
    writable: true,
    initializer: null
  }), _descriptor3 = _applyDecoratedDescriptor(_class.prototype, "hostRouter", [_service.inject], {
    configurable: true,
    enumerable: true,
    writable: true,
    initializer: null
  }), _descriptor4 = _applyDecoratedDescriptor(_class.prototype, "abilities", [_service.inject], {
    configurable: true,
    enumerable: true,
    writable: true,
    initializer: null
  }), _descriptor5 = _applyDecoratedDescriptor(_class.prototype, "intl", [_service.inject], {
    configurable: true,
    enumerable: true,
    writable: true,
    initializer: null
  }), _class);
});
`;

const operationsDispatchGridControllerJs = `define("@fleetbase/fleetops-engine/controllers/operations/dispatch-grid", ["exports", "@ember/controller", "@ember/service", "@glimmer/tracking"], function (_exports, _controller, _service, _tracking) {
  "use strict";

  Object.defineProperty(_exports, "__esModule", {
    value: true
  });
  _exports.default = void 0;
  var _class, _descriptor, _descriptor2, _descriptor3;
  function _initializerDefineProperty(e, i, r, l) { r && Object.defineProperty(e, i, { enumerable: r.enumerable, configurable: r.configurable, writable: r.writable, value: r.initializer ? r.initializer.call(l) : void 0 }); }
  function _applyDecoratedDescriptor(i, e, r, n, l) { var a = {}; return Object.keys(n).forEach(function (i) { a[i] = n[i]; }), a.enumerable = !!a.enumerable, a.configurable = !!a.configurable, ("value" in a || a.initializer) && (a.writable = !0), a = r.slice().reverse().reduce(function (r, n) { return n(i, e, r) || r; }, a), l && void 0 !== a.initializer && (a.value = a.initializer ? a.initializer.call(l) : void 0, a.initializer = void 0), void 0 === a.initializer ? (Object.defineProperty(i, e, a), null) : a; }

  let OperationsDispatchGridController = _exports.default = (_class = class OperationsDispatchGridController extends _controller.default {
    constructor(...args) {
      super(...args);
      _initializerDefineProperty(this, "orderActions", _descriptor, this);
      _initializerDefineProperty(this, "orderSocketEvents", _descriptor2, this);
      _initializerDefineProperty(this, "hostRouter", _descriptor3, this);
      this.orderSocketEvents.startCompany();
    }
  }, _descriptor = _applyDecoratedDescriptor(_class.prototype, "orderActions", [_service.inject], {
    configurable: true,
    enumerable: true,
    writable: true,
    initializer: null
  }), _descriptor2 = _applyDecoratedDescriptor(_class.prototype, "orderSocketEvents", [_service.inject], {
    configurable: true,
    enumerable: true,
    writable: true,
    initializer: null
  }), _descriptor3 = _applyDecoratedDescriptor(_class.prototype, "hostRouter", [_service.inject], {
    configurable: true,
    enumerable: true,
    writable: true,
    initializer: null
  }), _class);
});
`;

const operationsDispatchGridTemplateJs = `define("@fleetbase/fleetops-engine/templates/operations/dispatch-grid", ["exports", "@ember/template-factory"], function (_exports, _templateFactory) {
  "use strict";

  Object.defineProperty(_exports, "__esModule", {
    value: true
  });
  _exports.default = void 0;
  var _default = _exports.default = (0, _templateFactory.createTemplateFactory)(${operationsDispatchGridCompiled});
});
`;

// 5. Inject into console/dist/engines-dist/@fleetbase/fleetops-engine/assets/engine.js
const engineJsPath = '/home/wert/fleetbase/console/dist/engines-dist/@fleetbase/fleetops-engine/assets/engine.js';
let engineJs = fs.readFileSync(engineJsPath, 'utf-8');

// Append definitions or replace if already present
const dispatchGridMarker = 'define("@fleetbase/fleetops-engine/components/dispatch-grid"';
const dispatchGridIdx = engineJs.indexOf(dispatchGridMarker);
if (dispatchGridIdx !== -1) {
    engineJs = engineJs.substring(0, dispatchGridIdx) + dispatchGridComponentJs + '\n' + operationsDispatchGridRouteJs + '\n' + operationsDispatchGridControllerJs + '\n' + operationsDispatchGridTemplateJs + '\n';
    console.log('✓ Updated dispatch-grid component, route, controller, template in engine.js');
} else {
    engineJs += '\n' + dispatchGridComponentJs + '\n' + operationsDispatchGridRouteJs + '\n' + operationsDispatchGridControllerJs + '\n' + operationsDispatchGridTemplateJs + '\n';
    console.log('✓ Injected component, route, controller, template into engine.js');
}

// Add Dispatch Grid to operationsItems in engine.js
const oldEngineOp = `{
        priority: 0,
        intl: 'menu.dashboard',
        title: this.intl.t('menu.dashboard'),
        icon: 'home',
        route: 'operations.orders',
        permission: 'fleet-ops list order',
        visible: this.abilities.can('fleet-ops see order')
      },`;

const newEngineOp = `{
        priority: 0,
        intl: 'menu.dashboard',
        title: this.intl.t('menu.dashboard'),
        icon: 'home',
        route: 'operations.orders',
        permission: 'fleet-ops list order',
        visible: this.abilities.can('fleet-ops see order')
      }, {
        priority: 1,
        title: 'Dispatch Grid',
        icon: 'table-cells',
        route: 'operations.dispatch-grid',
        permission: 'fleet-ops list order',
        visible: this.abilities.can('fleet-ops see order')
      },`;

if (engineJs.includes(oldEngineOp) && !engineJs.includes("'Dispatch Grid'")) {
    engineJs = engineJs.replace(oldEngineOp, newEngineOp);
    console.log('✓ Added Dispatch Grid to operationsItems in engine.js');
}

// Also add to instance-initializers in engine.js
const oldExtOp = `{
          title: 'Calendar',
          description: 'View Google Calendar schedule and appointments.',
          icon: 'calendar',
          route: 'console.fleet-ops.operations.calendar'
        }]`;

const newExtOp = `{
          title: 'Calendar',
          description: 'View Google Calendar schedule and appointments.',
          icon: 'calendar',
          route: 'console.fleet-ops.operations.calendar'
        }, {
          title: 'Dispatch Grid',
          description: 'Live dispatch manifest and status matrix.',
          icon: 'table-cells',
          route: 'console.fleet-ops.operations.dispatch-grid'
        }]`;

if (engineJs.includes(oldExtOp) && !engineJs.includes("'Dispatch Grid'")) {
    engineJs = engineJs.replace(oldExtOp, newExtOp);
    console.log('✓ Added Dispatch Grid to instance-initializer in engine.js');
}

// 6. Restore operations/orders/index.hbs template and update order/panel-header in engine.js
const ordersIndexStartMarker = 'define("@fleetbase/fleetops-engine/templates/operations/orders/index",';
const ordersIndexNextMarker = 'define("@fleetbase/fleetops-engine/templates/operations/orders/index/details",';
const idx = engineJs.indexOf(ordersIndexStartMarker);
const nextIdx = engineJs.indexOf(ordersIndexNextMarker, idx);

if (idx !== -1 && nextIdx !== -1) {
    const newOrdersIndexModule = `define("@fleetbase/fleetops-engine/templates/operations/orders/index", ["exports", "@ember/template-factory"], function (_exports, _templateFactory) {
  "use strict";

  Object.defineProperty(_exports, "__esModule", {
    value: true
  });
  _exports.default = void 0;
  // dispatch-grid-dashboard-restored
  var _default = _exports.default = (0, _templateFactory.createTemplateFactory)(${operationsOrdersIndexCompiled});
});
`;
    engineJs = engineJs.substring(0, idx) + newOrdersIndexModule + engineJs.substring(nextIdx);
    console.log('✓ Restored operations/orders/index.hbs in engine.js with full dashboard & view switch');
}

// 6b. Update order/panel-header component & template in engine.js
const panelHeaderStartMarker = 'define("@fleetbase/fleetops-engine/components/order/panel-header",';
const panelHeaderNextMarker = 'define("@fleetbase/fleetops-engine/components/order/pill",';
const phIdx = engineJs.indexOf(panelHeaderStartMarker);
const phNextIdx = engineJs.indexOf(panelHeaderNextMarker, phIdx);

if (phIdx !== -1 && phNextIdx !== -1) {
    const newPanelHeaderModule = `define("@fleetbase/fleetops-engine/components/order/panel-header", ["exports", "@ember/component", "@glimmer/component", "@ember/template-factory"], function (_exports, _component, _component2, _templateFactory) {
  "use strict";

  Object.defineProperty(_exports, "__esModule", {
    value: true
  });
  _exports.default = void 0;
  const __COLOCATED_TEMPLATE__ = (0, _templateFactory.createTemplateFactory)(${orderPanelHeaderCompiled});
  class OrderPanelHeaderComponent extends _component2.default {
    get formattedCreatedAt() {
      const val = this.args.resource && this.args.resource.createdAt;
      if (typeof val === 'string' && val.length > 0) {
        return val;
      }
      return null;
    }
    get formattedType() {
      const val = this.args.resource && this.args.resource.type;
      if (typeof val === 'string' && val.length > 0) {
        return val;
      }
      return null;
    }
  }
  _exports.default = OrderPanelHeaderComponent;
  (0, _component.setComponentTemplate)(__COLOCATED_TEMPLATE__, OrderPanelHeaderComponent);
});
`;
    engineJs = engineJs.substring(0, phIdx) + newPanelHeaderModule + engineJs.substring(phNextIdx);
    console.log('✓ Updated order/panel-header in engine.js with guarded getters and clean template');
}

fs.writeFileSync(engineJsPath, engineJs, 'utf-8');
console.log('✓ engine.js saved successfully');

// 7. Update console/dist/assets/vendor.js route definition & guard smartHumanize
const vendorJsPath = '/home/wert/fleetbase/console/dist/assets/vendor.js';
let vendorJs = fs.readFileSync(vendorJsPath, 'utf-8');

const oldSmartHumanize = "function smartHumanize(string) {\n    if ((0, _utils.typeOf)(string) !== 'string') {\n      return string;\n    }";
const newSmartHumanize = "function smartHumanize(string) {\n    if (typeof string === 'function' || (0, _utils.typeOf)(string) !== 'string') {\n      return '';\n    }";

if (vendorJs.includes(oldSmartHumanize)) {
    vendorJs = vendorJs.replace(oldSmartHumanize, newSmartHumanize);
    console.log('✓ Guarded smartHumanize in vendor.js against functions/non-strings');
}

if (!vendorJs.includes("this.route('dispatch-grid'")) {
    const oldRoutes = "this.route('calendar');";
    const newRoutes = "this.route('calendar');\n      this.route('dispatch-grid', function () {});";
    vendorJs = vendorJs.replace(oldRoutes, newRoutes);
    console.log('✓ Updated vendor.js route definition for dispatch-grid');
}
fs.writeFileSync(vendorJsPath, vendorJs, 'utf-8');

// 8. Update console/dist/assets/@fleetbase/console.js
const consoleJsPath = '/home/wert/fleetbase/console/dist/assets/@fleetbase/console.js';
let consoleJs = fs.readFileSync(consoleJsPath, 'utf-8');

// Add operationsItems menu in console.js
const oldConsoleOp = `{
        priority: 0,
        intl: 'menu.dashboard',
        title: this.intl.t('menu.dashboard'),
        icon: 'home',
        route: 'operations.orders',
        permission: 'fleet-ops list order',
        visible: this.abilities.can('fleet-ops see order')
      },`;
const newConsoleOp = `{
        priority: 0,
        intl: 'menu.dashboard',
        title: this.intl.t('menu.dashboard'),
        icon: 'home',
        route: 'operations.orders',
        permission: 'fleet-ops list order',
        visible: this.abilities.can('fleet-ops see order')
      }, {
        priority: 1,
        title: 'Dispatch Grid',
        icon: 'table-cells',
        route: 'operations.dispatch-grid',
        permission: 'fleet-ops list order',
        visible: this.abilities.can('fleet-ops see order')
      },`;
if (consoleJs.includes(oldConsoleOp) && !consoleJs.includes("title: 'Dispatch Grid'")) {
    consoleJs = consoleJs.replace(oldConsoleOp, newConsoleOp);
    console.log('✓ Added Dispatch Grid to operationsItems in console.js');
}

// Add to extensions loader in console.js
if (consoleJs.includes(oldExtOp) && !consoleJs.includes("title: 'Dispatch Grid'")) {
    consoleJs = consoleJs.replace(oldExtOp, newExtOp);
    console.log('✓ Added Dispatch Grid to instance-initializer in console.js');
}

// Add console router route in console.js
if (consoleJs.includes("this.route('calendar');") && !consoleJs.includes("this.route('dispatch-grid');")) {
    consoleJs = consoleJs.replace("this.route('calendar');", "this.route('calendar');\n      this.route('dispatch-grid');");
    console.log('✓ Added dispatch-grid to router.map in console.js');
}

// Add re-exports for console
const consoleReExports = `
;define("@fleetbase/console/routes/operations/dispatch-grid", ["exports", "@fleetbase/fleetops-engine/routes/operations/dispatch-grid"], function (_exports, _dispatchGrid) {
  "use strict";
  Object.defineProperty(_exports, "__esModule", { value: true });
  Object.defineProperty(_exports, "default", { enumerable: true, get: function () { return _dispatchGrid.default; } });
});
;define("@fleetbase/console/controllers/operations/dispatch-grid", ["exports", "@fleetbase/fleetops-engine/controllers/operations/dispatch-grid"], function (_exports, _dispatchGrid) {
  "use strict";
  Object.defineProperty(_exports, "__esModule", { value: true });
  Object.defineProperty(_exports, "default", { enumerable: true, get: function () { return _dispatchGrid.default; } });
});
;define("@fleetbase/console/templates/operations/dispatch-grid", ["exports", "@fleetbase/fleetops-engine/templates/operations/dispatch-grid"], function (_exports, _dispatchGrid) {
  "use strict";
  Object.defineProperty(_exports, "__esModule", { value: true });
  Object.defineProperty(_exports, "default", { enumerable: true, get: function () { return _dispatchGrid.default; } });
});
;define("@fleetbase/console/components/dispatch-grid", ["exports", "@fleetbase/fleetops-engine/components/dispatch-grid"], function (_exports, _dispatchGrid) {
  "use strict";
  Object.defineProperty(_exports, "__esModule", { value: true });
  Object.defineProperty(_exports, "default", { enumerable: true, get: function () { return _dispatchGrid.default; } });
});
;define("@fleetbase/console/routes/console/dispatch-grid", ["exports", "@ember/routing/route", "@ember/service"], function (_exports, _route, _service) {
  "use strict";
  Object.defineProperty(_exports, "__esModule", { value: true });
  _exports.default = void 0;
  var _class, _descriptor;
  function _initializerDefineProperty(e, i, r, l) { r && Object.defineProperty(e, i, { enumerable: r.enumerable, configurable: r.configurable, writable: r.writable, value: r.initializer ? r.initializer.call(l) : void 0 }); }
  function _applyDecoratedDescriptor(i, e, r, n, l) { var a = {}; return Object.keys(n).forEach(function (i) { a[i] = n[i]; }), a.enumerable = !!a.enumerable, a.configurable = !!a.configurable, ("value" in a || a.initializer) && (a.writable = !0), a = r.slice().reverse().reduce(function (r, n) { return n(i, e, r) || r; }, a), l && void 0 !== a.initializer && (a.value = a.initializer ? a.initializer.call(l) : void 0, a.initializer = void 0), void 0 === a.initializer ? (Object.defineProperty(i, e, a), null) : a; }
  let ConsoleDispatchGridRoute = _exports.default = (_class = class ConsoleDispatchGridRoute extends _route.default {
    constructor(...args) {
      super(...args);
      _initializerDefineProperty(this, "router", _descriptor, this);
    }
    beforeModel() {
      return this.router.transitionTo('console.fleet-ops.operations.dispatch-grid');
    }
  }, _descriptor = _applyDecoratedDescriptor(_class.prototype, "router", [_service.inject], {
    configurable: true,
    enumerable: true,
    writable: true,
    initializer: null
  }), _class);
});
`;

if (!consoleJs.includes('@fleetbase/console/routes/operations/dispatch-grid')) {
    consoleJs += '\n' + consoleReExports + '\n';
    console.log('✓ Injected re-exports into console.js');
}

fs.writeFileSync(consoleJsPath, consoleJs, 'utf-8');
console.log('✓ console.js saved successfully');

// 9. Update fleet-ops-sidebar.js in console/app
const sidebarJsPath = '/home/wert/fleetbase/console/app/components/layout/fleet-ops-sidebar.js';
let sidebarJs = fs.readFileSync(sidebarJsPath, 'utf-8');
const oldSidebarOp = `{
                priority: 0,
                intl: 'menu.dashboard',
                title: this.intl.t('menu.dashboard'),
                icon: 'home',
                route: 'operations.orders',
                permission: 'fleet-ops list order',
                visible: this.abilities.can('fleet-ops see order'),
            },`;
const newSidebarOp = `{
                priority: 0,
                intl: 'menu.dashboard',
                title: this.intl.t('menu.dashboard'),
                icon: 'home',
                route: 'operations.orders',
                permission: 'fleet-ops list order',
                visible: this.abilities.can('fleet-ops see order'),
            },
            {
                priority: 1,
                title: 'Dispatch Grid',
                icon: 'table-cells',
                route: 'operations.dispatch-grid',
                permission: 'fleet-ops list order',
                visible: this.abilities.can('fleet-ops see order'),
            },`;
if (sidebarJs.includes(oldSidebarOp) && !sidebarJs.includes("title: 'Dispatch Grid'")) {
    sidebarJs = sidebarJs.replace(oldSidebarOp, newSidebarOp);
    fs.writeFileSync(sidebarJsPath, sidebarJs, 'utf-8');
    console.log('✓ Updated console/app/components/layout/fleet-ops-sidebar.js');
}

// 10. Update console/app/router.js
const appRouterPath = '/home/wert/fleetbase/console/app/router.js';
let appRouter = fs.readFileSync(appRouterPath, 'utf-8');
if (!appRouter.includes("this.route('dispatch-grid');")) {
    appRouter = appRouter.replace("this.route('calendar');", "this.route('calendar');\n        this.route('dispatch-grid');");
    fs.writeFileSync(appRouterPath, appRouter, 'utf-8');
    console.log('✓ Updated console/app/router.js');
}

// 11. CSS for Dispatch Grid & Row Color Coding
const dispatchGridCss = `
/* ==========================================================================
   Dispatch Grid Table & Row Color Coding Styles
   ========================================================================== */

.dispatch-grid-wrapper {
    min-height: calc(100vh - 120px);
}

/* 0. Table Header Contrast & Separation */
.dispatch-grid-thead,
.dispatch-grid-thead tr,
.dispatch-grid-table-container thead,
.dispatch-grid-table-container thead tr {
    background-color: #111827 !important;
    border-bottom: 1px solid #374151 !important;
}

.dispatch-grid-th,
.dispatch-grid-thead th,
.dispatch-grid-table-container thead th {
    background-color: #111827 !important;
    color: #F9FAFB !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
    font-size: 0.75rem !important;
    line-height: 1rem !important;
    border-bottom: 1px solid #374151 !important;
}

.dispatch-grid-row {
    transition: background-color 0.15s ease, transform 0.1s ease;
    cursor: pointer;
    border-bottom: 1px solid rgba(255, 255, 255, 0.07);
}

/* 1. Unassigned / Draft: Slate / Dark Gray (#374151) */
.dispatch-grid-row-draft {
    border-left: 4px solid #374151 !important;
    background-color: rgba(55, 65, 81, 0.45) !important;
    color: #F9FAFB !important;
}
.dispatch-grid-row-draft:hover {
    background-color: rgba(55, 65, 81, 0.70) !important;
}

/* 2. Assigned / Dispatched: Blue (#1D4ED8) */
.dispatch-grid-row-dispatched {
    border-left: 4px solid #1D4ED8 !important;
    background-color: rgba(29, 78, 216, 0.35) !important;
    color: #F9FAFB !important;
}
.dispatch-grid-row-dispatched:hover {
    background-color: rgba(29, 78, 216, 0.55) !important;
}

/* 3. En Route: Amber / Gold (#B45309) */
.dispatch-grid-row-enroute {
    border-left: 4px solid #B45309 !important;
    background-color: rgba(180, 83, 9, 0.35) !important;
    color: #F9FAFB !important;
}
.dispatch-grid-row-enroute:hover {
    background-color: rgba(180, 83, 9, 0.55) !important;
}

/* 4. Staged / Arrived: Purple (#6D28D9) */
.dispatch-grid-row-arrived {
    border-left: 4px solid #6D28D9 !important;
    background-color: rgba(109, 40, 217, 0.35) !important;
    color: #F9FAFB !important;
}
.dispatch-grid-row-arrived:hover {
    background-color: rgba(109, 40, 217, 0.55) !important;
}

/* 5. Trip Active / In Progress: Emerald / Green (#047857) */
.dispatch-grid-row-in-progress {
    border-left: 4px solid #047857 !important;
    background-color: rgba(4, 120, 87, 0.35) !important;
    color: #F9FAFB !important;
}
.dispatch-grid-row-in-progress:hover {
    background-color: rgba(4, 120, 87, 0.55) !important;
}

/* 6. Flagged / Conflict: Crimson / Red (#B91C1C) */
.dispatch-grid-row-conflict {
    border-left: 4px solid #B91C1C !important;
    background-color: rgba(185, 28, 28, 0.40) !important;
    color: #F9FAFB !important;
}
.dispatch-grid-row-conflict:hover {
    background-color: rgba(185, 28, 28, 0.60) !important;
}

/* Topbar Dispatch Grid Button */
.ops-grid-view-button {
    font-size: 0.8125rem;
    font-weight: 500;
    padding: 0.25rem 0.625rem;
    border-radius: 0.375rem;
    color: #9CA3AF;
    transition: all 0.15s ease;
}
.ops-grid-view-button:hover {
    color: #FFFFFF;
    background-color: rgba(255, 255, 255, 0.05);
}
.ops-grid-view-button.active {
    background-color: #2563EB;
    color: #FFFFFF;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
}
`;

const cssMarker = '/* ==========================================================================\\n   Dispatch Grid Table & Row Color Coding Styles';

const engineCssPath = '/home/wert/fleetbase/console/dist/engines-dist/@fleetbase/fleetops-engine/assets/engine.css';
let engineCss = fs.readFileSync(engineCssPath, 'utf-8');
const engineCssIdx = engineCss.indexOf('/* ==========================================================================\\n   Dispatch Grid Table & Row Color Coding Styles');
if (engineCssIdx !== -1) {
    engineCss = engineCss.substring(0, engineCssIdx) + dispatchGridCss;
} else {
    engineCss += '\\n' + dispatchGridCss;
}
fs.writeFileSync(engineCssPath, engineCss, 'utf-8');
console.log('✓ Updated Dispatch Grid CSS in engine.css');

const consoleCssPath = '/home/wert/fleetbase/console/dist/assets/@fleetbase/console.css';
let consoleCss = fs.readFileSync(consoleCssPath, 'utf-8');
const consoleCssIdx = consoleCss.indexOf('/* ==========================================================================\\n   Dispatch Grid Table & Row Color Coding Styles');
if (consoleCssIdx !== -1) {
    consoleCss = consoleCss.substring(0, consoleCssIdx) + dispatchGridCss;
} else {
    consoleCss += '\\n' + dispatchGridCss;
}
fs.writeFileSync(consoleCssPath, consoleCss, 'utf-8');
console.log('✓ Updated Dispatch Grid CSS in console.css');


// 12. Sync updated bundles to fleetbase-console-1 container
try {
    const { execSync } = require('child_process');
    console.log('--- Syncing updated bundles to fleetbase-console-1 ---');
    execSync('docker cp /home/wert/fleetbase/console/dist/engines-dist/@fleetbase/fleetops-engine/assets/engine.js fleetbase-console-1:/usr/share/nginx/html/engines-dist/@fleetbase/fleetops-engine/assets/engine.js', { stdio: 'inherit' });
    execSync('docker cp /home/wert/fleetbase/console/dist/engines-dist/@fleetbase/fleetops-engine/assets/engine.css fleetbase-console-1:/usr/share/nginx/html/engines-dist/@fleetbase/fleetops-engine/assets/engine.css', { stdio: 'inherit' });
    execSync('docker cp /home/wert/fleetbase/console/dist/assets/vendor.js fleetbase-console-1:/usr/share/nginx/html/assets/vendor.js', { stdio: 'inherit' });
    execSync('docker cp /home/wert/fleetbase/console/dist/assets/@fleetbase/console.js fleetbase-console-1:/usr/share/nginx/html/assets/@fleetbase/console.js', { stdio: 'inherit' });
    execSync('docker cp /home/wert/fleetbase/console/dist/assets/@fleetbase/console.css fleetbase-console-1:/usr/share/nginx/html/assets/@fleetbase/console.css', { stdio: 'inherit' });
    execSync('docker cp /home/wert/fleetbase/console/public/images/no-avatar.png fleetbase-console-1:/usr/share/nginx/html/images/no-avatar.png', { stdio: 'inherit' });
    console.log('✓ All assets synced to docker container successfully!');
} catch (e) {
    console.error('Failed to sync to container:', e.message);
}

console.log('--- Dispatch Grid Build Complete! ---');
