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
    console.log('Logging in to get auth token...');
    const token = await getAuthToken();
    console.log('✓ Token obtained:', token ? token.substring(0, 15) + '...' : 'none');

    console.log('Spawning headless Chrome...');
    const chrome = spawn('google-chrome', [
        '--headless=new',
        '--no-sandbox',
        '--disable-gpu',
        '--remote-debugging-port=9228',
        '--disable-dev-shm-usage',
        '--window-size=1440,900',
        'http://127.0.0.1:4200/auth'
    ]);

    await new Promise(r => setTimeout(r, 2500));

    const targets = await new Promise((resolve, reject) => {
        http.get('http://127.0.0.1:9228/json', (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => resolve(JSON.parse(data)));
        }).on('error', reject);
    });

    const pageTarget = targets.find(t => t.type === 'page') || targets[0];
    const ws = new WebSocket(pageTarget.webSocketDebuggerUrl);
    let id = 1;
    function send(method, params = {}) {
        return new Promise((resolve) => {
            const reqId = id++;
            const handler = (event) => {
                const msg = JSON.parse(event.data);
                if (msg.id === reqId) {
                    ws.removeEventListener('message', handler);
                    resolve(msg.result);
                }
            };
            ws.addEventListener('message', handler);
            ws.send(JSON.stringify({ id: reqId, method, params }));
        });
    }

    await new Promise(r => ws.addEventListener('open', r, { once: true }));
    console.log('✓ CDP WebSocket connected');

    await send('Runtime.enable');
    await send('Page.enable');

    // Set ember_simple_auth-session in localStorage
    console.log('Setting auth session...');
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

    // Navigate to /fleet-ops/operations/orders?layout=grid
    console.log('Navigating to http://127.0.0.1:4200/fleet-ops/operations/orders?layout=grid ...');
    await send('Page.navigate', { url: 'http://127.0.0.1:4200/fleet-ops/operations/orders?layout=grid' });

    console.log('Waiting for page and data to load (8s)...');
    await new Promise(r => setTimeout(r, 8000));

    // Measure geometry and layering
    const domMetrics = await send('Runtime.evaluate', {
        expression: `
            (() => {
                const topbar = document.querySelector('.next-map-container-topbar') || document.querySelector('#map-topbar-container');
                const container = document.querySelector('.dispatch-grid-container');
                const toolbar = document.querySelector('.dispatch-grid-toolbar');
                const tableContainer = document.querySelector('.dispatch-grid-table-container');
                const thead = document.querySelector('.dispatch-grid-thead');
                const firstTh = document.querySelector('.dispatch-grid-th');
                const firstRow = document.querySelector('.dispatch-grid-row');

                const getInfo = (el) => {
                    if (!el) return null;
                    const r = el.getBoundingClientRect();
                    const s = window.getComputedStyle(el);
                    return {
                        rect: { top: r.top, bottom: r.bottom, left: r.left, right: r.right, width: r.width, height: r.height },
                        zIndex: s.zIndex,
                        position: s.position,
                        paddingTop: s.paddingTop,
                        marginTop: s.marginTop,
                        overflowY: s.overflowY
                    };
                };

                const topbarRect = topbar ? topbar.getBoundingClientRect() : null;
                const toolbarRect = toolbar ? toolbar.getBoundingClientRect() : null;
                const theadRect = thead ? thead.getBoundingClientRect() : null;

                const topbarBottom = topbarRect ? topbarRect.bottom : 0;
                const toolbarTop = toolbarRect ? toolbarRect.top : 0;
                const theadTop = theadRect ? theadRect.top : 0;

                return {
                    topbar: getInfo(topbar),
                    container: getInfo(container),
                    toolbar: getInfo(toolbar),
                    tableContainer: getInfo(tableContainer),
                    thead: getInfo(thead),
                    firstTh: getInfo(firstTh),
                    firstRow: getInfo(firstRow),
                    toolbarClearsTopbar: toolbarTop >= topbarBottom,
                    topbarBottom,
                    toolbarTop,
                    theadTop,
                    gapBetweenTopbarAndToolbar: toolbarTop - topbarBottom
                };
            })()
        `,
        returnByValue: true
    });

    console.log('\n=== DOM & GEOMETRY METRICS ===');
    console.log(JSON.stringify(domMetrics.result.value, null, 2));

    // Capture initial screenshot
    let snap = await send('Page.captureScreenshot', { format: 'png' });
    fs.writeFileSync('/home/wert/orders_grid_fixed.png', Buffer.from(snap.data, 'base64'));
    console.log('✓ Saved /home/wert/orders_grid_fixed.png');

    // Test vertical scrolling inside dispatch-grid-table-container
    console.log('\nTesting vertical scrolling inside .dispatch-grid-table-container...');
    const scrollResult = await send('Runtime.evaluate', {
        expression: `
            (() => {
                const tableContainer = document.querySelector('.dispatch-grid-table-container');
                const thead = document.querySelector('.dispatch-grid-thead');
                const firstRow = document.querySelector('.dispatch-grid-row');
                if (!tableContainer) return { error: 'No table container' };

                const theadBefore = thead ? thead.getBoundingClientRect().top : null;
                const rowBefore = firstRow ? firstRow.getBoundingClientRect().top : null;

                tableContainer.scrollTop = 250;

                const theadAfter = thead ? thead.getBoundingClientRect().top : null;
                const rowAfter = firstRow ? firstRow.getBoundingClientRect().top : null;

                return {
                    scrollTop: tableContainer.scrollTop,
                    theadTopBefore: theadBefore,
                    theadTopAfter: theadAfter,
                    rowTopBefore: rowBefore,
                    rowTopAfter: rowAfter,
                    headerPinned: Math.abs(theadBefore - theadAfter) < 1,
                    rowsScrolled: rowAfter < rowBefore
                };
            })()
        `,
        returnByValue: true
    });

    console.log('=== SCROLL TEST RESULT ===');
    console.log(JSON.stringify(scrollResult.result.value, null, 2));

    // Capture scrolled screenshot
    snap = await send('Page.captureScreenshot', { format: 'png' });
    fs.writeFileSync('/home/wert/orders_grid_scrolled.png', Buffer.from(snap.data, 'base64'));
    console.log('✓ Saved /home/wert/orders_grid_scrolled.png');

    ws.close();
    chrome.kill();
    console.log('Verification finished!');
}

verify().catch(console.error);
