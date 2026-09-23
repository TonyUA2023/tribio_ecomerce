import './bootstrap';
import './marketing';
import './dashboard-images';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

// ═══════════════════════════════════════════════════════════
//  TRIBIO APP — Main JS
//  Plugins: Alpine.js (via CDN in layouts), Swiper.js (via CDN en vistas que lo requieran)
// ═══════════════════════════════════════════════════════════

// ── Confirmación de pedido al volver de una pasarela (o del flujo por WhatsApp) ──
// No hay página de confirmación propia: siempre se vuelve a la tienda con
// ?pedido=<order_number> y se le pregunta al backend el estado real antes de
// decir nada — así nunca se muestra "gracias por tu compra" sin haber pagado.
(async () => {
    const params = new URLSearchParams(window.location.search);
    const orderNumber = params.get('pedido');
    if (!orderNumber) return;

    params.delete('pedido');
    const cleanQuery = params.toString();
    window.history.replaceState(null, '', window.location.pathname + (cleanQuery ? '?' + cleanQuery : ''));

    try {
        const res = await fetch(`/api/pedido-estado/${encodeURIComponent(orderNumber)}`, { headers: { Accept: 'application/json' } });
        if (!res.ok) return;
        const order = await res.json();
        const isGatewayOrder = ['mercadopago', 'flow', 'paypal'].includes(order.payment_method);
        // Only present for a paid order of a store with a Meta Pixel (see orderStatus()).
        window.TribioTrack?.purchase(order.tracking);

        let config;
        if (order.payment_status === 'partial') {
            // Made-to-order deposit charged: the order is confirmed, the rest is paid later.
            window.TribioCart?.clear();
            config = { icon: 'success', title: '¡Adelanto recibido!', text: `Tu pedido ${order.order_number} ya está confirmado y entra a producción. Te avisaremos cuando esté listo para pagar el saldo.` };
        } else if (order.payment_status === 'paid') {
            // Whatsapp/card orders already cleared the cart synchronously at checkout;
            // for a gateway redirect this is the first moment payment is actually
            // confirmed, so it's the first safe moment to clear it here too.
            window.TribioCart?.clear();
            config = { icon: 'success', title: '¡Pago confirmado!', text: `Tu pedido ${order.order_number} fue pagado y ya está en preparación.` };
        } else if (isGatewayOrder && order.payment_status === 'failed') {
            config = { icon: 'error', title: 'El pago no se completó', text: `Tu pedido ${order.order_number} sigue disponible. Contacta a la tienda para coordinar otra forma de pago.` };
        } else if (isGatewayOrder) {
            config = { icon: 'info', title: 'Pedido registrado', text: `Aún no hemos confirmado el pago de tu pedido ${order.order_number}. Si ya pagaste, espera la verificación antes de intentar de nuevo.` };
        } else {
            config = { icon: 'success', title: '¡Pedido registrado!', text: `Tu pedido ${order.order_number} fue registrado con éxito. Nos pondremos en contacto por WhatsApp.` };
        }
        Swal.fire({ ...config, confirmButtonColor: '#1A1A1A' });
    } catch (e) {
        // El pedido ya existe de todas formas; si esto falla, el cliente puede
        // revisarlo desde "Mis Pedidos" — no hay nada más que hacer aquí.
    }
})();

// ── Intersection Observer para animaciones al hacer scroll ──
// El opacity:0 inicial de [data-animate] ahora vive en CSS (bajo html.js-anim, ver
// app.css), no aquí: cualquier ocultamiento hecho por JS llega después de que el HTML
// ya se parseó y pudo pintarse una vez a opacidad normal, así el script corra donde
// corra — eso es justo lo que causaba el parpadeo ("carga dos veces"): se veía la
// tarjeta ya visible, recién ahí saltaba a invisible, y luego reaparecía animada. El
// script inline en el <head> del layout marca html.js-anim antes de ese primer pintado,
// así que aquí solo queda observar y revelar.
(() => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-slide-up');
                entry.target.style.opacity = '1';
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

    document.querySelectorAll('[data-animate]').forEach(el => observer.observe(el));
})();

document.addEventListener('DOMContentLoaded', () => {

    // ── Color picker sync ──
    document.querySelectorAll('input[type="color"][data-sync]').forEach(picker => {
        const target = document.querySelector(picker.dataset.sync);
        if (!target) return;

        const updateColor = () => {
            target.value = picker.value;
            // Update CSS variable for live preview
            const varName = picker.dataset.cssVar;
            if (varName) document.documentElement.style.setProperty(varName, picker.value);
        };

        picker.addEventListener('input', updateColor);
        target.addEventListener('input', () => picker.value = target.value);
    });

    // ── Auto-generate slug from name input ──
    const nameInput = document.getElementById('store_name');
    const slugInput = document.getElementById('store_slug');

    if (nameInput && slugInput && !slugInput.dataset.locked) {
        nameInput.addEventListener('input', () => {
            slugInput.value = nameInput.value
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9\s-]/g, '')
                .trim()
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .slice(0, 60);
        });

        slugInput.addEventListener('focus', () => {
            slugInput.dataset.locked = 'true';
        });
    }

    // ── Image preview on file input ──
    document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
        const preview = document.getElementById(input.dataset.preview);
        if (!preview) return;

        input.addEventListener('change', () => {
            const file = input.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        });
    });
});

