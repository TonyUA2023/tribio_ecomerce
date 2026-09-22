{{-- Confirmation before switching designs. Lives inside a templateGallery() Alpine scope. --}}
<div class="tpl-dialog" x-show="applying" x-cloak x-transition.opacity @keydown.escape.window="applying = null" role="dialog" aria-modal="true" aria-labelledby="tpl-dialog-title">
    <div class="tpl-dialog-scrim" @click="applying = null"></div>
    <form method="POST" :action="applyAction" class="tpl-dialog-panel" @submit="submitting = true">
        @csrf
        <span class="tpl-dialog-icon"><x-dashboard-icon name="sparkles"/></span>
        <h2 id="tpl-dialog-title">¿Usar <span x-text="applying?.name"></span>?</h2>
        <p>Tu tienda cambiará de diseño al instante. Tus productos, pedidos, pagos y datos no se tocan, y puedes volver a tu diseño anterior cuando quieras.</p>
        <div class="tpl-dialog-actions">
            <button type="button" class="btn-secondary" @click="applying = null">Cancelar</button>
            <button type="submit" class="btn-primary" :disabled="submitting" x-ref="applyConfirm">
                <span x-show="!submitting">Sí, usar esta plantilla</span>
                <span x-show="submitting" x-cloak>Aplicando…</span>
            </button>
        </div>
    </form>
</div>
