// ═══════════════════════════════════════════════════════════
//  MARKETING — Meta Pixel + Google tag + aviso de cookies + origen de la visita
// ═══════════════════════════════════════════════════════════
// Solo se activa en tiendas que configuraron Meta y/o Google en Dashboard → Marketing:
// el <head> de la tienda (components/marketing/head) deja window.__tribioMarketing con
// `meta` (pixelId) y/o `google` (measurementId, adsId, adsLabel). Sin eso,
// window.TribioTrack existe igual pero no hace nada, así el resto del código (carrito,
// drawer, confirmación de pedido) puede llamarlo sin preguntar.
//
// Consentimiento (Ley 29733): ninguna etiqueta (ni la de Meta ni la de Google) se carga
// hasta que el comprador pulsa "Aceptar". Lo que pasa antes (ver un producto, agregar
// al carrito) queda en cola y se envía al aceptar; si rechaza, se descarta. La decisión
// se guarda por tienda (la cookie lleva el path de la tienda) y el servidor la lee en el
// checkout para decidir si la compra también se reporta por Conversions API.

const config = window.__tribioMarketing || null;
const meta = config?.meta || null;
const google = config?.google || null;
const CONSENT_COOKIE = 'tribio_consent';
const ATTR_COOKIE = 'tribio_attr';

// Meta event name → Google Analytics 4 ecommerce event.
const GA4_EVENTS = { ViewContent: 'view_item', AddToCart: 'add_to_cart', InitiateCheckout: 'begin_checkout', Purchase: 'purchase' };

let state = 'off'; // off | pending | granted | denied
const queue = [];

function readCookie(name) {
    const match = document.cookie.split('; ').find(row => row.startsWith(name + '='));
    return match ? decodeURIComponent(match.split('=').slice(1).join('=')) : null;
}

