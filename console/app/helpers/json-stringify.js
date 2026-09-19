import { helper } from '@ember/component/helper';

export function jsonStringify([value, replacer, space]) {
    try {
        return JSON.stringify(value, replacer, space || 2);
    } catch (e) {
        return String(value);
    }
}

export default helper(jsonStringify);
