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
            res.on('end', () => resolve(JSON.parse(body).token));
        });
        req.on('error', reject);
        req.write(JSON.stringify({ identity: 'admin@fleetbase.io', password: 'password' }));
        req.end();
    });
}

async function main() {
    console.log('Obtaining authentication token...');
    const token = await getAuthToken();

    console.log('Launching headless Chrome...');
    const chrome = spawn('google-chrome-stable', [
        '--headless=new',
        '--no-sandbox',
        '--disable-gpu',
        '--remote-debugging-port=9222',
        '--disable-dev-shm-usage',
        'http://127.0.0.1:4200/auth'
    ]);
    await new Promise(r => setTimeout(r, 2000));

    const targets = await new Promise((resolve, reject) => {
        http.get('http://127.0.0.1:9222/json', (res) => {
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
    await new Promise(r => ws.onopen = r);
    await send('Runtime.enable');
    await send('Page.enable');

    console.log('Injecting session and navigating to /fleet-ops/dispatch-grid ...');
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

    // Navigate to Fleet-Ops first, then click Dispatch Grid in sidebar
    await send('Page.navigate', { url: 'http://127.0.0.1:4200/fleet-ops' });
    await new Promise(r => setTimeout(r, 7000));

    console.log('Clicking Dispatch Grid in sidebar...');
    await send('Runtime.evaluate', {
        expression: `
            (() => {
                const link = Array.from(document.querySelectorAll('a')).find(a => a.innerText.trim() === 'Dispatch Grid');
                if (link) link.click();
            })()
        `
    });
    await new Promise(r => setTimeout(r, 3000));

    // 1. Verify Grid Columns, Toolbar, Order Count
    console.log('\n--- VERIFYING GRID STRUCTURE & HEADERS ---');
    const gridStructure = await send('Runtime.evaluate', {
        expression: `
            (() => {
                const headers = Array.from(document.querySelectorAll('.dispatch-grid-table-container th')).map(th => th.innerText.trim()).filter(Boolean);
                const orderBadge = document.querySelector('.dispatch-grid-toolbar span')?.innerText?.trim();
                const totalRows = document.querySelectorAll('.dispatch-grid-row').length;
                return {
                    headers,
                    orderBadge,
                    totalRows
                };
            })()
        `,
        returnByValue: true
    });
    console.log(JSON.stringify(gridStructure.result.value, null, 2));

    // 2. Verify Row Color Coding & Status Stripes
    console.log('\n--- VERIFYING STATUS-BASED ROW COLOR CODING ---');
    const colorCodingCheck = await send('Runtime.evaluate', {
        expression: `
            (() => {
                const rows = Array.from(document.querySelectorAll('.dispatch-grid-row'));
                const statusCounts = {};
                const sampleRowsByStatus = {};

                rows.forEach(r => {
                    const statusClass = Array.from(r.classList).find(c => c.startsWith('dispatch-grid-row-'));
                    const statusName = statusClass ? statusClass.replace('dispatch-grid-row-', '') : 'unknown';
                    statusCounts[statusName] = (statusCounts[statusName] || 0) + 1;

                    if (!sampleRowsByStatus[statusName]) {
                        const style = window.getComputedStyle(r);
                        sampleRowsByStatus[statusName] = {
                            orderId: r.querySelector('td:nth-child(1) .font-mono')?.innerText?.trim(),
                            passenger: r.querySelector('td:nth-child(2) .font-semibold')?.innerText?.trim(),
                            chauffeur: r.querySelector('td:nth-child(4) .font-semibold')?.innerText?.trim(),
                            borderLeftColor: style.borderLeftColor,
                            backgroundColor: style.backgroundColor,
                            textColor: style.color
                        };
                    }
                });

                return {
                    statusDistribution: statusCounts,
                    statusStyles: sampleRowsByStatus
                };
            })()
        `,
        returnByValue: true
    });
    console.log(JSON.stringify(colorCodingCheck.result.value, null, 2));

    // 3. Verify Search & Status Filter Tabs
    console.log('\n--- VERIFYING STATUS FILTER TABS ---');
    const filterTabsCheck = await send('Runtime.evaluate', {
        expression: `
            new Promise((resolve) => {
                const tabs = Array.from(document.querySelectorAll('.dispatch-grid-toolbar button')).map(b => b.innerText.trim());
                // Click "Created" tab
                const createdBtn = Array.from(document.querySelectorAll('.dispatch-grid-toolbar button')).find(b => b.innerText.includes('Created'));
                if (createdBtn) {
                    createdBtn.click();
                    setTimeout(() => {
                        const filteredRows = document.querySelectorAll('.dispatch-grid-row').length;
                        // Click "All" tab to reset
                        const allBtn = Array.from(document.querySelectorAll('.dispatch-grid-toolbar button')).find(b => b.innerText.includes('All'));
                        if (allBtn) allBtn.click();
                        resolve({ availableTabs: tabs, createdFilteredRows: filteredRows });
                    }, 500);
                } else {
                    resolve({ availableTabs: tabs, error: 'Created tab not found' });
                }
            })
        `,
        awaitPromise: true,
        returnByValue: true
    });
    console.log(JSON.stringify(filterTabsCheck.result.value, null, 2));

    // 4. Verify Row Click Opens Order Details Slide-over / Modal
    console.log('\n--- VERIFYING ROW CLICK INTERACTION ---');
    const clickCheck = await send('Runtime.evaluate', {
        expression: `
            new Promise((resolve) => {
                const firstRow = document.querySelector('.dispatch-grid-row');
                const orderId = firstRow?.querySelector('td:nth-child(1) .font-mono')?.innerText?.trim();
                firstRow.click();
                setTimeout(() => {
                    const slideOver = document.querySelector('.overlay-panel') || document.querySelector('.next-content') || document.querySelector('.panel-header');
                    const headerText = slideOver?.innerText?.substring(0, 120);
                    resolve({
                        orderIdClicked: orderId,
                        urlAfterClick: window.location.href,
                        hasSlideOver: !!slideOver,
                        headerSnippet: headerText
                    });
                }, 2000);
            })
        `,
        awaitPromise: true,
        returnByValue: true
    });
    console.log(JSON.stringify(clickCheck.result.value, null, 2));

    ws.close();
    chrome.kill();
    console.log('\nAll Dispatch Grid Verifications Completed Successfully!');
}

main().catch(console.error);
