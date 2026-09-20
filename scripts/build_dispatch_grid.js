const fs = require('fs');
const path = require('path');

const compilerPath = require.resolve('ember-source/dist/ember-template-compiler.js', {
    paths: ['/home/wert/fleetbase/console']
});
const compiler = require(compilerPath);

console.log('--- Compiling Dispatch Grid HBS Templates ---');

// 1. Template for <DispatchGrid> component
const dispatchGridHbs = fs.readFileSync('/home/wert/fleetbase/console/packages/fleetops/addon/components/dispatch-grid.hbs', 'utf-8');

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
    <div class="dispatch-grid-container flex-1 overflow-hidden h-full pt-10">
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
const dispatchGridJsSrc = fs.readFileSync('/home/wert/fleetbase/console/packages/fleetops/addon/components/dispatch-grid.js', 'utf-8');
fs.mkdirSync('/home/wert/fleetbase/console/app/components', { recursive: true });
fs.writeFileSync('/home/wert/fleetbase/console/app/components/dispatch-grid.hbs', dispatchGridHbs, 'utf-8');
fs.writeFileSync('/home/wert/fleetbase/console/app/components/dispatch-grid.js', "export { default } from '@fleetbase/fleetops-engine/components/dispatch-grid';\n", 'utf-8');

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

