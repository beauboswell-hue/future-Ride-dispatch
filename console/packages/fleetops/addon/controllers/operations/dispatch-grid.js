import Controller from '@ember/controller';
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