// 🛒 Cart system (Tribio store public pages) 🛒
window.TribioCart = {
    items: JSON.parse(localStorage.getItem('tribio_cart') || '[]').map(i => {
        if (!i.cartKey) {
            i.cartKey = i.variant_id ? `${i.id}-${i.variant_id}` : `${i.id}`;
        }
        return i;
    }),

    save() {
        localStorage.setItem('tribio_cart', JSON.stringify(this.items));
        this.updateUI();
        window.dispatchEvent(new CustomEvent('cart-updated', { detail: JSON.parse(JSON.stringify(this.items)) }));
    },

    // `originEl` is the clicked "add to cart" element, used as the start point for the
    // fly-to-cart animation. Leave it unset (not even `null`) to get the default
    // fly-from-cart-icon fallback; pass `null` explicitly to skip the animation for
    // this call (used when a single click adds more than one unit in a loop, so only
    // one flight plays instead of one per unit).
    add(id, name, price, image = '', variant = null, originEl) {
        const variantId = variant && variant.id ? variant.id : null;
        const variantTitle = variant && variant.title ? variant.title : null;
        const variantAttributes = variant && variant.attributes ? variant.attributes : null;
        const cartKey = variantId ? `${id}-${variantId}` : `${id}`;

        const existing = this.items.find(i => (i.cartKey === cartKey || (!i.cartKey && i.id === id && !variantId)));
        if (existing) {
            existing.quantity++;
            existing.cartKey = cartKey;
        } else {
            this.items.push({
                id,
                cartKey,
                name,
                price: parseFloat(price) || 0,
                image,
                quantity: 1,
                variant_id: variantId,
                variant_title: variantTitle,
                variant_attributes: variantAttributes
            });
        }
        this.save();
        window.TribioTrack?.addToCart(id, price, variantId);
        if (originEl !== null) {
            this.flyToCart(image, originEl);
        }
    },

    // Made-to-order line: every distinct set of answers (text, logo, sizes…) is its own
    // line. `fixedQuantity` lines (quantity decided by a size grid) can't be +/- edited.
    addCustom({ id, name, price, image = '', variant = null, quantity = 1, customization = {}, summary = [], fixedQuantity = false }, originEl) {
        const variantId = variant && variant.id ? variant.id : null;
        let hash = 0;
        for (const ch of JSON.stringify([variantId, customization])) hash = (Math.imul(hash, 31) + ch.charCodeAt(0)) | 0;
        const cartKey = `${id}-c${(hash >>> 0).toString(36)}`;

        const existing = this.items.find(i => i.cartKey === cartKey);
        if (existing) {
            if (!fixedQuantity) existing.quantity += quantity;
        } else {
            this.items.push({
                id, cartKey, name, price: parseFloat(price) || 0, image, quantity,
                variant_id: variantId,
                variant_title: variant && variant.title ? variant.title : null,
                variant_attributes: variant && variant.attributes ? variant.attributes : null,
                customization, customization_summary: summary,
                fixed_quantity: fixedQuantity, made_to_order: true,
            });
        }
        this.save();
        window.TribioTrack?.addToCart(id, price, variantId);
        if (originEl !== null) {
            this.flyToCart(image, originEl);
        }
    },

    remove(cartKeyOrId) {
        this.items = this.items.filter(i => i.cartKey !== String(cartKeyOrId) && i.id !== cartKeyOrId);
        this.save();
    },

    updateQuantity(cartKeyOrId, qty) {
        const item = this.items.find(i => i.cartKey === String(cartKeyOrId) || i.id === cartKeyOrId);
        if (item) {
            if (qty <= 0) {
                this.remove(cartKeyOrId);
            } else if (item.fixed_quantity) {
                return;
            } else {
                item.quantity = qty;
                this.save();
            }
        }
    },

    clear() {
        this.items = [];
        this.save();
    },

    total() {
        return this.items.reduce((sum, i) => sum + (i.price * i.quantity), 0);
    },

    count() {
        return this.items.reduce((sum, i) => sum + i.quantity, 0);
    },

    updateUI() {
        // Update cart badge count
        document.querySelectorAll('[data-cart-count]').forEach(el => {
            el.textContent = this.count();
            el.style.display = this.count() > 0 ? 'flex' : 'none';
        });

        // Update cart total
        document.querySelectorAll('[data-cart-total]').forEach(el => {
            el.textContent = 'S/. ' + this.total().toFixed(2);
        });
        
        window.dispatchEvent(new CustomEvent('cart-updated', { detail: JSON.parse(JSON.stringify(this.items)) }));
    },

    // "Fly to cart": shrinks a clone of the product thumbnail from the clicked button
    // into the cart icon, instead of a toast — the old toast sat over the cart button
    // itself and was too intrusive for something that happens on every single add.
    flyToCart(imageUrl, originEl) {
        const cartIcon = document.querySelector('[data-cart-count]');
        if (!cartIcon) return;

        const source = (originEl && originEl.getBoundingClientRect) ? originEl : cartIcon;
        const originRect = source.getBoundingClientRect();
        const cartRect = cartIcon.getBoundingClientRect();
        const size = 56;

        const flier = document.createElement('div');
        flier.className = 'tribio-fly-to-cart';
        Object.assign(flier.style, {
            left: `${originRect.left + originRect.width / 2 - size / 2}px`,
            top: `${originRect.top + originRect.height / 2 - size / 2}px`,
            width: `${size}px`,
            height: `${size}px`,
        });
        if (imageUrl) flier.style.backgroundImage = `url("${imageUrl}")`;
        flier.style.setProperty('--fly-dx', `${(cartRect.left + cartRect.width / 2) - (originRect.left + originRect.width / 2)}px`);
        flier.style.setProperty('--fly-dy', `${(cartRect.top + cartRect.height / 2) - (originRect.top + originRect.height / 2)}px`);

        document.body.appendChild(flier);
        void flier.offsetWidth; // force layout so the animation class reliably transitions
        flier.classList.add('tribio-fly-to-cart-active');

        flier.addEventListener('animationend', () => {
            flier.remove();
            cartIcon.classList.add('tribio-cart-bump');
            setTimeout(() => cartIcon.classList.remove('tribio-cart-bump'), 300);
        }, { once: true });
    },

    async checkout(storeSlug, customerData) {
        if (this.items.length === 0) {
            alert('El carrito está vacío');
            return false;
        }

        const token = document.querySelector('meta[name="csrf-token"]')?.content || window.tribioCsrfToken || customerData._token || '';
        const payload = {
            _token: token,
            items: this.items,
            customer_name: customerData.customer_name || customerData.name || '',
            customer_email: customerData.customer_email || customerData.email || '',
            customer_phone: customerData.customer_phone || customerData.phone || '',
            customer_address: customerData.customer_address || customerData.address || '',
            customer_country: customerData.customer_country || customerData.country || 'PE',
            customer_state: customerData.customer_state || customerData.state || '',
            customer_city: customerData.customer_city || customerData.city || '',
            customer_zipcode: customerData.customer_zipcode || customerData.zipcode || '',
            customer_notes: customerData.customer_notes || customerData.notes || '',
            express_shipping: !!customerData.express_shipping,
            ...customerData
        };

        try {
            const response = await fetch(`/tienda/${storeSlug}/checkout`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (response.ok && data.success) {
                if (data.payment_url) {
                    // Redirecting to a gateway, not confirming a sale — keep the cart
                    // until the buyer actually pays. It's cleared on return once the
                    // SweetAlert above sees a 'paid' status; if they just come back
                    // without paying, the cart must still be exactly as they left it.
                    window.location.href = data.payment_url;
                    return true;
                }
                this.clear();
                if (data.whatsapp_url) {
                    window.open(data.whatsapp_url, '_blank');
                    window.location.href = data.redirect_url;
                } else {
                    window.location.href = data.redirect_url;
                }
                return true;
            } else {
                let errorMsg = data.error || data.message;
                if (!errorMsg && data.errors) {
                    errorMsg = Object.values(data.errors).flat().join('\n');
                }
                // Flow's drawer owns its inline error and loading state.
                if (customerData.payment_method !== 'flow') {
                    alert(errorMsg || 'Error al procesar el pedido. Por favor verifica los datos.');
                }
                console.error(data);

                const btn = document.getElementById('btnSubmitOrder');
                if (btn && customerData.payment_method !== 'flow') {
                    btn.innerText = 'Confirmar y Pagar';
                    btn.disabled = false;
                }
                throw new Error(errorMsg || 'Error processing order');
            }
        } catch (error) {
            console.error('Error during checkout:', error);
            // Only alert if it's not a thrown error from above
            if (error.message === 'Failed to fetch' && customerData.payment_method !== 'flow') {
                alert('Ocurrió un error inesperado al procesar el checkout.');
            }
            const btn = document.getElementById('btnSubmitOrder');
            if (btn && customerData.payment_method !== 'flow') {
                btn.innerText = 'Confirmar y Pagar';
                btn.disabled = false;
            }
            throw error;
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    // Initialize cart UI
    window.TribioCart.updateUI();
});
