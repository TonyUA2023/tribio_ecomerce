{{-- Keeps the sidebar where the user left it between pages (every link is a full page load): restores the saved
     scroll before first paint and, if the active item would be out of view, brings it to the middle. Place right
     after the element carrying data-keep-scroll="<name>". --}}
<script>
(function () {
    var nav = document.querySelector('[data-keep-scroll]');
    if (!nav) return;
    var key = 'tribio:nav-scroll:' + (nav.getAttribute('data-keep-scroll') || ''), saved = null, ticking = false;
    try { saved = sessionStorage.getItem(key); } catch (e) {}
    if (saved !== null && !isNaN(+saved)) nav.scrollTop = +saved;
    var active = nav.querySelector('.active');
    if (active) {
        var a = active.getBoundingClientRect(), n = nav.getBoundingClientRect();
        if (a.top < n.top + 8 || a.bottom > n.bottom - 8) nav.scrollTop += a.top - n.top - (n.height - a.height) / 2;
    }
    function save() { try { sessionStorage.setItem(key, String(nav.scrollTop)); } catch (e) {} ticking = false; }
    nav.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(save); } }, { passive: true });
    window.addEventListener('pagehide', save);
    nav.addEventListener('click', save);
})();
</script>
