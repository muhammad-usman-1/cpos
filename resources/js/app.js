/**
 * Translate a UI phrase with the strings the layout passes in (window.translations). Placeholders look like :name.
 */
function t(key, replacements = {}) {
    let text = (window.translations && window.translations[key]) || key;

    Object.entries(replacements).forEach(([name, value]) => {
        text = text.replaceAll(`:${name}`, String(value));
    });

    return text;
}

/**
 * Auto logout after the configured period without keyboard, mouse or touch activity (AUTH06).
 * The server enforces the same limit, this only returns the screen to the login page.
 */
function initIdleLogout() {
    const minutes = Number(document.querySelector('meta[name="idle-timeout"]')?.content || 0);
    const form = document.getElementById('idle-logout-form');

    if (!minutes || !form) {
        return;
    }

    let timer;
    const reset = () => {
        clearTimeout(timer);
        // Logging out needs the server; while offline the timer just starts again.
        timer = setTimeout(() => (navigator.onLine ? form.submit() : reset()), minutes * 60 * 1000);
    };

    ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll'].forEach((event) =>
        document.addEventListener(event, reset, { passive: true }),
    );
    reset();
}

/**
 * Any form or button with data-confirm asks before submitting.
 */
function initConfirmations() {
    document.addEventListener('submit', (event) => {
        const message = event.target.dataset.confirm;

        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });
}

/**
 * Repeating rows backed by a <template data-row-template="name"> and a container [data-rows="name"].
 * Placeholders __INDEX__ in the template are replaced with a unique index.
 */
function initRepeatableRows() {
    document.querySelectorAll('[data-add-row]').forEach((button) => {
        button.addEventListener('click', () => addRow(button.dataset.addRow));
    });

    document.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-remove-row]');

        if (remove) {
            remove.closest('[data-row]').remove();
            document.dispatchEvent(new CustomEvent('rows-changed'));
        }
    });
}

let rowCounter = 1000;

function addRow(name, values = {}) {
    const template = document.querySelector(`template[data-row-template="${name}"]`);
    const container = document.querySelector(`[data-rows="${name}"]`);

    if (!template || !container) {
        return null;
    }

    const html = template.innerHTML.replaceAll('__INDEX__', String(rowCounter++));
    const wrapper = document.createElement('tbody');
    wrapper.innerHTML = html.trim();
    const row = wrapper.firstElementChild;

    Object.entries(values).forEach(([key, value]) => {
        const field = row.querySelector(`[data-field="${key}"]`);

        if (field) {
            if ('value' in field) {
                field.value = value;
            } else {
                field.textContent = value;
            }
        }
    });

    container.appendChild(row);
    document.dispatchEvent(new CustomEvent('rows-changed'));

    return row;
}

/**
 * Whole numbers for suits and pieces, decimals for loose fabric. The browser counts valid values up from `min`,
 * so min must match the step or a quantity like 100 is rejected.
 */
function setQuantityRule(input, fractional) {
    input.step = fractional ? '0.001' : '1';
    input.min = fractional ? '0.001' : '1';
}

const money = (value) =>
    Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/**
 * Purchase entry: search or scan products, add lines, live totals (PUR01).
 */
function initPurchaseForm() {
    const form = document.querySelector('[data-purchase-form]');

    if (!form) {
        return;
    }

    const search = form.querySelector('[data-product-search]');
    const results = form.querySelector('[data-search-results]');
    const lookupUrl = search.dataset.url;
    let debounce;

    const recalc = () => {
        let subtotal = 0;

        form.querySelectorAll('[data-rows="items"] [data-row]').forEach((row) => {
            const qty = parseFloat(row.querySelector('[data-field="quantity"]').value) || 0;
            const rate = parseFloat(row.querySelector('[data-field="rate"]').value) || 0;
            const amount = Math.round(qty * rate * 100) / 100;
            row.querySelector('[data-amount]').textContent = money(amount);
            subtotal += amount;
        });

        const discount = parseFloat(form.querySelector('[name="discount"]').value) || 0;
        const charges = parseFloat(form.querySelector('[name="other_charges"]').value) || 0;
        const paid = parseFloat(form.querySelector('[name="paid_amount"]').value) || 0;
        const total = subtotal - discount + charges;

        form.querySelector('[data-subtotal]').textContent = money(subtotal);
        form.querySelector('[data-total]').textContent = money(total);
        form.querySelector('[data-balance]').textContent = money(total - paid);
        form.querySelector('[data-empty-row]')?.classList.toggle(
            'hidden',
            form.querySelectorAll('[data-rows="items"] [data-row]').length > 0,
        );
    };

    const addItem = (item, focus = true) => {
        const existing = form.querySelector(`[data-rows="items"] [data-variant-id="${item.id}"]`);

        if (existing) {
            const qty = existing.querySelector('[data-field="quantity"]');
            qty.value = (parseFloat(qty.value) || 0) + 1;

            if (focus) {
                qty.focus();
                qty.select();
            }

            recalc();

            return existing;
        }

        const row = addRow('items', {
            product_variant_id: item.id,
            label: item.label,
            sku: item.sku,
            quantity: 1,
            rate: item.cost ?? '',
        });

        row.dataset.variantId = item.id;
        setQuantityRule(row.querySelector('[data-field="quantity"]'), item.fractional);

        if (focus) {
            row.querySelector('[data-field="quantity"]').focus();
            row.querySelector('[data-field="quantity"]').select();
        }

        recalc();

        return row;
    };

    /**
     * Results grouped by design. Tick several colours (or "All colours") and add them in one go.
     */
    const showResults = (items) => {
        results.innerHTML = '';

        if (!items.length) {
            results.innerHTML = '<div class="px-3 py-2 text-sm text-slate-500"></div>';
            results.firstChild.textContent = t('No matching products');
            results.classList.remove('hidden');

            return;
        }

        const groups = new Map();
        items.forEach((item) => {
            if (!groups.has(item.code)) {
                groups.set(item.code, []);
            }
            groups.get(item.code).push(item);
        });

        const footer = document.createElement('div');
        const addButton = document.createElement('button');
        const selected = () => [...results.querySelectorAll('input[data-pick]:checked')].map((box) => items[Number(box.dataset.pick)]);
        const refreshFooter = () => {
            const count = selected().length;
            addButton.disabled = count === 0;
            addButton.textContent = count ? t('Add :count selected', { count }) : t('Tick colours to add');
            results.querySelectorAll('input[data-group]').forEach((groupBox) => {
                const boxes = [...results.querySelectorAll(`input[data-in-group="${groupBox.dataset.group}"]`)];
                groupBox.checked = boxes.length > 0 && boxes.every((box) => box.checked);
                groupBox.indeterminate = !groupBox.checked && boxes.some((box) => box.checked);
            });
        };

        let groupIndex = 0;
        groups.forEach((groupItems) => {
            const groupId = String(groupIndex++);

            if (groupItems.length > 1) {
                const header = document.createElement('label');
                header.className = 'flex cursor-pointer items-center gap-2 border-t border-slate-100 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-600';
                header.innerHTML = '<input type="checkbox" class="h-4 w-4 rounded border-slate-300"><span></span>';
                header.querySelector('input').dataset.group = groupId;
                header.querySelector('span').textContent = t('All :count colours', { count: groupItems.length });
                header.querySelector('input').addEventListener('change', (event) => {
                    results.querySelectorAll(`input[data-in-group="${groupId}"]`).forEach((box) => (box.checked = event.target.checked));
                    refreshFooter();
                });
                results.appendChild(header);
            }

            groupItems.forEach((item) => {
                const option = document.createElement('label');
                option.className = 'flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-indigo-50';
                option.innerHTML = '<input type="checkbox" class="h-4 w-4 rounded border-slate-300"><span class="font-medium"></span><span class="text-xs text-slate-500"></span>';
                const box = option.querySelector('input');
                box.dataset.pick = String(items.indexOf(item));
                box.dataset.inGroup = groupId;
                box.addEventListener('change', refreshFooter);
                option.children[1].textContent = item.label;
                option.children[2].textContent = t(':sku · stock :stock', { sku: item.sku, stock: item.stock });
                results.appendChild(option);
            });
        });

        footer.className = 'sticky bottom-0 flex items-center justify-between gap-2 border-t border-slate-200 bg-white px-3 py-2';
        footer.innerHTML = '<button type="button" class="text-xs text-slate-500 hover:underline"></button>';
        footer.querySelector('button').textContent = t('Select all');
        footer.querySelector('button').addEventListener('click', () => {
            results.querySelectorAll('input[data-pick]').forEach((box) => (box.checked = true));
            refreshFooter();
        });
        addButton.type = 'button';
        addButton.className = 'btn btn-primary btn-sm';
        addButton.addEventListener('click', () => {
            const picked = selected();
            const rows = picked.map((item) => addItem(item, false));
            results.classList.add('hidden');
            search.value = '';
            rows[0]?.querySelector('[data-field="quantity"]').focus();
            rows[0]?.querySelector('[data-field="quantity"]').select();
        });
        footer.appendChild(addButton);
        results.appendChild(footer);

        refreshFooter();
        results.classList.remove('hidden');
    };

    const lookup = async (term) => {
        const response = await fetch(`${lookupUrl}?q=${encodeURIComponent(term)}`, {
            headers: { Accept: 'application/json' },
        });

        return response.ok ? response.json() : [];
    };

    search.addEventListener('input', () => {
        clearTimeout(debounce);
        const term = search.value.trim();

        if (term.length < 2) {
            results.classList.add('hidden');

            return;
        }

        debounce = setTimeout(async () => {
            const items = await lookup(term);

            // The scanner may already have added the item and cleared the box; do not reopen an old list.
            if (search.value.trim() === term) {
                showResults(items);
            }
        }, 250);
    });

    // A barcode scanner types the code and presses Enter: add the exact match straight away.
    search.addEventListener('keydown', async (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        clearTimeout(debounce);
        const term = search.value.trim();

        if (!term) {
            return;
        }

        const items = await lookup(term);

        if (items.length === 1) {
            addItem(items[0]);
            search.value = '';
            results.classList.add('hidden');
        } else {
            showResults(items);
        }
    });

    document.addEventListener('click', (event) => {
        if (!results.contains(event.target) && event.target !== search) {
            results.classList.add('hidden');
        }
    });

    // Enter in a quantity or rate box goes back to the scan box for the next item instead of saving the purchase.
    form.querySelector('[data-rows="items"]').addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.tagName === 'INPUT') {
            event.preventDefault();
            search.focus();
        }
    });

    form.addEventListener('input', recalc);
    document.addEventListener('rows-changed', recalc);

    JSON.parse(form.dataset.items || '[]').forEach((item) => {
        const row = addRow('items', {
            product_variant_id: item.product_variant_id,
            label: item.label,
            sku: item.sku,
            quantity: item.quantity,
            rate: item.rate,
        });
        row.dataset.variantId = item.product_variant_id;
        setQuantityRule(row.querySelector('[data-field="quantity"]'), item.fractional);
    });

    recalc();
}

