// Preview only: uploads still use the existing authenticated multipart forms.
export function validateImages(files, maxMb, accepted = ['image/jpeg', 'image/png', 'image/webp']) {
    for (const file of files) {
        if (!accepted.includes(file.type)) return `${file.name}: elige una imagen con uno de los formatos indicados.`;
        if (file.size > maxMb * 1024 * 1024) return `${file.name}: supera el máximo de ${maxMb} MB.`;
    }
    return '';
}

export function initImagePickers(root = document) {
    root.querySelectorAll('[data-image-picker]').forEach(picker => {
        if (picker.dataset.ready) return;
        picker.dataset.ready = 'true';
        const input = picker.querySelector('[data-image-input]');
        const original = picker.querySelector('[data-image-original]');
        const preview = picker.querySelector('[data-image-preview]');
        const status = picker.querySelector('[data-image-status]');
        const badge = picker.querySelector('[data-image-badge]');
        const error = picker.querySelector('[data-image-error]');
        const reset = picker.querySelector('[data-image-reset]');
        const save = picker.querySelector('[data-image-save]');
        const remove = picker.querySelector('[data-image-remove]');
        const initialStatus = status.textContent;
        const initialBadge = badge.textContent;
        let files = [], urls = [];
        const syncFiles = () => {
            const transfer = new DataTransfer();
            files.forEach(file => transfer.items.add(file));
            input.files = transfer.files;
        };
        const release = () => { urls.forEach(url => URL.revokeObjectURL(url)); urls = []; };
        const render = () => {
            release(); preview.replaceChildren();
            const pending = files.length > 0 || !!remove?.checked;
            picker.classList.toggle('has-pending-image', pending);
            original.hidden = pending;
            preview.hidden = !files.length;
            reset.hidden = !pending; save.hidden = !pending;
            badge.textContent = pending ? 'Sin guardar' : initialBadge;
            status.textContent = files.length
                ? `${files.length === 1 ? 'Nueva imagen seleccionada' : `${files.length} fotos seleccionadas`}. Vista previa; pulsa «${picker.dataset.saveLabel}» para confirmar.`
                : remove?.checked ? 'Se quitará la imagen al guardar. Puedes deshacer este cambio.' : initialStatus;
            files.forEach(file => {
                const figure = document.createElement('figure');
                const img = document.createElement('img');
                const url = URL.createObjectURL(file); urls.push(url);
                img.src = url; img.alt = `Vista previa: ${file.name}`;
                const caption = document.createElement('figcaption'); caption.textContent = file.name;
                img.addEventListener('error', () => {
                    error.textContent = `No se pudo mostrar ${file.name}. Prueba con otra imagen.`; error.hidden = false;
                });
                figure.append(img, caption); preview.append(figure);
            });
            preview.classList.toggle('is-multiple', input.multiple);
        };
        picker.querySelector('[data-image-choose]').addEventListener('click', () => input.click());
        input.addEventListener('change', () => {
            const selected = Array.from(input.files);
            if (!selected.length) { syncFiles(); return; }
            const message = validateImages(selected, Number(picker.dataset.maxMb), input.accept.split(','));
            error.textContent = message; error.hidden = !message;
            if (message) { syncFiles(); return; }
            files = selected;
            if (remove) remove.checked = false;
            render();
        });
        reset.addEventListener('click', () => {
            files = []; input.value = ''; if (remove) remove.checked = false;
            error.hidden = true; render(); picker.querySelector('[data-image-choose]').focus();
        });
        remove?.addEventListener('change', () => { files = []; input.value = ''; error.hidden = true; render(); });
        input.form?.addEventListener('submit', event => {
            if (event.defaultPrevented || (!files.length && !remove?.checked)) return;
            status.textContent = 'Enviando cambios… Espera la confirmación antes de salir.';
            picker.setAttribute('aria-busy', 'true');
        });
        input.form?.addEventListener('reset', () => {
            files = []; if (remove) remove.checked = false; error.hidden = true; render();
        });
        window.addEventListener('pageshow', () => {
            picker.removeAttribute('aria-busy'); render();
        });
    });
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => initImagePickers());
    else initImagePickers();
}
