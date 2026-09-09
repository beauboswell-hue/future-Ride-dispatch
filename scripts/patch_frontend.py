import re

# 1. Patch engine.js
engine_js_path = '/home/wert/fleetbase/console/dist/engines-dist/@fleetbase/fleetops-engine/assets/engine.js'
with open(engine_js_path, 'r', encoding='utf-8') as f:
    js_content = f.read()

# Replace start statuses in constructor
old_start = "start: ['created', 'dispatched', 'started']"
new_start = "start: ['created', 'dispatched']"
if old_start in js_content:
    js_content = js_content.replace(old_start, new_start)

# Replace column title mapping
old_title = "title: ({created:'Created',dispatched:'Dispatched',enroute_pickup:'En Route',on_location:'On Location',pob:'Passenger On Board',completed:'Completed',canceled:'Canceled'})[status] || (0, _titleize.default)((0, _smartHumanize.default)(status))"
new_title = "title: ({created:'Created',dispatched:'Dispatched',enroute_pickup:'En Route',enroute:'En Route',on_location:'On Location',pob:'POB (Passenger on Board)',completed:'Completed',canceled:'Canceled'})[status] || (0, _titleize.default)((0, _smartHumanize.default)(status))"
if old_title in js_content:
    js_content = js_content.replace(old_title, new_title, 1)

with open(engine_js_path, 'w', encoding='utf-8') as f:
    f.write(js_content)

print("Successfully checked console dist engine.js")

# 2. Patch engine.css
engine_css_path = '/home/wert/fleetbase/console/dist/engines-dist/@fleetbase/fleetops-engine/assets/engine.css'
with open(engine_css_path, 'r', encoding='utf-8') as f:
    css_content = f.read()

css_addition = """
/* ==========================================================================
   Limo Anywhere Dispatch Pipeline Kanban & Badge Styles
   ========================================================================== */

.kanban-board .kanban-column[data-column-id="created"] {
    border-top: 4px solid #6B7280;
}
.kanban-board .kanban-column[data-column-id="dispatched"] {
    border-top: 4px solid #2563EB;
}
.kanban-board .kanban-column[data-column-id="enroute_pickup"] {
    border-top: 4px solid #EAB308;
}
.kanban-board .kanban-column[data-column-id="on_location"] {
    border-top: 4px solid #9333EA;
}
.kanban-board .kanban-column[data-column-id="pob"] {
    border-top: 4px solid #F97316;
}
.kanban-board .kanban-column[data-column-id="completed"] {
    border-top: 4px solid #16A34A;
}
.kanban-board .kanban-column[data-column-id="canceled"] {
    border-top: 4px solid #DC2626;
}

/* Status Badges */
.status-badge.created-status-badge > span {
    background-color: #374151 !important;
    border-color: #6B7280 !important;
    color: #F3F4F6 !important;
}
.status-badge.created-status-badge > span svg {
    color: #9CA3AF !important;
}

.status-badge.dispatched-status-badge > span {
    background-color: #1E40AF !important;
    border-color: #2563EB !important;
    color: #EFF6FF !important;
}
.status-badge.dispatched-status-badge > span svg {
    color: #60A5FA !important;
}

.status-badge.enroute-pickup-status-badge > span,
.status-badge.enroute_pickup-status-badge > span {
    background-color: #854D0E !important;
    border-color: #EAB308 !important;
    color: #FEFCE8 !important;
}
.status-badge.enroute-pickup-status-badge > span svg,
.status-badge.enroute_pickup-status-badge > span svg {
    color: #FDE047 !important;
}

.status-badge.on-location-status-badge > span,
.status-badge.on_location-status-badge > span {
    background-color: #581C87 !important;
    border-color: #9333EA !important;
    color: #FAF5FF !important;
}
.status-badge.on-location-status-badge > span svg,
.status-badge.on_location-status-badge > span svg {
    color: #C084FC !important;
}

.status-badge.pob-status-badge > span {
    background-color: #9A3412 !important;
    border-color: #F97316 !important;
    color: #FFF7ED !important;
}
.status-badge.pob-status-badge > span svg {
    color: #FB923C !important;
}

.status-badge.completed-status-badge > span {
    background-color: #166534 !important;
    border-color: #16A34A !important;
    color: #F0FDF4 !important;
}
.status-badge.completed-status-badge > span svg {
    color: #4ADE80 !important;
}

.status-badge.canceled-status-badge > span,
.status-badge.cancelled-status-badge > span {
    background-color: #991B1B !important;
    border-color: #DC2626 !important;
    color: #FEF2F2 !important;
}
.status-badge.canceled-status-badge > span svg,
.status-badge.cancelled-status-badge > span svg {
    color: #F87171 !important;
}
"""

if "Limo Anywhere Dispatch Pipeline Kanban" not in css_content:
    css_content += css_addition
    with open(engine_css_path, 'w', encoding='utf-8') as f:
        f.write(css_content)
    print("Successfully patched console dist engine.css")

# 3. Patch node_modules kanban.js
node_modules_kanban = '/home/wert/fleetbase/console/node_modules/.pnpm/@fleetbase+fleetops-engine@0.6.49_b421113b8e787158f8d42852c5267750/node_modules/@fleetbase/fleetops-engine/addon/components/order/kanban.js'
try:
    with open(node_modules_kanban, 'r', encoding='utf-8') as f:
        kanban_src = f.read()

    kanban_src = kanban_src.replace(
        "start: ['created', 'dispatched', 'started'],",
        "start: ['created', 'dispatched'],"
    )
    old_kanban_return = """        return final.map((status, index) => ({
            id: status,
            title: titleize(smartHumanize(status)),
            position: index,
            cards: this.#getOrdersByStatus(status, this.orders),
        }));"""
    new_kanban_return = """        const statusTitles = {
            created: 'Created',
            dispatched: 'Dispatched',
            enroute_pickup: 'En Route',
            on_location: 'On Location',
            pob: 'Passenger On Board',
            completed: 'Completed',
            canceled: 'Canceled',
        };

        return final.map((status, index) => ({
            id: status,
            title: statusTitles[status] || titleize(smartHumanize(status)),
            position: index,
            cards: this.#getOrdersByStatus(status, this.orders),
        }));"""
    if old_kanban_return in kanban_src:
        kanban_src = kanban_src.replace(old_kanban_return, new_kanban_return)
        with open(node_modules_kanban, 'w', encoding='utf-8') as f:
            f.write(kanban_src)
        print("Successfully patched node_modules kanban.js")
except Exception as e:
    print(f"Note on node_modules kanban: {e}")