const nodeEngineDir = '/home/wert/fleetbase/console/packages/fleetops';
fs.mkdirSync(path.join(nodeEngineDir, 'addon/components'), { recursive: true });
fs.writeFileSync(path.join(nodeEngineDir, 'addon/components/dispatch-grid.hbs'), dispatchGridHbs, 'utf-8');
fs.writeFileSync(path.join(nodeEngineDir, 'addon/components/dispatch-grid.js'), dispatchGridJsSrc, 'utf-8');
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
    engineRoutesSrc = engineRoutesSrc.replace("this.route('calendar', function () {});", "this.route('calendar', function () {});\n        this.route('dispatch-grid', function () {});");
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
  var _class, _descriptor, _descriptor2, _descriptor3, _descriptor4, _descriptor5;
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

  function getTodayDateString() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return \`\${year}-\${month}-\${day}\`;
  }

  function extractDateString(raw) {
    if (!raw) return null;
    if (raw instanceof Date) {
      if (isNaN(raw.getTime())) return null;
      const y = raw.getFullYear();
      const m = String(raw.getMonth() + 1).padStart(2, '0');
      const d = String(raw.getDate()).padStart(2, '0');
      return \`\${y}-\${m}-\${d}\`;
    }
    if (typeof raw === 'string') {
      const trimmed = raw.trim();
      if (trimmed.startsWith('0000-00-00')) return null;
      if (/^\\d{4}-\\d{2}-\\d{2}$/.test(trimmed)) {
        return trimmed;
      }
      if (/^\\d{4}-\\d{2}-\\d{2}\\s+\\d{2}:\\d{2}/.test(trimmed)) {
        return trimmed.substring(0, 10);
      }
      const parsed = new Date(trimmed);
      if (!isNaN(parsed.getTime())) {
        const y = parsed.getFullYear();
        const m = String(parsed.getMonth() + 1).padStart(2, '0');
        const d = String(parsed.getDate()).padStart(2, '0');
        return \`\${y}-\${m}-\${d}\`;
      }
      const match = trimmed.match(/^(\\d{4}-\\d{2}-\\d{2})/);
      if (match) return match[1];
    }
    return null;
  }

  function extractTime(raw) {
    if (!raw) return null;
    let d = null;
    if (raw instanceof Date) {
      d = isNaN(raw.getTime()) ? null : raw;
    } else if (typeof raw === 'string') {
      const trimmed = raw.trim();
      if (trimmed.startsWith('0000-00-00')) return null;
      const parsed = new Date(trimmed.includes(' ') ? trimmed.replace(' ', 'T') : trimmed);
      if (!isNaN(parsed.getTime())) {
        d = parsed;
      }
    }
    if (!d) return null;
    let hours = d.getHours();
    const minutes = String(d.getMinutes()).padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    const formattedHours = String(hours).padStart(2, '0');
    return \`\${formattedHours}:\${minutes} \${ampm}\`;
  }

  let DispatchGridComponent = _exports.default = (_class = class DispatchGridComponent extends _component2.default {
    constructor(...args) {
      super(...args);
      _initializerDefineProperty(this, "searchQuery", _descriptor, this);
      _initializerDefineProperty(this, "statusFilter", _descriptor2, this);
      _initializerDefineProperty(this, "orderActions", _descriptor3, this);
      _initializerDefineProperty(this, "dateFilterMode", _descriptor4, this);
      _initializerDefineProperty(this, "customSelectedDate", _descriptor5, this);
    }

    get todayDateString() {
      return getTodayDateString();
    }

    get orders() {
      const orders = this.args.orders ?? [];
      if ((0, _array.isArray)(orders)) {
        return orders;
      }
      return orders.toArray ? orders.toArray() : [];
    }

    getOrderDateString(order) {
      if (!order) return null;
      const scheduled = order.scheduled_at || order.scheduledAt;
      const scheduledDate = extractDateString(scheduled);
      if (scheduledDate) {
        return scheduledDate;
      }
      const created = order.created_at || order.createdAt;
      return extractDateString(created);
    }

    formatPickupTime(order) {
      if (!order) return '--:--';
      const scheduled = order.scheduled_at || order.scheduledAt;
      const scheduledTime = extractTime(scheduled);
      if (scheduledTime) {
        return scheduledTime;
      }
      const created = order.created_at || order.createdAt;
      const createdTime = extractTime(created);
      return createdTime || '--:--';
    }

    get dateFilteredOrders() {
      const orders = this.orders;
      if (this.dateFilterMode === 'all') {
        return orders;
      }
      const targetDate = this.dateFilterMode === 'today' ? this.todayDateString : this.customSelectedDate;
      if (!targetDate) {
        return orders;
      }
      const filtered = orders.filter(order => {
        const orderDate = this.getOrderDateString(order);
        return orderDate === targetDate;
      });
      return [...filtered].sort((a, b) => {
        const timeA = new Date(a.scheduled_at || a.scheduledAt || a.created_at || a.createdAt || 0).getTime();
        const timeB = new Date(b.scheduled_at || b.scheduledAt || b.created_at || b.createdAt || 0).getTime();
        return timeA - timeB;
      });
    }

    get statusCounts() {
      const orders = this.dateFilteredOrders;
      const counts = {
        all: orders.length,
        created: 0,
        dispatched: 0,
        enroute_pickup: 0,
        on_location: 0,
        pob: 0,
        completed: 0,
        canceled: 0
      };

      for (let i = 0; i < orders.length; i++) {
        const order = orders[i];
        const st = (order.status || '').toLowerCase();
        if (st === 'created' || st === 'draft' || st === 'pending' || st === 'unassigned') {
          counts.created++;
        } else if (st === 'dispatched') {
          counts.dispatched++;
        } else if (st === 'enroute_pickup' || st === 'enroute' || st === 'driver_enroute') {
          counts.enroute_pickup++;
        } else if (st === 'on_location' || st === 'arrived') {
          counts.on_location++;
        } else if (st === 'pob' || st === 'in_progress' || st === 'started' || st === 'passenger_on_board') {
          counts.pob++;
        } else if (st === 'completed') {
          counts.completed++;
        } else if (st === 'canceled' || st === 'cancelled') {
          counts.canceled++;
        }
      }

      return counts;
    }

    get filteredOrders() {
      let list = this.dateFilteredOrders;
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
          const st = (order.status || '').toLowerCase();
          if (this.statusFilter === 'created') {
            return st === 'created' || st === 'draft' || st === 'pending' || st === 'unassigned';
          }
          if (this.statusFilter === 'dispatched') {
            return st === 'dispatched';
          }
          if (this.statusFilter === 'enroute_pickup' || this.statusFilter === 'enroute' || this.statusFilter === 'driver_enroute') {
            return st === 'enroute_pickup' || st === 'enroute' || st === 'driver_enroute';
          }
          if (this.statusFilter === 'on_location' || this.statusFilter === 'arrived') {
            return st === 'on_location' || st === 'arrived';
          }
          if (this.statusFilter === 'pob' || this.statusFilter === 'in_progress' || this.statusFilter === 'passenger_on_board') {
            return st === 'pob' || st === 'in_progress' || st === 'started' || st === 'passenger_on_board';
          }
          if (this.statusFilter === 'completed') {
            return st === 'completed';
          }
          if (this.statusFilter === 'canceled' || this.statusFilter === 'cancelled') {
            return st === 'canceled' || st === 'cancelled';
          }
          return st === this.statusFilter;
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
      const st = (order?.status || '').toLowerCase();
      switch (st) {
        case 'completed':
          return 'bg-emerald-200 hover:bg-emerald-300 border-b border-emerald-400 border-l-[6px] border-l-emerald-600';
        case 'dispatched':
          return 'bg-blue-200 hover:bg-blue-300 border-b border-blue-400 border-l-[6px] border-l-blue-600';
        case 'enroute':
        case 'enroute_pickup':
        case 'driver_enroute':
          return 'bg-amber-200 hover:bg-amber-300 border-b border-amber-400 border-l-[6px] border-l-amber-600';
        case 'on_location':
        case 'arrived':
          return 'bg-purple-200 hover:bg-purple-300 border-b border-purple-400 border-l-[6px] border-l-purple-600';
        case 'pob':
        case 'passenger_on_board':
        case 'in_progress':
        case 'started':
          return 'bg-orange-200 hover:bg-orange-300 border-b border-orange-400 border-l-[6px] border-l-orange-600';
        case 'canceled':
        case 'cancelled':
          return 'bg-rose-200 hover:bg-rose-300 border-b border-rose-400 border-l-[6px] border-l-rose-600';
        case 'created':
        case 'draft':
        case 'pending':
        case 'unassigned':
        default:
          return 'bg-slate-200 hover:bg-slate-300 border-b border-slate-400 border-l-[6px] border-l-slate-500';
      }
    }

    getStatusBadge(status) {
      const st = (status || '').toLowerCase();
      switch (st) {
        case 'dispatched':
          return {
            title: 'Dispatched',
            class: 'bg-white text-blue-900 border border-blue-500 shadow-sm'
          };
        case 'enroute':
        case 'enroute_pickup':
        case 'driver_enroute':
          return {
            title: 'En Route',
            class: 'bg-white text-amber-900 border border-amber-500 shadow-sm'
          };
        case 'on_location':
        case 'arrived':
          return {
            title: 'On Location',
            class: 'bg-white text-purple-900 border border-purple-500 shadow-sm'
          };
        case 'pob':
        case 'passenger_on_board':
        case 'in_progress':
        case 'started':
          return {
            title: 'POB',
            class: 'bg-white text-orange-900 border border-orange-500 shadow-sm'
          };
        case 'completed':
          return {
            title: 'Completed',
            class: 'bg-white text-emerald-950 border border-emerald-500 shadow-sm'
          };
        case 'canceled':
        case 'cancelled':
          return {
            title: 'Canceled',
            class: 'bg-white text-red-900 border border-red-500 shadow-sm'
          };
        case 'created':
        case 'draft':
        case 'pending':
        case 'unassigned':
        default:
          return {
            title: 'Created',
            class: 'bg-white text-slate-900 border border-slate-500 shadow-sm'
          };
      }
    }

    getStatusBadgeClass(status) {
      const st = (status || '').toLowerCase();
      if (st === 'dispatched') {
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
      } else if (st === 'enroute' || st === 'enroute_pickup' || st === 'driver_enroute') {
        return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200';
      } else if (st === 'on_location' || st === 'arrived') {
        return 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-200';
      } else if (st === 'pob' || st === 'in_progress' || st === 'started' || st === 'passenger_on_board') {
        return 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-200';
      } else if (st === 'completed') {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200';
      } else if (st === 'canceled' || st === 'cancelled') {
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
      }
      return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
    }

    getOrderInfo(order) {
      const id = order.public_id || order.id || '';
      const internalId = order.internal_id || '';
      const scheduledAt = order.scheduled_at || order.scheduledAt || '';
      const statusTitles = {
        created: 'Created',
        dispatched: 'Dispatched',
        enroute: 'En Route',
        enroute_pickup: 'En Route',
        driver_enroute: 'En Route',
        on_location: 'On Location',
        arrived: 'On Location',
        pob: 'Passenger On Board',
        passenger_on_board: 'Passenger On Board',
        in_progress: 'Passenger On Board',
        started: 'Passenger On Board',
        completed: 'Completed',
        canceled: 'Canceled',
        cancelled: 'Canceled'
      };
      const st = order.status || 'created';
      const statusTitle = statusTitles[st] || st;
      return { id, internalId, scheduledAt, status: st, statusTitle };
    }

    getPassengerInfo(order) {
      const meta = typeof order.meta === 'string' ? (function() { try { return JSON.parse(order.meta); } catch(e) { return {}; } })() : (order.meta || {});
      
      // Extract Name with multiple fallback paths
      let name = order.customer?.name 
          || order.payload?.customer?.name 
          || meta?.passenger_name 
          || meta?.name;

      if (!name) {
          const pickupName = order.payload?.pickup?.name || order.payload?.pickup_place?.name;
          if (pickupName) {
              const extracted = pickupName.split(' (')[0].trim();
              if (extracted && !['pickup', 'pickup place', 'pickup location'].includes(extracted.toLowerCase())) {
                  name = extracted;
              }
          }
      }
      if (!name) {
          name = 'Guest Passenger';
      }

      // Extract Phone with multiple fallback paths
      let phone = order.customer?.phone 
          || order.payload?.customer?.phone 
          || meta?.phone 
          || meta?.passenger_phone 
          || meta?.contact_phone 
          || order.payload?.pickup?.phone 
          || order.payload?.pickup_place?.phone;

      if (!phone) {
          phone = '--';
      }

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
      if (!d) {
        return { assigned: false, name: 'Unassigned', avatar_url: '/images/no-avatar.png' };
      }
      const name = d.name || 'Chauffeur';
      const avatar_url = _resolveDriverAvatar(d);
      let status = 'Offline';
      let dotClass = 'bg-gray-500';
      let statusClass = 'text-gray-400 font-medium';

      if (d.online) {
        status = 'Online';
        dotClass = 'bg-emerald-500';
        statusClass = 'text-emerald-400 font-medium';
      }

      const st = (order.status || '').toLowerCase();
      if (st === 'dispatched' || st === 'enroute_pickup' || st === 'enroute' || st === 'driver_enroute') {
        status = 'En Route';
        dotClass = 'bg-amber-500';
        statusClass = 'text-amber-400 font-medium';
      } else if (st === 'on_location' || st === 'arrived') {
        status = 'On Location';
        dotClass = 'bg-purple-500';
        statusClass = 'text-purple-400 font-medium';
      } else if (st === 'pob' || st === 'in_progress' || st === 'started' || st === 'passenger_on_board') {
        status = 'POB';
        dotClass = 'bg-orange-500';
        statusClass = 'text-orange-300 font-medium';
      } else if (st === 'completed') {
        status = 'Completed';
        dotClass = 'bg-emerald-500';
        statusClass = 'text-emerald-400 font-medium';
      } else if (st === 'canceled' || st === 'cancelled') {
        status = 'Canceled';
        dotClass = 'bg-red-500';
        statusClass = 'text-red-400 font-medium';
      } else if (d.online === false) {
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

    getRouteInfo(order) {
      const p = this.getPickupInfo(order);
      const d = this.getDropoffInfo(order);
      const text = \`\${p.address} ➔ \${d.address}\`;
      return { pickup: p.address, dropoff: d.address, text };
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

      return flags;
    }

    get gridRows() {
      return this.filteredOrders.map(order => {
        const meta = typeof order.meta === 'string' ? (function() { try { return JSON.parse(order.meta); } catch(e) { return {}; } })() : (order.meta || {});
        return {
          order,
          rowClass: this.getRowClass(order),
          formattedTime: this.formatPickupTime(order),
          statusBadge: this.getStatusBadge(order.status),
          statusBadgeClass: this.getStatusBadgeClass(order.status),
          orderInfo: this.getOrderInfo(order),
          passenger: this.getPassengerInfo(order),
          route: this.getRouteInfo(order),
          vehicle: this.getVehicleInfo(order),
          driver: this.getDriverInfo(order),
          pickup: this.getPickupInfo(order),
          dropoff: this.getDropoffInfo(order),
          flags: this.getFlags(order),
          vehicleChoice: meta?.vehicle_type || meta?.vehicle || meta?.carChoice || meta?.car_choice || order.payload?.meta?.vehicle_type || order.payload?.meta?.vehicle || order.payload?.meta?.carChoice || order.payload?.meta?.car_choice || order.payload?.entities?.[0]?.meta?.vehicle_type || order.payload?.entities?.[0]?.meta?.vehicle || order.payload?.entities?.[0]?.meta?.carChoice || order.payload?.entities?.[0]?.meta?.car_choice || '-',
          paxCount: meta?.passengers || meta?.passenger_count || meta?.pax || order.payload?.meta?.passengers || order.payload?.meta?.passenger_count || order.payload?.meta?.pax || order.payload?.entities?.[0]?.meta?.passengers || order.payload?.entities?.[0]?.meta?.passenger_count || order.payload?.entities?.[0]?.meta?.pax || '-',
          childSeatsCount: meta?.child_seats || meta?.child_seats_count || meta?.seats || order.payload?.meta?.child_seats || order.payload?.meta?.child_seats_count || order.payload?.meta?.seats || order.payload?.entities?.[0]?.meta?.child_seats || order.payload?.entities?.[0]?.meta?.child_seats_count || order.payload?.entities?.[0]?.meta?.seats || '-'
        };
      });
    }

    setDateMode = (mode) => {
      this.dateFilterMode = mode;
    };

    onCustomDateChange = (event) => {
      const val = event?.target?.value || event;
      if (val) {
        this.customSelectedDate = val;
        this.dateFilterMode = 'custom';
      }
    };

    setStatusFilter = (filter) => {
      this.statusFilter = filter;
    };

    clearSearch = () => {
      this.searchQuery = '';
    };

    openOrderDetails = (order) => {
      if (typeof this.args.onOrderClick === 'function') {
        this.args.onOrderClick(order);
      } else if (this.orderActions?.transition?.view) {
        this.orderActions.transition.view(order);
      }
    };

    onClickRow = (order) => {
      this.openOrderDetails(order);
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
  }), _descriptor4 = _applyDecoratedDescriptor(_class.prototype, "dateFilterMode", [_tracking.tracked], {
    configurable: true,
    enumerable: true,
    writable: true,
    initializer: function () {
      return 'today';
    }
  }), _descriptor5 = _applyDecoratedDescriptor(_class.prototype, "customSelectedDate", [_tracking.tracked], {
    configurable: true,
    enumerable: true,
    writable: true,
    initializer: function () {
      return getTodayDateString();
    }
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

// 6c. Precompile and Update order/details/notes template in engine.js safely
const notesHbsPath = '/home/wert/fleetbase/console/packages/fleetops/addon/components/order/details/notes.hbs';
if (fs.existsSync(notesHbsPath)) {
    const notesHbs = fs.readFileSync(notesHbsPath, 'utf-8');
    const notesCompiled = compiler.precompile(notesHbs, {
        moduleName: '@fleetbase/fleetops-engine/components/order/details/notes.hbs'
    });
    const notesStartMarker = 'define("@fleetbase/fleetops-engine/components/order/details/notes",';
    const notesIdx = engineJs.indexOf(notesStartMarker);
    if (notesIdx !== -1) {
        const templateFactoryMarker = 'const __COLOCATED_TEMPLATE__ = (0, _templateFactory.createTemplateFactory)(';
        const tfIdx = engineJs.indexOf(templateFactoryMarker, notesIdx);
        if (tfIdx !== -1) {
            const moduleNameMarker = '"moduleName": "@fleetbase/fleetops-engine/components/order/details/notes.hbs"';
            const modIdx = engineJs.indexOf(moduleNameMarker, tfIdx);
            if (modIdx !== -1) {
                const endOfTfIdx = engineJs.indexOf('});', modIdx);
                if (endOfTfIdx !== -1) {
                    const startToReplace = tfIdx + templateFactoryMarker.length;
                    engineJs = engineJs.substring(0, startToReplace) + notesCompiled + engineJs.substring(endOfTfIdx + 1);
                    console.log('✓ Compiled and updated order/details/notes template in engine.js safely!');
                }
            }
        }
    }
}

// 6d. Configure CARTO Basemaps API key in engine.js
const cartoKey = 'cb1_32li_1_aa9e6424da513b1200464fad';
function updateCartoUrls(content) {
    return content.replace(
        /https:\/\/\{s\}\.basemaps\.cartocdn\.com\/([a-zA-Z0-9_\/-]+)\/\{z\}\/\{x\}\/\{y\}(\{r\})?\.png(?!\?key=)/g,
        (match, style, r) => `https://{s}.basemaps.cartocdn.com/${style}/{z}/{x}/{y}${r || ''}.png?key=${cartoKey}`
    );
}
engineJs = updateCartoUrls(engineJs);
console.log('✓ Configured CARTO Basemaps API key in engine.js');

