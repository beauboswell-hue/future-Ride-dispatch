const { spawn } = require('child_process');
const http = require('http');

async function getAuthToken() {
    return new Promise((resolve, reject) => {
        const req = http.request('http://127.0.0.1:8000/int/v1/auth/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        }, (res) => {
            let body = '';
            res.on('data', chunk => body += chunk);
            res.on('end', () => {
                try {
                    const parsed = JSON.parse(body);
                    resolve(parsed.token);
                } catch (e) {
                    reject(new Error('Failed to parse login response: ' + body));
                }
            });
        });
        req.on('error', reject);
        req.write(JSON.stringify({ identity: 'admin@fleetbase.io', password: 'password' }));
        req.end();
    });
}

async function runTest() {
    console.log('=== SIDEBAR BOOKING NAVIGATION ITEM VERIFICATION ===\n');
    const token = await getAuthToken();
    console.log('✓ Obtained API token');

    const chrome = spawn('google-chrome', [
        '--headless=new',
        '--no-sandbox',
        '--disable-gpu',
        '--remote-debugging-port=9258',
        '--disable-dev-shm-usage',
        '--window-size=1280,900',
        'http://127.0.0.1:4200/auth'
    ]);

    await new Promise(r => setTimeout(r, 2000));

    const targets = await new Promise((resolve, reject) => {
        http.get('http://127.0.0.1:9258/json', (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => resolve(JSON.parse(data)));
        }).on('error', reject);
    });

    const pageTarget = targets.find(t => t.type === 'page') || targets[0];
    const ws = new WebSocket(pageTarget.webSocketDebuggerUrl);
    let id = 1;
    const pendingCallbacks = new Map();

    ws.addEventListener('message', (event) => {
        const msg = JSON.parse(event.data);
        if (msg.id && pendingCallbacks.has(msg.id)) {
            const cb = pendingCallbacks.get(msg.id);
            pendingCallbacks.delete(msg.id);
            cb(msg.result);
        }
    });

    function send(method, params = {}) {
        return new Promise((resolve) => {
            const reqId = id++;
            pendingCallbacks.set(reqId, resolve);
            ws.send(JSON.stringify({ id: reqId, method, params }));
        });
    }

    await new Promise(r => ws.addEventListener('open', r, { once: true }));
    await send('Runtime.enable');
    await send('Page.enable');

    console.log('✓ Setting ember_simple_auth-session in localStorage...');
    await send('Runtime.evaluate', {
        expression: `
            window.localStorage.setItem('ember_simple_auth-session', JSON.stringify({
                authenticated: {
                    authenticator: 'authenticator:fleetbase',
                    token: '${token}',
                    type: 'admin'
                }
            }));
        `
    });

    console.log('✓ Navigating to /fleet-ops/operations/orders...');
    await send('Page.navigate', { url: 'http://127.0.0.1:4200/fleet-ops/operations/orders' });

    // Wait for sidebar items to load
    let sidebarLoaded = false;
    for (let i = 0; i < 20; i++) {
        await new Promise(r => setTimeout(r, 1000));
        const check = await send('Runtime.evaluate', {
            expression: `
                (() => {
                    const sidebar = document.querySelector('.next-sidebar');
                    if (!sidebar) return false;
                    const items = Array.from(sidebar.querySelectorAll('*')).map(el => el.innerText ? el.innerText.trim() : '');
                    return items.includes('Calendar') || items.includes('Booking') || items.includes('Dashboard');
                })()
            `,
            returnByValue: true
        });
        if (check?.result?.value) {
            sidebarLoaded = true;
            break;
        }
    }

    if (!sidebarLoaded) {
        throw new Error('Sidebar failed to load within timeout');
    }

    console.log('✓ Sidebar loaded! Inspecting Operations menu items...');
    const sidebarInfo = await send('Runtime.evaluate', {
        returnByValue: true,
        expression: `
            (() => {
                const sidebar = document.querySelector('.next-sidebar');
                if (!sidebar) return { error: 'Sidebar not found' };

                // Get all links/buttons in sidebar
                const items = Array.from(sidebar.querySelectorAll('.next-sidebar-item, a, button, [role="menuitem"]'))
                    .map(el => el.innerText ? el.innerText.trim() : '')
                    .filter(t => t.length > 0 && t.length < 30);

                // Specifically find Operations section items
                const opsItems = items.filter(t => 
                    ['Dashboard', 'Orchestrator', 'Scheduler', 'Calendar', 'Booking', 'Order Config', 'Service Rates'].includes(t)
                );

                const bookingEl = Array.from(sidebar.querySelectorAll('a, button, .next-sidebar-item')).find(
                    el => el.innerText && el.innerText.trim().startsWith('Booking')
                );

                const iconEl = bookingEl ? bookingEl.querySelector('svg, i, .fa, .fa-icon') : null;

                return {
                    uniqueOpsItems: [...new Set(opsItems)],
                    bookingFound: !!bookingEl,
                    bookingText: bookingEl ? bookingEl.innerText.trim() : null,
                    hasIcon: !!iconEl,
                    iconClass: iconEl ? iconEl.getAttribute('class') : null,
                    html: bookingEl ? bookingEl.outerHTML : null
                };
            })()
        `
    });

    console.log('Sidebar Operations analysis:', JSON.stringify(sidebarInfo.result.value, null, 2));

    console.log('\n✓ Testing clicking "Booking" in sidebar...');
    const clickResult = await send('Runtime.evaluate', {
        returnByValue: true,
        expression: `
            (() => {
                const sidebar = document.querySelector('.next-sidebar');
                const items = Array.from(sidebar.querySelectorAll('a, button, .next-sidebar-item'));
                const bookingEl = items.find(el => el.innerText && el.innerText.trim().startsWith('Booking'));
                if (!bookingEl) return { error: 'Booking element not found in sidebar' };

                bookingEl.click();
                return { clicked: true, itemText: bookingEl.innerText.trim() };
            })()
        `
    });
    console.log('Click result:', JSON.stringify(clickResult.result.value, null, 2));

    await new Promise(r => setTimeout(r, 1000));

    console.log('\n✓ Checking if Booking drawer opened...');
    const drawerResult = await send('Runtime.evaluate', {
        returnByValue: true,
        expression: `
            (() => {
                const drawer = document.querySelector('.future-limo-drawer-panel');
                if (!drawer) return { drawerOpen: false };

                const iframe = drawer.querySelector('iframe');
                const title = drawer.querySelector('.font-semibold')?.innerText;
                const url = drawer.querySelector('.text-gray-400')?.innerText;

                return {
                    drawerOpen: true,
                    title,
                    url,
                    iframeSrc: iframe ? iframe.src : null
                };
            })()
        `
    });
    console.log('Drawer result:', JSON.stringify(drawerResult.result.value, null, 2));

    ws.close();
    chrome.kill();
    console.log('\n=== TEST COMPLETED ===');
}

runTest().catch((err) => {
    console.error('Test error:', err);
    process.exit(1);
});
