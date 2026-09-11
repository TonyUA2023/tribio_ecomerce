import './bootstrap';

// ═══════════════════════════════════════════════════════════
//  TRIBIO APP — Main JS
//  Plugins: Alpine.js (via CDN in layouts), Swiper.js (via CDN en vistas que lo requieran)
// ═══════════════════════════════════════════════════════════

// ── Intersection Observer para animaciones al hacer scroll ──
document.addEventListener('DOMContentLoaded', () => {

    // Animate elements when they come into view
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-slide-up');
                entry.target.style.opacity = '1';
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

    document.querySelectorAll('[data-animate]').forEach(el => {
        el.style.opacity = '0';
        observer.observe(el);
    });

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

    add(id, name, price, image = '', variant = null) {
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
        const displayName = variantTitle ? `${name} (${variantTitle})` : name;
        this.showNotification(`🛍️ ${displayName} añadido al carrito`);
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

    showNotification(msg) {
        const el = document.createElement('div');
        el.className = 'fixed bottom-6 right-6 z-[9999] px-5 py-3 rounded-2xl text-white text-sm font-medium shadow-2xl';
        el.style.cssText = 'background: rgba(26,26,46,0.95); border: 1px solid rgba(124,58,237,0.4); backdrop-filter: blur(20px); animation: slide-up 0.3s ease;';
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3000);
    },

    async checkout(storeSlug, customerData) {
        if (this.items.length === 0) {
            alert('El carrito está vacío');
            return;
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
                this.clear();
                if (data.payment_url) {
                    window.location.href = data.payment_url;
                } else if (data.whatsapp_url) {
                    window.open(data.whatsapp_url, '_blank');
                    window.location.href = data.redirect_url;
                } else {
                    window.location.href = data.redirect_url;
                }
            } else {
                let errorMsg = data.error || data.message;
                if (!errorMsg && data.errors) {
                    errorMsg = Object.values(data.errors).flat().join('\n');
                }
                alert(errorMsg || 'Error al procesar el pedido. Por favor verifica los datos.');
                console.error(data);

                const btn = document.getElementById('btnSubmitOrder');
                if (btn) {
                    btn.innerText = 'Confirmar y Pagar';
                    btn.disabled = false;
                }
            }
        } catch (error) {
            console.error('Error during checkout:', error);
            alert('Ocurrió un error inesperado al procesar el checkout.');
            const btn = document.getElementById('btnSubmitOrder');
            if (btn) {
                btn.innerText = 'Confirmar y Pagar';
                btn.disabled = false;
            }
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    // Initialize cart UI
    window.TribioCart.updateUI();
});
