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
    console.log('--- Verifying Future Limo Slide-Out Drawer ---');
    let token;
    try {
        token = await getAuthToken();
        console.log('✓ API Token obtained:', token ? token.substring(0, 15) + '...' : 'none');
    } catch (e) {
        console.error('Failed to get token:', e.message);
    }

    const chrome = spawn('google-chrome', [
        '--headless=new',
        '--no-sandbox',
        '--disable-gpu',
        '--remote-debugging-port=9244',
        '--disable-dev-shm-usage',
        '--window-size=1600,1000',
        'http://127.0.0.1:4200/auth'
    ]);

    await new Promise(r => setTimeout(r, 2000));

    const targets = await new Promise((resolve, reject) => {
        http.get('http://127.0.0.1:9244/json', (res) => {
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

    if (token) {
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
    }

    console.log('Navigating to http://127.0.0.1:4200/fleet-ops/operations/orders...');
    await send('Page.navigate', { url: 'http://127.0.0.1:4200/fleet-ops/operations/orders' });
    
    // Wait for the operations orders view to render
    console.log('Waiting for page render...');
    let buttonFound = false;
    for (let i = 0; i < 20; i++) {
        await new Promise(r => setTimeout(r, 1000));
        const check = await send('Runtime.evaluate', {
            expression: `
                (() => {
                    const btns = Array.from(document.querySelectorAll('button'));
                    const bookingBtn = btns.find(b => b.innerText && (b.innerText.includes('Booking') || b.innerText.includes('Future Limo')));
                    const topbar = document.querySelector('.next-map-container-view-switch');
                    return {
                        hasTopbar: !!topbar,
                        hasBookingBtn: !!bookingBtn,
                        btnText: bookingBtn ? bookingBtn.innerText.trim() : null,
                        url: window.location.href
                    };
                })()
            `,
            returnByValue: true
        });

        if (check?.result?.value?.hasBookingBtn) {
            buttonFound = true;
            console.log('✓ Found Booking button:', check.result.value);
            break;
        } else {
            console.log(`[Attempt ${i+1}] Current url:`, check?.result?.value?.url, 'hasTopbar:', check?.result?.value?.hasTopbar);
        }
    }

    if (!buttonFound) {
        console.error('FAIL: Booking button not found in page');
        chrome.kill();
        process.exit(1);
    }

    // Test 1: Verify drawer is initially closed
    const initialDrawerCheck = await send('Runtime.evaluate', {
        expression: `
            (() => {
                const drawer = document.querySelector('.future-limo-drawer-panel');
                const backdrop = document.querySelector('.future-limo-drawer-backdrop');
                return { hasDrawer: !!drawer, hasBackdrop: !!backdrop };
            })()
        `,
        returnByValue: true
    });
    console.log('Test 1 - Initial drawer state (should be false):', initialDrawerCheck?.result?.value);
    if (initialDrawerCheck?.result?.value?.hasDrawer) {
        console.error('FAIL: Drawer should initially be closed!');
        chrome.kill();
        process.exit(1);
    }
    console.log('✓ Initial drawer state is CLOSED');

    // Test 2: Click "Booking" button to open drawer
    console.log('Test 2 - Clicking Booking button...');
    const clickOpen = await send('Runtime.evaluate', {
        expression: `
            (() => {
                const btns = Array.from(document.querySelectorAll('button'));
                const btn = btns.find(b => b.innerText && (b.innerText.includes('Booking') || b.innerText.includes('Future Limo')));
                if (btn) {
                    btn.click();
                    return true;
                }
                return false;
            })()
        `,
        returnByValue: true
    });
    console.log('Button click result:', clickOpen?.result?.value);

    await new Promise(r => setTimeout(r, 600));

    // Verify drawer contents and responsive sizing
    const openDrawerCheck = await send('Runtime.evaluate', {
        expression: `
            (() => {
                const panel = document.querySelector('.future-limo-drawer-panel');
                const backdrop = document.querySelector('.future-limo-drawer-backdrop');
                const iframe = panel ? panel.querySelector('iframe') : null;
                const iframeWrapper = iframe ? iframe.parentElement : null;
                const header = panel ? panel.querySelector('.border-b') : null;
                const closeBtn = header ? header.querySelector('button') : null;
                const rect = panel ? panel.getBoundingClientRect() : null;
                const iframeRect = iframe ? iframe.getBoundingClientRect() : null;
                const compStyle = panel ? window.getComputedStyle(panel) : null;
                return {
                    hasPanel: !!panel,
                    hasBackdrop: !!backdrop,
                    headerText: header ? header.innerText.trim() : null,
                    iframeSrc: iframe ? iframe.getAttribute('src') : null,
                    iframeTitle: iframe ? iframe.getAttribute('title') : null,
                    iframeSandbox: iframe ? iframe.getAttribute('sandbox') : null,
                    iframeAllow: iframe ? iframe.getAttribute('allow') : null,
                    iframeWrapperClass: iframeWrapper ? iframeWrapper.className : null,
                    hasCloseBtn: !!closeBtn,
                    panelWidth: rect ? rect.width : null,
                    panelHeight: rect ? rect.height : null,
                    computedWidth: compStyle ? compStyle.width : null,
                    computedMinWith: compStyle ? compStyle.minWidth : null,
                    computedMaxWidth: compStyle ? compStyle.maxWidth : null,
                    iframeWidth: iframeRect ? iframeRect.width : null,
                    iframeHeight: iframeRect ? iframeRect.height : null
                };
            })()
        `,
        returnByValue: true
    });
    console.log('Open drawer inspection:', JSON.stringify(openDrawerCheck?.result?.value, null, 2));

    const val = openDrawerCheck?.result?.value;
    const isValidTenantUrl = val?.iframeSrc === 'https://future.limo' || val?.iframeSrc === 'https://futureride.website';
    if (!val?.hasPanel || !val?.hasBackdrop || !isValidTenantUrl) {
        console.error('FAIL: Drawer did not open properly with iframe!');
        chrome.kill();
        process.exit(1);
    }
    if (!val?.headerText || !val.headerText.includes('Booking')) {
        console.error('FAIL: Header text does not contain "Booking"! Found:', val?.headerText);
        chrome.kill();
        process.exit(1);
    }
    console.log('✓ Header title verified: "Booking"');
    if (!val.panelWidth || val.panelWidth < 600 || val.panelWidth > 850) {
        console.error(`FAIL: Drawer width ${val.panelWidth}px is outside expected bounds [600px, 850px]!`);
        chrome.kill();
        process.exit(1);
    }
    console.log(`✓ Drawer OPENED with width: ${val.panelWidth}px, height: ${val.panelHeight}px (within 600-850px bounds)!`);
    console.log(`✓ Iframe size: ${val.iframeWidth}px x ${val.iframeHeight}px`);

    // Capture screenshot of the open drawer
    console.log('Capturing screenshot of open drawer...');
    await new Promise(r => setTimeout(r, 2000)); // allow iframe contents to load
    const screenshot = await send('Page.captureScreenshot', { format: 'png' });
    if (screenshot && screenshot.data) {
        const screenshotPath = '/home/wert/.gemini/antigravity-cli/brain/9de417c2-aeab-4f04-9825-4f63a6a5b175/future_limo_drawer.png';
        fs.writeFileSync(screenshotPath, Buffer.from(screenshot.data, 'base64'));
        console.log(`✓ Screenshot saved to ${screenshotPath}`);
    }

    // Test 3: Dismiss via 'X' close button
    console.log('Test 3 - Clicking close button (X)...');
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
    const closeCheck1 = await send('Runtime.evaluate', {
        expression: `!document.querySelector('.future-limo-drawer-panel') && !document.querySelector('.future-limo-drawer-backdrop')`,
        returnByValue: true
    });
    if (!closeCheck1?.result?.value) {
        console.error('FAIL: Drawer was not closed by X button!');
        chrome.kill();
        process.exit(1);
    }
    console.log('✓ Drawer CLOSED successfully via X button!');

    // Test 4: Reopen and dismiss via Backdrop click
    console.log('Test 4 - Reopening drawer and clicking backdrop...');
    await send('Runtime.evaluate', {
        expression: `
            (() => {
                const btns = Array.from(document.querySelectorAll('button'));
                const btn = btns.find(b => b.innerText && (b.innerText.includes('Booking') || b.innerText.includes('Future Limo')));
                if (btn) btn.click();
            })()
        `
    });
    await new Promise(r => setTimeout(r, 400));

    await send('Runtime.evaluate', {
        expression: `
            (() => {
                const backdrop = document.querySelector('.future-limo-drawer-backdrop');
                if (backdrop) backdrop.click();
            })()
        `
    });
    await new Promise(r => setTimeout(r, 400));

    const closeCheck2 = await send('Runtime.evaluate', {
        expression: `!document.querySelector('.future-limo-drawer-panel') && !document.querySelector('.future-limo-drawer-backdrop')`,
        returnByValue: true
    });
    if (!closeCheck2?.result?.value) {
        console.error('FAIL: Drawer was not closed by Backdrop click!');
        chrome.kill();
        process.exit(1);
    }
    console.log('✓ Drawer CLOSED successfully via Backdrop click!');

    // Test 5: Reopen and dismiss via Escape key
    console.log('Test 5 - Reopening drawer and pressing Escape key...');
    await send('Runtime.evaluate', {
        expression: `
            (() => {
                const btns = Array.from(document.querySelectorAll('button'));
                const btn = btns.find(b => b.innerText && (b.innerText.includes('Booking') || b.innerText.includes('Future Limo')));
                if (btn) btn.click();
            })()
        `
    });
    await new Promise(r => setTimeout(r, 400));

    // Dispatch Escape keydown event
    await send('Runtime.evaluate', {
        expression: `
            window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', code: 'Escape', keyCode: 27, which: 27, bubbles: true }));
        `
    });
    await new Promise(r => setTimeout(r, 400));

    const closeCheck3 = await send('Runtime.evaluate', {
        expression: `!document.querySelector('.future-limo-drawer-panel') && !document.querySelector('.future-limo-drawer-backdrop')`,
        returnByValue: true
    });
    if (!closeCheck3?.result?.value) {
        console.error('FAIL: Drawer was not closed by Escape key!');
        chrome.kill();
        process.exit(1);
    }
    console.log('✓ Drawer CLOSED successfully via Escape key!');

    console.log('\n========================================');
    console.log('ALL TESTS PASSED WITH 100% SUCCESS!');
    console.log('========================================');

    chrome.kill();
    process.exit(0);
}

verify().catch(err => {
    console.error('Fatal error during verification:', err);
    process.exit(1);
});
