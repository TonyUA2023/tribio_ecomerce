{{-- Textil Pro: estilos propios de la plantilla (prefijo tx-). CSS plano a propósito: no depende
     de clases nuevas de Tailwind, así que ningún cambio aquí requiere recompilar con Vite.
     Color principal (--t-primary) = el tono vivo de la marca (botones de cotizar, destacados);
     color complementario (--t-secondary) = el tono oscuro (menú, bandas, textos fuertes). --}}
<style>
    :root {
        --tx-ink: #141A2E;
        --tx-muted: #5F6678;
        --tx-line: #E2E5EC;
        --tx-soft: #F3F5F9;
        --tx-card: #F1F3F7;
        --tx-radius: 14px;
        --tx-header-h: 78px;
        --tx-head-bg: var(--t-secondary);
        --tx-head-fg: var(--t-on-secondary);
        --tx-head-line: color-mix(in srgb, var(--t-on-secondary) 55%, transparent);
        --font-body: 'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif;
    }
    body[data-choice="square"] { --tx-radius: 0px; }
    .tx-header[data-choice="dark"] { --tx-head-bg: #111111; --tx-head-fg: #FFFFFF; --tx-head-line: rgba(255, 255, 255, .55); }
    .tx-header[data-choice="light"] { --tx-head-bg: #FFFFFF; --tx-head-fg: var(--tx-ink); --tx-head-line: var(--tx-ink); }
    @media (max-width: 1023px) { :root { --tx-header-h: 62px; } }

    .tx-body { margin: 0; font-family: var(--font-body); color: var(--tx-ink); background: var(--t-bg); -webkit-font-smoothing: antialiased; }
    .tx-body *, .tx-body *::before, .tx-body *::after { box-sizing: border-box; }
    .tx-body a { color: inherit; }
    .tx-body img { max-width: 100%; }
    .tx-wrap { width: 100%; max-width: 1480px; margin: 0 auto; padding: 0 16px; }
    .tx-narrow { width: 100%; max-width: 1240px; margin: 0 auto; padding: 0 16px; }
    @media (min-width: 768px) { .tx-wrap, .tx-narrow { padding: 0 32px; } }
    .tx-sr { position: absolute !important; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
    [x-cloak] { display: none !important; }
    .tx-brand { font-family: var(--font-brand); }
    [hidden] { display: none !important; }
    .tx-body :focus-visible { outline: 3px solid var(--t-primary); outline-offset: 2px; }

    /* Botones */
    .tx-btn { display: inline-flex; align-items: center; justify-content: center; gap: 10px; min-height: 46px; padding: 0 22px; border: 2px solid var(--t-secondary); border-radius: var(--tx-radius);
        background: var(--t-secondary); color: var(--t-on-secondary) !important; font: 700 15px/1 var(--font-body); text-decoration: none; cursor: pointer; transition: background-color .2s, color .2s, border-color .2s, transform .2s; }
    .tx-btn:hover { background: var(--t-secondary-dark); border-color: var(--t-secondary-dark); transform: translateY(-1px); }
    .tx-btn svg { width: 20px; height: 20px; flex-shrink: 0; }
    .tx-btn.is-accent { background: var(--t-primary); border-color: var(--t-primary); color: var(--t-on-primary) !important; }
    .tx-btn.is-accent:hover { background: var(--t-primary-dark); border-color: var(--t-primary-dark); color: #fff !important; }
    .tx-btn.is-white { background: #fff; border-color: #fff; color: var(--tx-ink) !important; }
    .tx-btn.is-white:hover { background: var(--tx-ink); border-color: var(--tx-ink); color: #fff !important; }
    .tx-btn.is-outline { background: transparent; color: var(--tx-ink) !important; border-color: var(--tx-ink); }
    .tx-btn.is-outline:hover { background: var(--tx-ink); color: #fff !important; }
    .tx-btn.is-ghost { background: transparent; border-color: currentColor; color: inherit !important; }
    .tx-btn.is-ghost:hover { background: color-mix(in srgb, currentColor 12%, transparent); }
    .tx-btn.is-lg { min-height: 54px; padding: 0 28px; font-size: 16px; }
    .tx-btn.is-block { width: 100%; min-height: 54px; font-size: 16px; }
    .tx-btn:disabled { background: #C9C9C9; border-color: #C9C9C9; color: #fff !important; cursor: not-allowed; }
    .tx-link { font-weight: 700; font-size: 14px; text-decoration: underline; text-underline-offset: 4px; }
    .tx-eyebrow { margin: 0 0 10px; font-size: 13px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--t-primary-dark); }

    /* ── Franja de mensajes ─────────────────────────────────────────── */
    .tx-utility { background: #fff; border-bottom: 1px solid var(--tx-line); font-size: 12px; font-weight: 600; letter-spacing: .02em; text-transform: uppercase; color: var(--tx-ink); }
    .tx-utility .tx-wrap { display: flex; align-items: center; justify-content: center; min-height: 40px; gap: 16px; position: relative; }
    .tx-utility-msgs { display: flex; align-items: center; justify-content: center; flex: 1; min-width: 0; }
    .tx-utility-msgs span { padding: 0 16px; border-left: 1px solid #BDBDBD; white-space: nowrap; }
    .tx-utility-msgs span:first-child { border-left: 0; }
    .tx-utility-links { align-self: stretch; margin-left: auto; display: flex; align-items: center; gap: 0; }
    .tx-utility .tx-wrap { justify-content: space-between; }
    .tx-utility-msgs { overflow: hidden; }
    .tx-utility-links > * { height: 100%; border-left: 1px solid var(--tx-line); }
    .tx-utility-links a, .tx-utility-links > * > button, .tx-utility-links > button { display: inline-flex; align-items: center; gap: 7px; height: 100%; padding: 0 14px; border: 0; background: none; font: 500 12px var(--font-body); text-transform: uppercase; color: var(--tx-muted); text-decoration: none; cursor: pointer; white-space: nowrap; }
    .tx-utility-links a:hover, .tx-utility-links button:hover { color: var(--tx-ink); }
    .tx-utility-links .tx-drop button { height: auto; padding: 10px 12px; text-transform: none; font-size: 14px; color: var(--tx-ink); justify-content: space-between; width: 100%; }
    .tx-utility-links svg { width: 15px; height: 15px; }
    .tx-utility-links img { width: 17px; height: 12px; object-fit: cover; }
    @media (max-width: 1279px) { .tx-utility-links { display: none; } }
    @media (max-width: 1023px) {
        .tx-utility-msgs span { display: none; border: 0; }
        .tx-utility-msgs span.is-current { display: block; animation: tx-fade .4s ease; }
    }
    @keyframes tx-fade { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }

    /* ── Header ─────────────────────────────────────────────────────── */
    .tx-header { position: sticky; top: 0; z-index: 50; height: var(--tx-header-h); background: var(--tx-head-bg); color: var(--tx-head-fg); border-bottom: 1px solid rgba(127, 127, 127, .18); }
    .tx-header-row { height: 100%; display: flex; align-items: center; gap: 24px; }
    .tx-logo { display: inline-flex; align-items: center; flex-shrink: 0; text-decoration: none; color: inherit; margin-right: 18px; }
    .tx-logo img { height: 46px; width: auto; max-width: 200px; object-fit: contain; }
    .tx-logo span { font-family: var(--font-brand); font-size: 28px; font-weight: 800; letter-spacing: .01em; text-transform: uppercase; line-height: 1; white-space: nowrap; }
    .tx-header:is([data-choice="dark"], [data-choice="brand"]) .tx-logo img[data-transparent][data-choice="white"] { filter: brightness(0) invert(1); }
    .tx-header:is([data-choice="dark"], [data-choice="brand"]) .tx-logo img[data-opaque] { border-radius: 6px; }
    .tx-nav { display: flex; align-items: center; gap: 4px 34px; flex-wrap: wrap; min-width: 0; flex: 1; max-height: 100%; overflow: hidden; }
    .tx-nav a { position: relative; display: inline-flex; align-items: center; height: var(--tx-header-h); color: inherit; text-decoration: none; font-weight: 700; font-size: 17px; white-space: nowrap; }
    .tx-nav a::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: currentColor; transform: scaleX(0); transition: transform .2s; }
    .tx-nav a:hover::after, .tx-nav a.is-active::after { transform: scaleX(1); }
    .tx-nav a.is-sale { color: var(--t-primary); }
    .tx-header[data-choice="dark"] .tx-nav a.is-sale { color: var(--t-primary-300); }
    .tx-header[data-choice="light"] .tx-nav a.is-sale { color: var(--t-primary-deep); }
    .tx-tools { display: flex; align-items: center; gap: 8px; margin-left: auto; }
    .tx-search-btn { display: inline-flex; align-items: center; gap: 12px; height: 42px; min-width: 150px; padding: 0 18px; border: 1px solid var(--tx-head-line); border-radius: var(--tx-radius); background: transparent; color: inherit; font: 700 16px var(--font-body); text-transform: uppercase; cursor: pointer; margin-right: 14px; }
    .tx-search-btn:hover { background: rgba(127, 127, 127, .18); }
    .tx-search-btn { text-transform: none; font-weight: 600; font-size: 15px; min-width: 130px; }
    .tx-quote-btn { display: inline-flex; align-items: center; height: 42px; padding: 0 18px; margin-right: 4px; border-radius: var(--tx-radius); background: var(--t-primary); color: var(--t-on-primary) !important; font: 800 15px var(--font-body); text-decoration: none; white-space: nowrap; transition: background-color .2s, color .2s; }
    .tx-quote-btn:hover { background: var(--t-primary-dark); color: #fff !important; }
    .tx-nav a { font-size: 16px; }
    .tx-search-btn svg { width: 19px; height: 19px; }
    .tx-icon-btn { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border: 0; border-radius: 999px; background: none; color: inherit; cursor: pointer; text-decoration: none; }
    .tx-icon-btn:hover { background: rgba(127, 127, 127, .18); }
    .tx-icon-btn svg { width: 24px; height: 24px; }
    .tx-count { position: absolute; top: 4px; right: 2px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: var(--t-primary); color: var(--t-on-primary); font: 700 11px/18px var(--font-body); text-align: center; }
    .tx-count:empty, .tx-count[data-empty] { display: none; }
    .tx-only-mobile { display: none !important; }
    @media (max-width: 1280px) { .tx-nav { gap: 4px 24px; } .tx-nav a { font-size: 16px; } }
    @media (max-width: 1023px) {
        .tx-nav, .tx-search-btn, .tx-hide-mobile { display: none !important; }
        .tx-only-mobile { display: inline-flex !important; }
        .tx-header-row { gap: 4px; }
        .tx-logo { position: absolute; left: 50%; transform: translateX(-50%); margin: 0; }
        .tx-logo img { height: 36px; max-width: 150px; }
        .tx-logo span { font-size: 22px; }
        .tx-tools { gap: 0; }
    }
    .tx-shipbar { display: block; background: var(--tx-soft); color: var(--tx-ink); text-align: center; padding: 12px 16px; font: 700 15px var(--font-body); letter-spacing: .02em; text-transform: uppercase; text-decoration: none; border-bottom: 1px solid var(--tx-line); }
    a.tx-shipbar:hover { text-decoration: underline; text-underline-offset: 3px; }
    @media (max-width: 767px) { .tx-shipbar { font-size: 13px; padding: 10px 12px; } }

    /* Búsqueda */
    .tx-search-panel { position: fixed; inset: 0; z-index: 80; display: flex; flex-direction: column; }
    .tx-search-backdrop { position: absolute; inset: 0; background: rgba(0, 0, 0, .5); }
    .tx-search-box { position: relative; background: #fff; color: var(--tx-ink); padding: 28px 0 34px; box-shadow: 0 20px 60px rgba(0, 0, 0, .2); }
    .tx-search-box form { display: flex; align-items: center; gap: 14px; border-bottom: 2px solid var(--tx-ink); }
    .tx-search-box input { flex: 1; min-width: 0; border: 0; outline: none; background: none; padding: 12px 0; font: 600 26px var(--font-body); color: var(--tx-ink); }
    .tx-search-box input::-webkit-search-cancel-button { display: none; }
    .tx-search-box svg { width: 26px; height: 26px; flex-shrink: 0; }
    .tx-search-hints { margin-top: 20px; display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
    .tx-search-hints strong { font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: var(--tx-muted); margin-right: 6px; }
    .tx-chip { display: inline-flex; align-items: center; gap: 8px; min-height: 36px; padding: 0 14px; border: 1px solid var(--tx-line); border-radius: 999px; background: #fff; color: var(--tx-ink); font: 600 14px var(--font-body); text-decoration: none; cursor: pointer; white-space: nowrap; }
    .tx-chip:hover { border-color: var(--tx-ink); }
    .tx-chip.is-active { background: var(--tx-ink); border-color: var(--tx-ink); color: #fff; }
    @media (max-width: 767px) { .tx-search-box input { font-size: 20px; } }

    /* Menú móvil */
    .tx-drawer { position: fixed; inset: 0; z-index: 80; display: flex; }
    .tx-drawer-backdrop { position: absolute; inset: 0; background: rgba(0, 0, 0, .55); }
    .tx-drawer-panel { position: relative; width: min(90%, 400px); height: 100%; background: #fff; color: var(--tx-ink); display: flex; flex-direction: column; box-shadow: 0 0 60px rgba(0, 0, 0, .3); }
    .tx-drawer-panel.is-right { margin-left: auto; }
    .tx-drawer-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--tx-line); }
    .tx-drawer-head strong { font: 700 18px var(--font-body); text-transform: uppercase; letter-spacing: .03em; }
    .tx-drawer-body { flex: 1; overflow-y: auto; padding: 8px 16px 24px; }
    .tx-drawer-links a, .tx-drawer-links button { display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 16px 2px; border: 0; border-bottom: 1px solid var(--tx-line); background: none; color: var(--tx-ink); font: 700 18px var(--font-body); text-decoration: none; text-align: left; cursor: pointer; }
    .tx-drawer-links a.is-sale { color: var(--t-primary-deep); }
    .tx-drawer-links.is-small a, .tx-drawer-links.is-small button { font-size: 15px; font-weight: 500; padding: 13px 2px; }
    .tx-drawer-foot { display: grid; gap: 12px; margin-top: 20px; padding: 14px; background: var(--tx-soft); border-radius: var(--tx-radius); font-size: 14px; font-weight: 600; }
    .tx-drawer-foot > div { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .tx-drawer-foot select { border: 1px solid var(--tx-line); background: #fff; padding: 8px 10px; font: 600 14px var(--font-body); border-radius: var(--tx-radius); }
    .tx-seg { display: inline-flex; border: 1px solid var(--tx-ink); }
    .tx-seg button { border: 0; background: #fff; padding: 6px 14px; font: 700 13px var(--font-body); cursor: pointer; }
    .tx-seg button.is-active { background: var(--tx-ink); color: #fff; }
    .tx-tr { transition: transform .28s ease; }
    .tx-off-left { transform: translateX(-100%); }
    .tx-off-right { transform: translateX(100%); }
    .tx-on { transform: none; }
    .tx-drop { position: absolute; right: 0; top: calc(100% + 4px); z-index: 60; min-width: 220px; padding: 6px; background: #fff; color: var(--tx-ink); border: 1px solid var(--tx-line); box-shadow: 0 16px 40px rgba(0, 0, 0, .15); text-transform: none; }
    .tx-drop button { display: flex; width: 100%; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; border: 0; background: none; font: 600 14px var(--font-body); color: var(--tx-ink); cursor: pointer; text-align: left; }
    .tx-drop button:hover { background: var(--tx-soft); }
    .tx-drop button.is-active { color: var(--t-primary-deep); }
    .tx-drop img { width: 18px; height: 13px; object-fit: cover; }

    /* ── Portada ────────────────────────────────────────────────────── */
    .tx-hero { position: relative; overflow: hidden; background: var(--t-secondary); color: var(--t-on-secondary); padding: 64px 0 72px; }
    .tx-hero::before { content: ''; position: absolute; inset: auto -12% -40% auto; width: 62%; aspect-ratio: 1; border-radius: 50%; background: radial-gradient(circle, color-mix(in srgb, var(--t-on-secondary) 12%, transparent), color-mix(in srgb, var(--t-on-secondary) 4%, transparent) 70%); pointer-events: none; }
    .tx-hero[data-choice="light"] { background: var(--t-primary-50); color: var(--tx-ink); }
    .tx-hero[data-choice="light"]::before { background: radial-gradient(circle, color-mix(in srgb, var(--t-primary) 45%, transparent), transparent 70%); }
    .tx-hero-grid { position: relative; display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, .95fr); gap: 48px; align-items: center; }
    .tx-hero .tx-eyebrow { color: var(--t-primary); }
    .tx-hero[data-choice="light"] .tx-eyebrow { color: var(--t-primary-deep); }
    .tx-hero-title { margin: 0; font-family: var(--font-brand); font-weight: 800; font-size: clamp(36px, 5vw, 64px); line-height: 1.04; letter-spacing: -.02em; }
    .tx-hero-title span, .tx-hero-title mark { display: block; }
    .tx-hero-title mark { background: none; color: var(--t-primary); }
    .tx-hero[data-choice="light"] .tx-hero-title mark { color: var(--t-primary-deep); }
    .tx-hero-text { max-width: 560px; margin: 20px 0 0; font-size: clamp(16px, 1.3vw, 18px); line-height: 1.65; opacity: .9; }
    .tx-hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 30px; }
    .tx-hero-badges { list-style: none; display: flex; flex-wrap: wrap; gap: 10px 18px; margin: 28px 0 0; padding: 0; font-size: 14px; font-weight: 700; }
    .tx-hero-badges li { display: inline-flex; align-items: center; gap: 8px; }
    .tx-hero-badges li::before { content: ''; width: 18px; height: 18px; border-radius: 50%; background: var(--t-primary) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='3'%3E%3Cpath d='m6 12 4 4 8-8'/%3E%3C/svg%3E") center / 12px no-repeat; }
    .tx-hero-visual { position: relative; min-height: 420px; display: grid; place-items: center; }
    .tx-shirt { position: relative; width: min(100%, 440px); animation: tx-bob 6s ease-in-out infinite; filter: drop-shadow(0 30px 40px rgba(0, 0, 0, .3)); }
    .tx-shirt-svg { display: block; width: 100%; height: auto; }
    .tx-shirt-body { fill: var(--t-primary); }
    .tx-shirt-collar { fill: var(--t-primary-dark); }
    .tx-hero[data-choice="light"] .tx-shirt-body { fill: var(--t-secondary); }
    .tx-hero[data-choice="light"] .tx-shirt-collar { fill: var(--t-secondary-dark); }
    .tx-shirt-print { position: absolute; left: 50%; top: 38%; width: 34%; aspect-ratio: 1; transform: translate(-50%, -30%); display: grid; place-items: center; text-align: center; }
    .tx-shirt-print img { width: 100%; height: 100%; object-fit: contain; }
    .tx-shirt-print strong { font-family: var(--font-brand); font-weight: 800; font-size: clamp(18px, 2.4vw, 30px); line-height: 1; color: var(--t-secondary); text-transform: uppercase; word-break: break-word; }
    .tx-hero[data-choice="light"] .tx-shirt-print strong { color: var(--t-primary); }
    .tx-float { position: absolute; padding: 12px 16px; border-radius: 999px; background: #fff; color: var(--tx-ink); font-size: 14px; font-weight: 800; box-shadow: 0 16px 34px rgba(0, 0, 0, .22); white-space: nowrap; }
    .tx-float::before { content: '●'; margin-right: 8px; color: var(--t-primary-dark); }
    .tx-float.is-a { top: 12%; left: -2%; }
    .tx-float.is-b { bottom: 10%; right: 0; }
    @keyframes tx-bob { 0%, 100% { transform: translateY(0) rotate(-2deg); } 50% { transform: translateY(-12px) rotate(1deg); } }
    @media (prefers-reduced-motion: reduce) { .tx-shirt { animation: none; } }
    @media (max-width: 900px) {
        .tx-hero { padding: 40px 0 48px; }
        .tx-hero-grid { grid-template-columns: minmax(0, 1fr); gap: 28px; }
        .tx-hero-visual { min-height: 0; order: -1; }
        .tx-shirt { width: min(70%, 300px); }
        .tx-float { display: none; }
    }
    /* Portada con banner: la imagen manda y ocupa todo el ancho. En pantallas anchas el texto
       va encima (con un degradado para que se lea); en celular el banner va arriba y el texto
       debajo. "Solo el banner" muestra la imagen completa, sin recortes, y deja solo los botones. */
    .tx-hero-media, .tx-hero-scrim, .tx-hero-link { display: none; }
    .tx-hero-media picture, .tx-hero-media img { display: block; width: 100%; }
    .tx-hero[data-has-image] { padding: 0; --tx-scrim: color-mix(in srgb, var(--t-secondary) 55%, #000); }
    .tx-hero[data-has-image]::before, .tx-hero[data-has-image] .tx-hero-visual { display: none; }
    .tx-hero[data-has-image] .tx-hero-media { display: block; position: relative; }
    .tx-hero[data-has-image] .tx-hero-grid { grid-template-columns: minmax(0, 1fr); padding-top: 32px; padding-bottom: 44px; }
    .tx-hero[data-has-image] .tx-hero-mode[data-choice="overlay"] .tx-hero-media img { height: auto; min-height: 240px; max-height: 62vh; max-height: 62svh; object-fit: cover; }
    @media (min-width: 901px) {
        .tx-hero[data-has-image] .tx-hero-mode[data-choice="overlay"] .tx-hero-size { position: relative; display: flex; align-items: center; min-height: clamp(440px, 42vw, 660px); color: #fff; }
        .tx-hero[data-has-image] .tx-hero-mode[data-choice="overlay"] .tx-hero-size[data-choice="medium"] { min-height: clamp(340px, 31vw, 500px); }
        .tx-hero[data-has-image] .tx-hero-mode[data-choice="overlay"] .tx-hero-size[data-choice="full"] { min-height: calc(100vh - var(--tx-header-h) - 40px); min-height: calc(100svh - var(--tx-header-h) - 40px); }
        .tx-hero[data-has-image] .tx-hero-mode[data-choice="overlay"] .tx-hero-media { position: absolute; inset: 0; }
        .tx-hero[data-has-image] .tx-hero-mode[data-choice="overlay"] .tx-hero-media picture,
        .tx-hero[data-has-image] .tx-hero-mode[data-choice="overlay"] .tx-hero-media img { height: 100%; min-height: 0; max-height: none; }
        .tx-hero[data-has-image] .tx-hero-mode[data-choice="overlay"] .tx-hero-scrim { display: block; position: absolute; inset: 0; pointer-events: none;
            background: linear-gradient(90deg, color-mix(in srgb, var(--tx-scrim) 90%, transparent) 0%, color-mix(in srgb, var(--tx-scrim) 64%, transparent) 40%, transparent 78%); }
        .tx-hero[data-has-image] .tx-hero-mode[data-choice="overlay"] .tx-hero-scrim[data-choice="soft"] { background: linear-gradient(90deg, color-mix(in srgb, var(--tx-scrim) 58%, transparent) 0%, color-mix(in srgb, var(--tx-scrim) 30%, transparent) 40%, transparent 70%); }
        .tx-hero[data-has-image] .tx-hero-mode[data-choice="overlay"] .tx-hero-scrim[data-choice="none"] { background: none; }
        .tx-hero[data-has-image] .tx-hero-mode[data-choice="overlay"] .tx-hero-grid { width: 100%; z-index: 2; padding-top: 56px; padding-bottom: 56px; }
        /* Mediano: texto más compacto para que el banner de verdad quede más bajo. */
        .tx-hero[data-has-image] .tx-hero-mode[data-choice="overlay"] .tx-hero-size[data-choice="medium"] .tx-hero-grid { padding-top: 36px; padding-bottom: 36px; }
        .tx-hero[data-has-image] .tx-hero-mode[data-choice="overlay"] .tx-hero-size[data-choice="medium"] .tx-hero-title { font-size: clamp(30px, 3.4vw, 46px); }
        .tx-hero[data-has-image] .tx-hero-mode[data-choice="overlay"] .tx-hero-size[data-choice="medium"] .tx-hero-text { margin-top: 14px; font-size: 16px; }
        .tx-hero[data-has-image] .tx-hero-mode[data-choice="overlay"] .tx-hero-size[data-choice="medium"] .tx-hero-actions,
        .tx-hero[data-has-image] .tx-hero-mode[data-choice="overlay"] .tx-hero-size[data-choice="medium"] .tx-hero-badges { margin-top: 20px; }
        .tx-hero[data-has-image] .tx-hero-mode[data-choice="overlay"] .tx-hero-copy { max-width: 640px; text-shadow: 0 2px 22px rgba(0, 0, 0, .35); }
        .tx-hero[data-has-image] .tx-hero-mode[data-choice="overlay"] .tx-btn { text-shadow: none; }
        .tx-hero[data-has-image] .tx-hero-mode[data-choice="overlay"] .tx-eyebrow,
        .tx-hero[data-has-image] .tx-hero-mode[data-choice="overlay"] .tx-hero-title mark { color: var(--t-primary); }
    }
    .tx-hero[data-has-image] .tx-hero-mode[data-choice="image"] .tx-hero-media img { height: auto; }
    .tx-hero[data-has-image] .tx-hero-mode[data-choice="image"] .tx-hero-link { display: block; position: absolute; inset: 0; }
    .tx-hero[data-has-image] .tx-hero-mode[data-choice="image"] .tx-hero-grid { padding-top: 18px; padding-bottom: 18px; }
    .tx-hero[data-has-image] .tx-hero-mode[data-choice="image"] .tx-eyebrow,
    .tx-hero[data-has-image] .tx-hero-mode[data-choice="image"] .tx-hero-text,
    .tx-hero[data-has-image] .tx-hero-mode[data-choice="image"] .tx-hero-badges { display: none; }
    .tx-hero[data-has-image] .tx-hero-mode[data-choice="image"] .tx-hero-title { position: absolute; width: 1px; height: 1px; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; }
    .tx-hero[data-has-image] .tx-hero-mode[data-choice="image"] .tx-hero-actions { margin-top: 0; justify-content: center; }

    /* Cifras */
    .tx-stats { background: var(--t-primary); color: var(--t-on-primary); }
    .tx-stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .tx-stat { padding: 26px 18px; text-align: center; border-left: 1px solid color-mix(in srgb, currentColor 18%, transparent); }
    .tx-stat:first-child { border-left: 0; }
    .tx-stat strong { display: block; font-family: var(--font-brand); font-weight: 800; font-size: clamp(24px, 2.6vw, 34px); line-height: 1.1; }
    .tx-stat span { display: block; margin-top: 4px; font-size: 14px; font-weight: 600; opacity: .85; }
    @media (max-width: 767px) { .tx-stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .tx-stat:nth-child(3) { border-left: 0; } .tx-stat { padding: 18px 10px; } }

    /* Encabezados de sección */
    .tx-heading { max-width: 720px; margin: 0 auto 30px; text-align: center; }
    .tx-heading .tx-h2 { margin: 0; }

    /* Servicios */
    .tx-services { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
    .tx-service { display: grid; gap: 10px; align-content: start; padding: 26px; border: 1px solid var(--tx-line); border-radius: calc(var(--tx-radius) * 1.3); background: #fff; color: var(--tx-ink); text-decoration: none; transition: transform .25s, box-shadow .25s, border-color .25s; }
    a.tx-service[href]:hover { transform: translateY(-4px); box-shadow: 0 18px 36px rgba(20, 26, 46, .1); border-color: var(--t-primary); }
    .tx-service-icon { display: grid; place-items: center; width: 54px; height: 54px; border-radius: 16px; background: var(--t-primary-100); font-size: 28px; }
    .tx-service h3 { margin: 6px 0 0; font-family: var(--font-brand); font-size: 20px; font-weight: 800; }
    .tx-service p { margin: 0; color: var(--tx-muted); line-height: 1.55; font-size: 15px; }
    @media (max-width: 1023px) { .tx-services { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 599px) { .tx-services { grid-template-columns: minmax(0, 1fr); } .tx-service { padding: 20px; } }

    /* Cómo funciona */
    .tx-process-wrap { background: var(--tx-soft); }
    .tx-steps { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
    .tx-step { position: relative; padding: 26px 22px; border-radius: calc(var(--tx-radius) * 1.3); background: #fff; }
    .tx-step-n { display: grid; place-items: center; width: 46px; height: 46px; border-radius: 50%; background: var(--t-secondary); color: var(--t-on-secondary); font-family: var(--font-brand); font-weight: 800; font-size: 20px; }
    .tx-step h3 { margin: 18px 0 6px; font-family: var(--font-brand); font-size: 19px; font-weight: 800; }
    .tx-step p { margin: 0; color: var(--tx-muted); line-height: 1.55; font-size: 15px; }
    @media (max-width: 1023px) { .tx-steps { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 599px) { .tx-steps { grid-template-columns: minmax(0, 1fr); } }

    /* Por mayor y menor */
    .tx-wholesale { display: grid; grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr); gap: 40px; align-items: center; }
    .tx-wholesale-copy p { color: var(--tx-muted); line-height: 1.65; margin: 0 0 24px; }
    .tx-tiers { display: grid; gap: 10px; }
    .tx-tier { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 22px; border-radius: var(--tx-radius); background: #fff; border: 1px solid var(--tx-line); }
    .tx-tier strong { font-family: var(--font-brand); font-size: 18px; font-weight: 800; }
    .tx-tier span { font-weight: 700; color: var(--t-secondary); text-align: right; }
    .tx-tier:last-of-type { background: var(--t-secondary); border-color: var(--t-secondary); color: var(--t-on-secondary); }
    .tx-tier:last-of-type span { color: var(--t-primary); }
    .tx-tiers-note { margin: 4px 0 0; font-size: 13px; color: var(--tx-muted); }
    @media (max-width: 900px) { .tx-wholesale { grid-template-columns: minmax(0, 1fr); gap: 24px; } }

    /* Banda de impresión por metro */
    .tx-promo-band { background: var(--t-primary); color: var(--t-on-primary); padding: 56px 0; }
    .tx-promo-band .tx-eyebrow { color: inherit; opacity: .8; }
    .tx-promo-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, .8fr); gap: 36px; align-items: center; }
    .tx-promo-title { margin: 0; font-family: var(--font-brand); font-weight: 800; font-size: clamp(30px, 3.6vw, 46px); line-height: 1.06; }
    .tx-promo-text { margin: 14px 0 24px; font-size: 17px; line-height: 1.6; max-width: 560px; }
    .tx-promo-side { position: relative; display: grid; place-items: center; }
    .tx-promo-side img { width: 100%; max-height: 320px; object-fit: cover; border-radius: calc(var(--tx-radius) * 1.4); }
    .tx-price-tag { display: grid; justify-items: center; align-content: center; padding: 26px 34px; border-radius: 50%; aspect-ratio: 1; background: var(--t-secondary); color: var(--t-on-secondary); box-shadow: 0 20px 40px rgba(0, 0, 0, .2); transform: rotate(-6deg); }
    .tx-price-tag small { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; opacity: .85; }
    .tx-price-tag strong { font-family: var(--font-brand); font-size: clamp(40px, 5vw, 64px); font-weight: 800; line-height: 1; color: var(--t-primary); }
    .tx-price-tag span { font-size: 15px; font-weight: 700; }
    .tx-promo-side:has(img:not([hidden])) .tx-price-tag { position: absolute; right: -8px; bottom: -22px; z-index: 2; padding: 20px 26px; }
    @media (max-width: 767px) { .tx-promo-grid { grid-template-columns: minmax(0, 1fr); } .tx-promo-side { justify-items: start; } }

    /* Para quién trabajamos */
    .tx-segments { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
    .tx-segment { position: relative; display: flex; flex-direction: column; justify-content: flex-end; min-height: 260px; padding: 22px; overflow: hidden; border-radius: calc(var(--tx-radius) * 1.3); background: var(--t-secondary); color: var(--t-on-secondary); text-decoration: none; isolation: isolate; }
    .tx-segment:nth-child(even) { background: var(--t-secondary-dark); }
    .tx-body a.tx-segment { color: var(--t-on-secondary); }
    .tx-segment img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: -2; transition: transform .6s ease; }
    .tx-segment:has(img:not([hidden]))::before { content: ''; position: absolute; inset: 35% 0 0; background: linear-gradient(0deg, rgba(0, 0, 0, .78), rgba(0, 0, 0, 0)); z-index: -1; }
    .tx-segment:has(img:not([hidden])) .tx-segment-icon { display: none; }
    .tx-segment:hover img { transform: scale(1.05); }
    .tx-segment-icon { position: absolute; top: 20px; right: 20px; font-size: 44px; opacity: .9; }
    .tx-segment h3 { margin: 0; font-family: var(--font-brand); font-size: 22px; font-weight: 800; }
    .tx-segment p { margin: 6px 0 0; font-size: 14px; line-height: 1.5; opacity: .88; }
    .tx-segment-copy::after { content: '→'; display: inline-block; margin-top: 12px; font-weight: 800; color: var(--t-primary); transition: transform .2s; }
    .tx-segment:hover .tx-segment-copy::after { transform: translateX(6px); }
    @media (max-width: 1023px) { .tx-segments { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 520px) { .tx-segments { grid-template-columns: minmax(0, 1fr); } .tx-segment { min-height: 200px; } }

    /* Productos destacados (arriba) */
    .tx-showcase { padding-top: 48px; }
    .tx-rails[data-choice="featured"] .tx-rail-src:not([data-src="featured"]),
    .tx-rails[data-choice="newest"] .tx-rail-src:not([data-src="newest"]),
    .tx-rails[data-choice="sale"] .tx-rail-src:not([data-src="sale"]) { display: none; }
    .tx-muted-note { color: var(--tx-muted); padding: 20px 0; margin: 0; }
    .tx-works { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(220px, calc((100% - 3 * 16px) / 4)); gap: 16px; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; padding-bottom: 4px; }
    .tx-works::-webkit-scrollbar { display: none; }
    .tx-work { position: relative; display: block; aspect-ratio: 4 / 5; overflow: hidden; border-radius: calc(var(--tx-radius) * 1.2); background: var(--tx-card); scroll-snap-align: start; text-decoration: none; }
    .tx-work img { width: 100%; height: 100%; object-fit: cover; transition: transform .6s ease; }
    .tx-work:hover img { transform: scale(1.05); }
    .tx-work span { position: absolute; left: 0; right: 0; bottom: 0; padding: 40px 16px 14px; background: linear-gradient(0deg, rgba(0, 0, 0, .72), rgba(0, 0, 0, 0)); color: #fff; font-weight: 700; font-size: 15px; }
    @media (max-width: 767px) { .tx-showcase { padding-top: 32px; } .tx-works { grid-auto-columns: 70%; } }

    /* Catálogo vacío: invitación a cotizar */
    .tx-custom-cta { display: flex; align-items: center; justify-content: space-between; gap: 24px; flex-wrap: wrap; padding: 34px; border-radius: calc(var(--tx-radius) * 1.4); background: var(--t-primary-50); border: 2px dashed var(--t-primary); }
    .tx-custom-cta h3 { margin: 0; font-family: var(--font-brand); font-size: 24px; font-weight: 800; }
    .tx-custom-cta p { margin: 8px 0 0; color: var(--tx-muted); max-width: 620px; line-height: 1.6; }

    /* Preguntas frecuentes */
    .tx-faq { display: grid; grid-template-columns: minmax(0, .8fr) minmax(0, 1.2fr); gap: 40px; align-items: start; }
    .tx-faq-list { display: grid; gap: 10px; }
    .tx-faq-item { border: 1px solid var(--tx-line); border-radius: var(--tx-radius); background: #fff; }
    .tx-faq-item summary { list-style: none; cursor: pointer; display: flex; justify-content: space-between; gap: 16px; padding: 18px 20px; font-weight: 800; font-size: 16px; }
    .tx-faq-item summary::-webkit-details-marker { display: none; }
    .tx-faq-item summary::after { content: '+'; font-size: 22px; line-height: 1; color: var(--t-primary-dark); transition: transform .2s; }
    .tx-faq-item[open] summary::after { transform: rotate(45deg); }
    .tx-faq-item p { margin: 0; padding: 0 20px 18px; color: var(--tx-muted); line-height: 1.6; }
    @media (max-width: 900px) { .tx-faq { grid-template-columns: minmax(0, 1fr); gap: 16px; } }

    /* Visítanos */
    .tx-visit { background: var(--t-secondary); color: var(--t-on-secondary); padding: 60px 0; }
    .tx-visit-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 40px; align-items: center; }
    .tx-visit-text { margin: 0; font-size: 17px; line-height: 1.6; opacity: .9; }
    .tx-visit-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
    .tx-visit-list li { display: flex; gap: 14px; align-items: flex-start; padding: 16px 18px; border-radius: var(--tx-radius); background: color-mix(in srgb, var(--t-on-secondary) 8%, transparent); }
    .tx-visit-list li > span { font-size: 22px; line-height: 1; }
    .tx-visit-list strong { display: block; font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: var(--t-primary); margin-bottom: 3px; }
    .tx-visit-list a { color: inherit; text-decoration: underline; text-underline-offset: 3px; }
    .tx-visit-socials { display: flex; flex-wrap: wrap; gap: 6px 14px; }
    @media (max-width: 900px) { .tx-visit-grid { grid-template-columns: minmax(0, 1fr); gap: 24px; } }

    /* Precio por cantidad (ficha de producto) */
    .tx-tierbox { border: 1px solid var(--tx-line); border-radius: var(--tx-radius); padding: 16px 18px; background: var(--t-primary-50); }
    .tx-tierbox-title { margin: 0 0 10px; font-size: 13px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--tx-ink); }
    .tx-tierbox ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
    .tx-tierbox li { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 9px 12px; border-radius: calc(var(--tx-radius) * .7); background: #fff; font-size: 15px; transition: background-color .2s, color .2s; }
    .tx-tierbox li.is-on { background: var(--t-secondary); color: var(--t-on-secondary); }
    .tx-tierbox li strong { font-weight: 800; }
    .tx-tierbox li em { font-style: normal; margin-left: 6px; padding: 2px 7px; border-radius: 999px; background: var(--t-primary); color: var(--t-on-primary); font-size: 12px; }
    .tx-tierbox-note, .tx-tierbox-min { margin: 10px 0 0; font-size: 13px; color: var(--tx-muted); }
    .tx-tierbox-min strong { color: var(--tx-ink); }

    /* Botón flotante de WhatsApp */
    .tx-wa { position: fixed; right: 18px; bottom: 18px; z-index: 60; display: inline-flex; align-items: center; gap: 10px; padding: 14px 18px; border-radius: 999px; background: #25D366; color: #fff !important; font-weight: 800; font-size: 15px; text-decoration: none; box-shadow: 0 14px 30px rgba(0, 0, 0, .25); transition: transform .2s; }
    .tx-wa:hover { transform: translateY(-2px); }
    .tx-wa svg { width: 24px; height: 24px; }
    @media (max-width: 767px) { .tx-wa span { display: none; } .tx-wa { padding: 14px; } }

    /* ── Secciones ──────────────────────────────────────────────────── */
    .tx-section { padding: 64px 0; }
    .tx-section.is-tight { padding: 28px 0 40px; }
    .tx-h2 { margin: 0 0 24px; font-family: var(--font-brand); font-weight: 800; font-size: clamp(28px, 3vw, 40px); line-height: 1.1; text-align: center; letter-spacing: -.015em; }
    .tx-h2.is-left { text-align: left; }
    .tx-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
    .tx-head .tx-h2 { margin: 0; }
    @media (max-width: 767px) { .tx-section { padding: 36px 0; } }

    /* Carrusel de productos */
    .tx-rail { position: relative; }
    .tx-rail-track { display: grid; grid-auto-flow: column; grid-auto-columns: calc((100% - 3 * 16px) / 4); gap: 16px; overflow-x: auto; scroll-snap-type: x mandatory; scroll-behavior: smooth; scrollbar-width: none; }
    .tx-rail-track::-webkit-scrollbar { display: none; }
    .tx-rail-track > * { scroll-snap-align: start; }
    .tx-rail-btn { position: absolute; top: calc(var(--tx-rail-img, 300px) / 2); z-index: 4; width: 44px; height: 44px; transform: translateY(-50%); display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 999px; background: #fff; color: var(--tx-ink); box-shadow: 0 3px 12px rgba(0, 0, 0, .15); cursor: pointer; transition: opacity .2s; }
    .tx-rail-btn.is-prev { left: 12px; } .tx-rail-btn.is-next { right: 12px; }
    .tx-rail-btn:disabled { opacity: 0; pointer-events: none; }
    .tx-rail-btn svg { width: 20px; height: 20px; }
    .tx-rail-progress { position: relative; height: 5px; margin-top: 30px; background: #E4E4E4; border-radius: 99px; overflow: hidden; }
    .tx-rail-progress i { position: absolute; top: 0; bottom: 0; left: 0; background: #585858; border-radius: 99px; transition: transform .2s ease, width .2s ease; }
    @media (max-width: 1023px) { .tx-rail-track { grid-auto-columns: calc((100% - 2 * 12px) / 2.6); gap: 12px; } }
    @media (max-width: 639px) { .tx-rail-track { grid-auto-columns: 66%; } .tx-rail-btn { display: none; } }

    /* Tarjeta de producto */
    .tx-card { position: relative; display: flex; flex-direction: column; min-width: 0; }
    .tx-card-media { position: relative; display: block; aspect-ratio: 1 / 1; overflow: hidden; background: var(--tx-card); border-radius: var(--tx-radius); }
    .tx-card-media img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transition: opacity .35s ease, transform .6s ease; }
    .tx-main[data-choice="contain"] .tx-card-media img { object-fit: contain; padding: 8%; mix-blend-mode: multiply; }
    .tx-card-media img.is-alt { opacity: 0; }
    .tx-card:hover .tx-card-media img.is-alt { opacity: 1; }
    .tx-card:hover .tx-card-media.has-alt img:not(.is-alt) { opacity: 0; }
    .tx-card:hover .tx-card-media:not(.has-alt) img { transform: scale(1.04); }
    .tx-badge { position: absolute; top: 14px; left: 14px; z-index: 2; padding: 4px 9px; background: var(--t-primary); color: var(--t-on-primary); font: 700 13px var(--font-body); border-radius: calc(var(--tx-radius) / 2); }
    .tx-badge.is-dark { background: var(--tx-ink); color: #fff; }
    .tx-card-add { position: absolute; top: 12px; right: 12px; z-index: 3; width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 999px; background: #fff; color: var(--tx-ink); box-shadow: 0 2px 8px rgba(0, 0, 0, .08); cursor: pointer; text-decoration: none; transition: background-color .2s, color .2s; }
    .tx-card-add:hover { background: var(--tx-ink); color: #fff; }
    .tx-card-add svg { width: 19px; height: 19px; }
    .tx-card-info { padding: 16px 2px 4px; display: grid; gap: 6px; }
    .tx-card-meta { font-size: 13px; text-transform: uppercase; letter-spacing: .02em; color: var(--tx-muted); }
    .tx-card-name { font-size: 16px; font-weight: 600; line-height: 1.3; color: var(--tx-ink); text-decoration: none; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .tx-card-name:hover { text-decoration: underline; }
    .tx-price { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 10px; font-size: 17px; font-weight: 700; }
    /* Tono profundo: un primario claro (amarillo) no se lee sobre blanco. */
    .tx-price .is-sale { color: var(--t-primary-deep); }
    .tx-price s { color: #8C8C8C; font-weight: 500; font-size: 15px; }
    .tx-tag { display: inline-block; padding: 3px 8px; background: var(--tx-ink); color: #fff; font: 700 12px var(--font-body); letter-spacing: .04em; text-transform: uppercase; }

    /* Tarjetas "Lo nuevo" */
    .tx-tiles { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
    .tx-tile { position: relative; display: block; overflow: hidden; aspect-ratio: 4 / 5; background: var(--tx-card); color: #fff; text-decoration: none; border-radius: var(--tx-radius); }
    .tx-tile img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transition: transform .8s ease; }
    .tx-tile:hover img { transform: scale(1.04); }
    .tx-tile-fallback { position: absolute; inset: 0; background: linear-gradient(160deg, #3b3b3b, #0f0f0f); }
    .tx-tile::after { content: ''; position: absolute; inset: 40% 0 0; background: linear-gradient(0deg, rgba(0, 0, 0, .72), rgba(0, 0, 0, 0)); }
    .tx-tile-copy { position: absolute; left: 0; right: 0; bottom: 0; z-index: 2; padding: 24px; display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; }
    .tx-tile-copy h3 { margin: 0; font-family: var(--font-brand); font-weight: 700; text-transform: uppercase; font-size: clamp(26px, 2.6vw, 38px); line-height: 1; }
    @media (max-width: 767px) { .tx-tiles { grid-template-columns: repeat(3, 78%); overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; } .tx-tile { scroll-snap-align: start; } .tx-tile-copy { padding: 16px; } }

    /* Campañas */
    .tx-campaigns { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 16px; }
    .tx-campaign { display: flex; flex-direction: column; align-items: center; text-align: center; min-width: 0; }
    .tx-campaign-media { position: relative; display: block; width: 100%; aspect-ratio: 10 / 7; overflow: hidden; background: var(--tx-card); border-radius: var(--tx-radius); }
    .tx-campaign-media img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transition: transform .8s ease; }
    .tx-campaign-media:hover img { transform: scale(1.03); }
    .tx-campaign h3 { margin: 22px 0 0; font-family: var(--font-brand); font-weight: 700; text-transform: uppercase; font-size: clamp(26px, 2.4vw, 36px); line-height: 1; }
    .tx-campaign p { margin: 10px 0 0; font-size: 18px; text-transform: uppercase; letter-spacing: .03em; color: #333; }
    .tx-campaign .tx-btn { margin-top: 16px; }
    .tx-campaigns-wrap { padding: 0 0 12px; }
    @media (max-width: 767px) { .tx-campaigns { grid-template-columns: 1fr; gap: 36px; } .tx-campaign p { font-size: 15px; } }

    /* Categorías */
    .tx-cats { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(150px, 1fr); gap: 16px; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; padding-bottom: 4px; }
    .tx-cats::-webkit-scrollbar { display: none; }
    .tx-cat { scroll-snap-align: start; display: grid; gap: 12px; justify-items: center; text-decoration: none; color: var(--tx-ink); }
    .tx-cat-media { position: relative; width: 100%; aspect-ratio: 1; overflow: hidden; background: var(--tx-card); border-radius: var(--tx-radius); }
    .tx-cat-media img { width: 100%; height: 100%; object-fit: cover; transition: transform .6s ease; }
    .tx-cat:hover .tx-cat-media img { transform: scale(1.05); }
    .tx-cat strong { font-family: var(--font-brand); font-weight: 700; font-size: 20px; text-transform: uppercase; }
    @media (min-width: 1024px) { .tx-cats { grid-auto-flow: row; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); overflow: visible; } }

    /* Grilla */
    .tx-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 36px 16px; }
    @media (max-width: 1023px) { .tx-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 767px) { .tx-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 28px 10px; } .tx-card-name { font-size: 14px; } .tx-price { font-size: 15px; } .tx-badge { top: 8px; left: 8px; font-size: 12px; } .tx-card-add { width: 34px; height: 34px; top: 8px; right: 8px; } }

    /* Promos automáticas de la tienda */
    .tx-promos { background: var(--tx-ink); color: #fff; }
    .tx-promos .tx-wrap { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 28px; padding-top: 11px; padding-bottom: 11px; font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: .03em; }

    /* ── Catálogo ───────────────────────────────────────────────────── */
    .tx-crumbs { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 26px 0 0; font-size: 15px; color: var(--tx-ink); }
    .tx-crumbs a { font-weight: 700; text-decoration: none; }
    .tx-crumbs a:hover { text-decoration: underline; }
    .tx-crumbs i { width: 4px; height: 4px; border-radius: 99px; background: #9A9A9A; }
    .tx-cat-title { display: flex; align-items: baseline; flex-wrap: wrap; gap: 6px 16px; margin: 22px 0 22px; }
    .tx-cat-title h1 { margin: 0; font-family: var(--font-brand); font-weight: 700; text-transform: uppercase; font-size: clamp(32px, 3.4vw, 46px); line-height: 1; }
    .tx-cat-title span { color: #8C8C8C; font-size: 17px; }
    .tx-subcats { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; margin: -6px 0 18px; }
    .tx-filterbar { position: sticky; top: var(--tx-header-h); z-index: 30; display: flex; align-items: center; gap: 10px; padding: 18px 0; border-top: 1px solid var(--tx-line); border-bottom: 1px solid var(--tx-line); background: var(--t-bg); }
    .tx-filter-icon { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border: 0; background: none; cursor: pointer; color: var(--tx-ink); }
    .tx-filter-icon svg { width: 24px; height: 24px; }
    .tx-filters { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; flex: 1; min-width: 0; }
    .tx-dd { position: relative; }
    .tx-dd > button { display: inline-flex; align-items: center; gap: 22px; height: 42px; padding: 0 16px; border: 1px solid #CFCFCF; border-radius: var(--tx-radius); background: #fff; color: var(--tx-ink); font: 600 16px var(--font-body); cursor: pointer; white-space: nowrap; }
    .tx-dd > button:hover, .tx-dd.is-open > button { border-color: var(--tx-ink); }
    .tx-dd > button.is-on { border-color: var(--tx-ink); box-shadow: inset 0 0 0 1px var(--tx-ink); }
    .tx-dd > button svg { width: 16px; height: 16px; transition: transform .2s; }
    .tx-dd.is-open > button svg { transform: rotate(180deg); }
    .tx-dd-panel { position: absolute; left: 0; top: calc(100% + 6px); z-index: 40; min-width: 260px; max-width: min(92vw, 420px); max-height: 60vh; overflow-y: auto; padding: 14px; background: #fff; border: 1px solid var(--tx-line); box-shadow: 0 18px 40px rgba(0, 0, 0, .12); }
    .tx-dd-panel.is-right { left: auto; right: 0; }
    .tx-opt { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 8px; color: var(--tx-ink); text-decoration: none; font-size: 15px; font-weight: 500; border-radius: 4px; }
    .tx-opt:hover { background: var(--tx-soft); }
    .tx-opt.is-active { font-weight: 700; }
    .tx-opt small { color: #8C8C8C; font-size: 13px; }
    .tx-check { width: 18px; height: 18px; flex-shrink: 0; border: 1.5px solid #9A9A9A; display: inline-grid; place-items: center; margin-right: 10px; }
    .tx-opt.is-active .tx-check { background: var(--tx-ink); border-color: var(--tx-ink); }
    .tx-opt.is-active .tx-check::after { content: ''; width: 9px; height: 5px; border: 2px solid #fff; border-top: 0; border-right: 0; transform: rotate(-45deg) translate(1px, -1px); }
    .tx-sizes { display: grid; grid-template-columns: repeat(auto-fill, minmax(62px, 1fr)); gap: 8px; }
    .tx-size { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 6px; border: 1px solid #CFCFCF; border-radius: var(--tx-radius); background: #fff; color: var(--tx-ink); font: 600 15px var(--font-body); text-decoration: none; cursor: pointer; }
    .tx-size:hover { border-color: var(--tx-ink); }
    .tx-size.is-active { background: var(--tx-ink); border-color: var(--tx-ink); color: #fff; }
    .tx-size.is-out { color: #ABABAB; background: repeating-linear-gradient(135deg, #fff 0 8px, #F4F4F4 8px 9px); cursor: not-allowed; text-decoration: line-through; }
    .tx-price-form { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .tx-input { width: 100%; min-height: 44px; padding: 0 12px; border: 1px solid #CFCFCF; border-radius: var(--tx-radius); background: #fff; font: 500 15px var(--font-body); color: var(--tx-ink); outline: none; }
    .tx-input:focus { border-color: var(--tx-ink); }
    textarea.tx-input { padding: 12px; min-height: 110px; resize: vertical; }
    select.tx-input { padding-right: 32px; }
    .tx-label { display: block; margin-bottom: 6px; font-size: 14px; font-weight: 700; }
    .tx-sort { margin-left: auto; }
    .tx-sort > button { min-width: 250px; justify-content: space-between; font-weight: 700; text-transform: uppercase; }
    .tx-applied { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 16px 0 0; }
    .tx-applied a.tx-chip::after { content: '✕'; font-size: 12px; color: #8C8C8C; }
    .tx-results { padding: 24px 0 64px; }
    .tx-empty { text-align: center; padding: 70px 16px; border: 1px dashed #D0D0D0; border-radius: var(--tx-radius); }
    .tx-empty h2 { margin: 0 0 8px; font-family: var(--font-brand); font-size: 30px; text-transform: uppercase; }
    .tx-empty p { margin: 0 0 22px; color: var(--tx-muted); font-size: 16px; }
    .tx-pager { display: flex; justify-content: center; align-items: center; gap: 6px; margin-top: 50px; flex-wrap: wrap; }
    .tx-pager a, .tx-pager span { display: inline-flex; align-items: center; justify-content: center; min-width: 44px; height: 44px; padding: 0 12px; border: 1px solid var(--tx-line); color: var(--tx-ink); text-decoration: none; font-weight: 700; font-size: 15px; border-radius: var(--tx-radius); }
    .tx-pager a:hover { border-color: var(--tx-ink); }
    .tx-pager span.is-current { background: var(--tx-ink); border-color: var(--tx-ink); color: #fff; }
    .tx-pager span.is-gap { border: 0; }
    .tx-mobile-filter-btn { display: none; }
    @media (max-width: 1023px) {
        .tx-filters, .tx-sort, .tx-filter-icon { display: none !important; }
        .tx-mobile-filter-btn { display: flex; width: 100%; }
        .tx-filterbar { padding: 12px 0; }
    }
    .tx-acc { border-bottom: 1px solid var(--tx-line); }
    .tx-acc > summary { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 2px; font: 700 17px var(--font-body); text-transform: uppercase; cursor: pointer; list-style: none; }
    .tx-acc > summary::-webkit-details-marker { display: none; }
    .tx-acc > summary::after { content: '+'; font-size: 24px; font-weight: 400; line-height: 1; }
    .tx-acc[open] > summary::after { content: '−'; }
    .tx-acc-body { padding: 0 2px 20px; font-size: 16px; line-height: 1.6; color: #333; }

    /* ── Producto ───────────────────────────────────────────────────── */
    .tx-pdp { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(360px, 1fr); gap: 48px; padding: 20px 0 56px; align-items: start; }
    .tx-gallery { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .tx-gallery-item { position: relative; aspect-ratio: 1; background: var(--tx-card); overflow: hidden; border: 0; padding: 0; cursor: zoom-in; border-radius: var(--tx-radius); }
    .tx-gallery-item img, .tx-gallery-item video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .tx-main[data-choice="contain"] .tx-gallery-item img { object-fit: contain; padding: 6%; mix-blend-mode: multiply; }
    .tx-gallery-item.is-wide { grid-column: 1 / -1; aspect-ratio: 16 / 10; }
    .tx-gallery-dots { display: none; }
    .tx-buybox { position: sticky; top: calc(var(--tx-header-h) + 20px); display: grid; gap: 22px; }
    .tx-buybox-kicker { font-size: 14px; text-transform: uppercase; letter-spacing: .04em; color: var(--tx-muted); }
    .tx-buybox h1 { margin: 6px 0 0; font-family: var(--font-body); font-weight: 700; font-size: clamp(26px, 2.2vw, 34px); line-height: 1.15; }
    .tx-buybox .tx-price { font-size: 24px; }
    .tx-buybox .tx-price s { font-size: 19px; }
    .tx-rating { display: inline-flex; align-items: center; gap: 8px; font-size: 14px; color: var(--tx-muted); text-decoration: none; }
    .tx-opt-block { display: grid; gap: 12px; }
    .tx-opt-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: 15px; }
    .tx-opt-head strong { text-transform: uppercase; letter-spacing: .03em; }
    .tx-opt-head button { border: 0; background: none; padding: 0; font: 600 14px var(--font-body); text-decoration: underline; text-underline-offset: 3px; cursor: pointer; color: var(--tx-ink); }
    .tx-swatches { display: flex; flex-wrap: wrap; gap: 8px; }
    .tx-swatch { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 4px 14px 4px 4px; border: 1px solid #CFCFCF; border-radius: var(--tx-radius); background: #fff; font: 600 14px var(--font-body); cursor: pointer; color: var(--tx-ink); }
    .tx-swatch:not(:has(img)) { padding-left: 14px; }
    .tx-swatch img { width: 36px; height: 36px; object-fit: cover; background: var(--tx-card); }
    .tx-swatch.is-active { border-color: var(--tx-ink); box-shadow: inset 0 0 0 1px var(--tx-ink); }
    .tx-swatch.is-out { opacity: .45; text-decoration: line-through; }
    .tx-qty { display: inline-flex; align-items: center; border: 1px solid #CFCFCF; border-radius: var(--tx-radius); height: 54px; }
    .tx-qty button { width: 46px; height: 100%; border: 0; background: none; font-size: 22px; cursor: pointer; color: var(--tx-ink); }
    .tx-qty input { width: 44px; height: 100%; border: 0; text-align: center; font: 700 17px var(--font-body); -moz-appearance: textfield; background: none; }
    .tx-qty input::-webkit-outer-spin-button, .tx-qty input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .tx-buy-row { display: flex; gap: 10px; }
    .tx-buy-row .tx-btn { flex: 1; }
    .tx-note { display: flex; align-items: center; gap: 10px; font-size: 15px; color: #333; }
    .tx-note svg { width: 20px; height: 20px; flex-shrink: 0; }
    .tx-stock { display: inline-flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600; }
    .tx-stock i { width: 8px; height: 8px; border-radius: 99px; background: #1E9E57; }
    .tx-stock.is-warn i { background: #D98A00; }
    .tx-stock.is-out i { background: var(--t-primary); }
    .tx-hint { font-size: 14px; color: var(--t-primary-deep); font-weight: 600; }
    .tx-specs { display: grid; grid-template-columns: max-content 1fr; gap: 8px 22px; margin: 0; }
    .tx-specs dt { color: var(--tx-muted); }
    .tx-specs dd { margin: 0; font-weight: 600; }
    .tx-mobile-buy { display: none; }
    @media (max-width: 1023px) {
        .tx-pdp { grid-template-columns: minmax(0, 1fr); gap: 20px; padding-top: 0; }
        .tx-gallery { display: grid; grid-auto-flow: column; grid-auto-columns: 100%; grid-template-columns: none; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; margin: 0 -16px; gap: 0; }
        .tx-gallery::-webkit-scrollbar { display: none; }
        .tx-gallery-item { scroll-snap-align: start; border-radius: 0; }
        .tx-gallery-item.is-wide { grid-column: auto; aspect-ratio: 1; }
        .tx-gallery-dots { display: flex; justify-content: center; gap: 6px; margin-top: 10px; }
        .tx-gallery-dots i { width: 7px; height: 7px; border-radius: 99px; background: #CFCFCF; }
        .tx-gallery-dots i.is-active { background: var(--tx-ink); }
        .tx-buybox { position: static; }
        .tx-mobile-buy { position: fixed; left: 0; right: 0; bottom: 0; z-index: 45; display: flex; gap: 10px; align-items: center; padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); background: #fff; border-top: 1px solid var(--tx-line); box-shadow: 0 -8px 24px rgba(0, 0, 0, .08); transform: translateY(110%); transition: transform .25s ease; }
        .tx-mobile-buy.is-visible { transform: none; }
        .tx-mobile-buy .tx-btn { flex: 1; min-height: 48px; }
        .tx-mobile-buy strong { font-size: 17px; white-space: nowrap; }
    }

    /* Modal genérico (guía de tallas, zoom) */
    .tx-modal { position: fixed; inset: 0; z-index: 95; display: grid; place-items: center; padding: 16px; background: rgba(0, 0, 0, .6); animation: tx-fade .2s ease; }
    .tx-modal-card { position: relative; width: min(560px, 100%); max-height: calc(100vh - 32px); overflow: auto; background: #fff; color: var(--tx-ink); padding: 28px; border-radius: var(--tx-radius); }
    .tx-modal-card h2 { margin: 0 0 16px; font-family: var(--font-brand); font-size: 30px; text-transform: uppercase; }
    .tx-modal-close { position: absolute; top: 12px; right: 12px; width: 40px; height: 40px; border: 0; background: none; cursor: pointer; display: grid; place-items: center; color: var(--tx-ink); }
    .tx-modal-close svg { width: 22px; height: 22px; }
    .tx-zoom { position: fixed; inset: 0; z-index: 95; background: #fff; display: flex; align-items: center; justify-content: center; }
    .tx-zoom img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .tx-guide-list { margin: 0; padding: 0; list-style: none; display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 6px 16px; font-size: 16px; }
    .tx-guide-list li { padding: 8px 0; border-bottom: 1px solid var(--tx-line); }

    /* ── Contacto / Libro de reclamaciones ──────────────────────────── */
    .tx-page-head { padding: 34px 0 10px; }
    .tx-page-head h1 { margin: 0; font-family: var(--font-brand); font-weight: 700; text-transform: uppercase; font-size: clamp(34px, 3.6vw, 52px); line-height: 1; }
    .tx-page-head p { margin: 12px 0 0; font-size: 17px; color: #444; max-width: 720px; }
    .tx-contact { display: grid; grid-template-columns: 1fr 1.6fr; gap: 48px; padding: 30px 0 70px; }
    .tx-contact-info { display: grid; gap: 22px; align-content: start; }
    .tx-contact-info h3 { margin: 0 0 6px; font-size: 15px; text-transform: uppercase; letter-spacing: .05em; }
    .tx-contact-info p, .tx-contact-info a { margin: 0; font-size: 17px; color: #333; text-decoration: none; }
    .tx-form { display: grid; gap: 16px; }
    .tx-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .tx-radio-row { display: flex; flex-wrap: wrap; gap: 10px; }
    .tx-radio { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border: 1px solid #CFCFCF; cursor: pointer; font-weight: 600; border-radius: var(--tx-radius); }
    .tx-radio:has(input:checked) { border-color: var(--tx-ink); box-shadow: inset 0 0 0 1px var(--tx-ink); }
    .tx-alert { padding: 14px 16px; border-left: 4px solid #1E9E57; background: #EFF8F2; font-weight: 600; font-size: 15px; }
    .tx-alert.is-error { border-color: var(--t-primary); background: var(--t-primary-50); }
    .tx-legal-box { padding: 16px; background: var(--tx-soft); font-size: 14px; line-height: 1.55; color: #444; border-radius: var(--tx-radius); }
    .tx-tabs { display: flex; gap: 0; border-bottom: 1px solid var(--tx-line); margin-bottom: 26px; }
    .tx-tabs a { padding: 14px 20px; font-weight: 700; font-size: 16px; text-transform: uppercase; text-decoration: none; color: var(--tx-muted); border-bottom: 3px solid transparent; margin-bottom: -1px; }
    .tx-tabs a.is-active { color: var(--tx-ink); border-color: var(--tx-ink); }
    @media (max-width: 1023px) { .tx-contact { grid-template-columns: 1fr; gap: 30px; } }
    @media (max-width: 639px) { .tx-form-row { grid-template-columns: 1fr; } }

    /* Galería */
    .tx-lookbook { columns: 3 280px; column-gap: 12px; padding: 20px 0 70px; }
    .tx-look { position: relative; display: block; margin-bottom: 12px; break-inside: avoid; overflow: hidden; background: var(--tx-card); border-radius: var(--tx-radius); color: #fff; text-decoration: none; }
    .tx-look img { display: block; width: 100%; height: auto; transition: transform .7s ease; }
    .tx-look:hover img { transform: scale(1.03); }
    .tx-look figcaption { position: absolute; left: 0; right: 0; bottom: 0; padding: 40px 18px 16px; background: linear-gradient(0deg, rgba(0, 0, 0, .65), rgba(0, 0, 0, 0)); }
    .tx-look figcaption strong { display: block; font-family: var(--font-brand); font-size: 24px; text-transform: uppercase; line-height: 1; }
    .tx-look figcaption span { font-size: 14px; opacity: .9; }

    /* ── Footer ─────────────────────────────────────────────────────── */
    .tx-footer { background: color-mix(in srgb, var(--t-secondary) 55%, #0B0F1C); color: #fff; margin-top: 30px; }
    .tx-main:has(> .tx-visit:last-child) + .tx-footer { margin-top: 0; border-top: 1px solid rgba(255, 255, 255, .08); }
    .tx-footer-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)) 1.3fr; gap: 40px; padding-top: 52px; padding-bottom: 52px; }
    .tx-footer h4 { margin: 0 0 22px; font-size: 16px; font-weight: 700; text-transform: uppercase; letter-spacing: .02em; }
    .tx-footer ul { margin: 0; padding: 0; list-style: none; display: grid; gap: 12px; }
    .tx-footer li a, .tx-footer li button { border: 0; background: none; padding: 0; color: #fff; font: 400 16px var(--font-body); text-decoration: none; cursor: pointer; text-align: left; }
    .tx-footer li a:hover, .tx-footer li button:hover { text-decoration: underline; text-underline-offset: 3px; }
    .tx-footer-about { font-size: 15px; line-height: 1.6; color: #BDBDBD; margin: 0 0 16px; }
    .tx-libro { display: inline-flex; align-items: center; gap: 14px; margin-top: 22px; padding: 12px 16px; background: #fff; color: var(--tx-ink) !important; font: 700 15px var(--font-body); text-transform: uppercase; text-decoration: none; border-radius: var(--tx-radius); }
    .tx-libro svg { width: 22px; height: 22px; }
    .tx-news p { margin: 0 0 18px; font-size: 16px; color: #E0E0E0; }
    .tx-news form { display: flex; align-items: stretch; gap: 0; }
    .tx-news input { flex: 1; min-width: 0; height: 46px; padding: 0 12px; border: 0; background: #fff; color: var(--tx-ink); font: 400 16px var(--font-body); outline: none; border-radius: var(--tx-radius) 0 0 var(--tx-radius); }
    .tx-news button { border: 0; background: none; color: #fff; padding: 0 18px; font: 700 16px var(--font-body); text-transform: uppercase; cursor: pointer; }
    .tx-news button:hover { text-decoration: underline; }
    .tx-news-ok { font-weight: 600; color: #9BE3B4; }
    .tx-social { display: flex; gap: 22px; margin-top: 26px; }
    .tx-social a { color: #fff; display: inline-flex; }
    .tx-social svg { width: 26px; height: 26px; }
    .tx-footer-bottom { border-top: 1px solid #333; }
    .tx-footer-bottom .tx-wrap { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; padding-top: 22px; padding-bottom: 22px; font-size: 13px; }
    .tx-footer-bottom nav { display: flex; flex-wrap: wrap; gap: 8px 18px; }
    .tx-footer-bottom a, .tx-footer-bottom button { color: #fff; text-decoration: none; background: none; border: 0; padding: 0; font: 400 13px var(--font-body); cursor: pointer; }
    .tx-footer-bottom a:hover { text-decoration: underline; }
    .tx-pay { display: flex; gap: 8px; flex-wrap: wrap; }
    .tx-pay span { display: inline-flex; align-items: center; justify-content: center; height: 26px; min-width: 42px; padding: 0 6px; background: #fff; color: #1a1f71; border-radius: 3px; font: 800 11px var(--font-body); letter-spacing: .02em; }
    .tx-footer-copy { color: #BDBDBD; font-size: 13px; }
    @media (max-width: 1023px) { .tx-footer-grid { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 639px) { .tx-footer-grid { grid-template-columns: 1fr; gap: 30px; } }

    /* Pestaña "Regístrate" y WhatsApp */
    .tx-signup { position: fixed; left: 24px; bottom: 24px; z-index: 44; display: inline-flex; align-items: center; background: #fff; border: 1px solid var(--tx-ink); box-shadow: 0 6px 20px rgba(0, 0, 0, .12); border-radius: var(--tx-radius); }
    .tx-signup button { border: 0; background: none; cursor: pointer; color: var(--tx-ink); }
    .tx-signup .tx-signup-main { padding: 12px 6px 12px 16px; font: 700 15px var(--font-body); text-transform: uppercase; letter-spacing: .02em; }
    .tx-signup .tx-signup-x { padding: 10px 12px; display: grid; place-items: center; }
    .tx-signup svg { width: 18px; height: 18px; }
    @media (max-width: 767px) { .tx-signup { left: 12px; bottom: 12px; } .tx-signup .tx-signup-main { font-size: 13px; padding: 10px 4px 10px 12px; } }
    body.tx-has-mobile-buy .tx-signup { display: none; }

    @media (prefers-reduced-motion: reduce) {
        .tx-slide, .tx-slide.is-active { transition: none; }
        .tx-card-media img, .tx-tile img, .tx-campaign-media img { transition: none; }
    }
</style>
