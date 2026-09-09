import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { isArray } from '@ember/array';
import { debug } from '@ember/debug';
import { task } from 'ember-concurrency';
import titleize from 'ember-cli-string-helpers/utils/titleize';
import smartHumanize from '@fleetbase/ember-ui/utils/smart-humanize';
import isUuid from '@fleetbase/ember-core/utils/is-uuid';

export default class OrderKanbanComponent extends Component {
    @service fetch;
    @service notifications;
    @service intl;
    @tracked statuses = [];
    @tracked orders = this.args.orders ?? [];
    @tracked orderConfig = this.args.orderConfig ?? null;

    #canonicalStages = ['created', 'dispatched', 'enroute_pickup', 'on_location', 'pob', 'completed', 'canceled'];

    #stageTitles = {
        created: 'Created',
        dispatched: 'Dispatched',
        enroute: 'En Route',
        enroute_pickup: 'En Route',
        driver_enroute: 'En Route',
        on_location: 'On Location',
        arrived: 'On Location',
        pob: 'POB (Passenger on Board)',
        started: 'POB (Passenger on Board)',
        in_progress: 'POB (Passenger on Board)',
        completed: 'Completed',
        canceled: 'Canceled',
        cancelled: 'Canceled',
    };

    get columns() {
        const loaded = isArray(this.statuses) ? this.statuses : [];

        // Normalize loaded statuses:
        // Collapse 'enroute' to 'enroute_pickup' to prevent duplicate 'En Route' columns
        const normalized = loaded.map((s) => (s === 'enroute' ? 'enroute_pickup' : s)).filter(Boolean);

        const canonical = this.#canonicalStages;
        const canonicalSet = new Set(canonical);
        const seen = new Set();
        const stageList = [];

        // Add canonical stages in their exact defined 1-7 sequence:
        // 1. Created, 2. Dispatched, 3. En Route, 4. On Location, 5. POB (Passenger on Board), 6. Completed, 7. Canceled
        for (const stage of canonical) {
            // Include if no statuses were loaded yet (default fallback) or if present in loaded statuses
            if (normalized.length === 0 || normalized.includes(stage)) {
                stageList.push(stage);
                seen.add(stage);
            }
        }

        // If there are any other custom non-canonical statuses loaded from config,
        // insert them before 'completed'
        const extras = [];
        for (const s of normalized) {
            if (!canonicalSet.has(s) && !seen.has(s)) {
                seen.add(s);
                extras.push(s);
            }
        }

        if (extras.length > 0) {
            const completedIdx = stageList.indexOf('completed');
            if (completedIdx !== -1) {
                stageList.splice(completedIdx, 0, ...extras);
            } else {
                stageList.push(...extras);
            }
        }

        return stageList.map((status, index) => ({
            id: status,
            title: this.#stageTitles[status] || titleize(smartHumanize(status)),
            position: index,
            cards: this.#getOrdersByStatus(status, this.orders),
        }));
    }

    constructor() {
        super(...arguments);
        this.loadStatuses.perform();
    }

    /* eslint-disable no-unused-vars */
    @action async handleCardMove(card, targetColumnId, targetPosition, sourceColumnId) {
        // work against the array the UI renders from
        const order = this.orders.find((o) => o.id === card.id);
        if (!order) {
            console.error('Order not found in this.orders:', card.id);
            return;
        }

        const prevStatus = order.status;

        // First check available activities, if the `targetColumnId` is one, update the order activity.
        // If it's not an available status, show an error notification
        try {
            const nextActivities = await this.fetch.get(`orders/next-activity/${order.id}`);
            if (isArray(nextActivities)) {
                const activity = nextActivities.find(
                    (activity) =>
                        activity.code === targetColumnId ||
                        (targetColumnId === 'enroute_pickup' && activity.code === 'enroute') ||
                        (targetColumnId === 'enroute' && activity.code === 'enroute_pickup')
                );
                if (activity) {
                    try {
                        await this.fetch.patch(`orders/update-activity/${order.id}`, { activity });
                        this.notifications.success(this.intl.t('order.kanban.status-updated', { status: targetColumnId }));
                        order.status = activity.code || targetColumnId;
                        // replace the tracked array to notify Glimmer
                        // (this keeps the *same* object reference, which is fine)
                        this.orders = this.orders.slice();
                    } catch (err) {
                        this.notifications.serverError(err);
                    }
                } else {
                    this.notifications.warning(this.intl.t('order.kanban.cannot-update-status', { status: targetColumnId }));
                }
            }
        } catch (err) {
            this.notifications.warning(this.intl.t('order.kanban.cannot-update-status', { status: targetColumnId }));
        }
    }

    @action handleArgsChange(el, [orderConfig, orders = []]) {
        if (isArray(orders)) {
            this.orders = orders;
        }

        if (isUuid(orderConfig)) {
            this.orderConfig = orderConfig;
        } else {
            this.orderConfig = null;
        }
        this.loadStatuses.perform();
    }

    #getOrdersByStatus(status, orders = []) {
        let filteredOrders = orders.filter((order) => {
            const st = (order.status || '').toLowerCase();
            if (status === 'created') {
                return st === 'created' || st === 'draft' || st === 'pending' || st === 'unassigned';
            }
            if (status === 'dispatched') {
                return st === 'dispatched';
            }
            if (status === 'enroute_pickup' || status === 'enroute') {
                return st === 'enroute_pickup' || st === 'enroute' || st === 'driver_enroute';
            }
            if (status === 'on_location') {
                return st === 'on_location' || st === 'arrived';
            }
            if (status === 'pob') {
                return st === 'pob' || st === 'in_progress' || st === 'started';
            }
            if (status === 'completed') {
                return st === 'completed';
            }
            if (status === 'canceled') {
                return st === 'canceled' || st === 'cancelled';
            }
            return st === status;
        });
        if (this.orderConfig) {
            filteredOrders = filteredOrders.filter((order) => order.order_config_uuid === this.orderConfig);
        }

        return filteredOrders;
    }

    @task *loadStatuses() {
        const params = {};
        if (this.orderConfig) params.order_config_uuid = this.orderConfig;

        try {
            const statuses = yield this.fetch.get('orders/statuses', params);
            this.statuses = isArray(statuses) ? statuses : [];
        } catch (err) {
            debug('Unable to load order statuses: ' + err.message);
        }
    }
}
