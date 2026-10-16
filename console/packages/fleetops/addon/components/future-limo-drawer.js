import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';

export default class FutureLimoDrawerComponent extends Component {
    @service currentUser;

    get bookingUrl() {
        const companyName = this.currentUser?.company?.name || this.currentUser?.companyName || '';
        if (companyName.includes('Future Ride')) {
            return 'https://futureride.website';
        }
        return 'https://future.limo';
    }

    get bookingTitle() {
        const companyName = this.currentUser?.company?.name || this.currentUser?.companyName || '';
        if (companyName.includes('Future Ride')) {
            return 'Future Ride Portal';
        }
        return 'Future Limo Portal';
    }

    @action
    close() {
        if (typeof this.args.onClose === 'function') {
            this.args.onClose();
        }
    }
}
