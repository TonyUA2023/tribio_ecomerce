{{-- Sport Pro: estilos propios de la plantilla (prefijo sp-). CSS plano a propósito: no depende
     de clases nuevas de Tailwind, así que ningún cambio aquí requiere recompilar con Vite. --}}
<style>
    :root {
        --sp-ink: #111111;
        --sp-muted: #6D6D6D;
        --sp-line: #E3E3E3;
        --sp-soft: #F4F4F4;
        --sp-card: #F6F6F6;
        --sp-radius: 0px;
        --sp-header-h: 80px;
        --sp-head-bg: #111111;
        --sp-head-fg: #FFFFFF;
        --sp-head-line: rgba(255, 255, 255, .55);
        --font-body: 'Barlow', 'Helvetica Neue', Arial, sans-serif;
    }
    body[data-choice="soft"] { --sp-radius: 10px; }
    .sp-header[data-choice="light"] { --sp-head-bg: #FFFFFF; --sp-head-fg: #111111; --sp-head-line: #111111; }
    @media (max-width: 1023px) { :root { --sp-header-h: 60px; } }

    .sp-body { margin: 0; font-family: var(--font-body); color: var(--sp-ink); background: var(--t-bg); -webkit-font-smoothing: antialiased; }
    .sp-body *, .sp-body *::before, .sp-body *::after { box-sizing: border-box; }
    .sp-body a { color: inherit; }
    .sp-body img { max-width: 100%; }
    .sp-wrap { width: 100%; max-width: 1560px; margin: 0 auto; padding: 0 16px; }
    .sp-narrow { width: 100%; max-width: 1340px; margin: 0 auto; padding: 0 16px; }
    @media (min-width: 768px) { .sp-wrap, .sp-narrow { padding: 0 32px; } }
    .sp-sr { position: absolute !important; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
    [x-cloak] { display: none !important; }
    .sp-brand { font-family: var(--font-brand); }
    [hidden] { display: none !important; }

    /* Botones */
    .sp-btn { display: inline-flex; align-items: center; justify-content: center; gap: 10px; min-height: 44px; padding: 0 22px; border: 2px solid var(--sp-ink); border-radius: var(--sp-radius);
        background: var(--sp-ink); color: #fff !important; font: 700 15px/1 var(--font-body); letter-spacing: .04em; text-transform: uppercase; text-decoration: none; cursor: pointer; transition: background-color .2s, color .2s, border-color .2s; }
    .sp-btn:hover { background: #333; border-color: #333; }
    .sp-btn.is-white { background: #fff; border-color: #fff; color: var(--sp-ink) !important; }
    .sp-btn.is-white:hover { background: var(--sp-ink); border-color: var(--sp-ink); color: #fff !important; }
    .sp-btn.is-outline { background: transparent; color: var(--sp-ink) !important; }
    .sp-btn.is-outline:hover { background: var(--sp-ink); color: #fff !important; }
    .sp-btn.is-block { width: 100%; min-height: 54px; font-size: 16px; }
    .sp-btn:disabled { background: #C9C9C9; border-color: #C9C9C9; color: #fff !important; cursor: not-allowed; }
    .sp-link { font-weight: 700; font-size: 14px; letter-spacing: .04em; text-transform: uppercase; text-decoration: underline; text-underline-offset: 4px; }

    /* ── Franja de mensajes ─────────────────────────────────────────── */
    .sp-utility { background: #fff; border-bottom: 1px solid var(--sp-line); font-size: 12px; font-weight: 600; letter-spacing: .02em; text-transform: uppercase; color: var(--sp-ink); }
    .sp-utility .sp-wrap { display: flex; align-items: center; justify-content: center; min-height: 40px; gap: 16px; position: relative; }
    .sp-utility-msgs { display: flex; align-items: center; justify-content: center; flex: 1; min-width: 0; }
    .sp-utility-msgs span { padding: 0 16px; border-left: 1px solid #BDBDBD; white-space: nowrap; }
    .sp-utility-msgs span:first-child { border-left: 0; }
    .sp-utility-links { position: absolute; right: 32px; top: 0; bottom: 0; display: flex; align-items: center; gap: 0; }
    .sp-utility-links > * { height: 100%; border-left: 1px solid var(--sp-line); }
    .sp-utility-links a, .sp-utility-links > * > button, .sp-utility-links > button { display: inline-flex; align-items: center; gap: 7px; height: 100%; padding: 0 14px; border: 0; background: none; font: 500 12px var(--font-body); text-transform: uppercase; color: var(--sp-muted); text-decoration: none; cursor: pointer; white-space: nowrap; }
    .sp-utility-links a:hover, .sp-utility-links button:hover { color: var(--sp-ink); }
    .sp-utility-links .sp-drop button { height: auto; padding: 10px 12px; text-transform: none; font-size: 14px; color: var(--sp-ink); justify-content: space-between; width: 100%; }
    .sp-utility-links svg { width: 15px; height: 15px; }
    .sp-utility-links img { width: 17px; height: 12px; object-fit: cover; }
    @media (max-width: 1279px) { .sp-utility-links { display: none; } }
    @media (max-width: 1023px) {
        .sp-utility-msgs span { display: none; border: 0; }
        .sp-utility-msgs span.is-current { display: block; animation: sp-fade .4s ease; }
    }
    @keyframes sp-fade { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }

    /* ── Header ─────────────────────────────────────────────────────── */
    .sp-header { position: sticky; top: 0; z-index: 50; height: var(--sp-header-h); background: var(--sp-head-bg); color: var(--sp-head-fg); border-bottom: 1px solid rgba(127, 127, 127, .18); }
    .sp-header-row { height: 100%; display: flex; align-items: center; gap: 24px; }
    .sp-logo { display: inline-flex; align-items: center; flex-shrink: 0; text-decoration: none; color: inherit; margin-right: 18px; }
    .sp-logo img { height: 46px; width: auto; max-width: 200px; object-fit: contain; }
    .sp-logo span { font-family: var(--font-brand); font-size: 28px; font-weight: 800; letter-spacing: .01em; text-transform: uppercase; line-height: 1; white-space: nowrap; }
    .sp-header[data-choice="dark"] .sp-logo img[data-transparent][data-choice="white"] { filter: brightness(0) invert(1); }
    .sp-header[data-choice="dark"] .sp-logo img[data-opaque] { border-radius: 4px; }
    .sp-nav { display: flex; align-items: center; gap: 4px 34px; flex-wrap: wrap; min-width: 0; flex: 1; max-height: 100%; overflow: hidden; }
    .sp-nav a { position: relative; display: inline-flex; align-items: center; height: var(--sp-header-h); color: inherit; text-decoration: none; font-weight: 700; font-size: 17px; white-space: nowrap; }
    .sp-nav a::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: currentColor; transform: scaleX(0); transition: transform .2s; }
    .sp-nav a:hover::after, .sp-nav a.is-active::after { transform: scaleX(1); }
    .sp-nav a.is-sale { color: var(--t-primary); }
    .sp-header[data-choice="dark"] .sp-nav a.is-sale { color: var(--t-primary-300); }
    .sp-tools { display: flex; align-items: center; gap: 8px; margin-left: auto; }
    .sp-search-btn { display: inline-flex; align-items: center; gap: 12px; height: 42px; min-width: 150px; padding: 0 18px; border: 1px solid var(--sp-head-line); border-radius: var(--sp-radius); background: transparent; color: inherit; font: 700 16px var(--font-body); text-transform: uppercase; cursor: pointer; margin-right: 14px; }
    .sp-search-btn:hover { background: rgba(127, 127, 127, .18); }
    .sp-search-btn svg { width: 19px; height: 19px; }
    .sp-icon-btn { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border: 0; border-radius: 999px; background: none; color: inherit; cursor: pointer; text-decoration: none; }
    .sp-icon-btn:hover { background: rgba(127, 127, 127, .18); }
    .sp-icon-btn svg { width: 24px; height: 24px; }
    .sp-count { position: absolute; top: 4px; right: 2px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: var(--t-primary); color: var(--t-on-primary); font: 700 11px/18px var(--font-body); text-align: center; }
    .sp-count:empty, .sp-count[data-empty] { display: none; }
    .sp-only-mobile { display: none !important; }
    @media (max-width: 1280px) { .sp-nav { gap: 4px 24px; } .sp-nav a { font-size: 16px; } }
    @media (max-width: 1023px) {
        .sp-nav, .sp-search-btn, .sp-hide-mobile { display: none !important; }
        .sp-only-mobile { display: inline-flex !important; }
        .sp-header-row { gap: 4px; }
        .sp-logo { position: absolute; left: 50%; transform: translateX(-50%); margin: 0; }
        .sp-logo img { height: 36px; max-width: 150px; }
        .sp-logo span { font-size: 22px; }
        .sp-tools { gap: 0; }
    }
    .sp-shipbar { display: block; background: var(--sp-soft); color: var(--sp-ink); text-align: center; padding: 12px 16px; font: 700 15px var(--font-body); letter-spacing: .02em; text-transform: uppercase; text-decoration: none; border-bottom: 1px solid var(--sp-line); }
    a.sp-shipbar:hover { text-decoration: underline; text-underline-offset: 3px; }
    @media (max-width: 767px) { .sp-shipbar { font-size: 13px; padding: 10px 12px; } }

    /* Búsqueda */
    .sp-search-panel { position: fixed; inset: 0; z-index: 80; display: flex; flex-direction: column; }
    .sp-search-backdrop { position: absolute; inset: 0; background: rgba(0, 0, 0, .5); }
    .sp-search-box { position: relative; background: #fff; color: var(--sp-ink); padding: 28px 0 34px; box-shadow: 0 20px 60px rgba(0, 0, 0, .2); }
    .sp-search-box form { display: flex; align-items: center; gap: 14px; border-bottom: 2px solid var(--sp-ink); }
    .sp-search-box input { flex: 1; min-width: 0; border: 0; outline: none; background: none; padding: 12px 0; font: 600 26px var(--font-body); color: var(--sp-ink); }
    .sp-search-box input::-webkit-search-cancel-button { display: none; }
    .sp-search-box svg { width: 26px; height: 26px; flex-shrink: 0; }
    .sp-search-hints { margin-top: 20px; display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
    .sp-search-hints strong { font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: var(--sp-muted); margin-right: 6px; }
    .sp-chip { display: inline-flex; align-items: center; gap: 8px; min-height: 36px; padding: 0 14px; border: 1px solid var(--sp-line); border-radius: 999px; background: #fff; color: var(--sp-ink); font: 600 14px var(--font-body); text-decoration: none; cursor: pointer; white-space: nowrap; }
    .sp-chip:hover { border-color: var(--sp-ink); }
    .sp-chip.is-active { background: var(--sp-ink); border-color: var(--sp-ink); color: #fff; }
    @media (max-width: 767px) { .sp-search-box input { font-size: 20px; } }

    /* Menú móvil */
    .sp-drawer { position: fixed; inset: 0; z-index: 80; display: flex; }
    .sp-drawer-backdrop { position: absolute; inset: 0; background: rgba(0, 0, 0, .55); }
    .sp-drawer-panel { position: relative; width: min(90%, 400px); height: 100%; background: #fff; color: var(--sp-ink); display: flex; flex-direction: column; box-shadow: 0 0 60px rgba(0, 0, 0, .3); }
    .sp-drawer-panel.is-right { margin-left: auto; }
    .sp-drawer-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--sp-line); }
    .sp-drawer-head strong { font: 700 18px var(--font-body); text-transform: uppercase; letter-spacing: .03em; }
    .sp-drawer-body { flex: 1; overflow-y: auto; padding: 8px 16px 24px; }
    .sp-drawer-links a, .sp-drawer-links button { display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 16px 2px; border: 0; border-bottom: 1px solid var(--sp-line); background: none; color: var(--sp-ink); font: 700 18px var(--font-body); text-decoration: none; text-align: left; cursor: pointer; }
    .sp-drawer-links a.is-sale { color: var(--t-primary); }
    .sp-drawer-links.is-small a, .sp-drawer-links.is-small button { font-size: 15px; font-weight: 500; padding: 13px 2px; }
    .sp-drawer-foot { display: grid; gap: 12px; margin-top: 20px; padding: 14px; background: var(--sp-soft); border-radius: var(--sp-radius); font-size: 14px; font-weight: 600; }
    .sp-drawer-foot > div { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .sp-drawer-foot select { border: 1px solid var(--sp-line); background: #fff; padding: 8px 10px; font: 600 14px var(--font-body); border-radius: var(--sp-radius); }
    .sp-seg { display: inline-flex; border: 1px solid var(--sp-ink); }
    .sp-seg button { border: 0; background: #fff; padding: 6px 14px; font: 700 13px var(--font-body); cursor: pointer; }
    .sp-seg button.is-active { background: var(--sp-ink); color: #fff; }
    .sp-tr { transition: transform .28s ease; }
    .sp-off-left { transform: translateX(-100%); }
    .sp-off-right { transform: translateX(100%); }
    .sp-on { transform: none; }
    .sp-drop { position: absolute; right: 0; top: calc(100% + 4px); z-index: 60; min-width: 220px; padding: 6px; background: #fff; color: var(--sp-ink); border: 1px solid var(--sp-line); box-shadow: 0 16px 40px rgba(0, 0, 0, .15); text-transform: none; }
    .sp-drop button { display: flex; width: 100%; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; border: 0; background: none; font: 600 14px var(--font-body); color: var(--sp-ink); cursor: pointer; text-align: left; }
    .sp-drop button:hover { background: var(--sp-soft); }
    .sp-drop button.is-active { color: var(--t-primary); }
    .sp-drop img { width: 18px; height: 13px; object-fit: cover; }

    /* ── Hero ───────────────────────────────────────────────────────── */
    .sp-hero { position: relative; overflow: hidden; background: #1a1a1a; color: #fff; }
    .sp-hero-track { position: relative; height: min(76vh, 760px); min-height: 460px; }
    .sp-hero[data-choice="full"] .sp-hero-track { height: calc(100vh - var(--sp-header-h)); height: calc(100svh - var(--sp-header-h)); }
    .sp-hero[data-choice="medium"] .sp-hero-track { height: min(58vh, 560px); min-height: 380px; }
    @media (max-width: 767px) {
        .sp-hero-track { height: min(125vw, 80svh); min-height: 460px; }
        .sp-hero[data-choice="medium"] .sp-hero-track { height: min(100vw, 64svh); min-height: 360px; }
    }
    .sp-slide { position: absolute; inset: 0; opacity: 0; visibility: hidden; transition: opacity .7s ease, visibility 0s linear .7s; }
    .sp-slide.is-active { opacity: 1; visibility: visible; transition: opacity .7s ease; z-index: 1; }
    .sp-slide-bg { position: absolute; inset: 0; background: linear-gradient(120deg, #0d0d0d 0%, #2a2a2a 55%, var(--t-primary-deep) 100%); }
    .sp-slide[data-choice="dark"] .sp-slide-bg { background: linear-gradient(120deg, #f5f5f5 0%, #e9e9e9 60%, var(--t-secondary-50) 100%); }
    .sp-slide-bg picture, .sp-slide-bg img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .sp-slide-scrim { position: absolute; inset: 0; pointer-events: none; opacity: 0; }
    .sp-slide[data-has-image] .sp-slide-scrim { opacity: 1; }
    .sp-slide[data-choice="light"] .sp-slide-scrim { background: linear-gradient(90deg, rgba(0, 0, 0, .55) 0%, rgba(0, 0, 0, .12) 55%, rgba(0, 0, 0, 0) 75%); }
    .sp-slide[data-choice="light"] .sp-slide-scrim[data-choice="center"] { background: radial-gradient(60% 70% at 50% 50%, rgba(0, 0, 0, .45), rgba(0, 0, 0, .1)); }
    .sp-slide[data-choice="light"] .sp-slide-scrim[data-choice="bottom"] { background: linear-gradient(0deg, rgba(0, 0, 0, .6), rgba(0, 0, 0, 0) 60%); }
    .sp-slide[data-choice="dark"] .sp-slide-scrim { background: linear-gradient(90deg, rgba(255, 255, 255, .7), rgba(255, 255, 255, 0) 60%); }
    .sp-slide[data-choice="image"] .sp-slide-scrim, .sp-slide[data-choice="image"] .sp-slide-copy { display: none; }
    .sp-slide-link { position: absolute; inset: 0; z-index: 2; display: none; }
    .sp-slide[data-choice="image"] .sp-slide-link { display: block; }
    .sp-slide-copy { position: relative; z-index: 3; height: 100%; display: flex; flex-direction: column; justify-content: center; padding-top: 32px; padding-bottom: 64px; }
    .sp-slide-copy .sp-copy { max-width: 820px; display: flex; flex-direction: column; align-items: flex-start; gap: 14px; }
    .sp-slide-copy[data-choice="center"] { align-items: center; text-align: center; }
    .sp-slide-copy[data-choice="center"] .sp-copy { align-items: center; margin: 0 auto; }
    .sp-slide-copy[data-choice="bottom"] { justify-content: flex-end; padding-bottom: 56px; }
    .sp-slide-copy[data-choice="bottom"] .sp-copy { align-items: center; text-align: center; margin: 0 auto; }
    .sp-slide[data-choice="dark"] .sp-copy { color: var(--sp-ink); }
    .sp-hero-title { margin: 0; font-family: var(--font-brand); font-weight: 700; text-transform: uppercase; line-height: .9; letter-spacing: 0; font-size: clamp(44px, 7vw, 124px); text-wrap: balance; }
    .sp-slide-copy[data-choice="left"] .sp-hero-title { text-align: left; }
    .sp-hero-sub { margin: 0; color: var(--t-secondary); font-family: var(--font-brand); font-weight: 600; text-transform: uppercase; letter-spacing: .28em; font-size: clamp(16px, 2.2vw, 36px); }
    .sp-slide[data-choice="dark"] .sp-hero-sub { color: var(--t-secondary-dark); }
    .sp-hero-cta { margin-top: 12px; }
    .sp-slide[data-choice="dark"] .sp-hero-cta { background: var(--sp-ink); border-color: var(--sp-ink); color: #fff !important; }
    .sp-hero-arrow { position: absolute; bottom: 20px; z-index: 5; width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 999px; background: rgba(255, 255, 255, .9); color: var(--sp-ink); cursor: pointer; box-shadow: 0 4px 14px rgba(0, 0, 0, .15); }
    .sp-hero-arrow.is-prev { right: 74px; } .sp-hero-arrow.is-next { right: 22px; }
    .sp-hero-arrow svg { width: 22px; height: 22px; }
    .sp-hero-dots { position: absolute; left: 50%; bottom: 20px; z-index: 5; transform: translateX(-50%); display: flex; gap: 8px; }
    .sp-hero-dots button { width: 34px; height: 4px; padding: 0; border: 0; background: rgba(255, 255, 255, .45); cursor: pointer; }
    .sp-hero-dots button.is-active { background: #fff; }
    .sp-hero[data-tone="dark"] .sp-hero-dots button { background: rgba(0, 0, 0, .25); }
    .sp-hero[data-tone="dark"] .sp-hero-dots button.is-active { background: var(--sp-ink); }
    @media (max-width: 767px) {
        .sp-hero-arrow { display: none; }
        .sp-slide-copy { justify-content: flex-end; padding-bottom: 54px; }
        .sp-slide-copy .sp-copy { gap: 10px; }
        .sp-slide[data-choice="light"] .sp-slide-scrim[data-choice] { background: linear-gradient(0deg, rgba(0, 0, 0, .65), rgba(0, 0, 0, 0) 65%); }
        .sp-hero-sub { letter-spacing: .18em; }
    }

    /* ── Secciones ──────────────────────────────────────────────────── */
    .sp-section { padding: 48px 0; }
    .sp-section.is-tight { padding: 28px 0 40px; }
    .sp-h2 { margin: 0 0 24px; font-family: var(--font-brand); font-weight: 700; text-transform: uppercase; font-size: clamp(26px, 2.6vw, 36px); line-height: 1.05; text-align: center; letter-spacing: .01em; }
    .sp-h2.is-left { text-align: left; }
    .sp-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
    .sp-head .sp-h2 { margin: 0; }
    @media (max-width: 767px) { .sp-section { padding: 36px 0; } }

    /* Carrusel de productos */
    .sp-rail { position: relative; }
    .sp-rail-track { display: grid; grid-auto-flow: column; grid-auto-columns: calc((100% - 3 * 16px) / 4); gap: 16px; overflow-x: auto; scroll-snap-type: x mandatory; scroll-behavior: smooth; scrollbar-width: none; }
    .sp-rail-track::-webkit-scrollbar { display: none; }
    .sp-rail-track > * { scroll-snap-align: start; }
    .sp-rail-btn { position: absolute; top: calc(var(--sp-rail-img, 300px) / 2); z-index: 4; width: 44px; height: 44px; transform: translateY(-50%); display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 999px; background: #fff; color: var(--sp-ink); box-shadow: 0 3px 12px rgba(0, 0, 0, .15); cursor: pointer; transition: opacity .2s; }
    .sp-rail-btn.is-prev { left: 12px; } .sp-rail-btn.is-next { right: 12px; }
    .sp-rail-btn:disabled { opacity: 0; pointer-events: none; }
    .sp-rail-btn svg { width: 20px; height: 20px; }
    .sp-rail-progress { position: relative; height: 5px; margin-top: 30px; background: #E4E4E4; border-radius: 99px; overflow: hidden; }
    .sp-rail-progress i { position: absolute; top: 0; bottom: 0; left: 0; background: #585858; border-radius: 99px; transition: transform .2s ease, width .2s ease; }
    @media (max-width: 1023px) { .sp-rail-track { grid-auto-columns: calc((100% - 2 * 12px) / 2.6); gap: 12px; } }
    @media (max-width: 639px) { .sp-rail-track { grid-auto-columns: 66%; } .sp-rail-btn { display: none; } }

    /* Tarjeta de producto */
    .sp-card { position: relative; display: flex; flex-direction: column; min-width: 0; }
    .sp-card-media { position: relative; display: block; aspect-ratio: 1 / 1; overflow: hidden; background: var(--sp-card); border-radius: var(--sp-radius); }
    .sp-card-media img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transition: opacity .35s ease, transform .6s ease; }
    .sp-main[data-choice="contain"] .sp-card-media img { object-fit: contain; padding: 8%; mix-blend-mode: multiply; }
    .sp-card-media img.is-alt { opacity: 0; }
    .sp-card:hover .sp-card-media img.is-alt { opacity: 1; }
    .sp-card:hover .sp-card-media.has-alt img:not(.is-alt) { opacity: 0; }
    .sp-card:hover .sp-card-media:not(.has-alt) img { transform: scale(1.04); }
    .sp-badge { position: absolute; top: 14px; left: 14px; z-index: 2; padding: 4px 9px; background: var(--t-primary); color: var(--t-on-primary); font: 700 13px var(--font-body); border-radius: calc(var(--sp-radius) / 2); }
    .sp-badge.is-dark { background: var(--sp-ink); color: #fff; }
    .sp-card-add { position: absolute; top: 12px; right: 12px; z-index: 3; width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 999px; background: #fff; color: var(--sp-ink); box-shadow: 0 2px 8px rgba(0, 0, 0, .08); cursor: pointer; text-decoration: none; transition: background-color .2s, color .2s; }
    .sp-card-add:hover { background: var(--sp-ink); color: #fff; }
    .sp-card-add svg { width: 19px; height: 19px; }
    .sp-card-info { padding: 16px 2px 4px; display: grid; gap: 6px; }
    .sp-card-meta { font-size: 13px; text-transform: uppercase; letter-spacing: .02em; color: var(--sp-muted); }
    .sp-card-name { font-size: 16px; font-weight: 600; line-height: 1.3; color: var(--sp-ink); text-decoration: none; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .sp-card-name:hover { text-decoration: underline; }
    .sp-price { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 10px; font-size: 17px; font-weight: 700; }
    .sp-price .is-sale { color: var(--t-primary); }
    .sp-price s { color: #8C8C8C; font-weight: 500; font-size: 15px; }
    .sp-tag { display: inline-block; padding: 3px 8px; background: var(--sp-ink); color: #fff; font: 700 12px var(--font-body); letter-spacing: .04em; text-transform: uppercase; }

    /* Tarjetas "Lo nuevo" */
    .sp-tiles { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
    .sp-tile { position: relative; display: block; overflow: hidden; aspect-ratio: 4 / 5; background: var(--sp-card); color: #fff; text-decoration: none; border-radius: var(--sp-radius); }
    .sp-tile img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transition: transform .8s ease; }
    .sp-tile:hover img { transform: scale(1.04); }
    .sp-tile-fallback { position: absolute; inset: 0; background: linear-gradient(160deg, #3b3b3b, #0f0f0f); }
    .sp-tile::after { content: ''; position: absolute; inset: 40% 0 0; background: linear-gradient(0deg, rgba(0, 0, 0, .72), rgba(0, 0, 0, 0)); }
    .sp-tile-copy { position: absolute; left: 0; right: 0; bottom: 0; z-index: 2; padding: 24px; display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; }
    .sp-tile-copy h3 { margin: 0; font-family: var(--font-brand); font-weight: 700; text-transform: uppercase; font-size: clamp(26px, 2.6vw, 38px); line-height: 1; }
    @media (max-width: 767px) { .sp-tiles { grid-template-columns: repeat(3, 78%); overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; } .sp-tile { scroll-snap-align: start; } .sp-tile-copy { padding: 16px; } }

    /* Campañas */
    .sp-campaigns { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 16px; }
    .sp-campaign { display: flex; flex-direction: column; align-items: center; text-align: center; min-width: 0; }
    .sp-campaign-media { position: relative; display: block; width: 100%; aspect-ratio: 10 / 7; overflow: hidden; background: var(--sp-card); border-radius: var(--sp-radius); }
    .sp-campaign-media img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transition: transform .8s ease; }
    .sp-campaign-media:hover img { transform: scale(1.03); }
    .sp-campaign h3 { margin: 22px 0 0; font-family: var(--font-brand); font-weight: 700; text-transform: uppercase; font-size: clamp(26px, 2.4vw, 36px); line-height: 1; }
    .sp-campaign p { margin: 10px 0 0; font-size: 18px; text-transform: uppercase; letter-spacing: .03em; color: #333; }
    .sp-campaign .sp-btn { margin-top: 16px; }
    .sp-campaigns-wrap { padding: 0 0 12px; }
    @media (max-width: 767px) { .sp-campaigns { grid-template-columns: 1fr; gap: 36px; } .sp-campaign p { font-size: 15px; } }

    /* Categorías */
    .sp-cats { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(150px, 1fr); gap: 16px; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; padding-bottom: 4px; }
    .sp-cats::-webkit-scrollbar { display: none; }
    .sp-cat { scroll-snap-align: start; display: grid; gap: 12px; justify-items: center; text-decoration: none; color: var(--sp-ink); }
    .sp-cat-media { position: relative; width: 100%; aspect-ratio: 1; overflow: hidden; background: var(--sp-card); border-radius: var(--sp-radius); }
    .sp-cat-media img { width: 100%; height: 100%; object-fit: cover; transition: transform .6s ease; }
    .sp-cat:hover .sp-cat-media img { transform: scale(1.05); }
    .sp-cat strong { font-family: var(--font-brand); font-weight: 700; font-size: 20px; text-transform: uppercase; }
    @media (min-width: 1024px) { .sp-cats { grid-auto-flow: row; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); overflow: visible; } }

    /* Grilla */
    .sp-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 36px 16px; }
    @media (max-width: 1023px) { .sp-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 767px) { .sp-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 28px 10px; } .sp-card-name { font-size: 14px; } .sp-price { font-size: 15px; } .sp-badge { top: 8px; left: 8px; font-size: 12px; } .sp-card-add { width: 34px; height: 34px; top: 8px; right: 8px; } }

    /* Promos automáticas de la tienda */
    .sp-promos { background: var(--sp-ink); color: #fff; }
    .sp-promos .sp-wrap { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 28px; padding-top: 11px; padding-bottom: 11px; font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: .03em; }

    /* ── Catálogo ───────────────────────────────────────────────────── */
    .sp-crumbs { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 26px 0 0; font-size: 15px; color: var(--sp-ink); }
    .sp-crumbs a { font-weight: 700; text-decoration: none; }
    .sp-crumbs a:hover { text-decoration: underline; }
    .sp-crumbs i { width: 4px; height: 4px; border-radius: 99px; background: #9A9A9A; }
    .sp-cat-title { display: flex; align-items: baseline; flex-wrap: wrap; gap: 6px 16px; margin: 22px 0 22px; }
    .sp-cat-title h1 { margin: 0; font-family: var(--font-brand); font-weight: 700; text-transform: uppercase; font-size: clamp(32px, 3.4vw, 46px); line-height: 1; }
    .sp-cat-title span { color: #8C8C8C; font-size: 17px; }
    .sp-subcats { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; margin: -6px 0 18px; }
    .sp-filterbar { position: sticky; top: var(--sp-header-h); z-index: 30; display: flex; align-items: center; gap: 10px; padding: 18px 0; border-top: 1px solid var(--sp-line); border-bottom: 1px solid var(--sp-line); background: var(--t-bg); }
    .sp-filter-icon { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border: 0; background: none; cursor: pointer; color: var(--sp-ink); }
    .sp-filter-icon svg { width: 24px; height: 24px; }
    .sp-filters { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; flex: 1; min-width: 0; }
    .sp-dd { position: relative; }
    .sp-dd > button { display: inline-flex; align-items: center; gap: 22px; height: 42px; padding: 0 16px; border: 1px solid #CFCFCF; border-radius: var(--sp-radius); background: #fff; color: var(--sp-ink); font: 600 16px var(--font-body); cursor: pointer; white-space: nowrap; }
    .sp-dd > button:hover, .sp-dd.is-open > button { border-color: var(--sp-ink); }
    .sp-dd > button.is-on { border-color: var(--sp-ink); box-shadow: inset 0 0 0 1px var(--sp-ink); }
    .sp-dd > button svg { width: 16px; height: 16px; transition: transform .2s; }
    .sp-dd.is-open > button svg { transform: rotate(180deg); }
    .sp-dd-panel { position: absolute; left: 0; top: calc(100% + 6px); z-index: 40; min-width: 260px; max-width: min(92vw, 420px); max-height: 60vh; overflow-y: auto; padding: 14px; background: #fff; border: 1px solid var(--sp-line); box-shadow: 0 18px 40px rgba(0, 0, 0, .12); }
    .sp-dd-panel.is-right { left: auto; right: 0; }
    .sp-opt { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 8px; color: var(--sp-ink); text-decoration: none; font-size: 15px; font-weight: 500; border-radius: 4px; }
    .sp-opt:hover { background: var(--sp-soft); }
    .sp-opt.is-active { font-weight: 700; }
    .sp-opt small { color: #8C8C8C; font-size: 13px; }
    .sp-check { width: 18px; height: 18px; flex-shrink: 0; border: 1.5px solid #9A9A9A; display: inline-grid; place-items: center; margin-right: 10px; }
    .sp-opt.is-active .sp-check { background: var(--sp-ink); border-color: var(--sp-ink); }
    .sp-opt.is-active .sp-check::after { content: ''; width: 9px; height: 5px; border: 2px solid #fff; border-top: 0; border-right: 0; transform: rotate(-45deg) translate(1px, -1px); }
    .sp-sizes { display: grid; grid-template-columns: repeat(auto-fill, minmax(62px, 1fr)); gap: 8px; }
    .sp-size { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 6px; border: 1px solid #CFCFCF; border-radius: var(--sp-radius); background: #fff; color: var(--sp-ink); font: 600 15px var(--font-body); text-decoration: none; cursor: pointer; }
    .sp-size:hover { border-color: var(--sp-ink); }
    .sp-size.is-active { background: var(--sp-ink); border-color: var(--sp-ink); color: #fff; }
    .sp-size.is-out { color: #ABABAB; background: repeating-linear-gradient(135deg, #fff 0 8px, #F4F4F4 8px 9px); cursor: not-allowed; text-decoration: line-through; }
    .sp-price-form { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .sp-input { width: 100%; min-height: 44px; padding: 0 12px; border: 1px solid #CFCFCF; border-radius: var(--sp-radius); background: #fff; font: 500 15px var(--font-body); color: var(--sp-ink); outline: none; }
    .sp-input:focus { border-color: var(--sp-ink); }
    textarea.sp-input { padding: 12px; min-height: 110px; resize: vertical; }
    select.sp-input { padding-right: 32px; }
    .sp-label { display: block; margin-bottom: 6px; font-size: 14px; font-weight: 700; }
    .sp-sort { margin-left: auto; }
    .sp-sort > button { min-width: 250px; justify-content: space-between; font-weight: 700; text-transform: uppercase; }
    .sp-applied { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 16px 0 0; }
    .sp-applied a.sp-chip::after { content: '✕'; font-size: 12px; color: #8C8C8C; }
    .sp-results { padding: 24px 0 64px; }
    .sp-empty { text-align: center; padding: 70px 16px; border: 1px dashed #D0D0D0; border-radius: var(--sp-radius); }
    .sp-empty h2 { margin: 0 0 8px; font-family: var(--font-brand); font-size: 30px; text-transform: uppercase; }
    .sp-empty p { margin: 0 0 22px; color: var(--sp-muted); font-size: 16px; }
    .sp-pager { display: flex; justify-content: center; align-items: center; gap: 6px; margin-top: 50px; flex-wrap: wrap; }
    .sp-pager a, .sp-pager span { display: inline-flex; align-items: center; justify-content: center; min-width: 44px; height: 44px; padding: 0 12px; border: 1px solid var(--sp-line); color: var(--sp-ink); text-decoration: none; font-weight: 700; font-size: 15px; border-radius: var(--sp-radius); }
    .sp-pager a:hover { border-color: var(--sp-ink); }
    .sp-pager span.is-current { background: var(--sp-ink); border-color: var(--sp-ink); color: #fff; }
    .sp-pager span.is-gap { border: 0; }
    .sp-mobile-filter-btn { display: none; }
    @media (max-width: 1023px) {
        .sp-filters, .sp-sort, .sp-filter-icon { display: none !important; }
        .sp-mobile-filter-btn { display: flex; width: 100%; }
        .sp-filterbar { padding: 12px 0; }
    }
    .sp-acc { border-bottom: 1px solid var(--sp-line); }
    .sp-acc > summary { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 2px; font: 700 17px var(--font-body); text-transform: uppercase; cursor: pointer; list-style: none; }
    .sp-acc > summary::-webkit-details-marker { display: none; }
    .sp-acc > summary::after { content: '+'; font-size: 24px; font-weight: 400; line-height: 1; }
    .sp-acc[open] > summary::after { content: '−'; }
    .sp-acc-body { padding: 0 2px 20px; font-size: 16px; line-height: 1.6; color: #333; }

    /* ── Producto ───────────────────────────────────────────────────── */
    .sp-pdp { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(360px, 1fr); gap: 48px; padding: 20px 0 56px; align-items: start; }
    .sp-gallery { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .sp-gallery-item { position: relative; aspect-ratio: 1; background: var(--sp-card); overflow: hidden; border: 0; padding: 0; cursor: zoom-in; border-radius: var(--sp-radius); }
    .sp-gallery-item img, .sp-gallery-item video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .sp-main[data-choice="contain"] .sp-gallery-item img { object-fit: contain; padding: 6%; mix-blend-mode: multiply; }
    .sp-gallery-item.is-wide { grid-column: 1 / -1; aspect-ratio: 16 / 10; }
    .sp-gallery-dots { display: none; }
    .sp-buybox { position: sticky; top: calc(var(--sp-header-h) + 20px); display: grid; gap: 22px; }
    .sp-buybox-kicker { font-size: 14px; text-transform: uppercase; letter-spacing: .04em; color: var(--sp-muted); }
    .sp-buybox h1 { margin: 6px 0 0; font-family: var(--font-body); font-weight: 700; font-size: clamp(26px, 2.2vw, 34px); line-height: 1.15; }
    .sp-buybox .sp-price { font-size: 24px; }
    .sp-buybox .sp-price s { font-size: 19px; }
    .sp-rating { display: inline-flex; align-items: center; gap: 8px; font-size: 14px; color: var(--sp-muted); text-decoration: none; }
    .sp-opt-block { display: grid; gap: 12px; }
    .sp-opt-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: 15px; }
    .sp-opt-head strong { text-transform: uppercase; letter-spacing: .03em; }
    .sp-opt-head button { border: 0; background: none; padding: 0; font: 600 14px var(--font-body); text-decoration: underline; text-underline-offset: 3px; cursor: pointer; color: var(--sp-ink); }
    .sp-swatches { display: flex; flex-wrap: wrap; gap: 8px; }
    .sp-swatch { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 4px 14px 4px 4px; border: 1px solid #CFCFCF; border-radius: var(--sp-radius); background: #fff; font: 600 14px var(--font-body); cursor: pointer; color: var(--sp-ink); }
    .sp-swatch:not(:has(img)) { padding-left: 14px; }
    .sp-swatch img { width: 36px; height: 36px; object-fit: cover; background: var(--sp-card); }
    .sp-swatch.is-active { border-color: var(--sp-ink); box-shadow: inset 0 0 0 1px var(--sp-ink); }
    .sp-swatch.is-out { opacity: .45; text-decoration: line-through; }
    .sp-qty { display: inline-flex; align-items: center; border: 1px solid #CFCFCF; border-radius: var(--sp-radius); height: 54px; }
    .sp-qty button { width: 46px; height: 100%; border: 0; background: none; font-size: 22px; cursor: pointer; color: var(--sp-ink); }
    .sp-qty input { width: 44px; height: 100%; border: 0; text-align: center; font: 700 17px var(--font-body); -moz-appearance: textfield; background: none; }
    .sp-qty input::-webkit-outer-spin-button, .sp-qty input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .sp-buy-row { display: flex; gap: 10px; }
    .sp-buy-row .sp-btn { flex: 1; }
    .sp-note { display: flex; align-items: center; gap: 10px; font-size: 15px; color: #333; }
    .sp-note svg { width: 20px; height: 20px; flex-shrink: 0; }
    .sp-stock { display: inline-flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600; }
    .sp-stock i { width: 8px; height: 8px; border-radius: 99px; background: #1E9E57; }
    .sp-stock.is-warn i { background: #D98A00; }
    .sp-stock.is-out i { background: var(--t-primary); }
    .sp-hint { font-size: 14px; color: var(--t-primary); font-weight: 600; }
    .sp-specs { display: grid; grid-template-columns: max-content 1fr; gap: 8px 22px; margin: 0; }
    .sp-specs dt { color: var(--sp-muted); }
    .sp-specs dd { margin: 0; font-weight: 600; }
    .sp-mobile-buy { display: none; }
    @media (max-width: 1023px) {
        .sp-pdp { grid-template-columns: minmax(0, 1fr); gap: 20px; padding-top: 0; }
        .sp-gallery { display: grid; grid-auto-flow: column; grid-auto-columns: 100%; grid-template-columns: none; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; margin: 0 -16px; gap: 0; }
        .sp-gallery::-webkit-scrollbar { display: none; }
        .sp-gallery-item { scroll-snap-align: start; border-radius: 0; }
        .sp-gallery-item.is-wide { grid-column: auto; aspect-ratio: 1; }
        .sp-gallery-dots { display: flex; justify-content: center; gap: 6px; margin-top: 10px; }
        .sp-gallery-dots i { width: 7px; height: 7px; border-radius: 99px; background: #CFCFCF; }
        .sp-gallery-dots i.is-active { background: var(--sp-ink); }
        .sp-buybox { position: static; }
        .sp-mobile-buy { position: fixed; left: 0; right: 0; bottom: 0; z-index: 45; display: flex; gap: 10px; align-items: center; padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); background: #fff; border-top: 1px solid var(--sp-line); box-shadow: 0 -8px 24px rgba(0, 0, 0, .08); transform: translateY(110%); transition: transform .25s ease; }
        .sp-mobile-buy.is-visible { transform: none; }
        .sp-mobile-buy .sp-btn { flex: 1; min-height: 48px; }
        .sp-mobile-buy strong { font-size: 17px; white-space: nowrap; }
    }

    /* Modal genérico (guía de tallas, zoom) */
    .sp-modal { position: fixed; inset: 0; z-index: 95; display: grid; place-items: center; padding: 16px; background: rgba(0, 0, 0, .6); animation: sp-fade .2s ease; }
    .sp-modal-card { position: relative; width: min(560px, 100%); max-height: calc(100vh - 32px); overflow: auto; background: #fff; color: var(--sp-ink); padding: 28px; border-radius: var(--sp-radius); }
    .sp-modal-card h2 { margin: 0 0 16px; font-family: var(--font-brand); font-size: 30px; text-transform: uppercase; }
    .sp-modal-close { position: absolute; top: 12px; right: 12px; width: 40px; height: 40px; border: 0; background: none; cursor: pointer; display: grid; place-items: center; color: var(--sp-ink); }
    .sp-modal-close svg { width: 22px; height: 22px; }
    .sp-zoom { position: fixed; inset: 0; z-index: 95; background: #fff; display: flex; align-items: center; justify-content: center; }
    .sp-zoom img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .sp-guide-list { margin: 0; padding: 0; list-style: none; display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 6px 16px; font-size: 16px; }
    .sp-guide-list li { padding: 8px 0; border-bottom: 1px solid var(--sp-line); }

    /* ── Contacto / Libro de reclamaciones ──────────────────────────── */
    .sp-page-head { padding: 34px 0 10px; }
    .sp-page-head h1 { margin: 0; font-family: var(--font-brand); font-weight: 700; text-transform: uppercase; font-size: clamp(34px, 3.6vw, 52px); line-height: 1; }
    .sp-page-head p { margin: 12px 0 0; font-size: 17px; color: #444; max-width: 720px; }
    .sp-contact { display: grid; grid-template-columns: 1fr 1.6fr; gap: 48px; padding: 30px 0 70px; }
    .sp-contact-info { display: grid; gap: 22px; align-content: start; }
    .sp-contact-info h3 { margin: 0 0 6px; font-size: 15px; text-transform: uppercase; letter-spacing: .05em; }
    .sp-contact-info p, .sp-contact-info a { margin: 0; font-size: 17px; color: #333; text-decoration: none; }
    .sp-form { display: grid; gap: 16px; }
    .sp-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .sp-radio-row { display: flex; flex-wrap: wrap; gap: 10px; }
    .sp-radio { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border: 1px solid #CFCFCF; cursor: pointer; font-weight: 600; border-radius: var(--sp-radius); }
    .sp-radio:has(input:checked) { border-color: var(--sp-ink); box-shadow: inset 0 0 0 1px var(--sp-ink); }
    .sp-alert { padding: 14px 16px; border-left: 4px solid #1E9E57; background: #EFF8F2; font-weight: 600; font-size: 15px; }
    .sp-alert.is-error { border-color: var(--t-primary); background: var(--t-primary-50); }
    .sp-legal-box { padding: 16px; background: var(--sp-soft); font-size: 14px; line-height: 1.55; color: #444; border-radius: var(--sp-radius); }
    .sp-tabs { display: flex; gap: 0; border-bottom: 1px solid var(--sp-line); margin-bottom: 26px; }
    .sp-tabs a { padding: 14px 20px; font-weight: 700; font-size: 16px; text-transform: uppercase; text-decoration: none; color: var(--sp-muted); border-bottom: 3px solid transparent; margin-bottom: -1px; }
    .sp-tabs a.is-active { color: var(--sp-ink); border-color: var(--sp-ink); }
    @media (max-width: 1023px) { .sp-contact { grid-template-columns: 1fr; gap: 30px; } }
    @media (max-width: 639px) { .sp-form-row { grid-template-columns: 1fr; } }

    /* Galería */
    .sp-lookbook { columns: 3 280px; column-gap: 12px; padding: 20px 0 70px; }
    .sp-look { position: relative; display: block; margin-bottom: 12px; break-inside: avoid; overflow: hidden; background: var(--sp-card); border-radius: var(--sp-radius); color: #fff; text-decoration: none; }
    .sp-look img { display: block; width: 100%; height: auto; transition: transform .7s ease; }
    .sp-look:hover img { transform: scale(1.03); }
    .sp-look figcaption { position: absolute; left: 0; right: 0; bottom: 0; padding: 40px 18px 16px; background: linear-gradient(0deg, rgba(0, 0, 0, .65), rgba(0, 0, 0, 0)); }
    .sp-look figcaption strong { display: block; font-family: var(--font-brand); font-size: 24px; text-transform: uppercase; line-height: 1; }
    .sp-look figcaption span { font-size: 14px; opacity: .9; }

    /* ── Footer ─────────────────────────────────────────────────────── */
    .sp-footer { background: #111; color: #fff; margin-top: 30px; }
    .sp-footer-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)) 1.3fr; gap: 40px; padding-top: 52px; padding-bottom: 52px; }
    .sp-footer h4 { margin: 0 0 22px; font-size: 16px; font-weight: 700; text-transform: uppercase; letter-spacing: .02em; }
    .sp-footer ul { margin: 0; padding: 0; list-style: none; display: grid; gap: 12px; }
    .sp-footer li a, .sp-footer li button { border: 0; background: none; padding: 0; color: #fff; font: 400 16px var(--font-body); text-decoration: none; cursor: pointer; text-align: left; }
    .sp-footer li a:hover, .sp-footer li button:hover { text-decoration: underline; text-underline-offset: 3px; }
    .sp-footer-about { font-size: 15px; line-height: 1.6; color: #BDBDBD; margin: 0 0 16px; }
    .sp-libro { display: inline-flex; align-items: center; gap: 14px; margin-top: 22px; padding: 12px 16px; background: #fff; color: var(--sp-ink) !important; font: 700 15px var(--font-body); text-transform: uppercase; text-decoration: none; border-radius: var(--sp-radius); }
    .sp-libro svg { width: 22px; height: 22px; }
    .sp-news p { margin: 0 0 18px; font-size: 16px; color: #E0E0E0; }
    .sp-news form { display: flex; align-items: stretch; gap: 0; }
    .sp-news input { flex: 1; min-width: 0; height: 46px; padding: 0 12px; border: 0; background: #fff; color: var(--sp-ink); font: 400 16px var(--font-body); outline: none; border-radius: var(--sp-radius) 0 0 var(--sp-radius); }
    .sp-news button { border: 0; background: none; color: #fff; padding: 0 18px; font: 700 16px var(--font-body); text-transform: uppercase; cursor: pointer; }
    .sp-news button:hover { text-decoration: underline; }
    .sp-news-ok { font-weight: 600; color: #9BE3B4; }
    .sp-social { display: flex; gap: 22px; margin-top: 26px; }
    .sp-social a { color: #fff; display: inline-flex; }
    .sp-social svg { width: 26px; height: 26px; }
    .sp-footer-bottom { border-top: 1px solid #333; }
    .sp-footer-bottom .sp-wrap { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; padding-top: 22px; padding-bottom: 22px; font-size: 13px; }
    .sp-footer-bottom nav { display: flex; flex-wrap: wrap; gap: 8px 18px; }
    .sp-footer-bottom a, .sp-footer-bottom button { color: #fff; text-decoration: none; background: none; border: 0; padding: 0; font: 400 13px var(--font-body); cursor: pointer; }
    .sp-footer-bottom a:hover { text-decoration: underline; }
    .sp-pay { display: flex; gap: 8px; flex-wrap: wrap; }
    .sp-pay span { display: inline-flex; align-items: center; justify-content: center; height: 26px; min-width: 42px; padding: 0 6px; background: #fff; color: #1a1f71; border-radius: 3px; font: 800 11px var(--font-body); letter-spacing: .02em; }
    .sp-footer-copy { color: #BDBDBD; font-size: 13px; }
    @media (max-width: 1023px) { .sp-footer-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 639px) { .sp-footer-grid { grid-template-columns: minmax(0, 1fr); gap: 30px; } }

    /* Pestaña "Regístrate" y WhatsApp */
    .sp-signup { position: fixed; left: 24px; bottom: 24px; z-index: 44; display: inline-flex; align-items: center; background: #fff; border: 1px solid var(--sp-ink); box-shadow: 0 6px 20px rgba(0, 0, 0, .12); border-radius: var(--sp-radius); }
    .sp-signup button { border: 0; background: none; cursor: pointer; color: var(--sp-ink); }
    .sp-signup .sp-signup-main { padding: 12px 6px 12px 16px; font: 700 15px var(--font-body); text-transform: uppercase; letter-spacing: .02em; }
    .sp-signup .sp-signup-x { padding: 10px 12px; display: grid; place-items: center; }
    .sp-signup svg { width: 18px; height: 18px; }
    @media (max-width: 767px) { .sp-signup { left: 12px; bottom: 12px; } .sp-signup .sp-signup-main { font-size: 13px; padding: 10px 4px 10px 12px; } }
    body.sp-has-mobile-buy .sp-signup { display: none; }

    @media (prefers-reduced-motion: reduce) {
        .sp-slide, .sp-slide.is-active { transition: none; }
        .sp-card-media img, .sp-tile img, .sp-campaign-media img { transition: none; }
    }
</style>
