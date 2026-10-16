const { spawn } = require('child_process');
const http = require('http');
const fs = require('fs');

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

async function switchOrg(token, orgUuid) {
    return new Promise((resolve, reject) => {
        const req = http.request('http://127.0.0.1:8000/int/v1/auth/switch-organization', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        }, (res) => {
            let body = '';
            res.on('data', chunk => body += chunk);
            res.on('end', () => {
                try {
                    const parsed = JSON.parse(body);
                    resolve(parsed);
                } catch (e) {
                    resolve(body);
                }
            });
        });
        req.on('error', reject);
        req.write(JSON.stringify({ next: orgUuid }));
        req.end();
    });
}

async function runTest() {
    console.log('=== MULTI-TENANT BOOKING DRAWER VERIFICATION ===\n');
    const token = await getAuthToken();
    console.log('✓ Obtained API token');

    const FUTURE_LIMO_UUID = '8bc611a7-0601-49a6-8f11-062b7946c3d8';
    const FUTURE_RIDE_UUID = 'c074248f-0bbd-45d5-bba7-39db1e2f2bda';

    const chrome = spawn('google-chrome', [
        '--headless=new',
        '--no-sandbox',
        '--disable-gpu',
        '--remote-debugging-port=9255',
        '--disable-dev-shm-usage',
        '--window-size=1600,1000',
        'http://127.0.0.1:4200/auth'
    ]);

    await new Promise(r => setTimeout(r, 2000));

    const targets = await new Promise((resolve, reject) => {
        http.get('http://127.0.0.1:9255/json', (res) => {
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

    async function testTenant(tenantName, tenantUuid, expectedUrl, expectedTitle) {
        console.log(`\n--- Testing Tenant: ${tenantName} ---`);
        console.log(`Switching API session to ${tenantName} (${tenantUuid})...`);
        const switchRes = await switchOrg(token, tenantUuid);
        console.log(`Switch response:`, switchRes);

        console.log(`Navigating to orders page...`);
        await send('Page.navigate', { url: 'http://127.0.0.1:4200/fleet-ops/operations/orders' });

        // Wait for booking button
        let buttonFound = false;
        for (let i = 0; i < 20; i++) {
            await new Promise(r => setTimeout(r, 1000));
            const check = await send('Runtime.evaluate', {
                expression: `
                    (() => {
                        const btns = Array.from(document.querySelectorAll('button'));
                        const bookingBtn = btns.find(b => b.innerText && (b.innerText.includes('Booking') || b.innerText.includes('Future Limo')));
                        return !!bookingBtn;
                    })()
                `,
                returnByValue: true
            });
            if (check?.result?.value) {
                buttonFound = true;
                break;
            }
        }

        if (!buttonFound) {
            throw new Error(`Booking button not found for ${tenantName}`);
        }

        console.log('✓ Found Booking button. Clicking to open drawer...');
        await send('Runtime.evaluate', {
            expression: `
                (() => {
                    const btns = Array.from(document.querySelectorAll('button'));
                    const btn = btns.find(b => b.innerText && (b.innerText.includes('Booking') || b.innerText.includes('Future Limo')));
                    if (btn) btn.click();
                })()
            `
        });

        await new Promise(r => setTimeout(r, 800));

        const inspection = await send('Runtime.evaluate', {
            expression: `
                (() => {
                    const panel = document.querySelector('.future-limo-drawer-panel');
                    const iframe = panel ? panel.querySelector('iframe') : null;
                    const header = panel ? panel.querySelector('.border-b') : null;
                    const closeBtn = header ? header.querySelector('button') : null;
                    return {
                        hasPanel: !!panel,
                        headerText: header ? header.innerText.trim() : null,
                        iframeSrc: iframe ? iframe.getAttribute('src') : null,
                        iframeTitle: iframe ? iframe.getAttribute('title') : null,
                        hasCloseBtn: !!closeBtn
                    };
                })()
            `,
            returnByValue: true
        });

        const res = inspection?.result?.value;
        console.log('Drawer state:', res);

        if (!res?.hasPanel) {
            throw new Error(`Drawer did not open for ${tenantName}`);
        }
        if (res.iframeSrc !== expectedUrl) {
            throw new Error(`Expected iframeSrc to be "${expectedUrl}" but got "${res.iframeSrc}"`);
        }
        if (!res.headerText.includes(expectedUrl)) {
            throw new Error(`Expected header to display "${expectedUrl}" but got "${res.headerText}"`);
        }
        if (res.iframeTitle !== expectedTitle) {
            throw new Error(`Expected iframeTitle to be "${expectedTitle}" but got "${res.iframeTitle}"`);
        }

        console.log(`✓ SUCCESS: ${tenantName} correctly resolved to ${res.iframeSrc} with title "${res.iframeTitle}"!`);

        // Close drawer
        await send('Runtime.evaluate', {
            expression: `
                (() => {
                    const panel = document.querySelector('.future-limo-drawer-panel');
                    const header = panel ? panel.querySelector('.border-b') : null;
                    const closeBtn = header ? header.querySelector('button') : null;
                    if (closeBtn) closeBtn.click();
                })()
            `
        });
        await new Promise(r => setTimeout(r, 400));
    }

    try {
        // Test Future Ride LLC
        await testTenant('Future Ride LLC', FUTURE_RIDE_UUID, 'https://futureride.website', 'Future Ride Portal');

        // Test Future Limo
        await testTenant('Future Limo', FUTURE_LIMO_UUID, 'https://future.limo', 'Future Limo Portal');

        console.log('\n==================================================');
        console.log('ALL MULTI-TENANT DRAWER TESTS PASSED SUCCESSFULLY!');
        console.log('==================================================');
    } finally {
        chrome.kill();
    }
}

runTest().catch((err) => {
    console.error('Fatal Test Failure:', err.message);
    process.exit(1);
});
