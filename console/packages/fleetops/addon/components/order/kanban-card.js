import Component from '@glimmer/component';
import { inject as service } from '@ember/service';

export default class OrderKanbanCardComponent extends Component {
    @service orderActions;

    get statusBorderColor() {
        const status = (this.args.card?.status || '').toLowerCase();
        switch (status) {
            case 'created':
            case 'pending':
            case 'unassigned':
            case 'draft':
                return 'border-t-slate-500';
            case 'dispatched':
                return 'border-t-blue-500';
            case 'enroute':
            case 'enroute_pickup':
            case 'driver_enroute':
                return 'border-t-amber-500';
            case 'on_location':
            case 'arrived':
                return 'border-t-purple-500';
            case 'pob':
            case 'passenger_on_board':
            case 'started':
            case 'in_progress':
                return 'border-t-orange-500';
            case 'completed':
                return 'border-t-emerald-500';
            case 'canceled':
            case 'cancelled':
                return 'border-t-rose-500';
            default:
                return 'border-t-slate-500';
        }
    }
}
