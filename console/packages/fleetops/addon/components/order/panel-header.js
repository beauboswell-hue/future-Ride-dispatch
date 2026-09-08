import Component from '@glimmer/component';

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