if (engineJs.includes('unable to acceot')) {
    engineJs = engineJs.replace(/unable to acceot/g, 'unable to accept');
    console.log('✓ Corrected spelling "unable to acceot" -> "unable to accept" in engine.js');
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
    const oldRoutes = "this.route('calendar', function () {});";
    const newRoutes = "this.route('calendar', function () {});\n      this.route('dispatch-grid', function () {});";
    vendorJs = vendorJs.replace(oldRoutes, newRoutes);
    console.log('✓ Updated vendor.js route definition for dispatch-grid');
}

vendorJs = updateCartoUrls(vendorJs);
console.log('✓ Configured CARTO Basemaps API key in vendor.js');

if (vendorJs.includes('unable to acceot')) {
    vendorJs = vendorJs.replace(/unable to acceot/g, 'unable to accept');
    console.log('✓ Corrected spelling "unable to acceot" -> "unable to accept" in vendor.js');
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

const badJsonStringifyModule = `;define("@fleetbase/console/helpers/json-stringify", ["exports", "@fleetbase/dev-engine/helpers/json-stringify"], function (_exports, _jsonStringify) {
  "use strict";

  Object.defineProperty(_exports, "__esModule", {
    value: true
  });
  Object.defineProperty(_exports, "default", {
    enumerable: true,
    get: function () {
      return _jsonStringify.default;
    }
  });
  0; //eaimeta@70e063a35619d71f0,"@fleetbase/dev-engine/helpers/json-stringify"eaimeta@70e063a35619d71f
});`;

const goodJsonStringifyModule = `;define("@fleetbase/console/helpers/json-stringify", ["exports", "@ember/component/helper"], function (_exports, _helper) {
  "use strict";
  Object.defineProperty(_exports, "__esModule", { value: true });
  _exports.default = _exports.jsonStringify = void 0;
  function jsonStringify(positional) {
    var value = positional[0];
    var replacer = positional[1];
    var space = positional[2];
    try {
      return JSON.stringify(value, replacer, space || 2);
    } catch (e) {
      return String(value);
    }
  }
  _exports.jsonStringify = jsonStringify;
  var _default = _exports.default = (0, _helper.helper)(jsonStringify);
});`;

if (consoleJs.includes(badJsonStringifyModule)) {
    consoleJs = consoleJs.replace(badJsonStringifyModule, goodJsonStringifyModule);
    console.log('✓ Successfully replaced @fleetbase/console/helpers/json-stringify with self-contained fallback in console.js');
} else {
    const shortBadPattern = 'define("@fleetbase/console/helpers/json-stringify", ["exports", "@fleetbase/dev-engine/helpers/json-stringify"]';
    if (consoleJs.includes(shortBadPattern)) {
        consoleJs = consoleJs.replace(/;define\("@fleetbase\/console\/helpers\/json-stringify"[\s\S]*?\}\);/, goodJsonStringifyModule);
        console.log('✓ Regex replaced @fleetbase/console/helpers/json-stringify with self-contained fallback in console.js');
    }
}

