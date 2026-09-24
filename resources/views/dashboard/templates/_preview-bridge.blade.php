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
        // Images: only same-origin http(s) or blob: URLs (the customizer's local file previews).
        const safeImage = (url) => url === '' || url.startsWith('blob:' + origin + '/') || url.startsWith(origin + '/');
        const imageTargets = [];
        Object.entries(data.images || {}).forEach(([path, url]) => {
            if (typeof url !== 'string' || !safeImage(url)) return;
            document.querySelectorAll('[data-tpl-img="' + CSS.escape(path) + '"]').forEach((el) => imageTargets.push([el, url]));
        });
        // <source> first: an <img> inside <picture> checks its mobile source to decide if it shows.
        imageTargets.sort((a, b) => (a[0].tagName === 'SOURCE' ? 0 : 1) - (b[0].tagName === 'SOURCE' ? 0 : 1)).forEach(([el, url]) => {
            const value = url || el.dataset.tplImgFallback || '';
            if (el.tagName === 'SOURCE') { el.srcset = value; el.media = value ? (el.dataset.media || '') : 'not all'; }
            else if (el.tagName === 'IMG') {
                const source = el.parentElement && el.parentElement.tagName === 'PICTURE' ? el.parentElement.querySelector('source') : null;
                const src = value || (source && source.media !== 'not all' ? source.getAttribute('srcset') || '' : '');
                if (src) el.src = src;
                el.hidden = !src;
            }
            else { el.style.backgroundImage = value ? 'url("' + value + '")' : ''; }
        });
        document.querySelectorAll('[data-tpl-img-host]').forEach((host) => {
            host.toggleAttribute('data-has-image', !!host.querySelector('img[data-tpl-img]:not([hidden])'));
        });
        // Select/date/link values: templates style themselves off [data-choice].
        Object.entries(data.choices || {}).forEach(([path, value]) => {
            document.querySelectorAll('[data-tpl-choice="' + CSS.escape(path) + '"]').forEach((el) => { el.dataset.choice = String(value); });
        });
        document.dispatchEvent(new CustomEvent('tribio:template-preview-applied'));
    });

    if (window.parent !== window) {
        window.parent.postMessage({ type: 'tribio:template-preview-ready' }, origin);
    }
})();
</script>
