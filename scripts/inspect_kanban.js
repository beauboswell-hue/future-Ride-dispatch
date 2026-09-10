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

async function inspect() {
    const token = await getAuthToken();
    console.log('✓ Token obtained:', token ? token.substring(0, 15) + '...' : 'none');

    const chrome = spawn('google-chrome', [
        '--headless=new',
        '--no-sandbox',
        '--disable-gpu',
        '--remote-debugging-port=9230',
        '--disable-dev-shm-usage',
        '--window-size=1600,1000',
        'http://127.0.0.1:4200/auth'
    ]);

    await new Promise(r => setTimeout(r, 2000));

    const targets = await new Promise((resolve, reject) => {
        http.get('http://127.0.0.1:9230/json', (res) => {
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

    await send('Page.navigate', { url: 'http://127.0.0.1:4200/fleet-ops/operations/orders?layout=kanban' });
    console.log('Waiting for Kanban page to load...');
    await new Promise(r => setTimeout(r, 8000));

    const result = await send('Runtime.evaluate', {
        expression: `
            (() => {
                const columns = Array.from(document.querySelectorAll('.kanban-column')).map(col => ({
                    id: col.getAttribute('data-column-id'),
                    title: col.querySelector('.kanban-column-title')?.innerText?.trim(),
                    cardsCount: col.querySelectorAll('.kanban-card').length,
                    cards: Array.from(col.querySelectorAll('.kanban-card')).map(card => {
                        const content = card.querySelector('.kanban-card-content');
                        return {
                            cardId: card.getAttribute('data-card-id'),
                            tracking: card.querySelector('.kanban-card-title')?.innerText?.trim(),
                            badge: card.querySelector('.badge')?.innerText?.trim(),
                            cardClass: card.className,
                            contentClass: content?.className,
                            cardBorderTop: window.getComputedStyle(card).borderTop,
                            contentBorderTop: content ? window.getComputedStyle(content).borderTop : null
                        };
                    })
                }));
                return {
                    url: window.location.href,
                    boardPresent: !!document.querySelector('.kanban-board'),
                    columnCount: columns.length,
                    columns
                };
            })()
        `,
        returnByValue: true
    });

    console.log('Inspection result:', JSON.stringify(result.result.value, null, 2));

    chrome.kill();
    process.exit(0);
}

inspect().catch(err => {
    console.error(err);
    process.exit(1);
});
