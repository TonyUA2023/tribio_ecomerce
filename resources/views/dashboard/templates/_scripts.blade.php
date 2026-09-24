<script>
(() => {
    // Storefront previews are real 1280px-wide pages scaled down to fit their box.
    const thumbObserver = new ResizeObserver((entries) => entries.forEach((entry) => {
        entry.target.style.setProperty('--tpl-scale', (entry.contentRect.width / 1280).toFixed(4));
    }));
    const observeThumbs = () => document.querySelectorAll('[data-tpl-thumb]').forEach((el) => thumbObserver.observe(el));
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', observeThumbs) : observeThumbs();

    // Same ratios as App\Services\Storefront\ColorScale — keep both in sync.
    const WHITE = [255, 255, 255], BLACK = [0, 0, 0];
    const isHex = (value) => /^#?[0-9a-f]{6}$/i.test(String(value || '').trim());
    const normHex = (value) => '#' + String(value).trim().replace('#', '').toUpperCase();
    const toRgb = (hex) => [1, 3, 5].map((i) => parseInt(normHex(hex).slice(i, i + 2), 16));
    const toHex = (rgb) => '#' + rgb.map((c) => Math.max(0, Math.min(255, c)).toString(16).padStart(2, '0')).join('').toUpperCase();
    const mix = (a, b, t) => a.map((x, i) => Math.round(x * (1 - t) + b[i] * t));
    const luminance = (rgb) => {
        const ch = rgb.map((v) => { v /= 255; return v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; });
        return 0.2126 * ch[0] + 0.7152 * ch[1] + 0.0722 * ch[2];
    };
    const primaryScale = (hex) => {
        const p = toRgb(hex);
        return {
            'primary': toHex(p), 'primary-dark': toHex(mix(p, BLACK, 0.40)), 'primary-deep': toHex(mix(p, BLACK, 0.66)),
            'primary-300': toHex(mix(p, WHITE, 0.30)), 'primary-200': toHex(mix(p, WHITE, 0.60)),
            'primary-100': toHex(mix(p, WHITE, 0.80)), 'primary-50': toHex(mix(p, WHITE, 0.88)),
            'on-primary': luminance(p) > 0.5 ? '#1E1D1B' : '#FFFFFF',
        };
    };
    const secondaryScale = (hex) => {
        const s = toRgb(hex);
        return {
            'secondary': toHex(s), 'secondary-dark': toHex(mix(s, BLACK, 0.45)), 'secondary-300': toHex(mix(s, WHITE, 0.45)),
            'secondary-200': toHex(mix(s, WHITE, 0.60)), 'secondary-50': toHex(mix(s, WHITE, 0.85)),
        };
    };

    // Merges mixins keeping getters as getters (object spread would freeze them into values).
    const compose = (...parts) => parts.reduce((target, part) => Object.defineProperties(target, Object.getOwnPropertyDescriptors(part)), {});

    // Shows a real device viewport (so vh-based heroes look as they will) scaled to fit the stage.
    const deviceStage = () => ({
        devices: { desktop: [1280, 800], tablet: [820, 1180], mobile: [390, 844] },
        scale: 1, frameWidth: 1280, frameHeight: 800, deviceChosen: false,
        fit() {
            const stage = this.$refs.stage;
            if (!stage || stage.clientWidth < 200) return;
            // The first real measurement picks the starting device: narrow stages start on "Celular".
            if (!this.deviceChosen) {
                this.deviceChosen = true;
                if (stage.clientWidth < 700) this.device = 'mobile';
            }
            const gap = this.device === 'desktop' ? 0 : 24;
            [this.frameWidth, this.frameHeight] = this.devices[this.device];
            this.scale = Math.min(1, (stage.clientWidth - gap) / this.frameWidth, (stage.clientHeight - gap) / this.frameHeight);
        },
        get frameStyle() { return `width:${this.frameWidth}px;height:${this.frameHeight}px;transform:scale(${this.scale})`; },
        get boxStyle() { return `width:${Math.round(this.frameWidth * this.scale)}px;height:${Math.round(this.frameHeight * this.scale)}px`; },
        watchStage() {
            new ResizeObserver(() => this.fit()).observe(this.$refs.stage);
            this.$watch('device', () => this.$nextTick(() => this.fit()));
            this.$nextTick(() => this.fit());
        },
    });

    const applyFlow = (config) => ({
        applying: null,
        submitting: false,
        get applyAction() { return this.applying ? config.applyUrl.replace('__TEMPLATE__', encodeURIComponent(this.applying.key)) : '#'; },
        ask(key, name) {
            this.applying = { key, name };
            this.submitting = false;
            this.$nextTick(() => this.$refs.applyConfirm && this.$refs.applyConfirm.focus());
        },
    });

    document.addEventListener('alpine:init', () => {
        Alpine.data('templateGallery', (config) => compose(applyFlow(config), { filter: '' }));

        Alpine.data('templatePreview', (config) => compose(deviceStage(), applyFlow(config), {
            device: 'desktop',
            init() { this.watchStage(); },
        }));

        Alpine.data('templateCustomizer', (config) => compose(deviceStage(), {
            v: { ...config.values },
            // Image fields: current URL (saved image or a local blob of the newly picked file).
            imgs: Object.fromEntries(Object.entries(config.fields).filter(([, f]) => f.type === 'image').map(([path]) => [path, config.values[path] || ''])),
            removed: {},
            pending: null,
            dirty: false,
            saving: false,
            previewOpen: false,
            device: 'desktop',
            init() {
                this.watchStage();
                window.addEventListener('message', (event) => {
                    if (event.origin === window.location.origin && event.data && event.data.type === 'tribio:template-preview-ready') this.send();
                });
                window.addEventListener('beforeunload', (event) => {
                    if (this.dirty && !this.saving) { event.preventDefault(); event.returnValue = ''; }
                });
                this.$watch('previewOpen', (open) => {
                    document.documentElement.classList.toggle('tpl-noscroll', open);
                    this.$nextTick(() => this.fit());
                });
            },
            changed() {
                this.dirty = true;
                // A timer, not requestAnimationFrame: rAF pauses while the tab isn't painting.
                clearTimeout(this.pending);
                this.pending = setTimeout(() => this.send(), 40);
            },
            color(path) {
                return isHex(this.v[path]) ? normHex(this.v[path]) : normHex(config.defaults[path]);
            },
            useColors(primary, secondary) {
                this.v['colors.primary'] = normHex(primary);
                if (secondary) this.v['colors.secondary'] = normHex(secondary);
                this.changed();
            },
            count(path) { return String(this.v[path] || '').length; },
            imageUrl(path) { return this.imgs[path] || ''; },
            pickImage(path, event) {
                const file = event.target.files && event.target.files[0];
                if (!file) return;
                if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size > 4 * 1024 * 1024) {
                    alert('Usa una imagen JPG, PNG o WEBP de hasta 4 MB.');
                    event.target.value = '';
                    return;
                }
                if (String(this.imgs[path]).startsWith('blob:')) URL.revokeObjectURL(this.imgs[path]);
                this.imgs[path] = URL.createObjectURL(file);
                this.removed[path] = false;
                this.changed();
            },
            removeImage(path, event) {
                const input = event.target.closest('.tpl-field').querySelector('input[type=file]');
                if (input) input.value = '';
                if (String(this.imgs[path]).startsWith('blob:')) URL.revokeObjectURL(this.imgs[path]);
                this.imgs[path] = '';
                this.removed[path] = true;
                this.changed();
            },
            get primaryIsLight() { return luminance(toRgb(this.color('colors.primary'))) > 0.5; },
            payload() {
                const primary = this.color('colors.primary');
                const background = this.v['colors.background'];
                const vars = { ...primaryScale(primary), ...secondaryScale(this.color('colors.secondary')) };
                vars.bg = background === 'tint' ? toHex(mix(toRgb(primary), WHITE, 0.95)) : (config.backgrounds[background] || config.backgrounds.cream);

                const texts = {}, toggles = {}, images = {}, choices = {};
                Object.entries(config.fields).forEach(([path, field]) => {
                    if (field.type === 'toggle') { toggles[path] = !!this.v[path]; return; }
                    if (field.type === 'image') { images[path] = this.imgs[path] || ''; return; }
                    if (['select', 'date', 'link'].includes(field.type)) { choices[path] = String(this.v[path] ?? ''); return; }
                    if (!['text', 'textarea', 'emoji'].includes(field.type)) return;
                    const value = String(this.v[path] || '').trim();
                    texts[path] = value !== '' || field.optional ? value : (config.defaults[path] || '');
                });
                const fontKey = this.v['typography.heading'];
                const font = config.fonts[fontKey] ? { key: fontKey, ...config.fonts[fontKey] } : null;

                return { type: 'tribio:template-preview', vars, texts, toggles, images, choices, font };
            },
            send() {
                const frame = this.$refs.frame;
                if (frame && frame.contentWindow) frame.contentWindow.postMessage(this.payload(), window.location.origin);
            },
        }));
    });
})();
</script>
