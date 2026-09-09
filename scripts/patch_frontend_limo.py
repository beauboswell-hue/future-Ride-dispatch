import re

# 1. Patch vendor.js
vendor_path = '/home/wert/fleetbase/console/dist/assets/vendor.js'
with open(vendor_path, 'r', encoding='utf-8') as f:
    vendor_js = f.read()

target_safe = '''  var _default = _exports.default = (0, _helper.helper)(function safeHumanize(params) {
    return (0, _humanize.humanize)(params.map(param => `${param}`));
  });'''

replacement_safe = '''  var _default = _exports.default = (0, _helper.helper)(function safeHumanize(params) {
    const statusMap = { created: 'Created', dispatched: 'Dispatched', enroute_pickup: 'En Route', on_location: 'On Location', pob: 'Passenger On Board', completed: 'Completed', canceled: 'Canceled' };
    if (params.length === 1 && statusMap[params[0]]) {
      return statusMap[params[0]];
    }
    return (0, _humanize.humanize)(params.map(param => statusMap[param] || `${param}`));
  });'''

assert target_safe in vendor_js, 'target_safe not found in vendor.js'
vendor_js = vendor_js.replace(target_safe, replacement_safe, 1)

target_smart = '''  function smartHumanize(string) {
    if ((0, _utils.typeOf)(string) !== 'string') {
      return string;
    }
    const uppercase = ['api', 'vat', 'id', 'uuid', 'sku', 'ean', 'upc', 'erp', 'tms', 'wms', 'ltl', 'ftl', 'lcl', 'fcl', 'rfid', 'jot', 'roi', 'eta', 'pod', 'asn', 'oem', 'ddp', 'fob', 'gsm', 'etd', 'eta', 'ect', 'aws', 'gcp'];
    return (0, _humanize.humanize)([(0, _string.decamelize)(string)]).toLowerCase().split(' ').map(word => {
      if (uppercase.includes(word)) {
        return word.toUpperCase();
      }
      return (0, _string.capitalize)(word);
    }).join(' ');
  }'''

replacement_smart = '''  function smartHumanize(string) {
    if ((0, _utils.typeOf)(string) !== 'string') {
      return string;
    }
    const statusMap = { created: 'Created', dispatched: 'Dispatched', enroute_pickup: 'En Route', on_location: 'On Location', pob: 'Passenger On Board', completed: 'Completed', canceled: 'Canceled' };
    if (statusMap[string]) {
      return statusMap[string];
    }
    const uppercase = ['api', 'vat', 'id', 'uuid', 'sku', 'ean', 'upc', 'erp', 'tms', 'wms', 'ltl', 'ftl', 'lcl', 'fcl', 'rfid', 'jot', 'roi', 'eta', 'pod', 'asn', 'oem', 'ddp', 'fob', 'gsm', 'etd', 'eta', 'ect', 'aws', 'gcp'];
    return (0, _humanize.humanize)([(0, _string.decamelize)(string)]).toLowerCase().split(' ').map(word => {
      if (uppercase.includes(word)) {
        return word.toUpperCase();
      }
      return (0, _string.capitalize)(word);
    }).join(' ');
  }'''

assert target_smart in vendor_js, 'target_smart not found in vendor.js'
vendor_js = vendor_js.replace(target_smart, replacement_smart, 1)

with open(vendor_path, 'w', encoding='utf-8') as f:
    f.write(vendor_js)
print('Patched vendor.js successfully')

# 2. Patch ember-ui sources in node_modules
ui_safe = '/home/wert/fleetbase/console/node_modules/@fleetbase/ember-ui/addon/helpers/safe-humanize.js'
with open(ui_safe, 'r', encoding='utf-8') as f:
    src_ui_safe = f.read()
