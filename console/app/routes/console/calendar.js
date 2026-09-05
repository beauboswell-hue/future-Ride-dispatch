import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class ConsoleCalendarRoute extends Route {
    @service router;

    beforeModel() {
        return this.router.transitionTo('console.fleet-ops.operations.calendar');
    }
}