if (consoleJs.includes('unable to acceot')) {
    consoleJs = consoleJs.replace(/unable to acceot/g, 'unable to accept');
    console.log('✓ Corrected spelling "unable to acceot" -> "unable to accept" in console.js');
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

.dispatch-grid-container {
    padding-top: 2.5rem !important;
    height: 100% !important;
    min-height: 0 !important;
    display: flex !important;
    flex-direction: column !important;
    overflow: hidden !important;
    box-sizing: border-box !important;
}

.dispatch-grid-wrapper {
    height: 100% !important;
    min-height: 0 !important;
    display: flex !important;
    flex-direction: column !important;
    overflow: hidden !important;
    box-sizing: border-box !important;
}

.dispatch-grid-toolbar {
    position: relative !important;
    z-index: 30 !important;
    flex-shrink: 0 !important;
}

.dispatch-grid-toolbar input[type="text"],
.dispatch-grid-toolbar .form-input {
    background-color: #ffffff !important;
    color: #111827 !important;
    border-color: #d1d5db !important;
}

.dispatch-grid-toolbar input::placeholder {
    color: #9ca3af !important;
}

.dispatch-grid-table-container {
    flex: 1 1 0% !important;
    min-height: 0 !important;
    overflow-y: auto !important;
    overflow-x: auto !important;
    position: relative !important;
    z-index: 10 !important;
}

/* 0. Table Header Contrast, Sticky Pinned & Separation */
.dispatch-grid-thead,
.dispatch-grid-table-container thead {
    position: sticky !important;
    top: 0 !important;
    z-index: 20 !important;
    background-color: #f3f4f6 !important;
    border-bottom: 1px solid #e5e7eb !important;
}

.dispatch-grid-th,
.dispatch-grid-thead th,
.dispatch-grid-table-container thead th {
    position: sticky !important;
    top: 0 !important;
    z-index: 20 !important;
    background-color: #f3f4f6 !important;
    color: #374151 !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
    font-size: 0.75rem !important;
    line-height: 1rem !important;
    border-bottom: 1px solid #e5e7eb !important;
}

.dispatch-grid-row {
    position: relative !important;
    z-index: 1 !important;
}

/* Full-Row Background Tinting by Status - 200-300 Saturated Fills with Left Accent Bar */
.dispatch-grid-row.bg-emerald-200,
.dispatch-grid-row[data-status="completed"] {
    background-color: #a7f3d0 !important;
    border-bottom: 1px solid #34d399 !important;
    border-left: 6px solid #059669 !important;
}
.dispatch-grid-row.bg-emerald-200:hover,
.dispatch-grid-row[data-status="completed"]:hover {
    background-color: #6ee7b7 !important;
}

.dispatch-grid-row.bg-blue-200,
.dispatch-grid-row[data-status="dispatched"] {
    background-color: #bfdbfe !important;
    border-bottom: 1px solid #60a5fa !important;
    border-left: 6px solid #2563eb !important;
}
.dispatch-grid-row.bg-blue-200:hover,
.dispatch-grid-row[data-status="dispatched"]:hover {
    background-color: #93c5fd !important;
}

.dispatch-grid-row.bg-amber-200,
.dispatch-grid-row[data-status="enroute"],
.dispatch-grid-row[data-status="enroute_pickup"],
.dispatch-grid-row[data-status="driver_enroute"] {
    background-color: #fde68a !important;
    border-bottom: 1px solid #fbbf24 !important;
    border-left: 6px solid #d97706 !important;
}
.dispatch-grid-row.bg-amber-200:hover,
.dispatch-grid-row[data-status="enroute"]:hover,
.dispatch-grid-row[data-status="enroute_pickup"]:hover,
.dispatch-grid-row[data-status="driver_enroute"]:hover {
    background-color: #fcd34d !important;
}

.dispatch-grid-row.bg-purple-200,
.dispatch-grid-row[data-status="on_location"],
.dispatch-grid-row[data-status="arrived"] {
    background-color: #e9d5ff !important;
    border-bottom: 1px solid #c084fc !important;
    border-left: 6px solid #9333ea !important;
}
.dispatch-grid-row.bg-purple-200:hover,
.dispatch-grid-row[data-status="on_location"]:hover,
.dispatch-grid-row[data-status="arrived"]:hover {
    background-color: #d8b4fe !important;
}

.dispatch-grid-row.bg-orange-200,
.dispatch-grid-row[data-status="in_progress"],
.dispatch-grid-row[data-status="started"],
.dispatch-grid-row[data-status="pob"],
.dispatch-grid-row[data-status="passenger_on_board"] {
    background-color: #fed7aa !important;
    border-bottom: 1px solid #fb923c !important;
    border-left: 6px solid #ea580c !important;
}
.dispatch-grid-row.bg-orange-200:hover,
.dispatch-grid-row[data-status="in_progress"]:hover,
.dispatch-grid-row[data-status="started"]:hover,
.dispatch-grid-row[data-status="pob"]:hover,
.dispatch-grid-row[data-status="passenger_on_board"]:hover {
    background-color: #fdba74 !important;
}

.dispatch-grid-row.bg-rose-200,
.dispatch-grid-row[data-status="canceled"],
.dispatch-grid-row[data-status="cancelled"] {
    background-color: #fecdd3 !important;
    border-bottom: 1px solid #fb7185 !important;
    border-left: 6px solid #e11d48 !important;
}
.dispatch-grid-row.bg-rose-200:hover,
.dispatch-grid-row[data-status="canceled"]:hover,
.dispatch-grid-row[data-status="cancelled"]:hover {
    background-color: #fda4af !important;
}

.dispatch-grid-row.bg-slate-200,
.dispatch-grid-row[data-status="created"],
.dispatch-grid-row[data-status="draft"],
.dispatch-grid-row[data-status="pending"],
.dispatch-grid-row[data-status="unassigned"] {
    background-color: #e2e8f0 !important;
    border-bottom: 1px solid #94a3b8 !important;
    border-left: 6px solid #64748b !important;
}
.dispatch-grid-row.bg-slate-200:hover,
.dispatch-grid-row[data-status="created"]:hover,
.dispatch-grid-row[data-status="draft"]:hover,
.dispatch-grid-row[data-status="pending"]:hover,
.dispatch-grid-row[data-status="unassigned"]:hover {
    background-color: #cbd5e1 !important;
}

/* Dispatch Grid High-Vibrancy Status Badge Color Coding */
.dispatch-grid-row span[data-status="dispatched"] {
    background-color: #ffffff !important;
    color: #1e3a8a !important;
    border: 1px solid #3b82f6 !important;
}
.dispatch-grid-row span[data-status="enroute"],
.dispatch-grid-row span[data-status="enroute_pickup"],
.dispatch-grid-row span[data-status="driver_enroute"] {
    background-color: #ffffff !important;
    color: #78350f !important;
    border: 1px solid #d97706 !important;
}
.dispatch-grid-row span[data-status="in_progress"],
.dispatch-grid-row span[data-status="started"],
.dispatch-grid-row span[data-status="pob"],
.dispatch-grid-row span[data-status="passenger_on_board"] {
    background-color: #ffffff !important;
    color: #7c2d12 !important;
    border: 1px solid #ea580c !important;
}
.dispatch-grid-row span[data-status="on_location"],
.dispatch-grid-row span[data-status="arrived"] {
    background-color: #ffffff !important;
    color: #581c87 !important;
    border: 1px solid #9333ea !important;
}
.dispatch-grid-row span[data-status="completed"] {
    background-color: #ffffff !important;
    color: #064e3b !important;
    border: 1px solid #059669 !important;
}
.dispatch-grid-row span[data-status="canceled"],
.dispatch-grid-row span[data-status="cancelled"] {
    background-color: #ffffff !important;
    color: #881337 !important;
    border: 1px solid #e11d48 !important;
}
.dispatch-grid-row span[data-status="created"],
.dispatch-grid-row span[data-status="draft"],
.dispatch-grid-row span[data-status="pending"],
.dispatch-grid-row span[data-status="unassigned"] {
    background-color: #ffffff !important;
    color: #0f172a !important;
    border: 1px solid #64748b !important;
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

/* ==========================================================================
   Kanban Column Status Accent border-t-4 Colors
   ========================================================================== */
.kanban-board .kanban-column[data-column-id="created"],
.kanban-board .kanban-column[data-column-id="pending"],
.kanban-board .kanban-column[data-column-id="unassigned"] {
    border-top: 4px solid #94a3b8 !important; /* gray */
}

.kanban-board .kanban-column[data-column-id="dispatched"] {
    border-top: 4px solid #8b5cf6 !important; /* purple */
}

.kanban-board .kanban-column[data-column-id="started"],
.kanban-board .kanban-column[data-column-id="in_progress"],
.kanban-board .kanban-column[data-column-id="in-progress"] {
    border-top: 4px solid #3b82f6 !important; /* blue */
}

.kanban-board .kanban-column[data-column-id="enroute"],
.kanban-board .kanban-column[data-column-id="driver_enroute"] {
    border-top: 4px solid #f59e0b !important; /* amber */
}

.kanban-board .kanban-column[data-column-id="completed"] {
    border-top: 4px solid #10b981 !important; /* green */
}

.kanban-board .kanban-column[data-column-id="canceled"],
.kanban-board .kanban-column[data-column-id="cancelled"] {
    border-top: 4px solid #ef4444 !important; /* red */
}

/* ==========================================================================
   Dispatch Grid High-Contrast & Explicit Readability Styles
   ========================================================================== */
.dispatch-grid-thead th,
.dispatch-grid-th,
.dispatch-grid-table-container table thead th {
    background-color: #f3f4f6 !important;
    color: #111827 !important; /* Very dark charcoal */
    font-weight: 800 !important;
    font-size: 0.75rem !important;
    border-bottom: 2px solid #cbd5e1 !important;
}

.dispatch-grid-row td {
    color: #111827 !important; /* Force high contrast dark charcoal for cell texts */
    font-weight: 600 !important;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1) !important;
}

.dispatch-grid-row td div,
.dispatch-grid-row td span:not([class*="bg-"]):not([class*="text-"]) {
    color: #111827 !important;
    font-weight: 600 !important;
}

.dispatch-grid-row td a {
    color: #111827 !important;
    font-weight: 700 !important;
}

/* Deep navy blue for high contrast phone link underneath row background tints */
.dispatch-grid-row td a.text-blue-900 {
    color: #1e3a8a !important; 
    text-decoration: underline !important;
}

.dispatch-grid-row td a.text-blue-900:hover {
    color: #172554 !important;
}

/* Specific styling for route text to stand out nicely */
.dispatch-grid-row td span.text-blue-900 {
    color: #1e3a8a !important;
    font-weight: 800 !important;
}

.dispatch-grid-row td span.text-gray-950,
.dispatch-grid-row td span.truncate {
    color: #111827 !important;
    font-weight: 600 !important;
}

.dispatch-grid-toolbar {
    background-color: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    color: #111827 !important;
}

.dispatch-grid-toolbar h2 {
    color: #111827 !important;
}

.dispatch-grid-toolbar p {
    color: #4b5563 !important;
}

/* Ensure empty/no-orders state is high contrast too */
.dispatch-grid-table-container tbody tr td.text-center {
    background-color: #ffffff !important;
    color: #111827 !important;
}

.dispatch-grid-table-container tbody tr td.text-center .text-gray-900 {
    color: #111827 !important;
}

.dispatch-grid-table-container tbody tr td.text-center .text-gray-500 {
    color: #4b5563 !important;
}

/* ==========================================================================
   High-Contrast Kanban Column, Gradient Backgrounds & Badge Color Coding Styles
   ========================================================================== */

/* Created */
.kanban-board .kanban-column[data-column-id="created"],
.kanban-board .kanban-column[data-column-id="pending"],
.kanban-board .kanban-column[data-column-id="unassigned"] {
    border-top: 4px solid #2563eb !important;
    background: linear-gradient(180deg, rgba(37, 99, 235, 0.12) 0%, rgba(26, 29, 36, 0.4) 100%) !important;
}
.kanban-board .kanban-column[data-column-id="created"] .kanban-column-count,
.kanban-board .kanban-column[data-column-id="pending"] .kanban-column-count,
.kanban-board .kanban-column[data-column-id="unassigned"] .kanban-column-count {
    background-color: #2563eb !important;
    color: #ffffff !important;
    font-weight: 700 !important;
}

/* Dispatched */
.kanban-board .kanban-column[data-column-id="dispatched"] {
    border-top: 4px solid #f59e0b !important;
    background: linear-gradient(180deg, rgba(245, 158, 11, 0.12) 0%, rgba(26, 29, 36, 0.4) 100%) !important;
}
.kanban-board .kanban-column[data-column-id="dispatched"] .kanban-column-count {
    background-color: #f59e0b !important;
    color: #111827 !important;
    font-weight: 700 !important;
}

/* Started / En Route */
.kanban-board .kanban-column[data-column-id="started"],
.kanban-board .kanban-column[data-column-id="enroute"],
.kanban-board .kanban-column[data-column-id="enroute_pickup"],
.kanban-board .kanban-column[data-column-id="driver_enroute"] {
    border-top: 4px solid #8b5cf6 !important;
    background: linear-gradient(180deg, rgba(139, 92, 246, 0.12) 0%, rgba(26, 29, 36, 0.4) 100%) !important;
}
.kanban-board .kanban-column[data-column-id="started"] .kanban-column-count,
.kanban-board .kanban-column[data-column-id="enroute"] .kanban-column-count,
.kanban-board .kanban-column[data-column-id="enroute_pickup"] .kanban-column-count,
.kanban-board .kanban-column[data-column-id="driver_enroute"] .kanban-column-count {
    background-color: #8b5cf6 !important;
    color: #ffffff !important;
    font-weight: 700 !important;
}

/* On Location */
.kanban-board .kanban-column[data-column-id="on_location"],
.kanban-board .kanban-column[data-column-id="arrived"] {
    border-top: 4px solid #06b6d4 !important;
    background: linear-gradient(180deg, rgba(6, 182, 212, 0.12) 0%, rgba(26, 29, 36, 0.4) 100%) !important;
}
.kanban-board .kanban-column[data-column-id="on_location"] .kanban-column-count,
.kanban-board .kanban-column[data-column-id="arrived"] .kanban-column-count {
    background-color: #06b6d4 !important;
    color: #111827 !important;
    font-weight: 700 !important;
}

/* POB (Passenger On Board) */
.kanban-board .kanban-column[data-column-id="pob"],
.kanban-board .kanban-column[data-column-id="in_progress"],
.kanban-board .kanban-column[data-column-id="in-progress"] {
    border-top: 4px solid #10b981 !important;
    background: linear-gradient(180deg, rgba(16, 185, 129, 0.12) 0%, rgba(26, 29, 36, 0.4) 100%) !important;
}
.kanban-board .kanban-column[data-column-id="pob"] .kanban-column-count,
.kanban-board .kanban-column[data-column-id="in_progress"] .kanban-column-count,
.kanban-board .kanban-column[data-column-id="in-progress"] .kanban-column-count {
    background-color: #10b981 !important;
    color: #ffffff !important;
    font-weight: 700 !important;
}

/* Completed */
.kanban-board .kanban-column[data-column-id="completed"] {
    border-top: 4px solid #475569 !important;
    background: linear-gradient(180deg, rgba(71, 85, 105, 0.12) 0%, rgba(26, 29, 36, 0.4) 100%) !important;
}
.kanban-board .kanban-column[data-column-id="completed"] .kanban-column-count {
    background-color: #475569 !important;
    color: #ffffff !important;
    font-weight: 700 !important;
}

/* Canceled */
.kanban-board .kanban-column[data-column-id="canceled"],
.kanban-board .kanban-column[data-column-id="cancelled"] {
    border-top: 4px solid #ef4444 !important;
    background: linear-gradient(180deg, rgba(239, 68, 68, 0.12) 0%, rgba(26, 29, 36, 0.4) 100%) !important;
}
.kanban-board .kanban-column[data-column-id="canceled"] .kanban-column-count,
.kanban-board .kanban-column[data-column-id="cancelled"] .kanban-column-count {
    background-color: #ef4444 !important;
    color: #ffffff !important;
    font-weight: 700 !important;
}

/* Kanban card container top accent trim overrides */
.kanban-board .kanban-card {
    overflow: hidden !important;
    border-top: none !important;
}

.kanban-board .kanban-card .kanban-card-content {
    border-top-width: 4px;
    border-top-style: solid;
    border-top-left-radius: 0.5rem;
    border-top-right-radius: 0.5rem;
    overflow: hidden;
}

/* Card top border status colors */
.kanban-board .kanban-card-content.border-t-slate-500,
.kanban-board .kanban-column[data-column-id="created"] .kanban-card-content,
.kanban-board .kanban-column[data-column-id="pending"] .kanban-card-content,
.kanban-board .kanban-column[data-column-id="unassigned"] .kanban-card-content {
    border-top-color: #2563eb !important;
}

.kanban-board .kanban-card-content.border-t-blue-500,
.kanban-board .kanban-column[data-column-id="dispatched"] .kanban-card-content {
    border-top-color: #f59e0b !important;
}

.kanban-board .kanban-card-content.border-t-amber-500,
.kanban-board .kanban-column[data-column-id="started"] .kanban-card-content,
.kanban-board .kanban-column[data-column-id="enroute"] .kanban-card-content,
.kanban-board .kanban-column[data-column-id="enroute_pickup"] .kanban-card-content,
.kanban-board .kanban-column[data-column-id="driver_enroute"] .kanban-card-content {
    border-top-color: #8b5cf6 !important;
}

.kanban-board .kanban-card-content.border-t-purple-500,
.kanban-board .kanban-column[data-column-id="on_location"] .kanban-card-content,
.kanban-board .kanban-column[data-column-id="arrived"] .kanban-card-content {
    border-top-color: #06b6d4 !important;
}

.kanban-board .kanban-card-content.border-t-orange-500,
.kanban-board .kanban-column[data-column-id="pob"] .kanban-card-content,
.kanban-board .kanban-column[data-column-id="in_progress"] .kanban-card-content,
.kanban-board .kanban-column[data-column-id="in-progress"] .kanban-card-content {
    border-top-color: #10b981 !important;
}

.kanban-board .kanban-card-content.border-t-emerald-500,
.kanban-board .kanban-column[data-column-id="completed"] .kanban-card-content {
    border-top-color: #475569 !important;
}

.kanban-board .kanban-card-content.border-t-rose-500,
.kanban-board .kanban-card-content.border-t-red-500,
.kanban-board .kanban-column[data-column-id="canceled"] .kanban-card-content,
.kanban-board .kanban-column[data-column-id="cancelled"] .kanban-card-content {
    border-top-color: #ef4444 !important;
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


// 12. Sync updated bundles to future-limo-dispatch-console-1 container
try {
    const { execSync } = require('child_process');
    console.log('--- Syncing updated bundles to future-limo-dispatch-console-1 ---');
    execSync('docker cp /home/wert/fleetbase/console/dist/engines-dist/@fleetbase/fleetops-engine/assets/engine.js future-limo-dispatch-console-1:/usr/share/nginx/html/engines-dist/@fleetbase/fleetops-engine/assets/engine.js', { stdio: 'inherit' });
    execSync('docker cp /home/wert/fleetbase/console/dist/engines-dist/@fleetbase/fleetops-engine/assets/engine.css future-limo-dispatch-console-1:/usr/share/nginx/html/engines-dist/@fleetbase/fleetops-engine/assets/engine.css', { stdio: 'inherit' });
    execSync('docker cp /home/wert/fleetbase/console/dist/assets/vendor.js future-limo-dispatch-console-1:/usr/share/nginx/html/assets/vendor.js', { stdio: 'inherit' });
    execSync('docker cp /home/wert/fleetbase/console/dist/assets/@fleetbase/console.js future-limo-dispatch-console-1:/usr/share/nginx/html/assets/@fleetbase/console.js', { stdio: 'inherit' });
    execSync('docker cp /home/wert/fleetbase/console/dist/assets/@fleetbase/console.css future-limo-dispatch-console-1:/usr/share/nginx/html/assets/@fleetbase/console.css', { stdio: 'inherit' });
    try {
        execSync('cat /home/wert/fleetbase/console/dist/fleetbase.config.json | docker exec -i future-limo-dispatch-console-1 sh -c "cat > /usr/share/nginx/html/fleetbase.config.json"', { stdio: 'inherit' });
    } catch (cfgErr) {
        console.warn('Note: Could not overwrite container fleetbase.config.json:', cfgErr.message);
    }
    console.log('✓ All assets synced to docker container successfully!');
} catch (e) {
    console.error('Failed to sync to container:', e.message);
}

console.log('--- Dispatch Grid Build Complete! ---');
