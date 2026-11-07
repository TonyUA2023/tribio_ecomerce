/*
 * Tribio Caja — selling without connection (ERP Fase 4d).
 *
 * Sales that could not reach the server wait in IndexedDB ("tribio-pos" → "queue") with
 * their client UUID, so sending one twice never sells twice (the server answers 200
 * duplicate). The product catalog is kept in "kv" to search and scan offline. Nothing
 * else is stored on the device.
 */
(function () {
    const DB = 'tribio-pos';
    let dbPromise = null;

    function open() {
        if (!('indexedDB' in window)) return Promise.reject(new Error('Sin IndexedDB'));
        dbPromise = dbPromise || new Promise((resolve, reject) => {
            const request = indexedDB.open(DB, 1);
            request.onupgradeneeded = () => {
                const db = request.result;
                if (!db.objectStoreNames.contains('kv')) db.createObjectStore('kv');
                if (!db.objectStoreNames.contains('queue')) db.createObjectStore('queue', { keyPath: 'uuid' });
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
        return dbPromise;
    }

    function tx(store, mode, work) {
        return open().then(db => new Promise((resolve, reject) => {
            const t = db.transaction(store, mode);
            const result = work(t.objectStore(store));
            t.oncomplete = () => resolve(result && 'result' in result ? result.result : result);
            t.onerror = () => reject(t.error);
        }));
    }

    const api = {
        async setCatalog(storeId, products) {
            return tx('kv', 'readwrite', s => s.put({ products, at: Date.now() }, 'catalog:' + storeId)).catch(() => null);
        },
        /** How old the saved catalog is, in ms (null when there is none). */
        async catalogAge(storeId) {
            const row = await tx('kv', 'readonly', s => s.get('catalog:' + storeId)).catch(() => null);
            return row ? Date.now() - row.at : null;
        },
        async catalog(storeId) {
            const row = await tx('kv', 'readonly', s => s.get('catalog:' + storeId)).catch(() => null);
            return row ? row.products : [];
        },
        /** Name contains the text, or a SKU / variant SKU / packaging barcode equals it. */
        async searchLocal(storeId, text) {
            const products = await api.catalog(storeId);
            const q = (text || '').trim().toLowerCase();
            if (!q) return { exact: null, products: products.slice(0, 20), size: products.length };
            let exact = null;
            for (const p of products) {
                const unit = p.units.find(u => u.barcode && u.barcode.toLowerCase() === q);
                const variant = p.variants.find(v => v.sku && v.sku.toLowerCase() === q);
                if (unit) { exact = { product_id: p.id, variant_id: null, unit_id: unit.id }; break; }
                if (variant) { exact = { product_id: p.id, variant_id: variant.id, unit_id: null }; break; }
                if (p.sku && p.sku.toLowerCase() === q) { exact = { product_id: p.id, variant_id: null, unit_id: null }; break; }
            }
            const found = products.filter(p => p.name.toLowerCase().includes(q) || (p.sku || '').toLowerCase().startsWith(q));
            if (exact && !found.some(p => p.id === exact.product_id)) found.unshift(products.find(p => p.id === exact.product_id));
            return { exact, products: found.slice(0, 20), size: products.length };
        },
        async enqueue(item) {
            return tx('queue', 'readwrite', s => s.put({ ...item, queued_at: Date.now(), error: null }));
        },
        async queue() {
            return tx('queue', 'readonly', s => s.getAll()).then(r => r || []).catch(() => []);
        },
        async remove(uuid) {
            return tx('queue', 'readwrite', s => s.delete(uuid));
        },
        async markError(uuid, error) {
            const items = await api.queue();
            const item = items.find(i => i.uuid === uuid);
            if (item) await tx('queue', 'readwrite', s => s.put({ ...item, error }));
        },
        /**
         * Sends what is waiting, oldest first, with a fresh CSRF token. Stops at the first
         * network failure; a refusal (422) stays in the queue with its reason.
         *
         * @return {Promise<{sent: number, failed: number}>}
         */
        async sync(tokenUrl) {
            const items = (await api.queue()).filter(i => !i.error).sort((a, b) => a.queued_at - b.queued_at);
            if (!items.length || !navigator.onLine) return { sent: 0, failed: 0 };
            let token;
            try {
                const response = await fetch(tokenUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) return { sent: 0, failed: 0 };
                token = (await response.json()).token;
            } catch (e) { return { sent: 0, failed: 0 }; }

            let sent = 0, failed = 0;
            for (const item of items) {
                let response;
                try {
                    response = await fetch(item.url, { method: 'POST', credentials: 'same-origin', body: JSON.stringify(item.body),
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token } });
                } catch (e) { break; }
                if (response.ok) { await api.remove(item.uuid); sent++; continue; }
                if (response.status === 419 || response.status === 423 || response.status >= 500) break; // try later
                const data = await response.json().catch(() => ({}));
                await api.markError(item.uuid, data.errors ? Object.values(data.errors)[0][0] : (data.message || 'El servidor no aceptó la venta.'));
                failed++;
            }
            return { sent, failed };
        },
        /** A provisional ticket printed on the spot; the real number comes when the sale is sent. */
        printProvisional(storeName, item) {
            const money = v => 'S/ ' + Number(v || 0).toFixed(2);
            const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
            const rows = item.lines.map(l => `<tr><td>${esc(l.quantity)} × ${esc(l.name)}</td><td style="text-align:right">${money(l.subtotal)}</td></tr>`).join('');
            const html = `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Ticket provisional</title>
                <style>body{font:12px ui-monospace,monospace;width:80mm;margin:0 auto;padding:4mm}table{width:100%;border-collapse:collapse}hr{border:0;border-top:1px dashed #777}
                @media print{@page{size:80mm auto;margin:0}}</style></head><body>
                <div style="text-align:center"><strong>${esc(storeName)}</strong><br>TICKET PROVISIONAL<br>Ref. ${esc(item.uuid.slice(0, 8).toUpperCase())}</div><hr>
                <div>${esc(new Date(item.offline_at).toLocaleString('es-PE'))}</div><hr><table>${rows}</table><hr>
                <table><tr><td><strong>TOTAL</strong></td><td style="text-align:right"><strong>${money(item.total)}</strong></td></tr>
                ${item.change > 0 ? `<tr><td>Vuelto</td><td style="text-align:right">${money(item.change)}</td></tr>` : ''}</table><hr>
                <div style="text-align:center;font-size:10px">Venta sin conexión: se registra al volver internet.</div>
                <script>window.onload=()=>window.print()<\/script></body></html>`;
            const w = window.open('', '_blank');
            if (w) { w.document.write(html); w.document.close(); }
        },
    };

    window.TribioPosOffline = api;
})();