/**
 * Stock adjustment: pick a single variant by search or scan.
 */
function initVariantPicker() {
    document.querySelectorAll('[data-variant-picker]').forEach((picker) => {
        const search = picker.querySelector('[data-product-search]');
        const results = picker.querySelector('[data-search-results]');
        const hidden = picker.querySelector('[data-variant-id]');
        const chosen = picker.querySelector('[data-chosen]');
        let debounce;

        const choose = (item) => {
            if (hidden) {
                hidden.value = item.id;
            }

            if (chosen) {
                chosen.textContent = t(':label (:sku) · current stock :stock', { label: item.label, sku: item.sku, stock: item.stock });
            }

            results.classList.add('hidden');
            search.value = '';
            picker.dispatchEvent(new CustomEvent('variant-chosen', { detail: item, bubbles: true }));
        };

        const run = async (term, pickSingle) => {
            const response = await fetch(`${search.dataset.url}?q=${encodeURIComponent(term)}`, {
                headers: { Accept: 'application/json' },
            });
            const items = response.ok ? await response.json() : [];

            if (pickSingle && items.length === 1) {
                choose(items[0]);

                return;
            }

            results.innerHTML = '';
            items.forEach((item) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'block w-full px-3 py-2 text-start text-sm hover:bg-indigo-50';
                option.textContent = t(':label · :sku · stock :stock', { label: item.label, sku: item.sku, stock: item.stock });
                option.addEventListener('click', () => choose(item));
                results.appendChild(option);
            });
            results.classList.toggle('hidden', !items.length);
        };

        search.addEventListener('input', () => {
            clearTimeout(debounce);
            const term = search.value.trim();

            if (term.length >= 2) {
                debounce = setTimeout(() => run(term, false), 250);
            }
        });

        search.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                run(search.value.trim(), true);
            }
        });
    });
}

/*
 * ---------------------------------------------------------------------------------------------
 * Offline sales. While the internet is down the New sale screen keeps working:
 *  - products are searched in a copy saved on this computer (refreshed whenever the screen opens online);
 *  - Complete sale stores the bill on this computer and prints a simple receipt;
 *  - stored bills upload by themselves when the internet is back. Each carries its own ID, so a bill
 *    is never saved twice, and the time it was made, so it lands on the right day.
 * ---------------------------------------------------------------------------------------------
 */
const OFFLINE_CATALOGUE_KEY = 'cpos.offline.catalogue';
const OFFLINE_QUEUE_KEY = 'cpos.offline.sales';
const OFFLINE_PRINT_KEY = 'cpos.offline.print';

const readStored = (key, fallback, storage = localStorage) => {
    try {
        return JSON.parse(storage.getItem(key)) ?? fallback;
    } catch (error) {
        return fallback;
    }
};

const writeStored = (key, value, storage = localStorage) => {
    try {
        storage.setItem(key, JSON.stringify(value));

        return true;
    } catch (error) {
        return false;
    }
};

const newBillId = () => (window.crypto?.randomUUID
    ? window.crypto.randomUUID()
    : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = Math.random() * 16 | 0;

        return (c === 'x' ? r : ((r & 0x3) | 0x8)).toString(16);
    }));

/** Product search from the saved copy: an exact code first, otherwise every typed word must match. */
function offlineProductSearch(term) {
    const catalogue = readStored(OFFLINE_CATALOGUE_KEY, null);

    if (!catalogue) {
        return [];
    }

    const needle = term.toLowerCase();
    const label = catalogue.labels.find((item) => item.sku.toLowerCase() === needle);

    if (label) {
        return [label];
    }

    const exact = catalogue.products.filter((item) => item.sku.toLowerCase() === needle);

    if (exact.length) {
        return exact;
    }

    const words = needle.split(/\s+/).filter(Boolean);

    return catalogue.products
        .filter((item) => {
            const text = `${item.label} ${item.sku} ${item.code}`.toLowerCase();

            return words.every((word) => text.includes(word));
        })
        .slice(0, 30);
}

/** A plain 80mm receipt for a bill saved offline; the invoice number is given when it uploads. */
function printOfflineReceipt(bill) {
    const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const lines = bill.items.map((item) => `
        <tr><td colspan="3">${escape(item.label)}</td></tr>
        <tr><td>${escape(item.quantity)} x ${money(item.price)}</td><td></td><td style="text-align:right">${money(item.amount)}</td></tr>`).join('');
    const payments = bill.payments.map((payment) => `<tr><td colspan="2">${escape(payment.method)}</td><td style="text-align:right">${money(payment.amount)}</td></tr>`).join('');
    const html = `<!doctype html><html><head><meta charset="utf-8"><title>Receipt</title>
        <style>body{font-family:'Courier New',monospace;font-size:13px;margin:0}.r{width:72mm;padding:3mm}td{padding:2px 0;vertical-align:top}
        table{width:100%;border-collapse:collapse}.line{border-top:1px dashed #000;margin:6px 0}.c{text-align:center}.b{font-weight:bold}
        @page{size:80mm auto;margin:0}</style></head><body><div class="r">
        <div class="c b" style="font-size:16px">${escape(bill.shop)}</div>
        <div class="c">${escape(new Date(bill.madeAt).toLocaleString())}</div>
        ${bill.customer ? `<div class="c">${escape(bill.customer)}</div>` : ''}
        <div class="line"></div><table>${lines}</table><div class="line"></div>
        <table><tr class="b"><td colspan="2">${escape(t('Net total'))} ${escape(bill.currency)}</td><td style="text-align:right">${money(bill.total)}</td></tr>${payments}</table>
        <div class="line"></div><div class="c">${escape(t('Offline bill. The invoice number is given when it uploads.'))}</div>
        <div class="c" style="font-size:11px;margin-top:4px">${escape(bill.id.slice(0, 8).toUpperCase())}</div></div></body></html>`;

    const frame = document.createElement('iframe');
    frame.style.cssText = 'position:fixed;inset-inline-end:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
    document.body.appendChild(frame);
    frame.contentDocument.open();
    frame.contentDocument.write(html);
    frame.contentDocument.close();
    setTimeout(() => {
        frame.contentWindow.focus();
        frame.contentWindow.print();
    }, 150);
}

