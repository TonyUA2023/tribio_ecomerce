{{-- Street Kicks: estilos propios de la plantilla (prefijo sk-). CSS plano a propósito: no depende
     de clases nuevas de Tailwind, así que ningún cambio aquí requiere recompilar con Vite. --}}
<style>
    :root {
        --sk-ink: #111111;
        --sk-muted: #6D6D6D;
        --sk-line: #E3E3E3;
        --sk-soft: #F4F4F4;
        --sk-card: #F6F6F6;
        --sk-radius: 0px;
        --sk-header-h: 80px;
        --sk-head-bg: #111111;
        --sk-head-fg: #FFFFFF;
        --sk-head-line: rgba(255, 255, 255, .55);
        --font-body: 'Barlow', 'Helvetica Neue', Arial, sans-serif;
    }
    body[data-choice="soft"] { --sk-radius: 10px; }
    .sk-header[data-choice="light"] { --sk-head-bg: #FFFFFF; --sk-head-fg: #111111; --sk-head-line: #111111; }
    @media (max-width: 1023px) { :root { --sk-header-h: 60px; } }

    .sk-body { margin: 0; font-family: var(--font-body); color: var(--sk-ink); background: var(--t-bg); -webkit-font-smoothing: antialiased; }
    .sk-body *, .sk-body *::before, .sk-body *::after { box-sizing: border-box; }
    .sk-body a { color: inherit; }
    .sk-body img { max-width: 100%; }
    .sk-wrap { width: 100%; max-width: 1560px; margin: 0 auto; padding: 0 16px; }
    .sk-narrow { width: 100%; max-width: 1340px; margin: 0 auto; padding: 0 16px; }
    @media (min-width: 768px) { .sk-wrap, .sk-narrow { padding: 0 32px; } }
    .sk-sr { position: absolute !important; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
    [x-cloak] { display: none !important; }
    .sk-brand { font-family: var(--font-brand); }
    [hidden] { display: none !important; }

    /* Botones */
    .sk-btn { display: inline-flex; align-items: center; justify-content: center; gap: 10px; min-height: 44px; padding: 0 22px; border: 2px solid var(--sk-ink); border-radius: var(--sk-radius);
        background: var(--sk-ink); color: #fff !important; font: 700 15px/1 var(--font-body); letter-spacing: .04em; text-transform: uppercase; text-decoration: none; cursor: pointer; transition: background-color .2s, color .2s, border-color .2s; }
    .sk-btn:hover { background: #333; border-color: #333; }
    .sk-btn.is-white { background: #fff; border-color: #fff; color: var(--sk-ink) !important; }
    .sk-btn.is-white:hover { background: var(--sk-ink); border-color: var(--sk-ink); color: #fff !important; }
    .sk-btn.is-outline { background: transparent; color: var(--sk-ink) !important; }
    .sk-btn.is-outline:hover { background: var(--sk-ink); color: #fff !important; }
    .sk-btn.is-block { width: 100%; min-height: 54px; font-size: 16px; }
    .sk-btn:disabled { background: #C9C9C9; border-color: #C9C9C9; color: #fff !important; cursor: not-allowed; }
    .sk-link { font-weight: 700; font-size: 14px; letter-spacing: .04em; text-transform: uppercase; text-decoration: underline; text-underline-offset: 4px; }

    /* ── Franja de mensajes ─────────────────────────────────────────── */
    .sk-utility { background: #fff; border-bottom: 1px solid var(--sk-line); font-size: 12px; font-weight: 600; letter-spacing: .02em; text-transform: uppercase; color: var(--sk-ink); }
    .sk-utility .sk-wrap { display: flex; align-items: center; justify-content: center; min-height: 40px; gap: 16px; position: relative; }
    .sk-utility-msgs { display: flex; align-items: center; justify-content: center; flex: 1; min-width: 0; }
    .sk-utility-msgs span { padding: 0 16px; border-left: 1px solid #BDBDBD; white-space: nowrap; }
    .sk-utility-msgs span:first-child { border-left: 0; }
    .sk-utility-links { position: absolute; right: 32px; top: 0; bottom: 0; display: flex; align-items: center; gap: 0; }
    .sk-utility-links > * { height: 100%; border-left: 1px solid var(--sk-line); }
    .sk-utility-links a, .sk-utility-links > * > button, .sk-utility-links > button { display: inline-flex; align-items: center; gap: 7px; height: 100%; padding: 0 14px; border: 0; background: none; font: 500 12px var(--font-body); text-transform: uppercase; color: var(--sk-muted); text-decoration: none; cursor: pointer; white-space: nowrap; }
    .sk-utility-links a:hover, .sk-utility-links button:hover { color: var(--sk-ink); }
    .sk-utility-links .sk-drop button { height: auto; padding: 10px 12px; text-transform: none; font-size: 14px; color: var(--sk-ink); justify-content: space-between; width: 100%; }
    .sk-utility-links svg { width: 15px; height: 15px; }
    .sk-utility-links img { width: 17px; height: 12px; object-fit: cover; }
    @media (max-width: 1279px) { .sk-utility-links { display: none; } }
    @media (max-width: 1023px) {
        .sk-utility-msgs span { display: none; border: 0; }
        .sk-utility-msgs span.is-current { display: block; animation: sk-fade .4s ease; }
    }
    @keyframes sk-fade { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }

    /* ── Header ─────────────────────────────────────────────────────── */
    .sk-header { position: sticky; top: 0; z-index: 50; height: var(--sk-header-h); background: var(--sk-head-bg); color: var(--sk-head-fg); border-bottom: 1px solid rgba(127, 127, 127, .18); }
    .sk-header-row { height: 100%; display: flex; align-items: center; gap: 24px; }
    .sk-logo { display: inline-flex; align-items: center; flex-shrink: 0; text-decoration: none; color: inherit; margin-right: 18px; }
    .sk-logo img { height: 46px; width: auto; max-width: 200px; object-fit: contain; }
    .sk-logo span { font-family: var(--font-brand); font-size: 28px; font-weight: 800; letter-spacing: .01em; text-transform: uppercase; line-height: 1; white-space: nowrap; }
    .sk-header[data-choice="dark"] .sk-logo img[data-transparent][data-choice="white"] { filter: brightness(0) invert(1); }
    .sk-header[data-choice="dark"] .sk-logo img[data-opaque] { border-radius: 4px; }
    .sk-nav { display: flex; align-items: center; gap: 4px 34px; flex-wrap: wrap; min-width: 0; flex: 1; max-height: 100%; overflow: hidden; }
    .sk-nav a { position: relative; display: inline-flex; align-items: center; height: var(--sk-header-h); color: inherit; text-decoration: none; font-weight: 700; font-size: 17px; white-space: nowrap; }
    .sk-nav a::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: currentColor; transform: scaleX(0); transition: transform .2s; }
    .sk-nav a:hover::after, .sk-nav a.is-active::after { transform: scaleX(1); }
    .sk-nav a.is-sale { color: var(--t-primary); }
    .sk-header[data-choice="dark"] .sk-nav a.is-sale { color: var(--t-primary-300); }
    .sk-tools { display: flex; align-items: center; gap: 8px; margin-left: auto; }
    .sk-search-btn { display: inline-flex; align-items: center; gap: 12px; height: 42px; min-width: 150px; padding: 0 18px; border: 1px solid var(--sk-head-line); border-radius: var(--sk-radius); background: transparent; color: inherit; font: 700 16px var(--font-body); text-transform: uppercase; cursor: pointer; margin-right: 14px; }
    .sk-search-btn:hover { background: rgba(127, 127, 127, .18); }
    .sk-search-btn svg { width: 19px; height: 19px; }
    .sk-icon-btn { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border: 0; border-radius: 999px; background: none; color: inherit; cursor: pointer; text-decoration: none; }
    .sk-icon-btn:hover { background: rgba(127, 127, 127, .18); }
    .sk-icon-btn svg { width: 24px; height: 24px; }
    .sk-count { position: absolute; top: 4px; right: 2px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: var(--t-primary); color: var(--t-on-primary); font: 700 11px/18px var(--font-body); text-align: center; }
    .sk-count:empty, .sk-count[data-empty] { display: none; }
    .sk-only-mobile { display: none !important; }
    @media (max-width: 1280px) { .sk-nav { gap: 4px 24px; } .sk-nav a { font-size: 16px; } }
    @media (max-width: 1023px) {
        .sk-nav, .sk-search-btn, .sk-hide-mobile { display: none !important; }
        .sk-only-mobile { display: inline-flex !important; }
        .sk-header-row { gap: 4px; }
        .sk-logo { position: absolute; left: 50%; transform: translateX(-50%); margin: 0; }
        .sk-logo img { height: 36px; max-width: 150px; }
        .sk-logo span { font-size: 22px; }
        .sk-tools { gap: 0; }
    }
    .sk-shipbar { display: block; background: var(--sk-soft); color: var(--sk-ink); text-align: center; padding: 12px 16px; font: 700 15px var(--font-body); letter-spacing: .02em; text-transform: uppercase; text-decoration: none; border-bottom: 1px solid var(--sk-line); }
    a.sk-shipbar:hover { text-decoration: underline; text-underline-offset: 3px; }
    @media (max-width: 767px) { .sk-shipbar { font-size: 13px; padding: 10px 12px; } }

    /* Búsqueda */
    .sk-search-panel { position: fixed; inset: 0; z-index: 80; display: flex; flex-direction: column; }
    .sk-search-backdrop { position: absolute; inset: 0; background: rgba(0, 0, 0, .5); }
    .sk-search-box { position: relative; background: #fff; color: var(--sk-ink); padding: 28px 0 34px; box-shadow: 0 20px 60px rgba(0, 0, 0, .2); }
    .sk-search-box form { display: flex; align-items: center; gap: 14px; border-bottom: 2px solid var(--sk-ink); }
    .sk-search-box input { flex: 1; min-width: 0; border: 0; outline: none; background: none; padding: 12px 0; font: 600 26px var(--font-body); color: var(--sk-ink); }
    .sk-search-box input::-webkit-search-cancel-button { display: none; }
    .sk-search-box svg { width: 26px; height: 26px; flex-shrink: 0; }
    .sk-search-hints { margin-top: 20px; display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
    .sk-search-hints strong { font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: var(--sk-muted); margin-right: 6px; }
    .sk-chip { display: inline-flex; align-items: center; gap: 8px; min-height: 36px; padding: 0 14px; border: 1px solid var(--sk-line); border-radius: 999px; background: #fff; color: var(--sk-ink); font: 600 14px var(--font-body); text-decoration: none; cursor: pointer; white-space: nowrap; }
    .sk-chip:hover { border-color: var(--sk-ink); }
    .sk-chip.is-active { background: var(--sk-ink); border-color: var(--sk-ink); color: #fff; }
    @media (max-width: 767px) { .sk-search-box input { font-size: 20px; } }

    /* Menú móvil */
    .sk-drawer { position: fixed; inset: 0; z-index: 80; display: flex; }
    .sk-drawer-backdrop { position: absolute; inset: 0; background: rgba(0, 0, 0, .55); }
    .sk-drawer-panel { position: relative; width: min(90%, 400px); height: 100%; background: #fff; color: var(--sk-ink); display: flex; flex-direction: column; box-shadow: 0 0 60px rgba(0, 0, 0, .3); }
    .sk-drawer-panel.is-right { margin-left: auto; }
    .sk-drawer-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--sk-line); }
    .sk-drawer-head strong { font: 700 18px var(--font-body); text-transform: uppercase; letter-spacing: .03em; }
    .sk-drawer-body { flex: 1; overflow-y: auto; padding: 8px 16px 24px; }
    .sk-drawer-links a, .sk-drawer-links button { display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 16px 2px; border: 0; border-bottom: 1px solid var(--sk-line); background: none; color: var(--sk-ink); font: 700 18px var(--font-body); text-decoration: none; text-align: left; cursor: pointer; }
    .sk-drawer-links a.is-sale { color: var(--t-primary); }
    .sk-drawer-links.is-small a, .sk-drawer-links.is-small button { font-size: 15px; font-weight: 500; padding: 13px 2px; }
    .sk-drawer-foot { display: grid; gap: 12px; margin-top: 20px; padding: 14px; background: var(--sk-soft); border-radius: var(--sk-radius); font-size: 14px; font-weight: 600; }
    .sk-drawer-foot > div { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .sk-drawer-foot select { border: 1px solid var(--sk-line); background: #fff; padding: 8px 10px; font: 600 14px var(--font-body); border-radius: var(--sk-radius); }
    .sk-seg { display: inline-flex; border: 1px solid var(--sk-ink); }
    .sk-seg button { border: 0; background: #fff; padding: 6px 14px; font: 700 13px var(--font-body); cursor: pointer; }
    .sk-seg button.is-active { background: var(--sk-ink); color: #fff; }
    .sk-tr { transition: transform .28s ease; }
    .sk-off-left { transform: translateX(-100%); }
    .sk-off-right { transform: translateX(100%); }
    .sk-on { transform: none; }
    .sk-drop { position: absolute; right: 0; top: calc(100% + 4px); z-index: 60; min-width: 220px; padding: 6px; background: #fff; color: var(--sk-ink); border: 1px solid var(--sk-line); box-shadow: 0 16px 40px rgba(0, 0, 0, .15); text-transform: none; }
    .sk-drop button { display: flex; width: 100%; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; border: 0; background: none; font: 600 14px var(--font-body); color: var(--sk-ink); cursor: pointer; text-align: left; }
    .sk-drop button:hover { background: var(--sk-soft); }
    .sk-drop button.is-active { color: var(--t-primary); }
    .sk-drop img { width: 18px; height: 13px; object-fit: cover; }

    /* ── Hero ───────────────────────────────────────────────────────── */
    .sk-hero { position: relative; overflow: hidden; background: #1a1a1a; color: #fff; }
    .sk-hero-track { position: relative; height: min(76vh, 760px); min-height: 460px; }
    .sk-hero[data-choice="full"] .sk-hero-track { height: calc(100vh - var(--sk-header-h)); height: calc(100svh - var(--sk-header-h)); }
    .sk-hero[data-choice="medium"] .sk-hero-track { height: min(58vh, 560px); min-height: 380px; }
    @media (max-width: 767px) {
        .sk-hero-track { height: min(125vw, 80svh); min-height: 460px; }
        .sk-hero[data-choice="medium"] .sk-hero-track { height: min(100vw, 64svh); min-height: 360px; }
    }
    .sk-slide { position: absolute; inset: 0; opacity: 0; visibility: hidden; transition: opacity .7s ease, visibility 0s linear .7s; }
    .sk-slide.is-active { opacity: 1; visibility: visible; transition: opacity .7s ease; z-index: 1; }
    .sk-slide-bg { position: absolute; inset: 0; background: linear-gradient(120deg, #0d0d0d 0%, #2a2a2a 55%, var(--t-primary-deep) 100%); }
    .sk-slide[data-choice="dark"] .sk-slide-bg { background: linear-gradient(120deg, #f5f5f5 0%, #e9e9e9 60%, var(--t-secondary-50) 100%); }
    .sk-slide-bg picture, .sk-slide-bg img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .sk-slide-scrim { position: absolute; inset: 0; pointer-events: none; opacity: 0; }
    .sk-slide[data-has-image] .sk-slide-scrim { opacity: 1; }
    .sk-slide[data-choice="light"] .sk-slide-scrim { background: linear-gradient(90deg, rgba(0, 0, 0, .55) 0%, rgba(0, 0, 0, .12) 55%, rgba(0, 0, 0, 0) 75%); }
    .sk-slide[data-choice="light"] .sk-slide-scrim[data-choice="center"] { background: radial-gradient(60% 70% at 50% 50%, rgba(0, 0, 0, .45), rgba(0, 0, 0, .1)); }
    .sk-slide[data-choice="light"] .sk-slide-scrim[data-choice="bottom"] { background: linear-gradient(0deg, rgba(0, 0, 0, .6), rgba(0, 0, 0, 0) 60%); }
    .sk-slide[data-choice="dark"] .sk-slide-scrim { background: linear-gradient(90deg, rgba(255, 255, 255, .7), rgba(255, 255, 255, 0) 60%); }
    .sk-slide[data-choice="image"] .sk-slide-scrim, .sk-slide[data-choice="image"] .sk-slide-copy { display: none; }
    .sk-slide-link { position: absolute; inset: 0; z-index: 2; display: none; }
    .sk-slide[data-choice="image"] .sk-slide-link { display: block; }
    .sk-slide-copy { position: relative; z-index: 3; height: 100%; display: flex; flex-direction: column; justify-content: center; padding-top: 32px; padding-bottom: 64px; }
    .sk-slide-copy .sk-copy { max-width: 820px; display: flex; flex-direction: column; align-items: flex-start; gap: 14px; }
    .sk-slide-copy[data-choice="center"] { align-items: center; text-align: center; }
    .sk-slide-copy[data-choice="center"] .sk-copy { align-items: center; margin: 0 auto; }
    .sk-slide-copy[data-choice="bottom"] { justify-content: flex-end; padding-bottom: 56px; }
    .sk-slide-copy[data-choice="bottom"] .sk-copy { align-items: center; text-align: center; margin: 0 auto; }
    .sk-slide[data-choice="dark"] .sk-copy { color: var(--sk-ink); }
    .sk-hero-title { margin: 0; font-family: var(--font-brand); font-weight: 700; text-transform: uppercase; line-height: .9; letter-spacing: 0; font-size: clamp(44px, 7vw, 124px); text-wrap: balance; }
    .sk-slide-copy[data-choice="left"] .sk-hero-title { text-align: left; }
    .sk-hero-sub { margin: 0; color: var(--t-secondary); font-family: var(--font-brand); font-weight: 600; text-transform: uppercase; letter-spacing: .28em; font-size: clamp(16px, 2.2vw, 36px); }
    .sk-slide[data-choice="dark"] .sk-hero-sub { color: var(--t-secondary-dark); }
    .sk-hero-cta { margin-top: 12px; }
    .sk-slide[data-choice="dark"] .sk-hero-cta { background: var(--sk-ink); border-color: var(--sk-ink); color: #fff !important; }
    .sk-hero-arrow { position: absolute; bottom: 20px; z-index: 5; width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 999px; background: rgba(255, 255, 255, .9); color: var(--sk-ink); cursor: pointer; box-shadow: 0 4px 14px rgba(0, 0, 0, .15); }
    .sk-hero-arrow.is-prev { right: 74px; } .sk-hero-arrow.is-next { right: 22px; }
    .sk-hero-arrow svg { width: 22px; height: 22px; }
    .sk-hero-dots { position: absolute; left: 50%; bottom: 20px; z-index: 5; transform: translateX(-50%); display: flex; gap: 8px; }
    .sk-hero-dots button { width: 34px; height: 4px; padding: 0; border: 0; background: rgba(255, 255, 255, .45); cursor: pointer; }
    .sk-hero-dots button.is-active { background: #fff; }
    .sk-hero[data-tone="dark"] .sk-hero-dots button { background: rgba(0, 0, 0, .25); }
    .sk-hero[data-tone="dark"] .sk-hero-dots button.is-active { background: var(--sk-ink); }
    @media (max-width: 767px) {
        .sk-hero-arrow { display: none; }
        .sk-slide-copy { justify-content: flex-end; padding-bottom: 54px; }
        .sk-slide-copy .sk-copy { gap: 10px; }
        .sk-slide[data-choice="light"] .sk-slide-scrim[data-choice] { background: linear-gradient(0deg, rgba(0, 0, 0, .65), rgba(0, 0, 0, 0) 65%); }
        .sk-hero-sub { letter-spacing: .18em; }
    }

    /* ── Secciones ──────────────────────────────────────────────────── */
    .sk-section { padding: 48px 0; }
    .sk-section.is-tight { padding: 28px 0 40px; }
    .sk-h2 { margin: 0 0 24px; font-family: var(--font-brand); font-weight: 700; text-transform: uppercase; font-size: clamp(26px, 2.6vw, 36px); line-height: 1.05; text-align: center; letter-spacing: .01em; }
    .sk-h2.is-left { text-align: left; }
    .sk-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
    .sk-head .sk-h2 { margin: 0; }
    @media (max-width: 767px) { .sk-section { padding: 36px 0; } }

    /* Carrusel de productos */
    .sk-rail { position: relative; }
    .sk-rail-track { display: grid; grid-auto-flow: column; grid-auto-columns: calc((100% - 3 * 16px) / 4); gap: 16px; overflow-x: auto; scroll-snap-type: x mandatory; scroll-behavior: smooth; scrollbar-width: none; }
    .sk-rail-track::-webkit-scrollbar { display: none; }
    .sk-rail-track > * { scroll-snap-align: start; }
    .sk-rail-btn { position: absolute; top: calc(var(--sk-rail-img, 300px) / 2); z-index: 4; width: 44px; height: 44px; transform: translateY(-50%); display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 999px; background: #fff; color: var(--sk-ink); box-shadow: 0 3px 12px rgba(0, 0, 0, .15); cursor: pointer; transition: opacity .2s; }
    .sk-rail-btn.is-prev { left: 12px; } .sk-rail-btn.is-next { right: 12px; }
    .sk-rail-btn:disabled { opacity: 0; pointer-events: none; }
    .sk-rail-btn svg { width: 20px; height: 20px; }
    .sk-rail-progress { position: relative; height: 5px; margin-top: 30px; background: #E4E4E4; border-radius: 99px; overflow: hidden; }
    .sk-rail-progress i { position: absolute; top: 0; bottom: 0; left: 0; background: #585858; border-radius: 99px; transition: transform .2s ease, width .2s ease; }
    @media (max-width: 1023px) { .sk-rail-track { grid-auto-columns: calc((100% - 2 * 12px) / 2.6); gap: 12px; } }
    @media (max-width: 639px) { .sk-rail-track { grid-auto-columns: 66%; } .sk-rail-btn { display: none; } }

    /* Tarjeta de producto */
    .sk-card { position: relative; display: flex; flex-direction: column; min-width: 0; }
    .sk-card-media { position: relative; display: block; aspect-ratio: 1 / 1; overflow: hidden; background: var(--sk-card); border-radius: var(--sk-radius); }
    .sk-card-media img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transition: opacity .35s ease, transform .6s ease; }
    .sk-main[data-choice="contain"] .sk-card-media img { object-fit: contain; padding: 8%; mix-blend-mode: multiply; }
    .sk-card-media img.is-alt { opacity: 0; }
    .sk-card:hover .sk-card-media img.is-alt { opacity: 1; }
    .sk-card:hover .sk-card-media.has-alt img:not(.is-alt) { opacity: 0; }
    .sk-card:hover .sk-card-media:not(.has-alt) img { transform: scale(1.04); }
    .sk-badge { position: absolute; top: 14px; left: 14px; z-index: 2; padding: 4px 9px; background: var(--t-primary); color: var(--t-on-primary); font: 700 13px var(--font-body); border-radius: calc(var(--sk-radius) / 2); }
    .sk-badge.is-dark { background: var(--sk-ink); color: #fff; }
    .sk-card-add { position: absolute; top: 12px; right: 12px; z-index: 3; width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 999px; background: #fff; color: var(--sk-ink); box-shadow: 0 2px 8px rgba(0, 0, 0, .08); cursor: pointer; text-decoration: none; transition: background-color .2s, color .2s; }
    .sk-card-add:hover { background: var(--sk-ink); color: #fff; }
    .sk-card-add svg { width: 19px; height: 19px; }
    .sk-card-info { padding: 16px 2px 4px; display: grid; gap: 6px; }
    .sk-card-meta { font-size: 13px; text-transform: uppercase; letter-spacing: .02em; color: var(--sk-muted); }
    .sk-card-name { font-size: 16px; font-weight: 600; line-height: 1.3; color: var(--sk-ink); text-decoration: none; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .sk-card-name:hover { text-decoration: underline; }
    .sk-price { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 10px; font-size: 17px; font-weight: 700; }
    .sk-price .is-sale { color: var(--t-primary); }
    .sk-price s { color: #8C8C8C; font-weight: 500; font-size: 15px; }
    .sk-tag { display: inline-block; padding: 3px 8px; background: var(--sk-ink); color: #fff; font: 700 12px var(--font-body); letter-spacing: .04em; text-transform: uppercase; }

    /* Tarjetas "Lo nuevo" */
    .sk-tiles { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
    .sk-tile { position: relative; display: block; overflow: hidden; aspect-ratio: 4 / 5; background: var(--sk-card); color: #fff; text-decoration: none; border-radius: var(--sk-radius); }
    .sk-tile img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transition: transform .8s ease; }
    .sk-tile:hover img { transform: scale(1.04); }
    .sk-tile-fallback { position: absolute; inset: 0; background: linear-gradient(160deg, #3b3b3b, #0f0f0f); }
    .sk-tile::after { content: ''; position: absolute; inset: 40% 0 0; background: linear-gradient(0deg, rgba(0, 0, 0, .72), rgba(0, 0, 0, 0)); }
    .sk-tile-copy { position: absolute; left: 0; right: 0; bottom: 0; z-index: 2; padding: 24px; display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; }
    .sk-tile-copy h3 { margin: 0; font-family: var(--font-brand); font-weight: 700; text-transform: uppercase; font-size: clamp(26px, 2.6vw, 38px); line-height: 1; }
    @media (max-width: 767px) { .sk-tiles { grid-template-columns: repeat(3, 78%); overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; } .sk-tile { scroll-snap-align: start; } .sk-tile-copy { padding: 16px; } }

    /* Campañas */
    .sk-campaigns { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 16px; }
    .sk-campaign { display: flex; flex-direction: column; align-items: center; text-align: center; min-width: 0; }
    .sk-campaign-media { position: relative; display: block; width: 100%; aspect-ratio: 10 / 7; overflow: hidden; background: var(--sk-card); border-radius: var(--sk-radius); }
    .sk-campaign-media img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transition: transform .8s ease; }
    .sk-campaign-media:hover img { transform: scale(1.03); }
    .sk-campaign h3 { margin: 22px 0 0; font-family: var(--font-brand); font-weight: 700; text-transform: uppercase; font-size: clamp(26px, 2.4vw, 36px); line-height: 1; }
    .sk-campaign p { margin: 10px 0 0; font-size: 18px; text-transform: uppercase; letter-spacing: .03em; color: #333; }
    .sk-campaign .sk-btn { margin-top: 16px; }
    .sk-campaigns-wrap { padding: 0 0 12px; }
    @media (max-width: 767px) { .sk-campaigns { grid-template-columns: 1fr; gap: 36px; } .sk-campaign p { font-size: 15px; } }

    /* Categorías */
    .sk-cats { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(150px, 1fr); gap: 16px; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; padding-bottom: 4px; }
    .sk-cats::-webkit-scrollbar { display: none; }
    .sk-cat { scroll-snap-align: start; display: grid; gap: 12px; justify-items: center; text-decoration: none; color: var(--sk-ink); }
    .sk-cat-media { position: relative; width: 100%; aspect-ratio: 1; overflow: hidden; background: var(--sk-card); border-radius: var(--sk-radius); }
    .sk-cat-media img { width: 100%; height: 100%; object-fit: cover; transition: transform .6s ease; }
    .sk-cat:hover .sk-cat-media img { transform: scale(1.05); }
    .sk-cat strong { font-family: var(--font-brand); font-weight: 700; font-size: 20px; text-transform: uppercase; }
    @media (min-width: 1024px) { .sk-cats { grid-auto-flow: row; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); overflow: visible; } }

    /* Grilla */
    .sk-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 36px 16px; }
    @media (max-width: 1023px) { .sk-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 767px) { .sk-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 28px 10px; } .sk-card-name { font-size: 14px; } .sk-price { font-size: 15px; } .sk-badge { top: 8px; left: 8px; font-size: 12px; } .sk-card-add { width: 34px; height: 34px; top: 8px; right: 8px; } }

    /* Promos automáticas de la tienda */
    .sk-promos { background: var(--sk-ink); color: #fff; }
    .sk-promos .sk-wrap { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 28px; padding-top: 11px; padding-bottom: 11px; font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: .03em; }

    /* ── Catálogo ───────────────────────────────────────────────────── */
    .sk-crumbs { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 26px 0 0; font-size: 15px; color: var(--sk-ink); }
    .sk-crumbs a { font-weight: 700; text-decoration: none; }
    .sk-crumbs a:hover { text-decoration: underline; }
    .sk-crumbs i { width: 4px; height: 4px; border-radius: 99px; background: #9A9A9A; }
    .sk-cat-title { display: flex; align-items: baseline; flex-wrap: wrap; gap: 6px 16px; margin: 22px 0 22px; }
    .sk-cat-title h1 { margin: 0; font-family: var(--font-brand); font-weight: 700; text-transform: uppercase; font-size: clamp(32px, 3.4vw, 46px); line-height: 1; }
    .sk-cat-title span { color: #8C8C8C; font-size: 17px; }
    .sk-subcats { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; margin: -6px 0 18px; }
    .sk-filterbar { position: sticky; top: var(--sk-header-h); z-index: 30; display: flex; align-items: center; gap: 10px; padding: 18px 0; border-top: 1px solid var(--sk-line); border-bottom: 1px solid var(--sk-line); background: var(--t-bg); }
    .sk-filter-icon { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border: 0; background: none; cursor: pointer; color: var(--sk-ink); }
    .sk-filter-icon svg { width: 24px; height: 24px; }
    .sk-filters { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; flex: 1; min-width: 0; }
    .sk-dd { position: relative; }
    .sk-dd > button { display: inline-flex; align-items: center; gap: 22px; height: 42px; padding: 0 16px; border: 1px solid #CFCFCF; border-radius: var(--sk-radius); background: #fff; color: var(--sk-ink); font: 600 16px var(--font-body); cursor: pointer; white-space: nowrap; }
    .sk-dd > button:hover, .sk-dd.is-open > button { border-color: var(--sk-ink); }
    .sk-dd > button.is-on { border-color: var(--sk-ink); box-shadow: inset 0 0 0 1px var(--sk-ink); }
    .sk-dd > button svg { width: 16px; height: 16px; transition: transform .2s; }
    .sk-dd.is-open > button svg { transform: rotate(180deg); }
    .sk-dd-panel { position: absolute; left: 0; top: calc(100% + 6px); z-index: 40; min-width: 260px; max-width: min(92vw, 420px); max-height: 60vh; overflow-y: auto; padding: 14px; background: #fff; border: 1px solid var(--sk-line); box-shadow: 0 18px 40px rgba(0, 0, 0, .12); }
    .sk-dd-panel.is-right { left: auto; right: 0; }
    .sk-opt { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 8px; color: var(--sk-ink); text-decoration: none; font-size: 15px; font-weight: 500; border-radius: 4px; }
    .sk-opt:hover { background: var(--sk-soft); }
    .sk-opt.is-active { font-weight: 700; }
    .sk-opt small { color: #8C8C8C; font-size: 13px; }
    .sk-check { width: 18px; height: 18px; flex-shrink: 0; border: 1.5px solid #9A9A9A; display: inline-grid; place-items: center; margin-right: 10px; }
    .sk-opt.is-active .sk-check { background: var(--sk-ink); border-color: var(--sk-ink); }
    .sk-opt.is-active .sk-check::after { content: ''; width: 9px; height: 5px; border: 2px solid #fff; border-top: 0; border-right: 0; transform: rotate(-45deg) translate(1px, -1px); }
    .sk-sizes { display: grid; grid-template-columns: repeat(auto-fill, minmax(62px, 1fr)); gap: 8px; }
    .sk-size { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 6px; border: 1px solid #CFCFCF; border-radius: var(--sk-radius); background: #fff; color: var(--sk-ink); font: 600 15px var(--font-body); text-decoration: none; cursor: pointer; }
    .sk-size:hover { border-color: var(--sk-ink); }
    .sk-size.is-active { background: var(--sk-ink); border-color: var(--sk-ink); color: #fff; }
    .sk-size.is-out { color: #ABABAB; background: repeating-linear-gradient(135deg, #fff 0 8px, #F4F4F4 8px 9px); cursor: not-allowed; text-decoration: line-through; }
    .sk-price-form { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .sk-input { width: 100%; min-height: 44px; padding: 0 12px; border: 1px solid #CFCFCF; border-radius: var(--sk-radius); background: #fff; font: 500 15px var(--font-body); color: var(--sk-ink); outline: none; }
    .sk-input:focus { border-color: var(--sk-ink); }
    textarea.sk-input { padding: 12px; min-height: 110px; resize: vertical; }
    select.sk-input { padding-right: 32px; }
    .sk-label { display: block; margin-bottom: 6px; font-size: 14px; font-weight: 700; }
    .sk-sort { margin-left: auto; }
    .sk-sort > button { min-width: 250px; justify-content: space-between; font-weight: 700; text-transform: uppercase; }
    .sk-applied { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 16px 0 0; }
    .sk-applied a.sk-chip::after { content: '✕'; font-size: 12px; color: #8C8C8C; }
    .sk-results { padding: 24px 0 64px; }
    .sk-empty { text-align: center; padding: 70px 16px; border: 1px dashed #D0D0D0; border-radius: var(--sk-radius); }
    .sk-empty h2 { margin: 0 0 8px; font-family: var(--font-brand); font-size: 30px; text-transform: uppercase; }
    .sk-empty p { margin: 0 0 22px; color: var(--sk-muted); font-size: 16px; }
    .sk-pager { display: flex; justify-content: center; align-items: center; gap: 6px; margin-top: 50px; flex-wrap: wrap; }
    .sk-pager a, .sk-pager span { display: inline-flex; align-items: center; justify-content: center; min-width: 44px; height: 44px; padding: 0 12px; border: 1px solid var(--sk-line); color: var(--sk-ink); text-decoration: none; font-weight: 700; font-size: 15px; border-radius: var(--sk-radius); }
    .sk-pager a:hover { border-color: var(--sk-ink); }
    .sk-pager span.is-current { background: var(--sk-ink); border-color: var(--sk-ink); color: #fff; }
    .sk-pager span.is-gap { border: 0; }
    .sk-mobile-filter-btn { display: none; }
    @media (max-width: 1023px) {
        .sk-filters, .sk-sort, .sk-filter-icon { display: none !important; }
        .sk-mobile-filter-btn { display: flex; width: 100%; }
        .sk-filterbar { padding: 12px 0; }
    }
    .sk-acc { border-bottom: 1px solid var(--sk-line); }
    .sk-acc > summary { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 2px; font: 700 17px var(--font-body); text-transform: uppercase; cursor: pointer; list-style: none; }
    .sk-acc > summary::-webkit-details-marker { display: none; }
    .sk-acc > summary::after { content: '+'; font-size: 24px; font-weight: 400; line-height: 1; }
    .sk-acc[open] > summary::after { content: '−'; }
    .sk-acc-body { padding: 0 2px 20px; font-size: 16px; line-height: 1.6; color: #333; }

    /* ── Producto ───────────────────────────────────────────────────── */
    .sk-pdp { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(360px, 1fr); gap: 48px; padding: 20px 0 56px; align-items: start; }
    .sk-gallery { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .sk-gallery-item { position: relative; aspect-ratio: 1; background: var(--sk-card); overflow: hidden; border: 0; padding: 0; cursor: zoom-in; border-radius: var(--sk-radius); }
    .sk-gallery-item img, .sk-gallery-item video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .sk-main[data-choice="contain"] .sk-gallery-item img { object-fit: contain; padding: 6%; mix-blend-mode: multiply; }
    .sk-gallery-item.is-wide { grid-column: 1 / -1; aspect-ratio: 16 / 10; }
    .sk-gallery-dots { display: none; }
    .sk-buybox { position: sticky; top: calc(var(--sk-header-h) + 20px); display: grid; gap: 22px; }
    .sk-buybox-kicker { font-size: 14px; text-transform: uppercase; letter-spacing: .04em; color: var(--sk-muted); }
    .sk-buybox h1 { margin: 6px 0 0; font-family: var(--font-body); font-weight: 700; font-size: clamp(26px, 2.2vw, 34px); line-height: 1.15; }
    .sk-buybox .sk-price { font-size: 24px; }
    .sk-buybox .sk-price s { font-size: 19px; }
    .sk-rating { display: inline-flex; align-items: center; gap: 8px; font-size: 14px; color: var(--sk-muted); text-decoration: none; }
    .sk-opt-block { display: grid; gap: 12px; }
    .sk-opt-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: 15px; }
    .sk-opt-head strong { text-transform: uppercase; letter-spacing: .03em; }
    .sk-opt-head button { border: 0; background: none; padding: 0; font: 600 14px var(--font-body); text-decoration: underline; text-underline-offset: 3px; cursor: pointer; color: var(--sk-ink); }
    .sk-swatches { display: flex; flex-wrap: wrap; gap: 8px; }
    .sk-swatch { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 4px 14px 4px 4px; border: 1px solid #CFCFCF; border-radius: var(--sk-radius); background: #fff; font: 600 14px var(--font-body); cursor: pointer; color: var(--sk-ink); }
    .sk-swatch:not(:has(img)) { padding-left: 14px; }
    .sk-swatch img { width: 36px; height: 36px; object-fit: cover; background: var(--sk-card); }
    .sk-swatch.is-active { border-color: var(--sk-ink); box-shadow: inset 0 0 0 1px var(--sk-ink); }
    .sk-swatch.is-out { opacity: .45; text-decoration: line-through; }
    .sk-qty { display: inline-flex; align-items: center; border: 1px solid #CFCFCF; border-radius: var(--sk-radius); height: 54px; }
    .sk-qty button { width: 46px; height: 100%; border: 0; background: none; font-size: 22px; cursor: pointer; color: var(--sk-ink); }
    .sk-qty input { width: 44px; height: 100%; border: 0; text-align: center; font: 700 17px var(--font-body); -moz-appearance: textfield; background: none; }
    .sk-qty input::-webkit-outer-spin-button, .sk-qty input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .sk-buy-row { display: flex; gap: 10px; }
    .sk-buy-row .sk-btn { flex: 1; }
    .sk-note { display: flex; align-items: center; gap: 10px; font-size: 15px; color: #333; }
    .sk-note svg { width: 20px; height: 20px; flex-shrink: 0; }
    .sk-stock { display: inline-flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600; }
    .sk-stock i { width: 8px; height: 8px; border-radius: 99px; background: #1E9E57; }
    .sk-stock.is-warn i { background: #D98A00; }
    .sk-stock.is-out i { background: var(--t-primary); }
    .sk-hint { font-size: 14px; color: var(--t-primary); font-weight: 600; }
    .sk-specs { display: grid; grid-template-columns: max-content 1fr; gap: 8px 22px; margin: 0; }
    .sk-specs dt { color: var(--sk-muted); }
    .sk-specs dd { margin: 0; font-weight: 600; }
    .sk-mobile-buy { display: none; }
    @media (max-width: 1023px) {
        .sk-pdp { grid-template-columns: minmax(0, 1fr); gap: 20px; padding-top: 0; }
        .sk-gallery { display: grid; grid-auto-flow: column; grid-auto-columns: 100%; grid-template-columns: none; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; margin: 0 -16px; gap: 0; }
        .sk-gallery::-webkit-scrollbar { display: none; }
        .sk-gallery-item { scroll-snap-align: start; border-radius: 0; }
        .sk-gallery-item.is-wide { grid-column: auto; aspect-ratio: 1; }
        .sk-gallery-dots { display: flex; justify-content: center; gap: 6px; margin-top: 10px; }
        .sk-gallery-dots i { width: 7px; height: 7px; border-radius: 99px; background: #CFCFCF; }
        .sk-gallery-dots i.is-active { background: var(--sk-ink); }
        .sk-buybox { position: static; }
        .sk-mobile-buy { position: fixed; left: 0; right: 0; bottom: 0; z-index: 45; display: flex; gap: 10px; align-items: center; padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); background: #fff; border-top: 1px solid var(--sk-line); box-shadow: 0 -8px 24px rgba(0, 0, 0, .08); transform: translateY(110%); transition: transform .25s ease; }
        .sk-mobile-buy.is-visible { transform: none; }
        .sk-mobile-buy .sk-btn { flex: 1; min-height: 48px; }
        .sk-mobile-buy strong { font-size: 17px; white-space: nowrap; }
    }

    /* Modal genérico (guía de tallas, zoom) */
    .sk-modal { position: fixed; inset: 0; z-index: 95; display: grid; place-items: center; padding: 16px; background: rgba(0, 0, 0, .6); animation: sk-fade .2s ease; }
    .sk-modal-card { position: relative; width: min(560px, 100%); max-height: calc(100vh - 32px); overflow: auto; background: #fff; color: var(--sk-ink); padding: 28px; border-radius: var(--sk-radius); }
    .sk-modal-card h2 { margin: 0 0 16px; font-family: var(--font-brand); font-size: 30px; text-transform: uppercase; }
    .sk-modal-close { position: absolute; top: 12px; right: 12px; width: 40px; height: 40px; border: 0; background: none; cursor: pointer; display: grid; place-items: center; color: var(--sk-ink); }
    .sk-modal-close svg { width: 22px; height: 22px; }
    .sk-zoom { position: fixed; inset: 0; z-index: 95; background: #fff; display: flex; align-items: center; justify-content: center; }
    .sk-zoom img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .sk-guide-list { margin: 0; padding: 0; list-style: none; display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 6px 16px; font-size: 16px; }
    .sk-guide-list li { padding: 8px 0; border-bottom: 1px solid var(--sk-line); }

    /* ── Contacto / Libro de reclamaciones ──────────────────────────── */
    .sk-page-head { padding: 34px 0 10px; }
    .sk-page-head h1 { margin: 0; font-family: var(--font-brand); font-weight: 700; text-transform: uppercase; font-size: clamp(34px, 3.6vw, 52px); line-height: 1; }
    .sk-page-head p { margin: 12px 0 0; font-size: 17px; color: #444; max-width: 720px; }
    .sk-contact { display: grid; grid-template-columns: 1fr 1.6fr; gap: 48px; padding: 30px 0 70px; }
    .sk-contact-info { display: grid; gap: 22px; align-content: start; }
    .sk-contact-info h3 { margin: 0 0 6px; font-size: 15px; text-transform: uppercase; letter-spacing: .05em; }
    .sk-contact-info p, .sk-contact-info a { margin: 0; font-size: 17px; color: #333; text-decoration: none; }
    .sk-form { display: grid; gap: 16px; }
    .sk-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .sk-radio-row { display: flex; flex-wrap: wrap; gap: 10px; }
    .sk-radio { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border: 1px solid #CFCFCF; cursor: pointer; font-weight: 600; border-radius: var(--sk-radius); }
    .sk-radio:has(input:checked) { border-color: var(--sk-ink); box-shadow: inset 0 0 0 1px var(--sk-ink); }
    .sk-alert { padding: 14px 16px; border-left: 4px solid #1E9E57; background: #EFF8F2; font-weight: 600; font-size: 15px; }
    .sk-alert.is-error { border-color: var(--t-primary); background: var(--t-primary-50); }
    .sk-legal-box { padding: 16px; background: var(--sk-soft); font-size: 14px; line-height: 1.55; color: #444; border-radius: var(--sk-radius); }
    .sk-tabs { display: flex; gap: 0; border-bottom: 1px solid var(--sk-line); margin-bottom: 26px; }
    .sk-tabs a { padding: 14px 20px; font-weight: 700; font-size: 16px; text-transform: uppercase; text-decoration: none; color: var(--sk-muted); border-bottom: 3px solid transparent; margin-bottom: -1px; }
    .sk-tabs a.is-active { color: var(--sk-ink); border-color: var(--sk-ink); }
    @media (max-width: 1023px) { .sk-contact { grid-template-columns: 1fr; gap: 30px; } }
    @media (max-width: 639px) { .sk-form-row { grid-template-columns: 1fr; } }

    /* Galería */
    .sk-lookbook { columns: 3 280px; column-gap: 12px; padding: 20px 0 70px; }
    .sk-look { position: relative; display: block; margin-bottom: 12px; break-inside: avoid; overflow: hidden; background: var(--sk-card); border-radius: var(--sk-radius); color: #fff; text-decoration: none; }
    .sk-look img { display: block; width: 100%; height: auto; transition: transform .7s ease; }
    .sk-look:hover img { transform: scale(1.03); }
    .sk-look figcaption { position: absolute; left: 0; right: 0; bottom: 0; padding: 40px 18px 16px; background: linear-gradient(0deg, rgba(0, 0, 0, .65), rgba(0, 0, 0, 0)); }
    .sk-look figcaption strong { display: block; font-family: var(--font-brand); font-size: 24px; text-transform: uppercase; line-height: 1; }
    .sk-look figcaption span { font-size: 14px; opacity: .9; }

    /* ── Footer ─────────────────────────────────────────────────────── */
    .sk-footer { background: #111; color: #fff; margin-top: 30px; }
    .sk-footer-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)) 1.3fr; gap: 40px; padding-top: 52px; padding-bottom: 52px; }
    .sk-footer h4 { margin: 0 0 22px; font-size: 16px; font-weight: 700; text-transform: uppercase; letter-spacing: .02em; }
    .sk-footer ul { margin: 0; padding: 0; list-style: none; display: grid; gap: 12px; }
    .sk-footer li a, .sk-footer li button { border: 0; background: none; padding: 0; color: #fff; font: 400 16px var(--font-body); text-decoration: none; cursor: pointer; text-align: left; }
    .sk-footer li a:hover, .sk-footer li button:hover { text-decoration: underline; text-underline-offset: 3px; }
    .sk-footer-about { font-size: 15px; line-height: 1.6; color: #BDBDBD; margin: 0 0 16px; }
    .sk-libro { display: inline-flex; align-items: center; gap: 14px; margin-top: 22px; padding: 12px 16px; background: #fff; color: var(--sk-ink) !important; font: 700 15px var(--font-body); text-transform: uppercase; text-decoration: none; border-radius: var(--sk-radius); }
    .sk-libro svg { width: 22px; height: 22px; }
    .sk-news p { margin: 0 0 18px; font-size: 16px; color: #E0E0E0; }
    .sk-news form { display: flex; align-items: stretch; gap: 0; }
    .sk-news input { flex: 1; min-width: 0; height: 46px; padding: 0 12px; border: 0; background: #fff; color: var(--sk-ink); font: 400 16px var(--font-body); outline: none; border-radius: var(--sk-radius) 0 0 var(--sk-radius); }
    .sk-news button { border: 0; background: none; color: #fff; padding: 0 18px; font: 700 16px var(--font-body); text-transform: uppercase; cursor: pointer; }
    .sk-news button:hover { text-decoration: underline; }
    .sk-news-ok { font-weight: 600; color: #9BE3B4; }
    .sk-social { display: flex; gap: 22px; margin-top: 26px; }
    .sk-social a { color: #fff; display: inline-flex; }
    .sk-social svg { width: 26px; height: 26px; }
    .sk-footer-bottom { border-top: 1px solid #333; }
    .sk-footer-bottom .sk-wrap { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; padding-top: 22px; padding-bottom: 22px; font-size: 13px; }
    .sk-footer-bottom nav { display: flex; flex-wrap: wrap; gap: 8px 18px; }
    .sk-footer-bottom a, .sk-footer-bottom button { color: #fff; text-decoration: none; background: none; border: 0; padding: 0; font: 400 13px var(--font-body); cursor: pointer; }
    .sk-footer-bottom a:hover { text-decoration: underline; }
    .sk-pay { display: flex; gap: 8px; flex-wrap: wrap; }
    .sk-pay span { display: inline-flex; align-items: center; justify-content: center; height: 26px; min-width: 42px; padding: 0 6px; background: #fff; color: #1a1f71; border-radius: 3px; font: 800 11px var(--font-body); letter-spacing: .02em; }
    .sk-footer-copy { color: #BDBDBD; font-size: 13px; }
    @media (max-width: 1023px) { .sk-footer-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 639px) { .sk-footer-grid { grid-template-columns: minmax(0, 1fr); gap: 30px; } }

    /* Pestaña "Regístrate" y WhatsApp */
    .sk-signup { position: fixed; left: 24px; bottom: 24px; z-index: 44; display: inline-flex; align-items: center; background: #fff; border: 1px solid var(--sk-ink); box-shadow: 0 6px 20px rgba(0, 0, 0, .12); border-radius: var(--sk-radius); }
    .sk-signup button { border: 0; background: none; cursor: pointer; color: var(--sk-ink); }
    .sk-signup .sk-signup-main { padding: 12px 6px 12px 16px; font: 700 15px var(--font-body); text-transform: uppercase; letter-spacing: .02em; }
    .sk-signup .sk-signup-x { padding: 10px 12px; display: grid; place-items: center; }
    .sk-signup svg { width: 18px; height: 18px; }
    @media (max-width: 767px) { .sk-signup { left: 12px; bottom: 12px; } .sk-signup .sk-signup-main { font-size: 13px; padding: 10px 4px 10px 12px; } }
    body.sk-has-mobile-buy .sk-signup { display: none; }

    @media (prefers-reduced-motion: reduce) {
        .sk-slide, .sk-slide.is-active { transition: none; }
        .sk-card-media img, .sk-tile img, .sk-campaign-media img { transition: none; }
    }

    /* ═══════════════════════════════════════════════════════════════════════
       STREET KICKS — capa visual propia (rojo y azul sobre negro, bandera de
       carreras, ciudad, tipografía inclinada). Va al final a propósito: reutiliza
       la estructura de Sport Pro y solo cambia cómo se ve.
       ═══════════════════════════════════════════════════════════════════════ */
    :root {
        --sk-ink: #0A0A0F;
        --sk-night: #0A0A0F;
        --sk-muted: #5B6072;
        --sk-line: #E1E4EE;
        --sk-soft: #F1F3F9;
        --sk-card: #EEF0F7;
        --sk-head-bg: #0A0A0F;
        --sk-checker: repeating-conic-gradient(#0A0A0F 0% 25%, #FFFFFF 0% 50%) 0 0 / 28px 28px;
    }
    .sk-body h1, .sk-body h2, .sk-body h3, .sk-hero-title, .sk-hero-sub, .sk-price, .sk-nav a { font-synthesis: none; }

    /* Cinta en movimiento */
    .sk-utility { background: var(--t-primary); color: #fff; border-bottom: 0; overflow: hidden; position: relative;
        font-family: var(--font-brand); font-size: 15px; font-weight: 400; letter-spacing: .08em; text-transform: uppercase; }
    .sk-utility .sk-wrap { max-width: none; padding: 0; display: block; min-height: 40px; }
    .sk-marquee { overflow: hidden; display: flex; align-items: center; min-height: 40px; }
    .sk-marquee-track { display: flex; width: max-content; animation: sk-marquee 44s linear infinite; }
    .sk-marquee:hover .sk-marquee-track { animation-play-state: paused; }
    .sk-marquee-set { display: flex; flex-shrink: 0; align-items: center; justify-content: space-around; min-width: 100vw; }
    .sk-marquee-set span { display: inline-flex; align-items: center; padding: 0 20px; font-style: italic; white-space: nowrap; }
    .sk-marquee-set span::after { content: '✦'; margin-left: 40px; color: var(--sk-night); font-style: normal; }
    @keyframes sk-marquee { to { transform: translateX(-33.3333%); } }
    .sk-utility-links { right: 0; padding-left: 26px; background: var(--sk-night); clip-path: polygon(14px 0, 100% 0, 100% 100%, 0 100%); }
    .sk-utility-links > * { border-left-color: rgba(255, 255, 255, .15); }
    .sk-utility-links a, .sk-utility-links > * > button, .sk-utility-links > button { color: #fff; font-family: var(--font-body); }
    @media (prefers-reduced-motion: reduce) {
        .sk-marquee-track { animation: none; width: auto; flex-wrap: wrap; justify-content: center; }
        .sk-marquee-set { min-width: 0; flex-wrap: wrap; justify-content: center; }
        .sk-marquee-set:nth-child(n + 2) { display: none; }
    }

    /* Menú: franja roja y azul debajo, enlaces inclinados */
    .sk-header { border-bottom: 4px solid var(--t-primary); box-shadow: 0 4px 0 var(--t-secondary); }
    .sk-nav a { font-family: var(--font-brand); font-style: italic; font-weight: 400; font-size: 20px; letter-spacing: .05em; text-transform: uppercase; }
    .sk-nav a::after { height: 4px; background: var(--t-primary); }
    .sk-nav a.is-sale, .sk-header[data-choice="dark"] .sk-nav a.is-sale { align-self: center; height: 40px; padding: 0 18px; background: var(--t-primary); color: #fff; transform: skewX(-12deg); box-shadow: 3px 3px 0 var(--t-secondary); }
    .sk-nav a.is-sale::after { display: none; }
    .sk-shipbar { background: var(--sk-night); color: #fff; border-bottom: 0; font-family: var(--font-brand); font-weight: 400; font-style: italic; letter-spacing: .06em; font-size: 15px; }
    .sk-count { border-radius: 0; transform: skewX(-12deg); }

    /* Botones: paralelogramo con sombra dura */
    .sk-btn { position: relative; border: 0; border-radius: 0; background: var(--t-primary); color: var(--t-on-primary) !important; min-height: 48px; padding: 0 28px;
        font-family: var(--font-brand); font-weight: 400; font-style: italic; font-size: 17px; letter-spacing: .06em; transform: skewX(-10deg); box-shadow: 4px 4px 0 var(--sk-night); transition: transform .15s, box-shadow .15s, background-color .15s; }
    .sk-btn:hover { background: var(--t-primary-deep, var(--t-primary)); transform: skewX(-10deg) translate(-2px, -2px); box-shadow: 7px 7px 0 var(--t-secondary); }
    .sk-btn:active { transform: skewX(-10deg) translate(2px, 2px); box-shadow: 1px 1px 0 var(--sk-night); }
    .sk-btn.is-white { background: #fff; color: var(--sk-ink) !important; box-shadow: 4px 4px 0 var(--t-primary); }
    .sk-btn.is-white:hover { background: #fff; box-shadow: 7px 7px 0 var(--t-secondary); }
    .sk-btn.is-outline { background: transparent; color: var(--sk-ink) !important; box-shadow: inset 0 0 0 3px var(--sk-ink), 4px 4px 0 var(--t-secondary); }
    .sk-btn.is-outline:hover { background: var(--sk-ink); color: #fff !important; }
    .sk-btn:disabled { background: #C9CCD8; color: #fff !important; box-shadow: none; }
    .sk-link { font-family: var(--font-brand); font-weight: 400; font-style: italic; font-size: 17px; letter-spacing: .05em; color: var(--t-primary); text-decoration: none; border-bottom: 3px solid var(--t-secondary); }

    /* Portada: ciudad, sol rojo, líneas de velocidad y corte en diagonal */
    .sk-hero { clip-path: polygon(0 0, 100% 0, 100% calc(100% - 26px), 0 100%); }
    .sk-slide-bg {
        background:
            radial-gradient(circle at 80% 38%, var(--t-primary) 0 13%, transparent 13.4%),
            radial-gradient(circle at 80% 38%, transparent 0 15.2%, var(--t-secondary) 15.6% 16.8%, transparent 17.2%),
            repeating-linear-gradient(115deg, rgba(255, 255, 255, .05) 0 2px, transparent 2px 28px),
            linear-gradient(135deg, #05050A 0%, #0F1230 58%, #1B0911 100%);
    }
    .sk-slide[data-choice="dark"] .sk-slide-bg {
        background:
            radial-gradient(circle at 80% 40%, var(--t-primary) 0 16%, transparent 16.4%),
            radial-gradient(circle at 80% 40%, transparent 0 18.6%, var(--t-secondary) 19% 20.4%, transparent 20.8%),
            linear-gradient(135deg, #FFFFFF 0%, #EAEEFF 100%);
    }
    .sk-slide[data-has-image] .sk-slide-bg { background: #000; }
    .sk-skyline { position: absolute; left: 0; right: 0; bottom: 0; height: 40%; min-height: 150px; pointer-events: none; z-index: 1; }
    .sk-skyline::before, .sk-skyline::after { content: ''; position: absolute; inset: 0; mask-repeat: repeat-x; -webkit-mask-repeat: repeat-x; mask-size: auto 100%; -webkit-mask-size: auto 100%; mask-position: left bottom; -webkit-mask-position: left bottom; }
    .sk-skyline::before {
        background: radial-gradient(circle, rgba(10, 10, 15, .55) 0 2.6px, transparent 3px) 0 0 / 16px 20px, var(--t-secondary);
        -webkit-mask-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 260'><path d='M0 150h80v110H0zM90 90h64v170H90zM164 120h90v140h-90zM264 60h56v200h-56zM280 60 292 12 304 60zM330 110h100v150H330zM440 80h70v180h-70zM520 130h90v130h-90zM620 50h60v210h-60zM690 100h84v160h-84zM784 140h70v120h-70zM864 70h66v190h-66zM880 70 897 18 914 70zM940 120h92v140h-92zM1042 90h70v170h-70zM1122 140h78v120h-78z'/></svg>");
        mask-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 260'><path d='M0 150h80v110H0zM90 90h64v170H90zM164 120h90v140h-90zM264 60h56v200h-56zM280 60 292 12 304 60zM330 110h100v150H330zM440 80h70v180h-70zM520 130h90v130h-90zM620 50h60v210h-60zM690 100h84v160h-84zM784 140h70v120h-70zM864 70h66v190h-66zM880 70 897 18 914 70zM940 120h92v140h-92zM1042 90h70v170h-70zM1122 140h78v120h-78z'/></svg>");
    }
    .sk-skyline::after {
        background: radial-gradient(circle, rgba(10, 10, 15, .6) 0 2.6px, transparent 3px) 0 0 / 16px 20px, var(--t-primary);
        -webkit-mask-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 260'><path d='M40 190h70v70H40zM130 160h80v100h-80zM230 200h60v60h-60zM360 170h90v90h-90zM470 200h70v60h-70zM580 150h76v110h-76zM700 190h80v70h-80zM820 160h70v100h-70zM930 200h80v60h-80zM1050 170h90v90h-90z'/></svg>");
        mask-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 260'><path d='M40 190h70v70H40zM130 160h80v100h-80zM230 200h60v60h-60zM360 170h90v90h-90zM470 200h70v60h-70zM580 150h76v110h-76zM700 190h80v70h-80zM820 160h70v100h-70zM930 200h80v60h-80zM1050 170h90v90h-90z'/></svg>");
    }
    .sk-slide[data-has-image] .sk-skyline { display: none; }
    .sk-slide[data-choice="image"] .sk-skyline { display: none; }
    .sk-slide-copy { z-index: 4; }
    .sk-hero-title { font-weight: 400; font-style: italic; color: #fff; line-height: .95; font-size: clamp(52px, 9.2vw, 158px); transform: skewX(-8deg); transform-origin: left bottom;
        text-shadow: 3px 3px 0 var(--t-primary), 6px 6px 0 var(--t-secondary); }
    .sk-slide-copy[data-choice="center"] .sk-hero-title, .sk-slide-copy[data-choice="bottom"] .sk-hero-title { transform-origin: center bottom; }
    .sk-slide[data-choice="dark"] .sk-hero-title { color: var(--sk-ink); text-shadow: 3px 3px 0 var(--t-primary); }
    .sk-hero-sub, .sk-slide[data-choice="dark"] .sk-hero-sub { display: inline-block; padding: 8px 20px; background: var(--t-secondary); color: #fff; font-style: italic; font-weight: 400; letter-spacing: .14em; font-size: clamp(14px, 1.7vw, 26px); transform: skewX(-12deg); box-shadow: 4px 4px 0 var(--sk-night); }
    .sk-hero-cta { margin-top: 18px; }
    .sk-hero-arrow { bottom: 40px; border-radius: 0; background: var(--t-primary); color: #fff; transform: skewX(-12deg); }
    .sk-hero-dots { bottom: 40px; }
    .sk-hero-dots button { height: 7px; width: 40px; transform: skewX(-30deg); background: rgba(255, 255, 255, .45); }
    .sk-hero-dots button.is-active { background: var(--t-primary); }
    .sk-hero[data-tone="dark"] .sk-hero-dots button.is-active { background: var(--t-primary); }
    @media (max-width: 767px) {
        .sk-hero { clip-path: polygon(0 0, 100% 0, 100% calc(100% - 16px), 0 100%); }
        .sk-slide-bg { background:
            radial-gradient(circle at 72% 26%, var(--t-primary) 0 20%, transparent 20.4%),
            radial-gradient(circle at 72% 26%, transparent 0 23%, var(--t-secondary) 23.4% 25%, transparent 25.4%),
            repeating-linear-gradient(115deg, rgba(255, 255, 255, .05) 0 2px, transparent 2px 28px),
            linear-gradient(160deg, #05050A 0%, #0F1230 58%, #1B0911 100%); }
        .sk-skyline { height: 34%; min-height: 110px; }
        .sk-hero-dots { bottom: 26px; }
        .sk-hero-title { font-size: clamp(46px, 15vw, 84px); }
    }

    /* Franja de sellos con bandera de carreras */
    .sk-hero + .sk-promos, .sk-hero + .sk-stamps { margin-top: -26px; }
    .sk-promos { background: var(--t-primary); font-family: var(--font-brand); font-style: italic; font-weight: 400; letter-spacing: .06em; }
    .sk-hero + .sk-promos .sk-wrap { padding-top: 34px; }
    .sk-stamps { position: relative; background: var(--sk-night); color: #fff; padding: 46px 0 24px; }
    .sk-stamps::before { content: ''; position: absolute; left: 0; right: 0; top: 0; height: 14px; background: var(--sk-checker); }
    .sk-stamps-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px 22px; }
    .sk-stamp-item { display: flex; align-items: center; gap: 14px; min-width: 0; }
    .sk-stamp-icon { flex: 0 0 52px; height: 52px; display: grid; place-items: center; background: var(--t-primary); color: #fff; transform: skewX(-10deg); box-shadow: 4px 4px 0 var(--t-secondary); }
    .sk-stamp-icon svg { width: 26px; height: 26px; transform: skewX(10deg); }
    .sk-stamp-copy { display: grid; gap: 2px; min-width: 0; }
    .sk-stamp-copy strong { font-family: var(--font-brand); font-style: italic; font-weight: 400; text-transform: uppercase; font-size: 19px; letter-spacing: .04em; line-height: 1.05; }
    .sk-stamp-copy small { color: #AEB4CA; font-size: 13px; line-height: 1.3; }
    @media (max-width: 899px) { .sk-stamps-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 479px) { .sk-stamp-icon { flex-basis: 42px; height: 42px; } .sk-stamp-icon svg { width: 21px; height: 21px; } .sk-stamp-copy strong { font-size: 15px; } .sk-stamp-copy small { font-size: 12px; } }

    /* Títulos de sección inclinados con barra tricolor */
    .sk-h2 { font-weight: 400; font-style: italic; font-size: clamp(32px, 4vw, 56px); letter-spacing: .01em; line-height: 1; transform: skewX(-6deg); color: var(--sk-ink); }
    .sk-h2::after { content: ''; display: block; width: 130px; height: 8px; margin: 14px auto 0; background: linear-gradient(90deg, var(--t-primary) 0 45%, var(--t-secondary) 45% 75%, var(--sk-night) 75%); transform: skewX(-24deg); }
    .sk-h2.is-left::after { margin-left: 0; }
    .sk-head .sk-h2::after { margin-top: 10px; }
    .sk-cat-title h1, .sk-buybox h1 { font-family: var(--font-brand); font-weight: 400; font-style: italic; text-transform: uppercase; letter-spacing: .01em; }
    .sk-section { padding: 58px 0; }
    .sk-section.is-dark { position: relative; background: var(--sk-night); color: #fff; padding: 84px 0 80px; }
    .sk-section.is-dark::before, .sk-section.is-dark::after { content: ''; position: absolute; left: 0; right: 0; height: 14px; background: var(--sk-checker); }
    .sk-section.is-dark::before { top: 0; } .sk-section.is-dark::after { bottom: 0; }
    .sk-section.is-dark .sk-h2 { color: #fff; }
    .sk-section.is-dark .sk-h2::after { background: linear-gradient(90deg, var(--t-primary) 0 45%, var(--t-secondary) 45% 75%, #fff 75%); }
    .sk-section.is-dark .sk-cat strong { color: #fff; }
    @media (max-width: 767px) { .sk-section { padding: 42px 0; } .sk-section.is-dark { padding: 64px 0 58px; } }

    /* Tarjetas de producto: foco de luz, bloque de color, sombra dura y estrella de oferta */
    .sk-card { transition: transform .22s ease; }
    .sk-card:hover { transform: translateY(-6px) rotate(-.4deg); }
    .sk-card-media { background: radial-gradient(circle at 50% 40%, #fff 0 30%, #E4E8F5 78%); border: 3px solid var(--sk-night); border-radius: 0; box-shadow: 0 0 0 0 var(--t-primary); transition: box-shadow .22s ease; }
    .sk-card:hover .sk-card-media { box-shadow: 7px 7px 0 0 var(--t-primary); }
    .sk-card-media::before { content: ''; position: absolute; z-index: 0; left: -18%; right: 34%; bottom: -26%; height: 66%; background: var(--t-primary); opacity: .16; transform: skewX(-24deg); transition: opacity .22s, transform .3s; }
    .sk-card:hover .sk-card-media::before { opacity: .3; transform: skewX(-24deg) translateX(14px); }
    .sk-card-media img { z-index: 1; }
    .sk-badge { z-index: 4; top: 6px; left: 6px; display: grid; place-items: center; width: 60px; height: 60px; padding: 0; background: var(--t-primary); color: #fff; border-radius: 0;
        font: 400 18px/1 var(--font-brand); font-style: italic; transform: rotate(-12deg);
        clip-path: polygon(50% 0%, 61% 12%, 79% 7%, 82% 25%, 100% 29%, 91% 45%, 100% 62%, 82% 66%, 79% 84%, 61% 79%, 50% 96%, 39% 79%, 21% 84%, 18% 66%, 0% 62%, 9% 45%, 0% 29%, 18% 25%, 21% 7%, 39% 12%); }
    .sk-badge.is-dark { background: var(--t-secondary); font-size: 14px; }
    .sk-original { position: absolute; left: 0; bottom: 10px; z-index: 4; padding: 5px 10px 5px 8px; background: var(--sk-night); color: #fff; border-left: 4px solid var(--t-primary);
        font: 400 12px/1 var(--font-brand); font-style: italic; letter-spacing: .14em; text-transform: uppercase; }
    .sk-card-add { z-index: 4; border-radius: 0; background: var(--t-primary); color: #fff; transform: skewX(-10deg); box-shadow: 3px 3px 0 var(--sk-night); }
    .sk-card-add:hover { background: var(--sk-night); color: #fff; }
    .sk-card-add svg { transform: skewX(10deg); }
    .sk-card-name { font-weight: 700; font-size: 15px; text-transform: uppercase; letter-spacing: .01em; }
    .sk-card-meta { font-weight: 600; letter-spacing: .08em; font-size: 12px; color: var(--t-secondary); }
    .sk-price { font-family: var(--font-brand); font-weight: 400; font-style: italic; font-size: 25px; letter-spacing: .01em; }
    .sk-price s { font-family: var(--font-body); font-style: normal; font-weight: 600; font-size: 15px; }
    .sk-buybox .sk-price { font-size: 34px; }
    .sk-buybox .sk-price s { font-size: 19px; }
    .sk-section.is-dark .sk-card-name, .sk-section.is-dark .sk-price { color: #fff; }
    @media (max-width: 767px) { .sk-badge { width: 50px; height: 50px; font-size: 15px; } .sk-price { font-size: 21px; } .sk-card-media { border-width: 2px; } }

    /* Tarjetas "Lo nuevo", campañas y categorías */
    .sk-tile, .sk-campaign-media, .sk-cat-media { border: 3px solid var(--sk-night); border-radius: 0; box-shadow: 8px 8px 0 var(--t-primary); transition: transform .25s ease, box-shadow .25s ease; }
    .sk-tile:hover, .sk-campaign-media:hover, .sk-cat:hover .sk-cat-media { transform: translate(-4px, -4px) rotate(-.6deg); box-shadow: 12px 12px 0 var(--t-secondary); }
    .sk-tile-fallback { background: linear-gradient(160deg, var(--t-secondary) 0%, var(--sk-night) 68%, var(--t-primary) 100%); }
    .sk-tile::after { background: linear-gradient(0deg, rgba(10, 10, 15, .88), rgba(10, 10, 15, 0)); }
    .sk-tile-copy h3 { color: #fff; }
    .sk-logo img { height: 60px; max-width: 230px; border-radius: 0; }
    @media (max-width: 1023px) { .sk-logo img { height: 46px; max-width: 170px; } }
    .sk-tile-copy h3, .sk-campaign h3 { font-weight: 400; font-style: italic; letter-spacing: .02em; text-shadow: 2px 2px 0 var(--t-primary); }
    .sk-campaign h3 { text-shadow: 2px 2px 0 var(--t-secondary); color: var(--sk-ink); }
    .sk-campaign p { font-weight: 600; color: var(--sk-muted); letter-spacing: .06em; }
    .sk-cat strong { font-weight: 400; font-style: italic; letter-spacing: .04em; font-size: 22px; }
    .sk-section.is-dark .sk-cat-media { border-color: #fff; box-shadow: 8px 8px 0 var(--t-primary); }
    .sk-campaign-media, .sk-cat-media { margin-bottom: 8px; }

    /* Catálogo y ficha de producto */
    .sk-crumbs a { color: var(--t-primary); }
    .sk-filterbar { border-top: 3px solid var(--sk-night); border-bottom: 3px solid var(--sk-night); }
    .sk-size, .sk-swatch, .sk-dd > button { border-width: 2px; border-color: var(--sk-night); border-radius: 0; font-weight: 700; }
    .sk-size.is-active { background: var(--t-primary); border-color: var(--t-primary); color: #fff; box-shadow: 3px 3px 0 var(--sk-night); }
    .sk-gallery-item { border: 3px solid var(--sk-night); border-radius: 0; background: radial-gradient(circle at 50% 40%, #fff 0 30%, #E4E8F5 78%); }
    .sk-buybox-kicker { color: var(--t-secondary); font-weight: 700; letter-spacing: .1em; }

    /* Pie de página: bandera de carreras, enlaces con color y nombre gigante */
    .sk-footer { position: relative; overflow: hidden; background: var(--sk-night); margin-top: 0; padding-top: 14px; }
    .sk-footer::before { content: ''; position: absolute; left: 0; right: 0; top: 0; height: 14px; background: var(--sk-checker); z-index: 2; }
    .sk-footer::after { content: attr(data-brand); position: absolute; left: 50%; bottom: -.18em; transform: translateX(-50%) skewX(-8deg); white-space: nowrap; pointer-events: none;
        font: 400 clamp(90px, 17vw, 280px)/1 var(--font-brand); font-style: italic; text-transform: uppercase; color: transparent; -webkit-text-stroke: 2px rgba(255, 255, 255, .07); }
    .sk-footer > * { position: relative; z-index: 1; }
    .sk-footer h4 { font-family: var(--font-brand); font-style: italic; font-weight: 400; font-size: 20px; letter-spacing: .06em; color: var(--t-primary); }
    .sk-footer li a:hover, .sk-footer li button:hover { color: var(--t-secondary); text-decoration: none; }
    .sk-news button { background: var(--t-primary); color: #fff; font-family: var(--font-brand); font-style: italic; font-weight: 400; letter-spacing: .06em; }
    .sk-news input { border-radius: 0; }
    .sk-footer-bottom { border-top: 1px solid rgba(255, 255, 255, .12); }
</style>
