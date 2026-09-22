{{-- Injected by TemplateController::frame() into the storefront preview. Keeps the preview
     inert (no navigation, no cart writes) and applies live customizer edits sent by the
     parent dashboard page. Only same-origin messages are honored. --}}
<style>
    .tpl-preview-toast { position: fixed; left: 50%; bottom: 20px; z-index: 2147483647; transform: translate(-50%, 16px); opacity: 0; pointer-events: none;
        background: #1f2937; color: #fff; font: 600 13px/1.4 'Plus Jakarta Sans', system-ui, sans-serif; padding: 10px 16px; border-radius: 999px;
        box-shadow: 0 10px 30px rgba(0,0,0,.25); transition: opacity .2s, transform .2s; }
    .tpl-preview-toast.is-visible { opacity: 1; transform: translate(-50%, 0); }
    @if($thumbnail)
    html { scroll-behavior: auto !important; }
    *, *::before, *::after { animation-duration: 0s !important; animation-delay: 0s !important; transition: none !important; }
    [data-animate] { opacity: 1 !important; transform: none !important; }
    @endif
</style>
<script>
(() => {
    const origin = window.location.origin;
    let toast, toastTimer;
    const notify = () => {
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'tpl-preview-toast';
            toast.setAttribute('role', 'status');
            toast.textContent = 'Vista previa: los enlaces y el carrito están desactivados';
            document.body.appendChild(toast);
        }
        toast.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 1800);
    };

    // Capture phase: runs before inline onclick handlers and Alpine listeners.
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        const href = link ? link.getAttribute('href') : null;
        const cartAction = event.target.closest('[onclick*="TribioCart"], [onclick*="open-cart-drawer"], [onclick*="window.location"]');
        if ((link && href && !href.startsWith('#') && !href.startsWith('javascript:')) || cartAction) {
            event.preventDefault();
            event.stopPropagation();
            notify();
        }
    }, true);
    document.addEventListener('submit', (event) => { event.preventDefault(); event.stopPropagation(); notify(); }, true);

    const root = document.documentElement;
    window.addEventListener('message', (event) => {
        const data = event.data;
        if (event.origin !== origin || !data || data.type !== 'tribio:template-preview') return;

        Object.entries(data.vars || {}).forEach(([token, value]) => {
            if (/^#[0-9A-F]{6}$/i.test(value)) root.style.setProperty('--t-' + token, value);
        });
        if (data.font && data.font.family) {
            if (data.font.href && !document.querySelector('link[data-tpl-font-live="' + data.font.key + '"]')) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = data.font.href;
                link.dataset.tplFontLive = data.font.key;
                document.head.appendChild(link);
            }
            root.style.setProperty('--font-brand', data.font.family);
        }
        Object.entries(data.texts || {}).forEach(([path, value]) => {
            document.querySelectorAll('[data-tpl-text="' + CSS.escape(path) + '"]').forEach((el) => {
                el.textContent = value;
                if (el.hasAttribute('data-tpl-hide-empty')) el.hidden = String(value).trim() === '';
            });
        });
        Object.entries(data.toggles || {}).forEach(([path, on]) => {
            document.querySelectorAll('[data-tpl-show="' + CSS.escape(path) + '"]').forEach((el) => { el.hidden = !on; });
        });
    });

    if (window.parent !== window) {
        window.parent.postMessage({ type: 'tribio:template-preview-ready' }, origin);
    }
})();
</script>
