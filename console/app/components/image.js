import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { isBlank } from '@ember/utils';

export default class ImageComponent extends Component {
    @tracked customSrc;

    get src() {
        if (this.customSrc) {
            return this.customSrc;
        }

        if (isBlank(this.args.src) && this.args.fallbackSrc) {
            return this.args.fallbackSrc;
        }

        return this.args.src;
    }

    @action onError(event) {
        const { fallbackSrc } = this.args;
        if (fallbackSrc && this.customSrc !== fallbackSrc) {
            this.customSrc = fallbackSrc;
        }
    }
}