if 'statusMap' not in src_ui_safe:
    src_ui_safe = '''import { helper } from '@ember/component/helper';
import { humanize } from 'ember-cli-string-helpers/helpers/humanize';

const statusMap = {
    created: 'Created',
    dispatched: 'Dispatched',
    enroute_pickup: 'En Route',
    on_location: 'On Location',
    pob: 'Passenger On Board',
    completed: 'Completed',
    canceled: 'Canceled',
};

export default helper(function safeHumanize(params) {
    if (params.length === 1 && statusMap[params[0]]) {
        return statusMap[params[0]];
    }
    return humanize(params.map((param) => statusMap[param] || `${param}`));
});
'''
    with open(ui_safe, 'w', encoding='utf-8') as f:
        f.write(src_ui_safe)
    print('Patched @fleetbase/ember-ui safe-humanize.js')

ui_smart = '/home/wert/fleetbase/console/node_modules/@fleetbase/ember-ui/addon/utils/smart-humanize.js'
with open(ui_smart, 'r', encoding='utf-8') as f:
    src_ui_smart = f.read()
if 'statusMap' not in src_ui_smart:
    target_smart_src = '''    if (typeOf(string) !== 'string') {
        return string;
    }'''
    replacement_smart_src = '''    if (typeOf(string) !== 'string') {
        return string;
    }

    const statusMap = {
        created: 'Created',
        dispatched: 'Dispatched',
        enroute_pickup: 'En Route',
        on_location: 'On Location',
        pob: 'Passenger On Board',
        completed: 'Completed',
        canceled: 'Canceled',
    };
    if (statusMap[string]) {
        return statusMap[string];
    }'''
    assert target_smart_src in src_ui_smart
    src_ui_smart = src_ui_smart.replace(target_smart_src, replacement_smart_src, 1)
    with open(ui_smart, 'w', encoding='utf-8') as f:
        f.write(src_ui_smart)
    print('Patched @fleetbase/ember-ui smart-humanize.js')

# 3. Append badge CSS to @fleetbase/console.css as well
console_css_path = '/home/wert/fleetbase/console/dist/assets/@fleetbase/console.css'
with open(console_css_path, 'r', encoding='utf-8') as f:
    console_css = f.read()

with open('/home/wert/fleetbase/console/dist/engines-dist/@fleetbase/fleetops-engine/assets/engine.css', 'r', encoding='utf-8') as f:
    engine_css = f.read()

limo_css_marker = 'Limo Anywhere Dispatch Pipeline Kanban & Badge Styles'
assert limo_css_marker in engine_css
limo_css_block = engine_css[engine_css.index('/* ==========================================================================\n   ' + limo_css_marker):]

if limo_css_marker not in console_css:
    console_css += '\n' + limo_css_block
    with open(console_css_path, 'w', encoding='utf-8') as f:
        f.write(console_css)
    print('Appended Limo Anywhere CSS to console.css')

# 4. Sync files into fleetbase-console-1 container
import subprocess
print('Copying patched assets into fleetbase-console-1...')
subprocess.run(['docker', 'cp', '/home/wert/fleetbase/console/dist/engines-dist/@fleetbase/fleetops-engine/assets/engine.js', 'fleetbase-console-1:/usr/share/nginx/html/engines-dist/@fleetbase/fleetops-engine/assets/engine.js'], check=True)
subprocess.run(['docker', 'cp', '/home/wert/fleetbase/console/dist/engines-dist/@fleetbase/fleetops-engine/assets/engine.css', 'fleetbase-console-1:/usr/share/nginx/html/engines-dist/@fleetbase/fleetops-engine/assets/engine.css'], check=True)
subprocess.run(['docker', 'cp', '/home/wert/fleetbase/console/dist/assets/vendor.js', 'fleetbase-console-1:/usr/share/nginx/html/assets/vendor.js'], check=True)
subprocess.run(['docker', 'cp', '/home/wert/fleetbase/console/dist/assets/@fleetbase/console.css', 'fleetbase-console-1:/usr/share/nginx/html/assets/@fleetbase/console.css'], check=True)

print('All frontend files successfully updated and synced to container!')
