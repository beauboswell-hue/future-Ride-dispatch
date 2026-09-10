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
        '--remote-debugging-port=9232',
        '--disable-dev-shm-usage',
        '--window-size=1920,1080',
        'http://127.0.0.1:4200/auth'
    ]);

    await new Promise(r => setTimeout(r, 2000));

    const targets = await new Promise((resolve, reject) => {
        http.get('http://127.0.0.1:9232/json', (res) => {
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

    console.log('--- Step 4: Navigating to http://127.0.0.1:4200/fleet-ops?layout=kanban ---');
    await send('Page.navigate', { url: 'http://127.0.0.1:4200/fleet-ops?layout=kanban' });

    console.log('Waiting for Kanban page to load (8s)...');
    await new Promise(r => setTimeout(r, 8000));

    console.log('--- Step 5: Extracting and Verifying Kanban Card Top Accent Styles Across All 7 Columns ---');
    const verification = await send('Runtime.evaluate', {
        expression: `
            (() => {
                const board = document.querySelector('.kanban-board');
                const columns = Array.from(document.querySelectorAll('.kanban-column')).map(col => {
                    const colId = col.getAttribute('data-column-id');
                    const colTitle = col.querySelector('.kanban-column-title')?.innerText?.trim();
                    const cardElements = Array.from(col.querySelectorAll('.kanban-card'));

                    const cards = cardElements.map(card => {
                        const content = card.querySelector('.kanban-card-content');
                        const cardCs = window.getComputedStyle(card);
                        const contentCs = content ? window.getComputedStyle(content) : null;

                        return {
                            cardId: card.getAttribute('data-card-id'),
                            tracking: card.querySelector('.kanban-card-title')?.innerText?.trim(),
                            badge: card.querySelector('.badge')?.innerText?.trim(),
                            contentClasses: content ? content.className : null,
                            cardBorderTop: cardCs.borderTop,
                            cardOverflow: cardCs.overflow,
                            contentBorderTop: contentCs ? contentCs.borderTop : null,
                            contentBorderTopWidth: contentCs ? contentCs.borderTopWidth : null,
                            contentBorderTopColor: contentCs ? contentCs.borderTopColor : null,
                            contentBorderTopStyle: contentCs ? contentCs.borderTopStyle : null,
                            contentOverflow: contentCs ? contentCs.overflow : null,
                            contentBorderTopLeftRadius: contentCs ? contentCs.borderTopLeftRadius : null,
                            contentBorderTopRightRadius: contentCs ? contentCs.borderTopRightRadius : null,
                        };
                    });

                    return {
                        id: colId,
                        title: colTitle,
                        cardCount: cardElements.length,
                        sampleCard: cards[0] || null
                    };
                });

                return {
                    url: window.location.href,
                    boardPresent: !!board,
                    columnCount: columns.length,
                    columns
                };
            })()
        `,
        returnByValue: true
    });

    const data = verification.result.value;
    console.log('Verification data:');
    data.columns.forEach(col => {
        const c = col.sampleCard;
        if (c) {
            console.log(`✓ Column ${col.id} (${col.title}) [${col.cardCount} cards]:`);
            console.log(`    Classes: ${c.contentClasses}`);
            console.log(`    Border:  ${c.contentBorderTop} (${c.contentBorderTopWidth}, ${c.contentBorderTopColor})`);
            console.log(`    Corners: top-left=${c.contentBorderTopLeftRadius}, top-right=${c.contentBorderTopRightRadius}`);
            console.log(`    Overflow: content=${c.contentOverflow}, card=${c.cardOverflow}`);
        } else {
            console.log(`! Column ${col.id} (${col.title}) [${col.cardCount} cards]: EMPTY`);
        }
    });

    console.log('--- Step 6: Capturing Screenshots ---');
    // First screenshot: Left side columns
    const screenshot1 = await send('Page.captureScreenshot', { format: 'png' });
    fs.writeFileSync('/home/wert/kanban_board_top_accent.png', Buffer.from(screenshot1.data, 'base64'));
    console.log('✓ Screenshot (overview) saved to /home/wert/kanban_board_top_accent.png');

    // Scroll right to capture POB, Completed, Canceled
    await send('Runtime.evaluate', {
        expression: `
            const container = document.querySelector('.kanban-columns-container');
            if (container) {
                container.scrollLeft = container.scrollWidth;
            }
        `
    });
    await new Promise(r => setTimeout(r, 1000));

    const screenshot2 = await send('Page.captureScreenshot', { format: 'png' });
    fs.writeFileSync('/home/wert/kanban_board_top_accent_scrolled.png', Buffer.from(screenshot2.data, 'base64'));
    console.log('✓ Screenshot (scrolled right) saved to /home/wert/kanban_board_top_accent_scrolled.png');

    console.log('--- Step 7: Console Errors Check ---');
    console.log('Console Errors count:', consoleErrors.length);
    if (consoleErrors.length > 0) {
        console.log('Console errors:', consoleErrors);
    } else {
        console.log('✓ Zero console errors verified!');
    }

    chrome.kill();
    process.exit(0);
}

verify().catch(err => {
    console.error('Verification failed:', err);
    process.exit(1);
});
