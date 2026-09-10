import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { isArray } from '@ember/array';
import { action } from '@ember/object';

function getTodayDateString() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function extractDateString(raw) {
    if (!raw) return null;
    if (raw instanceof Date) {
        if (isNaN(raw.getTime())) return null;
        const y = raw.getFullYear();
        const m = String(raw.getMonth() + 1).padStart(2, '0');
        const d = String(raw.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }
    if (typeof raw === 'string') {
        const trimmed = raw.trim();
        if (trimmed.startsWith('0000-00-00')) return null;
        if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
            return trimmed;
        }
        if (/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}/.test(trimmed)) {
            return trimmed.substring(0, 10);
        }
        const parsed = new Date(trimmed);
        if (!isNaN(parsed.getTime())) {
            const y = parsed.getFullYear();
            const m = String(parsed.getMonth() + 1).padStart(2, '0');
            const d = String(parsed.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }
        const match = trimmed.match(/^(\d{4}-\d{2}-\d{2})/);
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
    return `${formattedHours}:${minutes} ${ampm}`;
}

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
    @tracked dateFilterMode = 'today';
    @tracked customSelectedDate = getTodayDateString();

    get todayDateString() {
        return getTodayDateString();
    }

    get orders() {
        const orders = this.args.orders ?? [];
        if (isArray(orders)) {
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
        const filtered = orders.filter((order) => {
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
            canceled: 0,
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
            list = list.filter((order) => {
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
            list = list.filter((order) => {
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
                    class: 'bg-white text-blue-900 border border-blue-500 shadow-sm',
                };
            case 'enroute':
            case 'enroute_pickup':
            case 'driver_enroute':
                return {
                    title: 'En Route',
                    class: 'bg-white text-amber-900 border border-amber-500 shadow-sm',
                };
            case 'on_location':
            case 'arrived':
                return {
                    title: 'On Location',
                    class: 'bg-white text-purple-900 border border-purple-500 shadow-sm',
                };
            case 'pob':
            case 'passenger_on_board':
            case 'in_progress':
            case 'started':
                return {
                    title: 'POB',
                    class: 'bg-white text-orange-900 border border-orange-500 shadow-sm',
                };
            case 'completed':
                return {
                    title: 'Completed',
                    class: 'bg-white text-emerald-900 border border-emerald-500 shadow-sm',
                };
            case 'canceled':
            case 'cancelled':
                return {
                    title: 'Canceled',
                    class: 'bg-white text-rose-900 border border-rose-500 shadow-sm',
                };
            case 'created':
            case 'draft':
            case 'pending':
            case 'unassigned':
            default:
                return {
                    title: 'Created',
                    class: 'bg-white text-slate-900 border border-slate-500 shadow-sm',
                };
        }
    }

    getStatusBadgeClass(status) {
        return this.getStatusBadge(status).class;
    }

    getOrderInfo(order) {
        const id = order.public_id || order.id || 'N/A';
        const internalId = order.internal_id || order.tracking || '';
        const scheduledAt = order.scheduled_at_formatted || order.scheduled_at || order.createdAtShort || '';
        const st = order.status || 'created';
        const statusBadge = this.getStatusBadge(st);
        return { id, internalId, scheduledAt, status: st, statusTitle: statusBadge.title };
    }

    getPassengerInfo(order) {
        const name = order.customer?.name || order.customer_name || 'Guest Passenger';
        const phone = order.customer?.phone || order.customer_phone || '';
        let note = '';
        if (order.notes) {
            const lines = order.notes.split('\n').filter((l) => !l.includes('Coordinates:') && !l.includes('Locations:'));
            note = lines.slice(0, 2).join(' ').trim();
        }
        return { name, phone, note };
    }

    getRouteInfo(order) {
        const p = order.payload?.pickup;
        const d = order.payload?.dropoff;
        const pickupName = p?.name || order.pickup_name || p?.address || p?.street1 || 'Unspecified Pickup';
        const dropoffName = d?.name || order.dropoff_name || d?.address || d?.street1 || 'Unspecified Dropoff';
        const text = `${pickupName} ➔ ${dropoffName}`;
        return {
            pickup: pickupName,
            dropoff: dropoffName,
            text,
        };
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
                statusClass: 'text-gray-400 font-normal italic',
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
        } else if (order.status === 'pob' || order.status === 'in_progress' || order.status === 'passenger_on_board') {
            status = 'POB';
            dotClass = 'bg-orange-400 animate-pulse';
            statusClass = 'text-orange-300 font-medium';
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
            statusClass,
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
                badgeClass: 'bg-red-900/80 text-red-200 border-red-500/60 shadow-sm',
            });
        }

        if (notes.includes('meet') || notes.includes('greet') || meta.meet_and_greet || notes.includes('inside pickup')) {
            flags.push({
                type: 'meet_greet',
                label: 'Meet & Greet',
                icon: 'user-tag',
                badgeClass: 'bg-amber-900/80 text-amber-200 border-amber-500/60 shadow-sm',
            });
        }

        if (notes.includes('vip') || meta.vip || notes.includes('priority: high')) {
            flags.push({
                type: 'vip',
                label: 'VIP',
                icon: 'crown',
                badgeClass: 'bg-purple-900/80 text-purple-200 border-purple-500/60 shadow-sm',
            });
        }

        if (notes.includes('airport') || notes.includes('flight') || meta.airport || notes.includes('lga') || notes.includes('jfk') || notes.includes('ewr')) {
            flags.push({
                type: 'airport',
                label: 'Airport Transfer',
                icon: 'plane',
                badgeClass: 'bg-blue-900/80 text-blue-200 border-blue-500/60 shadow-sm',
            });
        }

        return flags;
    }

    get gridRows() {
        return this.filteredOrders.map((order) => ({
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
        }));
    }

    @action
    setDateMode(mode) {
        this.dateFilterMode = mode;
    }

    @action
    onCustomDateChange(event) {
        const val = event?.target?.value || event;
        if (val) {
            this.customSelectedDate = val;
            this.dateFilterMode = 'custom';
        }
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
    openOrderDetails(order) {
        if (typeof this.args.onOrderClick === 'function') {
            this.args.onOrderClick(order);
        } else if (this.orderActions?.transition?.view) {
            this.orderActions.transition.view(order);
        }
    }

    @action
    onClickRow(order) {
        return this.openOrderDetails(order);
    }

    @action
    stopEventPropagation(e) {
        e?.stopPropagation?.();
    }
}
