{{-- Urban Style: estilos propios de la plantilla (prefijo us-). CSS plano a propósito:
     no depende de clases nuevas de Tailwind, así que no requiere recompilar con Vite. --}}
<style>
    :root {
        --us-ink: #111111;
        --us-muted: #6B6B6B;
        --us-line: #E8E8E8;
        --us-soft: #F3F3F3;
        --us-header-h: 76px;
        --us-radius: 14px;
    }
    @media (max-width: 767px) { :root { --us-header-h: 60px; } }

    .us-body { font-family: 'Poppins', sans-serif; color: var(--us-ink); }
    .us-wrap { width: 100%; max-width: 1440px; margin: 0 auto; padding: 0 16px; }
    @media (min-width: 768px) { .us-wrap { padding: 0 32px; } }
    .us-sr { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
    [x-cloak] { display: none !important; }

    /* ── Barras superiores ─────────────────────────────────────────────── */
    .us-ann { background: #0B0B0B; color: #fff; font-size: 12px; font-weight: 500; text-align: center; padding: 9px 16px; letter-spacing: .01em; }
    .us-promo { display: flex; align-items: center; justify-content: center; gap: 10px 14px; flex-wrap: wrap; padding: 9px 16px; color: #fff; font-size: 12px; font-weight: 500; text-align: center; text-decoration: none;
        background: linear-gradient(90deg, var(--t-secondary), var(--t-primary-dark) 45%, var(--t-primary)); }
    .us-promo:hover .us-promo-text { text-decoration: underline; text-underline-offset: 3px; }
    .us-countdown { display: inline-flex; align-items: center; gap: 5px; font-variant-numeric: tabular-nums; }
    .us-countdown[hidden] { display: none; }
    .us-countdown b { display: inline-flex; align-items: baseline; gap: 3px; background: #fff; color: var(--us-ink); border-radius: 5px; padding: 3px 7px; font-size: 11px; font-weight: 700; line-height: 1.2; }
    .us-countdown b small { font-size: 10px; font-weight: 500; color: var(--us-muted); }
    .us-countdown i { font-style: normal; font-weight: 700; }

    /* ── Header ────────────────────────────────────────────────────────── */
    .us-header { position: sticky; top: 0; z-index: 50; height: var(--us-header-h); color: var(--us-ink); background: #fff; border-bottom: 1px solid var(--us-line);
        transition: background-color .3s, color .3s, box-shadow .3s, border-color .3s; }
    .us-header.is-solid { box-shadow: 0 6px 24px rgba(0, 0, 0, .06); }
    .us-header[data-choice="overlay"]:not(.is-solid) { background: transparent; color: #fff; border-color: transparent; }
    .us-header[data-choice="overlay"]:not(.is-solid)::before { content: ''; position: absolute; inset: 0 0 -40px; z-index: -1; pointer-events: none; background: linear-gradient(rgba(0, 0, 0, .42), rgba(0, 0, 0, 0)); }
    .us-header-row { height: 100%; display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 24px; }
    .us-logo { display: inline-flex; align-items: center; min-width: 0; text-decoration: none; color: inherit; }
    .us-logo img { height: 42px; width: auto; max-width: 180px; object-fit: contain; }
    .us-logo span { font-family: var(--font-brand); font-size: 26px; font-weight: 800; letter-spacing: -.02em; line-height: 1; white-space: nowrap; }
    .us-header[data-choice="overlay"]:not(.is-solid) .us-logo img[data-choice="white"] { filter: brightness(0) invert(1); }
    .us-nav { display: flex; justify-content: center; align-items: center; gap: 6px 26px; flex-wrap: wrap; min-width: 0; }
    .us-nav a { position: relative; color: inherit; text-decoration: none; font-size: 15px; font-weight: 600; white-space: nowrap; padding: 6px 0; }
    .us-nav a::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 2px; border-radius: 2px; background: var(--t-primary); transform: scaleX(0); transition: transform .25s; }
    .us-nav a:hover::after, .us-nav a.is-active::after { transform: scaleX(1); }
    .us-tools { display: flex; align-items: center; gap: 6px; }
    .us-search { position: relative; display: flex; align-items: center; width: 240px; margin-right: 10px; }
    .us-search input { width: 100%; background: transparent; border: 0; border-bottom: 1.5px solid currentColor; color: inherit; font: italic 600 13px/1 'Poppins', sans-serif; padding: 8px 30px 8px 0; outline: none; border-radius: 0; }
    .us-search input::placeholder { color: inherit; opacity: .85; }
    .us-search input:focus { border-bottom-color: var(--t-primary); }
    .us-search button { position: absolute; right: 0; color: inherit; background: none; border: 0; padding: 4px; cursor: pointer; }
    .us-icon-btn { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 999px; color: inherit; background: none; border: 0; cursor: pointer; transition: background-color .2s; text-decoration: none; }
    .us-icon-btn:hover { background: rgba(127, 127, 127, .14); }
    .us-icon-btn svg { width: 23px; height: 23px; }
    .us-cart-count { position: absolute; top: 3px; right: 1px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: var(--t-primary); color: var(--t-on-primary); font-size: 10px; font-weight: 700; line-height: 18px; text-align: center; }
    .us-pill-btn { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 10px; border-radius: 999px; border: 1px solid rgba(127, 127, 127, .35); background: transparent; color: inherit; font-size: 12px; font-weight: 600; cursor: pointer; white-space: nowrap; }
    .us-pill-btn img { width: 18px; height: 13px; object-fit: cover; border-radius: 2px; }
    .us-dropdown { position: absolute; right: 0; top: calc(100% + 8px); z-index: 60; min-width: 210px; padding: 6px; border-radius: 12px; background: #fff; color: var(--us-ink); box-shadow: 0 18px 50px rgba(0, 0, 0, .18); border: 1px solid var(--us-line); }
    .us-dropdown button { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 9px 10px; border-radius: 8px; border: 0; background: none; font: 600 12px/1.2 'Poppins', sans-serif; color: inherit; cursor: pointer; text-align: left; }
    .us-dropdown button:hover { background: var(--us-soft); }
    .us-dropdown button.is-active { color: var(--t-primary); }
    .us-dropdown img { width: 18px; height: 13px; object-fit: cover; border-radius: 2px; }
    .us-only-mobile { display: none !important; }
    @media (max-width: 1180px) { .us-search { width: 180px; } .us-nav { gap: 6px 18px; } .us-nav a { font-size: 14px; } }
    @media (max-width: 1023px) {
        .us-nav, .us-search, .us-hide-mobile { display: none !important; }
        .us-only-mobile { display: inline-flex !important; }
        .us-header-row { grid-template-columns: auto 1fr auto; gap: 8px; }
        .us-logo { justify-self: start; }
    }
    @media (max-width: 767px) { .us-logo img { height: 34px; max-width: 140px; } .us-logo span { font-size: 21px; } .us-icon-btn { width: 38px; height: 38px; } .us-icon-btn svg { width: 21px; height: 21px; } }

    /* Menú móvil */
    .us-drawer { position: fixed; inset: 0; z-index: 70; display: flex; }
    .us-slide-tr { transition: transform .3s ease; }
    .us-off-left { transform: translateX(-100%); }
    .us-on { transform: none; }
    .us-drawer-backdrop { position: absolute; inset: 0; background: rgba(0, 0, 0, .5); }
    .us-drawer-panel { position: relative; width: min(88%, 380px); height: 100%; background: #fff; display: flex; flex-direction: column; box-shadow: 0 0 60px rgba(0, 0, 0, .25); }
    .us-drawer-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--us-line); }
    .us-drawer-body { flex: 1; overflow-y: auto; padding: 16px; display: grid; gap: 18px; align-content: start; }
    .us-drawer-search { display: flex; align-items: center; gap: 8px; border: 1px solid var(--us-line); border-radius: 999px; padding: 0 14px; }
    .us-drawer-search input { flex: 1; min-width: 0; border: 0; outline: none; font: 500 14px 'Poppins', sans-serif; padding: 12px 0; background: none; }
    .us-drawer-links a, .us-drawer-links button { display: flex; align-items: center; justify-content: space-between; width: 100%; padding: 14px 4px; border: 0; border-bottom: 1px solid var(--us-line); background: none; color: var(--us-ink); font: 600 15px 'Poppins', sans-serif; text-decoration: none; cursor: pointer; text-align: left; }
    .us-drawer-links a.is-active { color: var(--t-primary); }
    .us-drawer-prefs { display: grid; gap: 10px; padding: 14px; border-radius: 12px; background: var(--us-soft); font-size: 12px; font-weight: 600; }
    .us-drawer-prefs > div { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .us-drawer-prefs select { border: 1px solid var(--us-line); border-radius: 999px; background: #fff; padding: 6px 28px 6px 12px; font: 600 12px 'Poppins', sans-serif; }
    .us-seg { display: inline-flex; padding: 3px; border-radius: 999px; background: #fff; border: 1px solid var(--us-line); }
    .us-seg button { border: 0; background: none; padding: 5px 12px; border-radius: 999px; font: 700 12px 'Poppins', sans-serif; cursor: pointer; color: var(--us-muted); }
    .us-seg button.is-active { background: var(--us-ink); color: #fff; }

    /* Búsqueda de pantalla completa (móvil) */
    .us-search-overlay { position: fixed; inset: 0 0 auto; z-index: 70; background: #fff; padding: 18px 16px; box-shadow: 0 20px 50px rgba(0, 0, 0, .15); }
    .us-search-overlay form { display: flex; align-items: center; gap: 10px; border-bottom: 2px solid var(--us-ink); }
    .us-search-overlay input { flex: 1; min-width: 0; border: 0; outline: none; font: 600 18px 'Poppins', sans-serif; padding: 12px 0; }

    /* ── Hero: banners ─────────────────────────────────────────────────── */
    .us-hero { position: relative; overflow: hidden; background: var(--t-secondary); color: #fff; }
    .us-hero-shell[data-choice="overlay"] .us-hero { margin-top: calc(-1 * var(--us-header-h)); }
    .us-hero-track { position: relative; height: min(86vh, 880px); min-height: 520px; }
    .us-hero[data-choice="full"] .us-hero-track { height: 100vh; height: 100svh; }
    .us-hero[data-choice="medium"] .us-hero-track { height: min(64vh, 640px); min-height: 440px; }
    @media (max-width: 767px) {
        .us-hero-track { height: min(128vw, 86svh); min-height: 480px; }
        .us-hero[data-choice="full"] .us-hero-track { height: 100svh; }
        .us-hero[data-choice="medium"] .us-hero-track { height: min(105vw, 70svh); min-height: 400px; }
    }
    .us-slide { position: absolute; inset: 0; opacity: 0; visibility: hidden; transition: opacity .8s ease, visibility 0s linear .8s; }
    .us-slide.is-active { opacity: 1; visibility: visible; transition: opacity .8s ease; z-index: 1; }
    .us-slide[hidden] { display: none; }
    .us-slide-bg { position: absolute; inset: 0; overflow: hidden;
        background: radial-gradient(120% 90% at 0% 100%, var(--t-primary) 0%, var(--t-primary-dark) 38%, var(--t-secondary) 72%); }
    .us-slide-bg::after { content: ''; position: absolute; right: -18%; top: -30%; width: 75%; height: 160%; border-radius: 50%; background: radial-gradient(closest-side, rgba(255, 255, 255, .16), rgba(255, 255, 255, 0)); }
    .us-slide[data-choice="dark"] .us-slide-bg { background: radial-gradient(120% 90% at 0% 100%, var(--t-primary-100) 0%, var(--t-primary-50) 45%, #fff 80%); }
    .us-slide-bg picture, .us-slide-bg img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .us-slide-bg img[hidden] { display: none; }
    .us-slide.is-active .us-slide-bg img { animation: us-kenburns 9s ease-out both; }
    @keyframes us-kenburns { from { transform: scale(1.06); } to { transform: scale(1); } }
    .us-slide-scrim { position: absolute; inset: 0; pointer-events: none; opacity: 0; transition: opacity .3s; }
    .us-slide[data-has-image] .us-slide-scrim { opacity: 1; }
    .us-slide[data-choice="light"] .us-slide-scrim { background: linear-gradient(90deg, rgba(0, 0, 0, .55), rgba(0, 0, 0, .15) 55%, rgba(0, 0, 0, 0)); }
    .us-slide[data-choice="light"] .us-slide-scrim[data-choice="right"] { background: linear-gradient(270deg, rgba(0, 0, 0, .55), rgba(0, 0, 0, .15) 55%, rgba(0, 0, 0, 0)); }
    .us-slide[data-choice="light"] .us-slide-scrim[data-choice="center"] { background: radial-gradient(60% 70% at 50% 55%, rgba(0, 0, 0, .45), rgba(0, 0, 0, .12)); }
    .us-slide[data-choice="dark"] .us-slide-scrim { background: linear-gradient(90deg, rgba(255, 255, 255, .75), rgba(255, 255, 255, 0) 60%); }
    .us-slide[data-choice="dark"] .us-slide-scrim[data-choice="right"] { background: linear-gradient(270deg, rgba(255, 255, 255, .75), rgba(255, 255, 255, 0) 60%); }
    .us-slide[data-choice="dark"] .us-slide-scrim[data-choice="center"] { background: radial-gradient(60% 70% at 50% 55%, rgba(255, 255, 255, .7), rgba(255, 255, 255, .15)); }
    .us-slide[data-choice="image"] .us-slide-scrim, .us-slide[data-choice="image"] .us-slide-copy { display: none; }
    .us-slide-link { position: absolute; inset: 0; z-index: 2; display: none; }
    .us-slide[data-choice="image"] .us-slide-link { display: block; }

    .us-slide-copy { position: relative; z-index: 3; height: 100%; display: flex; align-items: center; padding-top: var(--us-header-h); padding-bottom: 48px; }
    .us-hero-shell[data-choice="solid"] .us-slide-copy { padding-top: 0; }
    .us-slide-copy .us-wrap { display: flex; }
    .us-copy { width: min(100%, 640px); display: flex; flex-direction: column; align-items: center; text-align: center; margin-left: 4%; }
    .us-slide-copy[data-choice="center"] .us-wrap { justify-content: center; }
    .us-slide-copy[data-choice="center"] .us-copy { margin: 0; }
    .us-slide-copy[data-choice="right"] .us-wrap { justify-content: flex-end; }
    .us-slide-copy[data-choice="right"] .us-copy { margin: 0 4% 0 0; }
    .us-slide[data-choice="dark"] .us-copy { color: var(--us-ink); }
    .us-copy [hidden] { display: none !important; }
    .us-title { font-family: var(--font-brand); font-weight: 900; text-transform: uppercase; line-height: .92; letter-spacing: -.02em; font-size: clamp(38px, 5vw, 76px); text-shadow: 0 4px 24px rgba(0, 0, 0, .12); }
    .us-eyebrow { margin-top: 12px; padding-bottom: 14px; font-size: clamp(13px, 1.3vw, 18px); font-weight: 600; letter-spacing: .12em; text-transform: uppercase; position: relative; }
    .us-eyebrow::after { content: ''; position: absolute; left: 50%; bottom: 0; width: min(290px, 60vw); height: 2px; transform: translateX(-50%); background: currentColor; opacity: .7; }
    .us-figure { margin-top: 14px; display: grid; grid-template-columns: auto auto; grid-template-rows: auto auto; align-items: end; justify-content: center; column-gap: 10px; }
    .us-figure-prefix { grid-column: 1 / -1; justify-self: start; font-size: clamp(16px, 1.8vw, 24px); font-weight: 700; line-height: 1; margin-bottom: -4px; }
    .us-figure-value { font-family: var(--font-brand); font-weight: 900; font-size: clamp(80px, 11vw, 168px); line-height: .82; letter-spacing: -.05em; }
    .us-figure-note { font-weight: 800; font-size: clamp(14px, 1.6vw, 22px); text-transform: uppercase; letter-spacing: .02em; padding-bottom: .6em; }
    .us-badge { margin-top: 18px; display: inline-block; background: #fff; color: var(--t-primary); border-radius: 10px; padding: 8px 18px; font-family: var(--font-brand); font-weight: 800; font-size: clamp(15px, 1.7vw, 24px); text-transform: uppercase; letter-spacing: -.01em; box-shadow: 0 10px 30px rgba(0, 0, 0, .12); }
    .us-slide[data-choice="dark"] .us-badge { background: var(--t-primary); color: var(--t-on-primary); }
    .us-legal { margin-top: 10px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; opacity: .85; }
    .us-cta { margin-top: 20px; display: inline-flex; align-items: center; gap: 10px; color: inherit; font-weight: 700; font-size: 15px; letter-spacing: .06em; text-transform: uppercase; text-decoration: none; padding-bottom: 4px; border-bottom: 2px solid currentColor; transition: gap .2s, color .2s; }
    .us-cta:hover { gap: 16px; }
    .us-cta[hidden] { display: none; }

    .us-hero-arrow { position: absolute; top: 50%; z-index: 5; transform: translateY(-50%); width: 48px; height: 48px; border-radius: 999px; border: 0; background: transparent; color: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: background-color .2s; }
    .us-hero-arrow:hover { background: rgba(255, 255, 255, .16); }
    .us-hero-arrow svg { width: 28px; height: 28px; }
    .us-hero-arrow.is-prev { left: 12px; }
    .us-hero-arrow.is-next { right: 12px; }
    .us-hero[data-tone="dark"] .us-hero-arrow, .us-hero[data-tone="dark"] .us-hero-dots { color: var(--us-ink); }
    .us-hero-dots { position: absolute; left: 50%; bottom: 44px; z-index: 5; transform: translateX(-50%); display: flex; gap: 8px; color: #fff; }
    .us-hero-dots button { width: 9px; height: 9px; padding: 0; border-radius: 999px; border: 0; background: currentColor; opacity: .45; cursor: pointer; transition: width .3s, opacity .3s; }
    .us-hero-dots button.is-active { width: 28px; opacity: 1; }
    .us-hero-down { position: absolute; left: 50%; bottom: -22px; z-index: 5; transform: translateX(-50%); width: 46px; height: 46px; border-radius: 999px; background: #fff; color: var(--us-ink); display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 8px 24px rgba(0, 0, 0, .18); border: 0; cursor: pointer; }
    .us-hero-down svg { width: 20px; height: 20px; }
    @media (max-width: 767px) {
        .us-hero-arrow { display: none; }
        .us-copy, .us-slide-copy[data-choice="right"] .us-copy { margin: 0 auto; }
        .us-slide-copy .us-wrap { justify-content: center !important; }
        .us-slide[data-choice="light"] .us-slide-scrim[data-choice] { background: linear-gradient(0deg, rgba(0, 0, 0, .6), rgba(0, 0, 0, .15) 70%); }
        .us-slide[data-choice="dark"] .us-slide-scrim[data-choice] { background: linear-gradient(0deg, rgba(255, 255, 255, .8), rgba(255, 255, 255, .1) 70%); }
        .us-slide-copy { align-items: flex-end; padding-bottom: 70px; }
        .us-hero-dots { bottom: 40px; }
    }

    /* ── Secciones ─────────────────────────────────────────────────────── */
    .us-section { padding: 56px 0; }
    .us-section + .us-section { padding-top: 8px; }
    .us-section-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 26px; flex-wrap: wrap; }
    .us-h2 { font-family: var(--font-brand); font-weight: 800; text-transform: uppercase; letter-spacing: -.01em; font-size: clamp(24px, 3vw, 40px); line-height: 1.05; }
    .us-h2.is-center { text-align: center; width: 100%; }
    .us-link { color: var(--us-ink); font-weight: 700; font-size: 13px; letter-spacing: .06em; text-transform: uppercase; text-decoration: none; border-bottom: 2px solid var(--t-primary); padding-bottom: 3px; }
    .us-link:hover { color: var(--t-primary); }
    @media (max-width: 767px) { .us-section { padding: 40px 0; } }

    /* Categorías */
    .us-cats { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(132px, 1fr); gap: 16px; overflow-x: auto; scroll-snap-type: x mandatory; padding-bottom: 6px; scrollbar-width: none; }
    .us-cats::-webkit-scrollbar { display: none; }
    .us-cat { scroll-snap-align: start; display: grid; gap: 10px; justify-items: center; text-decoration: none; color: var(--us-ink); }
    .us-cat-media { position: relative; width: 100%; aspect-ratio: 1; border-radius: var(--us-radius); overflow: hidden; background: var(--us-soft); display: grid; place-items: center; transition: transform .3s, box-shadow .3s; }
    .us-cat-media img { width: 100%; height: 100%; object-fit: cover; transition: transform .6s; }
    .us-cat-media span { font-size: 42px; }
    .us-cat:hover .us-cat-media { box-shadow: 0 12px 30px rgba(0, 0, 0, .1); }
    .us-cat:hover .us-cat-media img { transform: scale(1.06); }
    .us-cat strong { font-size: 14px; font-weight: 500; text-align: center; }
    .us-cats[data-choice="circle"] .us-cat-media { border-radius: 999px; }
    @media (min-width: 1024px) { .us-cats { grid-auto-flow: row; grid-template-columns: repeat(auto-fit, minmax(150px, 180px)); justify-content: center; overflow: visible; } }

    /* Banners de colección */
    .us-editorial { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .us-edit { position: relative; display: block; overflow: hidden; border-radius: 4px; aspect-ratio: 5 / 6; background: var(--us-soft); color: #fff; text-decoration: none; }
    .us-edit img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transition: transform .9s ease; }
    .us-edit img[hidden] { display: none; }
    .us-edit:hover img { transform: scale(1.04); }
    .us-edit::before { content: ''; position: absolute; inset: 0; z-index: 1; background: linear-gradient(180deg, rgba(0, 0, 0, .45), rgba(0, 0, 0, 0) 40%, rgba(0, 0, 0, 0) 65%, rgba(0, 0, 0, .35)); }
    .us-edit-fallback { position: absolute; inset: 0; background: linear-gradient(160deg, var(--t-primary-300), var(--t-secondary)); }
    .us-edit-copy { position: absolute; inset: 0; z-index: 2; display: flex; flex-direction: column; justify-content: space-between; padding: clamp(20px, 4vw, 56px); }
    .us-edit-copy h3 { font-family: var(--font-brand); font-weight: 800; text-transform: uppercase; font-size: clamp(24px, 3.2vw, 46px); line-height: 1; max-width: 12ch; }
    .us-edit-copy span { align-self: flex-start; font-weight: 700; font-size: 13px; letter-spacing: .08em; text-transform: uppercase; border-bottom: 2px solid #fff; padding-bottom: 3px; }
    @media (max-width: 767px) { .us-editorial { grid-template-columns: 1fr; } .us-edit { aspect-ratio: 4 / 5; } }

    /* Vitrina con pestañas */
    .us-tabs { display: flex; align-items: center; gap: 0; flex-wrap: wrap; margin-bottom: 18px; font-size: 15px; }
    .us-tabs-label { font-weight: 700; margin-right: 18px; }
    .us-tabs button { border: 0; background: none; font: 400 15px 'Poppins', sans-serif; color: var(--us-muted); padding: 4px 24px; cursor: pointer; border-right: 1px solid #cfcfcf; }
    .us-tabs button:first-of-type { padding-left: 0; }
    .us-tabs button:last-of-type { border-right: 0; }
    .us-tabs button.is-active { color: var(--us-ink); font-weight: 700; }
    .us-rail { position: relative; }
    .us-rail-track { display: grid; grid-auto-flow: column; grid-auto-columns: calc((100% - 2 * 28px) / 3); gap: 28px; overflow-x: auto; scroll-snap-type: x mandatory; scroll-behavior: smooth; scrollbar-width: none; }
    .us-rail-track::-webkit-scrollbar { display: none; }
    .us-rail-track > * { scroll-snap-align: start; }
    .us-rail-btn { position: absolute; top: 40%; z-index: 3; width: 46px; height: 46px; border-radius: 999px; border: 0; background: #fff; color: var(--us-ink); box-shadow: 0 6px 20px rgba(0, 0, 0, .15); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
    .us-rail-btn svg { width: 22px; height: 22px; }
    .us-rail-btn.is-prev { left: -20px; }
    .us-rail-btn.is-next { right: -20px; }
    .us-rail-btn:disabled { opacity: 0; pointer-events: none; }
    @media (max-width: 1023px) { .us-rail-track { grid-auto-columns: calc((100% - 16px) / 2); gap: 16px; } .us-rail-btn.is-prev { left: 4px; } .us-rail-btn.is-next { right: 4px; } }
    @media (max-width: 639px) { .us-rail-track { grid-auto-columns: 78%; gap: 12px; } .us-rail-btn { display: none; } }

    /* Tarjeta de producto */
    .us-card { position: relative; display: flex; flex-direction: column; min-width: 0; }
    .us-card-media { position: relative; display: block; aspect-ratio: 4 / 5; overflow: hidden; background: #EFEFEF; }
    .us-card-media img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; transition: transform .8s ease; }
    .us-card:hover .us-card-media img { transform: scale(1.04); }
    .us-card-off { position: absolute; top: 12px; left: 12px; z-index: 2; width: 52px; height: 52px; border-radius: 999px; background: #0B0B0B; color: #fff; display: grid; place-items: center; font-weight: 800; font-size: 15px; letter-spacing: -.02em; }
    .us-card-add { position: absolute; top: 12px; right: 12px; z-index: 2; width: 40px; height: 40px; border-radius: 999px; border: 0; background: rgba(255, 255, 255, .92); color: var(--us-ink); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 14px rgba(0, 0, 0, .1); transition: background-color .2s, color .2s, transform .2s; text-decoration: none; }
    .us-card-add:hover { background: var(--t-primary); color: var(--t-on-primary); transform: scale(1.06); }
    .us-card-add svg { width: 19px; height: 19px; }
    .us-card-body { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 2px 12px; padding: 12px 10px 4px; align-items: end; }
    .us-card-kicker { grid-column: 1; font-size: 12px; color: var(--us-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .us-card-name { grid-column: 1; font-size: 14px; color: var(--us-ink); text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .us-card-name:hover { color: var(--t-primary); }
    .us-card-price { grid-column: 2; grid-row: 1 / span 2; text-align: right; display: grid; justify-items: end; line-height: 1.2; }
    .us-card-price s { font-size: 12px; color: #9a9a9a; }
    .us-card-price strong { font-size: 17px; font-weight: 700; white-space: nowrap; }
    .us-card-price strong.is-sale { color: var(--t-primary); }
    .us-card .tribio-rating-badge, .us-card-rating { grid-column: 1 / -1; }

    .us-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 28px 20px; }
    @media (max-width: 1023px) { .us-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 767px) { .us-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 22px 12px; } .us-card-body { grid-template-columns: 1fr; padding: 10px 2px 0; } .us-card-price { grid-column: 1; grid-row: auto; justify-items: start; display: flex; gap: 8px; align-items: baseline; } .us-card-off { width: 44px; height: 44px; font-size: 13px; } }
    .us-btn { display: inline-flex; align-items: center; justify-content: center; gap: 10px; min-height: 50px; padding: 0 30px; border-radius: 999px; background: var(--us-ink); color: #fff; font-weight: 700; font-size: 13px; letter-spacing: .08em; text-transform: uppercase; text-decoration: none; transition: background-color .2s, color .2s; border: 0; cursor: pointer; }
    .us-btn:hover { background: var(--t-primary); color: var(--t-on-primary); }
    .us-btn.is-primary { background: var(--t-primary); color: var(--t-on-primary); }
    .us-btn.is-primary:hover { background: var(--t-primary-dark); color: #fff; }

    /* Promos automáticas (envío gratis / descuento por volumen) */
    .us-promos { background: var(--t-primary-50); border-bottom: 1px solid var(--t-primary-100); }
    .us-promos .us-wrap { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 28px; padding-top: 12px; padding-bottom: 12px; font-size: 14px; font-weight: 700; color: var(--t-primary-deep); }

    /* Beneficios */
    .us-benefits { border-top: 1px solid var(--us-line); }
    .us-benefits .us-wrap { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px; padding-top: 40px; padding-bottom: 40px; }
    .us-benefit { display: flex; align-items: center; gap: 16px; }
    .us-benefit i { flex-shrink: 0; width: 54px; height: 54px; border-radius: 999px; display: grid; place-items: center; background: var(--t-primary-50); color: var(--t-primary); }
    .us-benefit i svg { width: 26px; height: 26px; }
    .us-benefit h4 { font-weight: 700; font-size: 15px; }
    .us-benefit p { font-size: 13px; color: var(--us-muted); margin-top: 2px; }
    @media (max-width: 767px) { .us-benefits .us-wrap { grid-template-columns: 1fr; gap: 18px; } }

    /* Pop-up promocional */
    .us-popup { position: fixed; inset: 0; z-index: 90; display: grid; place-items: center; padding: 16px; background: rgba(0, 0, 0, .55); animation: us-fade .25s ease both; }
    .us-popup .us-popup-card { animation: us-pop .3s ease both; }
    @keyframes us-fade { from { opacity: 0; } to { opacity: 1; } }
    @keyframes us-pop { from { transform: translateY(14px) scale(.97); } to { transform: none; } }
    .us-popup-card { position: relative; width: min(420px, 100%); max-height: calc(100vh - 32px); overflow: auto; color: #fff; text-align: center; box-shadow: 0 30px 80px rgba(0, 0, 0, .35);
        background: linear-gradient(135deg, var(--t-primary) 0%, var(--t-primary-dark) 55%, var(--t-secondary) 100%); }
    .us-popup-card::after { content: ''; position: absolute; inset: auto -30% -40% auto; width: 80%; height: 70%; transform: rotate(-35deg); background: rgba(255, 255, 255, .06); pointer-events: none; }
    .us-popup-copy { position: relative; z-index: 1; display: grid; justify-items: center; gap: 12px; padding: 56px 28px 44px; }
    .us-popup-copy [hidden] { display: none !important; }
    .us-popup-eyebrow { font-weight: 600; font-size: 19px; line-height: 1.35; text-transform: uppercase; max-width: 18ch; }
    .us-popup-ticket { position: relative; margin: 6px 0; padding: 14px 36px 12px; background: #fff; color: var(--t-primary); border-radius: 6px; display: grid; justify-items: center; line-height: 1;
        -webkit-mask: radial-gradient(circle 8px at 0 50%, transparent 98%, #000) left / 51% 100% no-repeat, radial-gradient(circle 8px at 100% 50%, transparent 98%, #000) right / 51% 100% no-repeat;
                mask: radial-gradient(circle 8px at 0 50%, transparent 98%, #000) left / 51% 100% no-repeat, radial-gradient(circle 8px at 100% 50%, transparent 98%, #000) right / 51% 100% no-repeat; }
    .us-popup-amount { font-family: var(--font-brand); font-weight: 900; font-size: 68px; letter-spacing: -.04em; }
    .us-popup-note { font-weight: 800; text-transform: uppercase; font-size: 16px; margin-top: 4px; }
    .us-popup-label { font-weight: 600; font-size: 17px; text-transform: uppercase; }
    .us-popup-code { display: inline-flex; align-items: center; gap: 10px; background: #0B0B0B; color: #fff; padding: 9px 22px; font-weight: 800; font-size: 20px; letter-spacing: .06em; border: 0; cursor: pointer; font-family: 'Poppins', sans-serif; }
    .us-popup-code small { font-size: 10px; font-weight: 600; letter-spacing: .04em; opacity: .75; }
    .us-popup-footer { font-weight: 600; font-size: 17px; text-transform: uppercase; }
    .us-popup-legal { font-size: 10px; opacity: .85; text-transform: uppercase; }
    .us-popup-close { position: absolute; top: 10px; right: 10px; z-index: 3; width: 36px; height: 36px; border-radius: 999px; border: 0; background: rgba(0, 0, 0, .25); color: #fff; display: grid; place-items: center; cursor: pointer; }
    .us-popup-close svg { width: 18px; height: 18px; }
    .us-popup-image { display: block; position: relative; }
    .us-popup-image img { display: block; width: 100%; height: auto; }
    .us-popup-card[data-has-image] { background: #000; }
    .us-popup-card[data-has-image] .us-popup-copy, .us-popup-card:not([data-has-image]) .us-popup-image { display: none; }
    .us-popup-code:has([data-tpl-text]:empty) { display: none; }
    .us-popup-preview-pill { position: fixed; left: 16px; bottom: 16px; z-index: 80; border: 0; border-radius: 999px; padding: 10px 16px; background: var(--us-ink); color: #fff; font: 600 12px 'Poppins', sans-serif; box-shadow: 0 10px 30px rgba(0, 0, 0, .25); cursor: pointer; }
    .us-popup-preview-pill[hidden] { display: none; }

    @media (prefers-reduced-motion: reduce) {
        .us-slide, .us-slide.is-active { transition: none; }
        .us-slide.is-active .us-slide-bg img, .us-popup, .us-popup .us-popup-card { animation: none; }
    }
</style>