function writeCookie(name, value, days) {
    const path = config?.cookiePath || '/';
    const secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${name}=${encodeURIComponent(value)}; max-age=${days * 86400}; path=${path}; SameSite=Lax${secure}`;
}

function loadMeta() {
    if (!meta || window.fbq) return;
    /* eslint-disable */
    !function (f, b, e, v, n, t, s) {
        if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments) };
        if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = []; t = b.createElement(e); t.async = !0;
        t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s)
    }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
    /* eslint-enable */
    window.fbq('init', meta.pixelId);
    window.fbq('track', 'PageView');
}

function loadGoogle() {
    if (!google || window.gtag) return;
    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(google.measurementId || google.adsId)}`;
    document.head.appendChild(script);

    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    // Only ever loaded after "Aceptar", so consent is granted by definition.
    window.gtag('consent', 'default', { ad_storage: 'granted', analytics_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted' });
    window.gtag('js', new Date());
    if (google.measurementId) window.gtag('config', google.measurementId);
    if (google.adsId) window.gtag('config', google.adsId);
}

// GA4 wants an `items` array; `id` + google_business_vertical also feed Google Ads
// dynamic remarketing (they must match the Merchant Center item ids).
function googleParams(data) {
    const contents = data.contents || (data.content_ids || []).map(id => ({ id, quantity: 1 }));
    const single = contents.length === 1;
    return {
        currency: data.currency,
        value: data.value,
        ...(data.order_id ? { transaction_id: data.order_id } : {}),
        items: contents.map(c => {
            const quantity = c.quantity || 1;
            const price = c.item_price !== undefined ? c.item_price : (single && data.value !== undefined ? data.value / quantity : undefined);
            return {
                item_id: String(c.id), id: String(c.id), google_business_vertical: 'retail', quantity,
                ...(price !== undefined ? { price } : {}),
                ...(single && data.content_name ? { item_name: data.content_name } : {}),
            };
        }),
    };
}

function send(name, data, eventId) {
    if (meta && window.fbq) {
        if (eventId) {
            window.fbq('track', name, data, { eventID: eventId });
        } else {
            window.fbq('track', name, data);
        }
    }
    if (google && window.gtag) {
        if (GA4_EVENTS[name]) {
            window.gtag('event', GA4_EVENTS[name], googleParams(data));
        }
        if (name === 'Purchase' && google.adsId && google.adsLabel) {
            window.gtag('event', 'conversion', {
                send_to: `${google.adsId}/${google.adsLabel}`,
                value: data.value, currency: data.currency, transaction_id: data.order_id || eventId,
            });
        }
    }
}

// The catalog's item id: `{productId}` or `{productId}-{variantId}` (see
// App\Services\Marketing\Meta\CatalogItemMapper). Not the cart's cartKey: a
// made-to-order line's key carries a hash of the buyer's answers.
function adId(item) {
    return item.variant_id ? `${item.id}-${item.variant_id}` : `${item.id}`;
}

function money(value) {
    return Math.round((parseFloat(value) || 0) * 100) / 100;
}

window.TribioTrack = {
    track(name, data = {}, eventId = null) {
        if (state === 'granted') {
            send(name, data, eventId);
        } else if (state === 'pending') {
            queue.push([name, data, eventId]);
        }
    },

    addToCart(id, price, variantId = null, name = null) {
        const contentId = adId({ id, variant_id: variantId });
        this.track('AddToCart', {
            content_ids: [contentId], content_type: 'product',
            contents: [{ id: contentId, quantity: 1 }],
            ...(name ? { content_name: String(name).slice(0, 150) } : {}),
            value: money(price), currency: config?.currency || 'PEN',
        });
    },

    initiateCheckout() {
        const items = window.TribioCart?.items || [];
        if (!items.length) return;
        this.track('InitiateCheckout', {
            content_ids: items.map(adId), content_type: 'product',
            contents: items.map(i => ({ id: adId(i), quantity: i.quantity, item_price: money(i.price) })),
            num_items: items.reduce((n, i) => n + (parseInt(i.quantity, 10) || 0), 0),
            value: money(items.reduce((sum, i) => sum + (parseFloat(i.price) || 0) * (parseInt(i.quantity, 10) || 0), 0)),
            currency: config?.currency || 'PEN',
        });
    },

    // `tracking` comes from /api/pedido-estado (StoreController::orderStatus): its
    // event_id is the one the server uses for the same sale, so Meta counts it once,
    // and its order_id is Google's transaction_id (same deduplication on Google's side).
    purchase(tracking) {
        if (!tracking || !tracking.event_id) return;
        const { event_id: eventId, ...data } = tracking;
        this.track('Purchase', data, eventId);
    },
};

function grant() {
    writeCookie(CONSENT_COOKIE, 'granted', 365);
    state = 'granted';
    loadMeta();
    loadGoogle();
    queue.splice(0).forEach(args => send(...args));
}

function deny() {
    writeCookie(CONSENT_COOKIE, 'denied', 180);
    state = 'denied';
    queue.length = 0;
}

// Last campaign visit wins (30 days): an order placed after arriving from an ad is
// labeled with that ad's utm_* / fbclid / gclid (PendingCheckout::materialize →
// order_attributions).
function captureAttribution() {
    const params = new URLSearchParams(window.location.search);
    const keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'fbclid', 'gclid'];
    const found = {};
    keys.forEach(k => {
        const v = params.get(k);
        if (v) found[k] = v.slice(0, 255);
    });
    if (!Object.keys(found).length) return;
    found.landing_path = window.location.pathname.slice(0, 255);
    found.ts = Math.floor(Date.now() / 1000);
    writeCookie(ATTR_COOKIE, JSON.stringify(found), 30);
}

function showBanner() {
    const en = config.lang === 'en';
    const text = en
        ? 'We use cookies to show you offers you may like and to measure our ads.'
        : 'Usamos cookies para mostrarte ofertas que te interesen y medir nuestros anuncios.';

    const style = document.createElement('style');
    style.textContent = `
.tb-consent{position:fixed;left:16px;bottom:16px;z-index:998;max-width:380px;box-sizing:border-box;padding:16px;border-radius:14px;
  background:#fff;color:#1f2937;border:1px solid rgba(15,23,42,.08);box-shadow:0 10px 30px rgba(15,23,42,.14);
  font-family:inherit;font-size:14px;line-height:1.45;animation:tb-consent-in .25s ease-out}
.tb-consent p{margin:0 0 12px}
.tb-consent a{color:inherit;text-decoration:underline;text-underline-offset:2px}
.tb-consent__actions{display:flex;gap:8px}
.tb-consent__btn{flex:1;min-height:40px;padding:8px 14px;border-radius:10px;font:inherit;font-weight:600;cursor:pointer;border:1px solid #1f2937}
.tb-consent__btn--ghost{background:#fff;color:#1f2937}
.tb-consent__btn--solid{background:#1f2937;color:#fff}
.tb-consent__btn:focus-visible{outline:2px solid #1f2937;outline-offset:2px}
@media (max-width:640px){.tb-consent{left:12px;right:12px;bottom:12px;max-width:none}}
@media (prefers-reduced-motion:reduce){.tb-consent{animation:none}}
@keyframes tb-consent-in{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}`;
    document.head.appendChild(style);

    const box = document.createElement('div');
    box.className = 'tb-consent';
    box.setAttribute('role', 'region');
    box.setAttribute('aria-label', en ? 'Cookie notice' : 'Aviso de cookies');

    const p = document.createElement('p');
    p.textContent = text + ' ';
    if (config.privacyUrl) {
        const link = document.createElement('a');
        link.href = config.privacyUrl;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = en ? 'Learn more' : 'Más información';
        p.appendChild(link);
    }

    const actions = document.createElement('div');
    actions.className = 'tb-consent__actions';
    const button = (label, cls, onClick) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = `tb-consent__btn ${cls}`;
        b.textContent = label;
        b.addEventListener('click', () => { onClick(); box.remove(); });
        return b;
    };
    actions.append(
        button(en ? 'Decline' : 'Rechazar', 'tb-consent__btn--ghost', deny),
        button(en ? 'Accept' : 'Aceptar', 'tb-consent__btn--solid', grant),
    );

    box.append(p, actions);
    document.body.appendChild(box);
}

if (config && (meta || google)) {
    captureAttribution();
    const choice = readCookie(CONSENT_COOKIE);
    if (choice === 'granted') {
        state = 'granted';
        loadMeta();
        loadGoogle();
    } else if (choice === 'denied') {
        state = 'denied';
    } else {
        state = 'pending';
        if (document.body) {
            showBanner();
        } else {
            document.addEventListener('DOMContentLoaded', showBanner);
        }
    }
    if (config.viewContent) {
        window.TribioTrack.track('ViewContent', config.viewContent);
    }
}
