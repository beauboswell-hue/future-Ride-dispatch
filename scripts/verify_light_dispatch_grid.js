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

async function verify() {
    console.log('--- Step 1: Logging in to get auth token ---');
    const token = await getAuthToken();
    console.log('✓ Token obtained:', token ? token.substring(0, 15) + '...' : 'none');

    console.log('--- Step 2: Spawning headless Chrome ---');
    const chrome = spawn('google-chrome', [
        '--headless=new',
        '--no-sandbox',
        '--disable-gpu',
        '--remote-debugging-port=9229',
        '--disable-dev-shm-usage',
        '--window-size=1600,1000',
        'http://127.0.0.1:4200/auth'
    ]);

    await new Promise(r => setTimeout(r, 2500));

    const targets = await new Promise((resolve, reject) => {
        http.get('http://127.0.0.1:9229/json', (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => resolve(JSON.parse(data)));
        }).on('error', reject);
    });

    const pageTarget = targets.find(t => t.type === 'page') || targets[0];
    const ws = new WebSocket(pageTarget.webSocketDebuggerUrl);
    let id = 1;
    const pendingCallbacks = new Map();

    const consoleErrors = [];

    ws.addEventListener('message', (event) => {
        const msg = JSON.parse(event.data);
        if (msg.id && pendingCallbacks.has(msg.id)) {
            const cb = pendingCallbacks.get(msg.id);
            pendingCallbacks.delete(msg.id);
            cb(msg.result);
        }
        if (msg.method === 'Runtime.consoleAPICalled') {
            if (msg.params.type === 'error') {
                const text = (msg.params.args || []).map(a => a.value || a.description || JSON.stringify(a)).join(' ');
                consoleErrors.push(text);
            }
        }
        if (msg.method === 'Runtime.exceptionThrown') {
            const desc = msg.params.exceptionDetails?.exception?.description || msg.params.exceptionDetails?.text;
            consoleErrors.push(desc);
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
    console.log('✓ CDP WebSocket connected');

    await send('Runtime.enable');
    await send('Page.enable');
    await send('Log.enable');

    console.log('--- Step 3: Setting auth session in localStorage ---');
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

    console.log('--- Step 4: Navigating to http://127.0.0.1:4200/fleet-ops/operations/orders?layout=grid ---');
    await send('Page.navigate', { url: 'http://127.0.0.1:4200/fleet-ops/operations/orders?layout=grid' });

    console.log('Waiting for page, Ember app, and orders data to load (8s)...');
    await new Promise(r => setTimeout(r, 8000));

    console.log('--- Step 5: Extracting and Verifying Light Theme Styles on Today View ---');
    const verification = await send('Runtime.evaluate', {
        expression: `
            (() => {
                const wrapper = document.querySelector('.dispatch-grid-wrapper');
                const toolbar = document.querySelector('.dispatch-grid-toolbar');
                const tableContainer = document.querySelector('.dispatch-grid-table-container');
                const thead = document.querySelector('.dispatch-grid-thead');
                const ths = Array.from(document.querySelectorAll('.dispatch-grid-th'));
                const rows = Array.from(document.querySelectorAll('.dispatch-grid-row'));

                const getStyle = (el) => {
                    if (!el) return null;
                    const s = window.getComputedStyle(el);
                    return {
                        backgroundColor: s.backgroundColor,
                        color: s.color,
                        borderColor: s.borderColor,
                        fontWeight: s.fontWeight,
                        fontSize: s.fontSize,
                        borderBottomColor: s.borderBottomColor,
                        borderBottomWidth: s.borderBottomWidth,
                        borderLeftColor: s.borderLeftColor,
                        borderLeftWidth: s.borderLeftWidth,
                        position: s.position,
                        top: s.top
                    };
                };

                const thData = ths.map(th => ({
                    text: th.innerText.trim(),
                    style: getStyle(th)
                }));

                const rowData = rows.map(row => {
                    const cells = Array.from(row.querySelectorAll('td'));
                    const timeEl = cells[0]?.querySelector('div');
                    const passEl = cells[1]?.querySelector('div');
                    const phoneEl = cells[2]?.querySelector('a') || cells[2]?.querySelector('span');
                    const routeEl = cells[3]?.querySelector('div');
                    const pickupSpan = routeEl?.children[0];
                    const arrowSpan = routeEl?.children[1];
                    const dropoffSpan = routeEl?.children[2];
                    const chauffeurEl = cells[4]?.querySelector('div') || cells[4]?.querySelector('span');
                    const badgeSpan = cells[5]?.querySelector('span');

                    return {
                        status: row.getAttribute('data-status'),
                        rowStyle: getStyle(row),
                        time: {
                            text: timeEl?.innerText?.trim(),
                            style: getStyle(timeEl)
                        },
                        passenger: {
                            text: passEl?.innerText?.trim(),
                            style: getStyle(passEl)
                        },
                        phone: {
                            text: phoneEl?.innerText?.trim(),
                            tag: phoneEl?.tagName,
                            style: getStyle(phoneEl)
                        },
                        route: {
                            pickup: { text: pickupSpan?.innerText?.trim(), style: getStyle(pickupSpan) },
                            arrow: { text: arrowSpan?.innerText?.trim(), style: getStyle(arrowSpan) },
                            dropoff: { text: dropoffSpan?.innerText?.trim(), style: getStyle(dropoffSpan) }
                        },
                        chauffeur: {
                            text: chauffeurEl?.innerText?.trim(),
                            style: getStyle(chauffeurEl)
                        },
                        statusBadge: {
                            text: badgeSpan?.innerText?.trim(),
                            className: badgeSpan?.className,
                            style: getStyle(badgeSpan)
                        }
                    };
                });

                return {
                    wrapper: getStyle(wrapper),
                    toolbar: getStyle(toolbar),
                    tableContainer: getStyle(tableContainer),
                    thead: getStyle(thead),
                    thCount: ths.length,
                    ths: thData,
                    rowCount: rows.length,
                    sampleRows: rowData
                };
            })()
        `,
        returnByValue: true
    });

    console.log('Verification data:');
    console.log(JSON.stringify(verification.result.value, null, 2));

    // Capture screenshot
    const snap = await send('Page.captureScreenshot', { format: 'png' });
    fs.writeFileSync('/home/wert/light_dispatch_grid.png', Buffer.from(snap.data, 'base64'));
    console.log('✓ Screenshot saved to /home/wert/light_dispatch_grid.png');

    console.log('\n--- Step 6: Console Errors Check ---');
    console.log(`Console Errors count: ${consoleErrors.length}`);
    if (consoleErrors.length > 0) {
        console.error('Console errors detected:');
        consoleErrors.forEach(err => console.error('  -', err));
    } else {
        console.log('✓ Zero console errors verified!');
    }

    ws.close();
    chrome.kill();
}

verify().catch(e => {
    console.error('Verification failed:', e);
    process.exit(1);
});
