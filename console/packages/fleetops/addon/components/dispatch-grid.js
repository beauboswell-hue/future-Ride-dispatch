import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { isArray } from '@ember/array';
import { action } from '@ember/object';

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

export default class DispatchGridComponent extends Component {
    @service orderActions;

    @tracked searchQuery = '';
    @tracked statusFilter = 'all';

    get orders() {
        const orders = this.args.orders ?? [];
        if (isArray(orders)) {
            return orders;
        }
        return orders.toArray ? orders.toArray() : [];
    }

    get statusCounts() {
        const orders = this.orders;
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
            } else if (st === 'pob' || st === 'in_progress' || st === 'started') {
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
                const st = (order.status || '').toLowerCase();
                if (this.statusFilter === 'created') {
                    return st === 'created' || st === 'draft' || st === 'pending' || st === 'unassigned';
                }
                if (this.statusFilter === 'dispatched') {
                    return st === 'dispatched';
                }
                if (this.statusFilter === 'enroute_pickup' || this.statusFilter === 'enroute') {
                    return st === 'enroute_pickup' || st === 'enroute' || st === 'driver_enroute';
                }
                if (this.statusFilter === 'on_location' || this.statusFilter === 'arrived') {
                    return st === 'on_location' || st === 'arrived';
                }
                if (this.statusFilter === 'pob' || this.statusFilter === 'in_progress') {
                    return st === 'pob' || st === 'in_progress' || st === 'started';
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
        if (this.hasConflict(order) || order.status === 'canceled' || order.status === 'cancelled') {
            return 'dispatch-grid-row-conflict';
        }
        switch (order.status) {
            case 'completed':
                return 'dispatch-grid-row-completed';
            case 'pob':
            case 'in_progress':
            case 'started':
                return 'dispatch-grid-row-in-progress';
            case 'on_location':
            case 'arrived':
                return 'dispatch-grid-row-arrived';
            case 'enroute_pickup':
            case 'enroute':
            case 'driver_enroute':
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
            case 'enroute':
            case 'enroute_pickup':
            case 'driver_enroute':
                return 'bg-amber-900/80 text-amber-200 border border-amber-500/60 shadow-sm';
            case 'on_location':
            case 'arrived':
                return 'bg-purple-900/80 text-purple-200 border border-purple-500/60 shadow-sm';
            case 'in_progress':
            case 'started':
            case 'pob':
                return 'bg-orange-900/80 text-orange-200 border border-orange-500/60 shadow-sm';
            case 'completed':
                return 'bg-emerald-900/80 text-emerald-200 border border-emerald-500/60 shadow-sm';
            case 'canceled':
            case 'cancelled':
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
            enroute: 'En Route',
            enroute_pickup: 'En Route',
            driver_enroute: 'En Route',
            arrived: 'On Location',
            on_location: 'On Location',
            in_progress: 'POB',
            started: 'POB',
            pob: 'POB',
            completed: 'Completed',
            canceled: 'Canceled',
            cancelled: 'Canceled'
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
            const lines = order.notes.split('\n').filter(l => !l.includes('Coordinates:') && !l.includes('Locations:'));
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

        if (order.status === 'enroute_pickup' || order.status === 'enroute') {
            status = 'En Route';
            dotClass = 'bg-amber-400';
            statusClass = 'text-amber-300 font-medium';
        } else if (order.status === 'on_location' || order.status === 'arrived') {
            status = 'On Location';
            dotClass = 'bg-purple-400';
            statusClass = 'text-purple-300 font-medium';
        } else if (order.status === 'pob' || order.status === 'in_progress') {
            status = 'POB';
            dotClass = 'bg-emerald-400 animate-pulse';
            statusClass = 'text-emerald-300 font-medium';
        } else if (order.status === 'completed') {
            status = 'Completed';
            dotClass = 'bg-emerald-500';
            statusClass = 'text-emerald-400 font-medium';
        } else if (order.status === 'canceled' || order.status === 'cancelled') {
            status = 'Canceled';
            dotClass = 'bg-red-500';
            statusClass = 'text-red-400 font-medium';
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

        const tagMatch = (order.notes || '').match(/\[Tags:\s*([^\]]+)\]/i);
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

    @action
    setStatusFilter(filter) {
        this.statusFilter = filter;
    }

    @action
    clearSearch() {
        this.searchQuery = '';
    }

    @action
    onClickRow(order) {
        if (typeof this.args.onOrderClick === 'function') {
            this.args.onOrderClick(order);
        } else if (this.orderActions?.transition?.view) {
            this.orderActions.transition.view(order);
        }
    }

    @action
    stopEventPropagation(e) {
        e?.stopPropagation?.();
    }
}