/**
 * "Install app" button in the top bar: appears when Chrome / Edge offer installing,
 * opens the browser's install box, and hides once the app is installed or opened as the app.
 */
function initInstallButton() {
    const button = document.querySelector('[data-install-app]');
    const runningAsApp = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
    let installPrompt = null;

    if (!button || runningAsApp) {
        return;
    }

    const show = (visible) => {
        button.classList.toggle('hidden', !visible);
        button.classList.toggle('inline-flex', visible);
    };

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installPrompt = event;
        show(true);
    });

    button.addEventListener('click', async () => {
        if (!installPrompt) {
            return;
        }

        installPrompt.prompt();
        await installPrompt.userChoice.catch(() => null);
        installPrompt = null;
        show(false);
    });

    window.addEventListener('appinstalled', () => show(false));
}

function initOfflineSales(form) {
    const bar = document.querySelector('[data-offline-bar]');
    const storeUrl = form.action;
    let uploading = false;

    // Save this screen's files (and a clean copy of the screen) soon after opening, so the very first
    // offline use works. Waiting a few seconds keeps the server free while the page (or an app install) loads.
    const saveForOffline = () => window.caches && navigator.onLine && caches.open('cpos-offline-v1').then(async (cache) => {
            const files = performance.getEntriesByType('resource')
                .map((entry) => new URL(entry.name))
                .filter((url) => url.origin === location.origin && url.pathname.startsWith('/build/'))
                .map((url) => url.pathname);
            await cache.addAll([...new Set(files)]).catch(() => null);

            if (!(await cache.match('/sales/create'))) {
                const response = await fetch('/sales/create', { credentials: 'same-origin' });

                if (response.ok && !response.redirected && response.headers.get('X-Offline-Copy') === '1') {
                    await cache.put('/sales/create', response);
                }
            }
        }).catch(() => null);

    const queue = () => readStored(OFFLINE_QUEUE_KEY, []);
    const saveQueue = (bills) => writeStored(OFFLINE_QUEUE_KEY, bills);

    const render = (message = '') => {
        if (!bar) {
            return;
        }

        const bills = queue();
        const waiting = bills.filter((bill) => bill.status !== 'failed');
        const failed = bills.filter((bill) => bill.status === 'failed');
        const offline = !navigator.onLine;
        const parts = [];

        if (offline) {
            parts.push(`<div class="flex items-center gap-2 font-semibold"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>${t('No internet. Bills are saved on this computer and upload when the internet is back.')}</div>`);
        }

        if (waiting.length) {
            parts.push(`<div class="flex flex-wrap items-center gap-2"><span>${t(':count bill(s) waiting to upload.', { count: waiting.length })}</span>${offline ? '' : `<button type="button" class="btn btn-secondary btn-sm" data-offline-upload>${t('Upload now')}</button>`}</div>`);
        }

        failed.forEach((bill) => {
            parts.push(`<div class="flex flex-wrap items-center gap-2 rounded-md bg-rose-50 px-2 py-1 text-rose-800"><span class="font-semibold"></span><span data-error></span>
                <button type="button" class="text-xs underline" data-offline-retry="${bill.id}">${t('Try again')}</button>
                <button type="button" class="text-xs underline" data-offline-remove="${bill.id}">${t('Remove')}</button></div>`);
        });

        if (message) {
            parts.push(`<div class="font-medium text-emerald-700" data-message></div>`);
        }

        bar.innerHTML = parts.join('');
        bar.classList.toggle('hidden', parts.length === 0);

        // Text from bills and the server goes in as text, never as HTML.
        [...bar.querySelectorAll('.bg-rose-50')].forEach((row, index) => {
            const bill = failed[index];
            row.children[0].textContent = `${t('Not uploaded')}: ${new Date(bill.madeAt).toLocaleString()} · ${money(bill.total)}`;
            row.querySelector('[data-error]').textContent = `— ${bill.error}`;
        });
        bar.querySelector('[data-message]')?.replaceChildren(document.createTextNode(message));
    };

    /** Keep the product copy fresh while online. */
    const refreshCatalogue = async () => {
        if (!navigator.onLine || !form.dataset.offlineCatalogue) {
            return;
        }

        try {
            const response = await fetch(form.dataset.offlineCatalogue, { headers: { Accept: 'application/json' } });

            if (response.ok && !response.redirected) {
                writeStored(OFFLINE_CATALOGUE_KEY, await response.json());
            }
        } catch (error) {
            // Keep the copy we have.
        }
    };

    /** Upload stored bills one by one; stop at the first sign the connection or login is gone. */
    const upload = async () => {
        if (uploading || !navigator.onLine || !queue().some((bill) => bill.status !== 'failed')) {
            render();

            return;
        }

        uploading = true;
        let uploaded = 0;
        let message = '';

        try {
            const tokenResponse = await fetch(form.dataset.offlineToken, { headers: { Accept: 'application/json' } });
            const token = tokenResponse.ok && !tokenResponse.redirected ? (await tokenResponse.json()).token : null;

            if (!token) {
                message = t('Log in again to upload the saved bills.');
            } else {
                for (const bill of queue().filter((item) => item.status !== 'failed')) {
                    const body = new URLSearchParams(bill.fields);
                    body.set('_token', token);

                    const response = await fetch(storeUrl, {
                        method: 'POST',
                        body,
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const bills = queue();
                    const index = bills.findIndex((item) => item.id === bill.id);

                    if (response.ok) {
                        bills.splice(index, 1);
                        saveQueue(bills);
                        uploaded++;
                    } else if (response.status === 422) {
                        const errors = (await response.json()).errors || {};
                        bills[index] = { ...bills[index], status: 'failed', error: Object.values(errors).flat()[0] || t('The server did not accept this bill.') };
                        saveQueue(bills);
                    } else {
                        message = response.status === 419 || response.status === 401
                            ? t('Log in again to upload the saved bills.')
                            : t('Upload stopped; it will try again shortly.');
                        break;
                    }
                }
            }
        } catch (error) {
            message = t('Upload stopped; it will try again shortly.');
        }

        uploading = false;
        render(uploaded ? t(':count offline bill(s) uploaded.', { count: uploaded }) : message);
    };

    /** Store the bill on this computer, print a simple receipt and start a new bill. */
    const saveOffline = () => {
        const rows = [...form.querySelectorAll('[data-rows="sale-items"] [data-row]')];

        if (!rows.length) {
            render(t('Add at least one item.'));

            return;
        }

        const fields = [...new FormData(form).entries()]
            .filter(([name, value]) => !['_token', 'held_id', 'action'].includes(name) && typeof value === 'string');
        const bill = {
            id: newBillId(),
            madeAt: new Date().toISOString(),
            status: 'waiting',
            total: parseFloat((form.querySelector('[data-total]')?.textContent || '0').replace(/,/g, '')) || 0,
        };
        fields.push(['action', 'complete'], ['client_uuid', bill.id], ['offline_at', bill.madeAt]);
        bill.fields = fields;

        const bills = queue();
        bills.push(bill);

        if (!saveQueue(bills)) {
            render(t('This computer has no space to save the bill. Remove old saved bills first.'));

            return;
        }

        writeStored(OFFLINE_PRINT_KEY, {
            id: bill.id,
            madeAt: bill.madeAt,
            total: bill.total,
            shop: form.dataset.shopName || '',
            currency: form.dataset.currency || '',
            customer: [form.querySelector('[data-counter-name]')?.value, form.querySelector('[data-counter-phone]')?.value].filter(Boolean).join(' · '),
            items: rows.map((row) => ({
                label: row.querySelector('[data-label-text]')?.textContent || '',
                quantity: row.querySelector('[data-field="quantity"]').value,
                price: parseFloat(row.querySelector('[data-field="price"]').value) || 0,
                amount: parseFloat((row.querySelector('[data-amount]').textContent || '0').replace(/,/g, '')) || 0,
            })),
            payments: [...form.querySelectorAll('[data-rows="payments"] [data-row]')]
                .map((row) => ({ method: row.querySelector('[data-field="method"]')?.selectedOptions[0]?.textContent.trim(), amount: parseFloat(row.querySelector('[data-field="amount"]')?.value) || 0 }))
                .filter((payment) => payment.amount > 0),
        }, sessionStorage);

        // A clean new bill: the saved screen reopens and prints the receipt.
        window.location.assign(window.location.pathname);
    };

    // Complete sale: check the server can be reached first; if not, keep the bill on this computer.
    // Listens on the page, so it runs after any other check that may stop the form.
    document.addEventListener('submit', async (event) => {
        if (event.target !== form || event.defaultPrevented || form.dataset.submitting) {
            return;
        }

        const action = event.submitter?.value;
        event.preventDefault();

        let reachable = navigator.onLine;

        if (reachable) {
            try {
                const controller = new AbortController();
                const timeout = setTimeout(() => controller.abort(), 4000);
                const response = await fetch(form.dataset.offlineToken, { headers: { Accept: 'application/json' }, signal: controller.signal });
                clearTimeout(timeout);
                reachable = response.ok || response.status === 401 || response.status === 302;
            } catch (error) {
                reachable = false;
            }
        }

        if (reachable) {
            // Send the form normally, keeping which button was pressed.
            if (action) {
                let actionField = form.querySelector('input[type="hidden"][name="action"]');

                if (!actionField) {
                    actionField = document.createElement('input');
                    actionField.type = 'hidden';
                    actionField.name = 'action';
                    form.appendChild(actionField);
                }

                actionField.value = action;
            }

            form.dataset.submitting = '1';
            form.submit();

            return;
        }

        if (action === 'hold') {
            render(t('Holding a bill needs the internet. Complete the sale instead; it will upload later.'));

            return;
        }

        saveOffline();
    });

    // After a bill was saved offline: print its receipt on the fresh screen.
    const toPrint = readStored(OFFLINE_PRINT_KEY, null, sessionStorage);

    if (toPrint) {
        sessionStorage.removeItem(OFFLINE_PRINT_KEY);
        render(t('Bill saved on this computer. It will upload when the internet is back.'));
        printOfflineReceipt(toPrint);
    }

    bar?.addEventListener('click', (event) => {
        const retry = event.target.closest('[data-offline-retry]');
        const remove = event.target.closest('[data-offline-remove]');

        if (event.target.closest('[data-offline-upload]')) {
            upload();
        } else if (retry) {
            saveQueue(queue().map((bill) => (bill.id === retry.dataset.offlineRetry ? { ...bill, status: 'waiting', error: null } : bill)));
            upload();
        } else if (remove && confirm(t('Remove this saved bill? It will not be uploaded.'))) {
            saveQueue(queue().filter((bill) => bill.id !== remove.dataset.offlineRemove));
            render();
        }
    });

    window.addEventListener('online', () => {
        render();
        refreshCatalogue();
        upload();
    });
    window.addEventListener('offline', () => render());
    setInterval(upload, 60 * 1000);

    if (!toPrint) {
        render();
    }

    // Background work starts a few seconds after the screen opens, one step at a time.
    setTimeout(async () => {
        await saveForOffline();
        await refreshCatalogue();
        await upload();
    }, 4000);
}

/**
 * Debounced type-ahead: calls fetchUrl?q=term and renders results with renderItem, Enter picks a single match.
 */
function attachSearch({ input, results, url, renderItem, onPick, minLength = 2, offlineSearch = null }) {
    let debounce;
    let latest = [];
    let activeIndex = -1;
    let requestId = 0;

    // Without internet, offlineSearch (if given) answers from the copy saved on this computer.
    const fetchItems = async (term) => {
        if (offlineSearch && !navigator.onLine) {
            return offlineSearch(term);
        }

        try {
            const separator = url.includes('?') ? '&' : '?';
            const response = await fetch(`${url}${separator}q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' } });

            return response.ok ? response.json() : [];
        } catch (error) {
            return offlineSearch ? offlineSearch(term) : [];
        }
    };

    const isOpen = () => !results.classList.contains('hidden');

    const close = () => {
        results.classList.add('hidden');
        activeIndex = -1;
    };

    const pick = (item) => {
        close();
        input.value = '';
        onPick(item);
    };

    /** Highlight one row for arrow-key use and keep it in view inside the scrolling list. */
    const highlight = (index) => {
        const options = [...results.querySelectorAll('[data-search-option]')];
        activeIndex = options.length ? (index + options.length) % options.length : -1;

        options.forEach((option, position) => {
            const isActive = position === activeIndex;
            option.classList.toggle('bg-indigo-100', isActive);
            option.setAttribute('aria-selected', isActive ? 'true' : 'false');

            if (isActive) {
                option.scrollIntoView({ block: 'nearest' });
            }
        });
    };

    const render = (items) => {
        latest = items;
        activeIndex = -1;
        results.innerHTML = '';

        if (!items.length) {
            results.innerHTML = '<div class="px-3 py-2 text-sm text-slate-500"></div>';
            results.firstChild.textContent = t('No matches');
        }

        items.forEach((item, index) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.tabIndex = -1;
            option.dataset.searchOption = '';
            option.setAttribute('role', 'option');
            option.className = 'block w-full px-3 py-2 text-start text-sm hover:bg-indigo-50';
            renderItem(option, item);
            option.addEventListener('click', () => pick(item));
            option.addEventListener('mousemove', () => activeIndex !== index && highlight(index));
            results.appendChild(option);
        });

        results.classList.remove('hidden');
    };

    /** Only the newest request may draw the list, so a slow answer for "h" never replaces the one for "ha". */
    const search = async (term) => {
        const current = ++requestId;
        const items = await fetchItems(term);

        if (current === requestId && input.value.trim() === term) {
            render(items);
        }

        return items;
    };

    input.addEventListener('input', () => {
        clearTimeout(debounce);
        const term = input.value.trim();

        if (term.length < minLength) {
            requestId++;
            close();

            return;
        }

        debounce = setTimeout(() => search(term), 150);
    });

    input.addEventListener('focus', () => {
        if (input.value.trim().length >= minLength && latest.length) {
            results.classList.remove('hidden');
        }
    });

    input.addEventListener('keydown', async (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            if (!latest.length) {
                return;
            }

            event.preventDefault();

            if (!isOpen()) {
                results.classList.remove('hidden');
            }

            highlight(event.key === 'ArrowDown' ? activeIndex + 1 : (activeIndex < 0 ? latest.length - 1 : activeIndex - 1));

            return;
        }

        if (event.key === 'Escape') {
            close();

            return;
        }

        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();

        if (isOpen() && activeIndex >= 0 && latest[activeIndex]) {
            pick(latest[activeIndex]);

            return;
        }

        clearTimeout(debounce);
        const term = input.value.trim();

        if (!term) {
            return;
        }

        const items = await fetchItems(term);

        if (items.length === 1) {
            pick(items[0]);
        } else {
            render(items);
        }
    });

    document.addEventListener('click', (event) => {
        if (!results.contains(event.target) && event.target !== input) {
            results.classList.add('hidden');
        }
    });

    return { latest: () => latest };
}

const customerOption = (option, customer) => {
    option.innerHTML = '<span class="font-medium"></span> <span class="text-slate-500"></span><span class="float-end text-xs"></span>';
    option.children[0].textContent = customer.name;
    option.children[1].textContent = customer.matched_member
        ? t('Family member: :member', { member: customer.matched_member })
        : customer.phone + (customer.city ? ` · ${customer.city}` : '');
    option.children[2].textContent = customer.balance ? t('Balance :amount', { amount: money(customer.balance) }) : '';
};

/**
 * Top bar search: type a phone or name and jump to the customer profile (CUS02, workflow 6.1).
 */
function initGlobalCustomerSearch() {
    const input = document.querySelector('[data-global-customer-search]');

    if (!input) {
        return;
    }

    attachSearch({
        input,
        results: input.parentElement.querySelector('[data-search-results]'),
        url: input.dataset.url,
        renderItem: customerOption,
        onPick: (customer) => (window.location.href = customer.url),
        minLength: 1,
    });

    // F2 jumps to the search box from anywhere.
    document.addEventListener('keydown', (event) => {
        if (event.key === 'F2') {
            event.preventDefault();
            input.focus();
        }
    });
}

/**
 * Warn about an existing customer with the same phone while typing (CUS05).
 */
function initPhoneCheck() {
    const url = document.querySelector('meta[name="phone-check-url"]')?.content;

    if (!url) {
        return;
    }

    const digits = (value) => value.replace(/\D/g, '').replace(/^92(?=3\d{9}$)/, '0');
    const timers = new WeakMap();

    const warningFor = (input) => {
        let warning = input.parentElement.querySelector(':scope > [data-phone-warning]');

        if (!warning) {
            warning = document.createElement('div');
            warning.dataset.phoneWarning = '';
            warning.className = 'mt-1 hidden rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800';
            input.insertAdjacentElement('afterend', warning);
        }

        return warning;
    };

    const show = (input, message, link) => {
        const warning = warningFor(input);
        warning.replaceChildren(message);

        if (link) {
            const anchor = document.createElement('a');
            anchor.href = link;
            anchor.className = 'ms-1 font-medium underline';
            anchor.textContent = t('Open that customer');
            warning.append(anchor);
        }

        warning.classList.remove('hidden');
        input.classList.add('border-amber-400');
    };

    const clear = (input) => {
        warningFor(input).classList.add('hidden');
        input.classList.remove('border-amber-400');
    };

    const check = async (input) => {
        const value = digits(input.value);

        if (value.length < 7) {
            clear(input);

            return;
        }

        // The same number typed twice on one form (for example the customer and a family member).
        const twin = [...(input.form?.querySelectorAll('[data-phone-check]') ?? [])].find(
            (other) => other !== input && digits(other.value) === value,
        );

        if (twin) {
            show(input, t('The same phone number is entered twice on this form.'));

            return;
        }

        const params = new URLSearchParams({ phone: input.value, ignore_type: input.dataset.ignoreType || '', ignore_id: input.dataset.ignoreId || '' });
        const response = await fetch(`${url}?${params}`, { headers: { Accept: 'application/json' } });
        const result = response.ok ? await response.json() : { exists: false };

        if (result.exists) {
            show(input, result.message, result.url);
        } else {
            clear(input);
        }
    };

    document.addEventListener('input', (event) => {
        const input = event.target.closest?.('[data-phone-check]');

        if (!input) {
            return;
        }

        clearTimeout(timers.get(input));
        timers.set(input, setTimeout(() => check(input), 300));
    });

    // Numbers already filled in (for example after a failed save) are checked straight away.
    document.querySelectorAll('[data-phone-check]').forEach((input) => input.value && check(input));
}

/**
 * Family member rows on the customer form: choosing the relation picks the usual measurement form.
 */
function initFamilyRows() {
    const rows = document.querySelector('[data-rows="family"]');

    if (!rows) {
        return;
    }

    const templates = JSON.parse(rows.dataset.relationTemplates || '{}');

    rows.addEventListener('change', (event) => {
        const relation = event.target.closest('[data-family-relation]');

        if (relation) {
            const template = relation.closest('[data-row]').querySelector('[data-family-template]');
            template.value = templates[relation.value] || template.value;
        }
    });

    document.addEventListener('rows-changed', () => {
        rows.querySelectorAll('[data-row]').forEach((row) => {
            const relation = row.querySelector('[data-family-relation]');
            const template = row.querySelector('[data-family-template]');

            if (!row.dataset.templateSet) {
                template.value = templates[relation.value] || template.value;
                row.dataset.templateSet = '1';
            }
        });
    });
}

/**
 * Switching between inches and centimetres converts the numbers already entered (MSR06).
 */
function initMeasurementUnits() {
    document.querySelectorAll('[data-measurement-unit]').forEach((select) => {
        let previous = select.value;

        select.addEventListener('change', () => {
            const factor = previous === 'in' && select.value === 'cm' ? 2.54 : previous === 'cm' && select.value === 'in' ? 1 / 2.54 : 1;
            const scope = select.closest('[data-measurement-scope]') || document;

            scope.querySelectorAll('input[data-numeric-measurement]').forEach((input) => {
                if (input.value !== '' && !Number.isNaN(Number(input.value))) {
                    input.value = String(Math.round(Number(input.value) * factor * 4) / 4);
                }
            });

            previous = select.value;
        });
    });
}

/**
 * Counter sale screen (SAL01 to SAL03): scan or search items, discounts, split payments, change and credit.
 */
function initSaleForm() {
    const form = document.querySelector('[data-sale-form]');

    if (!form) {
        return;
    }

    initOfflineSales(form);

    const rows = form.querySelector('[data-rows="sale-items"]');
    const payRows = form.querySelector('[data-rows="payments"]');
    const customerId = form.querySelector('[name="customer_id"]');
    const customerBox = form.querySelector('[data-customer-box]');
    const counterPhone = form.querySelector('[data-counter-phone]');
    const counterName = form.querySelector('[data-counter-name]');
    let paymentTouched = false;

    /** A phone and name typed for a customer who is not saved yet: that customer is created with the bill. */
    const hasNewCounterCustomer = () =>
        Boolean(counterPhone && counterName && counterPhone.value.replace(/\D/g, '').length >= 7 && counterName.value.trim());

    const totals = () => {
        let subtotal = 0;
        let itemDiscount = 0;

        rows.querySelectorAll('[data-row]').forEach((row) => {
            const qty = parseFloat(row.querySelector('[data-field="quantity"]').value) || 0;
            const price = parseFloat(row.querySelector('[data-field="price"]').value) || 0;
            const discount = parseFloat(row.querySelector('[data-field="discount"]').value) || 0;
            const amount = Math.round((qty * price - discount) * 100) / 100;
            const minPrice = parseFloat(row.dataset.minPrice || 0);

            row.querySelector('[data-amount]').textContent = money(amount);
            row.querySelector('[data-below-min]')?.classList.toggle('hidden', !(minPrice > 0 && qty > 0 && amount / qty < minPrice - 0.004));
            subtotal += qty * price;
            itemDiscount += discount;
        });

        const billDiscount = parseFloat(form.querySelector('[name="bill_discount"]').value) || 0;
        const extra = parseFloat(form.querySelector('[name="extra_charges"]').value) || 0;

        return { subtotal, itemDiscount, total: Math.round((subtotal - itemDiscount - billDiscount + extra) * 100) / 100 };
    };

    const recalc = () => {
        const { subtotal, itemDiscount, total } = totals();

        if (!paymentTouched) {
            const first = payRows.querySelector('[data-row] [data-field="amount"]');

            if (first) {
                first.value = total > 0 ? total.toFixed(2) : '';
            }
        }

        let paid = 0;
        payRows.querySelectorAll('[data-field="amount"]').forEach((input) => (paid += parseFloat(input.value) || 0));

        const tendered = parseFloat(form.querySelector('[data-tendered]').value) || 0;
        const credit = Math.max(Math.round((total - paid) * 100) / 100, 0);

        form.querySelector('[data-subtotal]').textContent = money(subtotal);
        form.querySelector('[data-item-discount]').textContent = money(itemDiscount);
        form.querySelectorAll('[data-total]').forEach((element) => (element.textContent = money(total)));

        let lineCount = 0;
        let quantity = 0;
        rows.querySelectorAll('[data-row]').forEach((row) => {
            lineCount++;
            quantity += parseFloat(row.querySelector('[data-field="quantity"]').value) || 0;
        });
        const amountOf = (name) => money(parseFloat(form.querySelector(`[name="${name}"]`).value) || 0);
        form.querySelectorAll('[data-item-count]').forEach((element) => (element.textContent = `${lineCount} / ${Math.round(quantity * 1000) / 1000}`));
        form.querySelectorAll('[data-bill-discount-view]').forEach((element) => (element.textContent = amountOf('bill_discount')));
        form.querySelectorAll('[data-extra-view]').forEach((element) => (element.textContent = amountOf('extra_charges')));
        // Paid / credit boxes are optional on the screen.
        const paidBox = form.querySelector('[data-paid]');
        const creditBox = form.querySelector('[data-credit]');

        if (paidBox) {
            paidBox.textContent = money(paid);
        }

        if (creditBox) {
            creditBox.textContent = money(credit);
        }

        form.querySelector('[data-credit-row]')?.classList.toggle('text-rose-600', credit > 0);
        form.querySelector('[data-credit-warning]')?.classList.toggle('hidden', !(credit > 0 && !customerId.value && !hasNewCounterCustomer()));
        form.querySelector('[data-change]').textContent = money(Math.max(tendered - paid, 0));
        form.querySelector('[data-empty-row]').classList.toggle('hidden', rows.querySelectorAll('[data-row]').length > 0);
    };

    /**
     * One bill line per product and price label: a suit scanned from a Rs 2,500 price label stays on its own
     * line at that price, apart from the same suit at its normal price.
     */
    const addItem = (item) => {
        const lineKey = `${item.id}|${item.barcode_label_id || ''}`;
        const existing = rows.querySelector(`[data-line-key="${lineKey}"]`);

        if (existing) {
            const qty = existing.querySelector('[data-field="quantity"]');
            qty.value = (parseFloat(qty.value) || 0) + 1;
            recalc();

            return existing;
        }

        const row = addRow('sale-items', {
            product_variant_id: item.id,
            barcode_label_id: item.barcode_label_id || '',
            label: item.label,
            sku: item.sku,
            quantity: 1,
            price: item.sale_price,
            discount: 0,
        });

        row.dataset.variantId = item.id;
        row.dataset.lineKey = lineKey;
        row.dataset.minPrice = item.min_price || 0;
        row.querySelector('[data-label-text]').textContent = item.label;
        row.querySelector('[data-sku-text]').textContent = item.barcode_label_id
            ? `${item.sku} · ${t('Price label')} · in stock ${item.stock}`
            : `${item.sku} · in stock ${item.stock}`;
        row.querySelector('[data-price-label-badge]')?.classList.toggle('hidden', !item.barcode_label_id);
        setQuantityRule(row.querySelector('[data-field="quantity"]'), item.fractional);
        row.querySelector('[data-field="quantity"]').select();
        recalc();

        return row;
    };

    const setCustomer = (customer) => {
        customerId.value = customer ? customer.id : '';
        customerBox.querySelector('[data-customer-name]').textContent = customer ? `${customer.name} · ${customer.phone}` : t('Walk-in customer');
        customerBox.querySelector('[data-customer-meta]').textContent = customer
            ? [customer.tag, customer.discount_percent ? t(':percent% discount', { percent: customer.discount_percent }) : '', customer.balance ? t('balance :amount', { amount: money(customer.balance) }) : '']
                  .filter(Boolean)
                  .join(' · ')
            : '';
        customerBox.querySelector('[data-clear-customer]').classList.toggle('hidden', !customer);
        form.querySelectorAll('[data-customer-balance]').forEach((element) => {
            element.textContent = money(customer?.balance || 0);
            element.classList.toggle('text-rose-600', Boolean(customer && customer.balance > 0));
        });
        recalc();
        form.dispatchEvent(new CustomEvent('customer-changed', { detail: customer }));
    };

    attachSearch({
        input: form.querySelector('[data-product-search]'),
        results: form.querySelector('[data-product-results]'),
        url: form.querySelector('[data-product-search]').dataset.url,
        renderItem: (option, item) => {
            // A scanned bill barcode: show the bill; picking it opens the bill (reprint or return).
            if (item.type === 'sale') {
                option.innerHTML = '<span class="me-2 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-800"></span><span class="font-medium"></span><span class="ms-2 text-xs text-slate-500"></span><span class="float-end font-medium"></span>';
                option.children[0].textContent = t('Bill');
                option.children[1].textContent = item.number;
                option.children[2].textContent = item.details;
                option.children[3].textContent = money(item.total);

                return;
            }

            option.innerHTML = '<span class="font-medium"></span><span class="ms-2 text-xs text-slate-500"></span><span class="float-end"></span>';
            option.children[0].textContent = item.label;
            option.children[1].textContent = `${item.sku} · stock ${item.stock}`;
            option.children[2].textContent = money(item.sale_price);
        },
        offlineSearch: offlineProductSearch,
        onPick: (item) => {
            if (item.type !== 'sale') {
                addItem(item);

                return;
            }

            const billHasItems = form.querySelectorAll('[data-rows="sale-items"] [data-row]').length > 0;

            if (!billHasItems || confirm(t('Open bill :number? Items on this bill will be removed. Use Hold bill to keep them.', { number: item.number }))) {
                window.location.assign(item.url);
            }
        },
    });

    // Customer card: type the phone; at 11 digits (or Enter) it shows whether the customer exists.
    const counter = form.querySelector('[data-counter-customer]');
    const foundBox = form.querySelector('[data-counter-found]');
    const newBox = form.querySelector('[data-counter-new]');
    const checkingBox = form.querySelector('[data-counter-checking]');
    let checkedPhone = null;
    let lookupId = 0;

    const showState = (state, customer = null) => {
        foundBox.classList.toggle('hidden', state !== 'found');
        foundBox.classList.toggle('flex', state === 'found');
        newBox.classList.toggle('hidden', state !== 'new');
        checkingBox.classList.toggle('hidden', state !== 'checking');

        if (customer) {
            const member = customer.matched_member ? ` · ${t('Family member: :member', { member: customer.matched_member })}` : '';
            foundBox.querySelector('[data-counter-found-text]').textContent = `${t('Customer found')}: ${customer.name}${member}`;
            foundBox.querySelector('[data-counter-details]').href = counter.dataset.showUrl.replace('__ID__', customer.id);
        }
    };

    const checkPhone = async () => {
        const digits = counterPhone.value.replace(/\D/g, '');

        if (digits.length < 7 || digits === checkedPhone) {
            return;
        }

        checkedPhone = digits;
        const current = ++lookupId;
        showState('checking');

        // Offline the customer cannot be looked up: the typed phone and name go with the bill,
        // and when it is uploaded the server uses the existing customer with that phone (or adds one).
        let customers = [];

        try {
            const response = await fetch(`${counter.dataset.url}?q=${encodeURIComponent(digits)}`, { headers: { Accept: 'application/json' } });
            customers = response.ok ? await response.json() : [];
        } catch (error) {
            customers = [];
        }

        if (current !== lookupId) {
            return;
        }

        const customer = customers[0];

        if (customer) {
            setCustomer(customer);
            counterName.value = customer.name;
            counterName.readOnly = true;
            showState('found', customer);
            form.querySelector('[data-product-search]').focus();
        } else {
            counterName.readOnly = false;
            showState('new');
            counterName.focus();
            recalc();
        }
    };

    counterPhone.addEventListener('input', () => {
        const digits = counterPhone.value.replace(/\D/g, '');

        // A changed number no longer belongs to the customer found before.
        if (customerId.value && digits !== checkedPhone) {
            setCustomer(null);
            counterName.value = '';
            counterName.readOnly = false;
        }

        if (digits !== checkedPhone) {
            checkedPhone = null;
            lookupId++;
            showState('none');
        }

        if (digits.length === 11) {
            checkPhone();
        }

        recalc();
    });
    counterPhone.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            checkPhone();
        }
    });
    counterPhone.addEventListener('blur', checkPhone);
    counterName.addEventListener('input', recalc);

    form.addEventListener('customer-changed', (event) => {
        const customer = event.detail;

        if (customer && !counterPhone.value) {
            counterPhone.value = customer.phone || '';
            counterName.value = customer.name;
            counterName.readOnly = true;
            checkedPhone = (customer.phone || '').replace(/\D/g, '');
            showState('found', customer);
        }
    });

    // After a bill comes back with an error, check the typed phone again.
    setTimeout(() => {
        if (counterPhone.value && !customerId.value) {
            checkPhone();
        }
    }, 0);

    customerBox.querySelector('[data-clear-customer]').addEventListener('click', () => {
        setCustomer(null);
        counterPhone.value = '';
        counterName.value = '';
        counterName.readOnly = false;
        checkedPhone = null;
        showState('none');
        recalc();
        counterPhone.focus();
    });
    form.querySelector('[data-add-payment]').addEventListener('click', () => {
        paymentTouched = true;
        addRow('payments', { method: 'card' });
    });
    payRows.addEventListener('input', () => (paymentTouched = true));
    form.addEventListener('input', recalc);
    document.addEventListener('rows-changed', recalc);

    // A held bill or a bill sent back with errors: rebuild each line with its own quantity, price and discount.
    JSON.parse(form.dataset.items || '[]').forEach((item) => {
        const row = addItem({
            id: item.product_variant_id,
            barcode_label_id: item.barcode_label_id,
            label: item.label,
            sku: item.sku,
            stock: item.stock,
            sale_price: item.price,
            min_price: item.min_price,
            fractional: item.fractional,
        });
        row.querySelector('[data-field="quantity"]').value = item.quantity;
        row.querySelector('[data-field="price"]').value = item.price;
        row.querySelector('[data-field="discount"]').value = item.discount;
    });

    if (form.dataset.customer) {
        setCustomer(JSON.parse(form.dataset.customer));
    }

    recalc();
    form.querySelector('[data-product-search]').focus();
}

/**
 * Stitching order form: profile switch shows that profile's measurements, cloth source toggles, live total.
 */
function initOrderForm() {
    const form = document.querySelector('[data-order-form]');

    if (!form) {
        return;
    }

    const profileSelect = form.querySelector('[name="customer_profile_id"]');
    const syncProfile = () => {
        form.querySelectorAll('[data-profile-section]').forEach((section) => {
            const active = section.dataset.profileSection === profileSelect.value;
            section.classList.toggle('hidden', !active);
            section.querySelectorAll('input, select, textarea').forEach((input) => (input.disabled = !active));
        });
    };

    const sourceInputs = form.querySelectorAll('[name="cloth_source"]');
    const syncSource = () => {
        const source = form.querySelector('[name="cloth_source"]:checked')?.value;
        form.querySelector('[data-source-shop]').classList.toggle('hidden', source !== 'shop');
        form.querySelector('[data-source-customer]').classList.toggle('hidden', source !== 'customer');
    };

    const typeSelect = form.querySelector('[name="type"]');
    const syncType = () => form.querySelector('[data-parent-order]').classList.toggle('hidden', typeSelect.value !== 'alteration');

    const recalc = () => {
        let total = parseFloat(form.querySelector('[name="stitching_charges"]').value) || 0;
        form.querySelectorAll('[data-rows="extras"] [data-field="amount"]').forEach((input) => (total += parseFloat(input.value) || 0));
        const advance = parseFloat(form.querySelector('[name="advance_amount"]')?.value) || 0;
        form.querySelector('[data-order-total]').textContent = money(total);
        form.querySelector('[data-order-balance]').textContent = money(total - advance - parseFloat(form.dataset.paid || 0));
    };

    profileSelect.addEventListener('change', syncProfile);
    sourceInputs.forEach((input) => input.addEventListener('change', syncSource));
    typeSelect.addEventListener('change', syncType);
    form.addEventListener('input', recalc);
    document.addEventListener('rows-changed', recalc);

    syncProfile();
    syncSource();
    syncType();
    recalc();
}

/**
 * Order screen: customer picker for a new order.
 */
function initCustomerPicker() {
    document.querySelectorAll('[data-customer-picker]').forEach((input) => {
        attachSearch({
            input,
            results: input.parentElement.querySelector('[data-search-results]'),
            url: input.dataset.url,
            renderItem: customerOption,
            onPick: (customer) => (window.location.href = input.dataset.target.replace('__ID__', customer.id)),
        });
    });
}

/**
 * Barcode generator: pick a product, fill in its normal price as a starting point, and keep the
 * label preview in step with the name, code, price and chosen label size.
 */
function initBarcodeGenerator() {
    const form = document.querySelector('[data-barcode-generator]');

    if (!form) {
        return;
    }

    const search = form.querySelector('[data-generator-search]');
    const variantInput = form.querySelector('[data-generator-variant]');
    const productBox = form.querySelector('[data-generator-product]');
    const priceInput = form.querySelector('[data-generator-price]');
    const sizeSelect = form.querySelector('[data-generator-size]');
    const preview = document.querySelector('[data-preview-label]');

    const updatePreview = () => {
        preview.querySelector('[data-preview-price]').textContent = Math.round(parseFloat(priceInput.value) || 0).toLocaleString();
        const [width, height] = (sizeSelect.value || '50x25').split('x').map(Number);
        const scale = 4;
        preview.style.width = `${width * scale}px`;
        preview.style.height = `${height * scale}px`;
    };

    const choose = (item, keepPrice = false) => {
        variantInput.value = item.id;
        productBox.classList.remove('hidden');
        productBox.querySelector('[data-product-name]').textContent = item.label;
        productBox.querySelector('[data-product-sku]').textContent = item.sku;
        productBox.querySelector('[data-product-code]').textContent = item.code || '—';
        productBox.querySelector('[data-product-price]').textContent = money(item.normal_price ?? item.sale_price);
        productBox.querySelector('[data-product-stock]').textContent = item.stock;
        preview.querySelector('[data-preview-name]').textContent = item.label;
        preview.querySelector('[data-preview-sku]').textContent = item.sku;

        if (!keepPrice || priceInput.value === '') {
            priceInput.value = (item.normal_price ?? item.sale_price).toFixed(2);
        }

        updatePreview();
        priceInput.focus();
        priceInput.select();
    };

    attachSearch({
        input: search,
        results: form.querySelector('[data-generator-results]'),
        url: search.dataset.url,
        renderItem: (option, item) => {
            option.innerHTML = '<span class="font-medium"></span><span class="ms-2 text-xs text-slate-500"></span><span class="float-end"></span>';
            option.children[0].textContent = item.label;
            option.children[1].textContent = t(':sku · stock :stock', { sku: item.sku, stock: item.stock });
            option.children[2].textContent = money(item.normal_price ?? item.sale_price);
        },
        onPick: (item) => choose(item),
    });

    priceInput.addEventListener('input', updatePreview);
    sizeSelect.addEventListener('change', updatePreview);

    if (form.dataset.oldItem) {
        choose(JSON.parse(form.dataset.oldItem), true);
    } else {
        updatePreview();
    }
}

/**
 * List rows with data-href open their record when clicked anywhere on the row. Links, buttons and
 * form fields inside the row keep working; Ctrl/⌘-click or the middle button opens a new tab;
 * selecting text does not navigate.
 */
function initClickableRows() {
    const isInteractive = (target) => target.closest('a, button, input, select, textarea, label, form, [data-no-row-click]');

    const open = (event, row) => {
        if (window.getSelection()?.toString()) {
            return;
        }

        if (event.ctrlKey || event.metaKey || event.button === 1) {
            window.open(row.dataset.href, '_blank');
        } else {
            window.location.href = row.dataset.href;
        }
    };

    document.addEventListener('click', (event) => {
        const row = event.target.closest('tr[data-href]');

        if (row && !isInteractive(event.target)) {
            open(event, row);
        }
    });

    document.addEventListener('auxclick', (event) => {
        const row = event.target.closest('tr[data-href]');

        if (row && event.button === 1 && !isInteractive(event.target)) {
            event.preventDefault();
            open(event, row);
        }
    });
}

/**
 * After saving a new product: "Add another product" (or Esc) closes the box and starts the next one.
 */
function initProductCreatedDialog() {
    const dialog = document.querySelector('[data-product-created]');

    if (!dialog) {
        return;
    }

    const startNext = () => {
        dialog.remove();
        const first = document.querySelector('[name="barcode"], [name="name"]');
        first?.focus();
    };

    dialog.querySelector('[data-create-another]').addEventListener('click', startNext);
    dialog.querySelector('[data-create-another]').focus();
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.contains(dialog)) {
            startNext();
        }
    });
}

/**
 * Customer photo: show the chosen picture straight away; ticking "Remove photo" hides it.
 */
function initPhotoPreview() {
    document.querySelectorAll('[data-photo-field]').forEach((field) => {
        const input = field.querySelector('input[type="file"]');
        const preview = field.querySelector('[data-photo-preview]');
        const placeholder = field.querySelector('[data-photo-placeholder]');
        const remove = field.querySelector('[data-photo-remove]');
        const originalSrc = preview.getAttribute('src') || '';

        const show = (src) => {
            preview.classList.toggle('hidden', !src);
            placeholder.classList.toggle('hidden', Boolean(src));

            if (src) {
                preview.src = src;
            }
        };

        input.addEventListener('change', () => {
            const file = input.files[0];

            if (remove) {
                remove.checked = false;
            }

            show(file ? URL.createObjectURL(file) : originalSrc);
        });

        remove?.addEventListener('change', () => show(remove.checked ? '' : originalSrc));
    });
}

/**
 * Full-screen tile menu (Menu button in the top bar or on the counter screen) and the clock in the status bar.
 */
function initMenu() {
    const menu = document.querySelector('[data-menu]');

    if (menu) {
        // While the menu is open the page behind it does not scroll, so its scrollbar is hidden.
        const open = () => {
            document.documentElement.classList.add('overflow-hidden');
            menu.classList.remove('hidden');
            menu.classList.add('flex');
            menu.querySelector('[data-menu-close]').focus();
        };
        const close = () => {
            document.documentElement.classList.remove('overflow-hidden');
            menu.classList.add('hidden');
            menu.classList.remove('flex');
        };

        document.addEventListener('click', (event) => {
            if (event.target.closest('[data-menu-open]')) {
                open();
            } else if (event.target.closest('[data-menu-close]') || event.target === menu) {
                close();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !menu.classList.contains('hidden')) {
                close();
            }
        });
    }

    const clock = document.querySelector('[data-clock]');

    if (clock) {
        const tick = () => (clock.textContent = new Date().toLocaleTimeString(document.documentElement.lang || undefined, { hour: '2-digit', minute: '2-digit' }));
        tick();
        setInterval(tick, 15000);
    }
}

/**
 * Counter screen helpers: F4 jumps to the customer search, F8 to the payment amount, the Held bills
 * pop-up, Clear items and New bill. The bill, customer and payment work as on the normal sale form.
 */
function initPosScreen() {
    const form = document.querySelector('[data-sale-form]');

    if (!form) {
        return;
    }

    const rows = form.querySelector('[data-rows="sale-items"]');
    const productSearch = form.querySelector('[data-product-search]');
    const hasItems = () => rows.querySelectorAll('[data-row]').length > 0;
    const openModal = () => form.querySelector('.pos-modal.is-open');

    const focusField = (selector) => {
        const field = form.querySelector(selector);
        field?.focus();
        field?.select?.();
    };

    const closeModal = () => {
        openModal()?.classList.remove('is-open');
        productSearch.focus();
    };

    form.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-modal-open]');

        if (opener) {
            form.querySelector(`[data-modal="${opener.dataset.modalOpen}"]`)?.classList.add('is-open');

            return;
        }

        if (event.target.closest('[data-modal-close]') || event.target.classList.contains('pos-modal')) {
            closeModal();
        }
    });

    form.addEventListener('customer-changed', () => productSearch.focus());

    form.querySelector('[data-clear-items]')?.addEventListener('click', () => {
        if (hasItems() && confirm(t('Remove all items from this bill?'))) {
            rows.innerHTML = '';
            document.dispatchEvent(new CustomEvent('rows-changed'));
        }

        productSearch.focus();
    });

    // Opening the customer's page leaves this screen, so items on the bill would be lost: ask first.
    form.querySelector('[data-counter-details]')?.addEventListener('click', (event) => {
        if (hasItems() && !confirm(t('Leave this bill to see the customer? Items on this bill will be removed. Use Hold bill to keep them.'))) {
            event.preventDefault();
        }
    });

    form.querySelector('[data-new-bill]')?.addEventListener('click', (event) => {
        if (hasItems() && !confirm(t('Start a new bill? Items on this bill will be removed.'))) {
            event.preventDefault();
        }
    });

    // Enter in a quantity or price box goes back to the scan box instead of submitting; in the payment box it completes the sale.
    form.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.target.tagName !== 'INPUT' || event.target.matches('[data-product-search], [data-counter-phone]')) {
            return;
        }

        event.preventDefault();

        if (event.target.closest('[data-payment-panel]')) {
            form.querySelector('[data-complete-sale]').click();
        } else {
            productSearch.focus();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (document.querySelector('[data-sale-done].flex, [data-menu].flex')) {
            return;
        }

        if (event.key === 'F4') {
            event.preventDefault();
            focusField('[data-counter-phone]');
        } else if (event.key === 'F8') {
            event.preventDefault();
            focusField('[data-rows="payments"] [data-field="amount"]');
        } else if (event.key === 'Escape' && openModal()) {
            closeModal();
        }
    });
}
/**
 * After a completed sale the counter returns to a new bill: the 80mm receipt prints from a hidden frame,
 * then a box offers to open that sale or close and carry on.
 */
function initSaleDonePrint() {
    const dialog = document.querySelector('[data-sale-done]');

    if (!dialog) {
        return;
    }

    const productSearch = document.querySelector('[data-product-search]');
    let frame;

    const open = () => {
        dialog.classList.remove('hidden');
        dialog.classList.add('flex');
        dialog.querySelector('[data-sale-done-close]').focus();
    };

    const close = () => {
        dialog.classList.add('hidden');
        dialog.classList.remove('flex');
        productSearch?.focus();
    };

    const print = () => {
        frame?.remove();
        frame = document.createElement('iframe');
        frame.title = 'receipt';
        frame.style.cssText = 'position:fixed;inset-inline-end:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
        frame.addEventListener('load', () => {
            try {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            } catch (error) {
                window.open(dialog.dataset.receiptUrl, '_blank');
            }

            // print() waits for the dialog in most browsers; the delay covers the ones that return at once.
            setTimeout(open, 300);
        });
        frame.src = dialog.dataset.receiptUrl;
        document.body.appendChild(frame);
    };

    dialog.querySelector('[data-sale-done-close]').addEventListener('click', close);
    dialog.querySelector('[data-sale-done-print]').addEventListener('click', () => {
        close();
        print();
    });
    dialog.addEventListener('click', (event) => event.target === dialog && close());
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !dialog.classList.contains('hidden')) {
            close();
        }
    });

    print();
}

document.addEventListener('DOMContentLoaded', () => {
    initMenu();
    initPhotoPreview();
    initBarcodeGenerator();
    initProductCreatedDialog();
    initClickableRows();

    // Installable app + offline sale screen (see public/sw.js).
    if ('serviceWorker' in navigator && document.querySelector('link[rel="manifest"]')) {
        navigator.serviceWorker.register('/sw.js').catch(() => null);
    }

    initInstallButton();
    initIdleLogout();
    initConfirmations();
    initRepeatableRows();
    initPurchaseForm();
    initVariantPicker();
    initGlobalCustomerSearch();
    initPhoneCheck();
    initFamilyRows();
    initMeasurementUnits();
    initSaleForm();
    initSaleDonePrint();
    initPosScreen();
    initOrderForm();
    initCustomerPicker();
});
