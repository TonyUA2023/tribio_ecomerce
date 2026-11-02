// ═══════════════════════════════════════════════════════════
//  ESTUDIO DE FLYERS — Dashboard → Marketing → Contenido
// ═══════════════════════════════════════════════════════════
// Convierte el flyer que se ve en pantalla (HTML a tamaño real, 1080 px de ancho)
// en un PNG. Se carga solo cuando el dueño exporta (import() desde app.js), así la
// tienda pública nunca descarga html-to-image.
import { toBlob } from 'html-to-image';

// html-to-image dibuja el nodo dentro de un SVG (XML): atributos de Alpine como
// `:src` o `@click` no son nombres XML válidos y el navegador rechaza la imagen entera.
// Por eso se exporta una copia limpia, sin directivas ni <template>.
function cleanClone(node) {
    const clone = node.cloneNode(true);
    clone.querySelectorAll('template').forEach(template => template.remove());
    for (const element of [clone, ...clone.querySelectorAll('*')]) {
        for (const attribute of [...element.attributes]) {
            if (/^(x-|:|@)/.test(attribute.name)) element.removeAttribute(attribute.name);
        }
    }
    return clone;
}

export async function exportFlyer(node, width, height) {
    const clone = cleanClone(node);
    const holder = document.createElement('div');
    holder.style.cssText = `position:fixed;left:-${width + 100}px;top:0;width:${width}px;height:${height}px;overflow:hidden;pointer-events:none`;
    clone.style.transform = 'none';
    holder.appendChild(clone);
    document.body.appendChild(holder);

    try {
        // Las fuentes del flyer son del sistema: no hace falta incrustar (ni leer) las web fonts.
        return await toBlob(clone, { width, height, pixelRatio: 1, skipFonts: true, cacheBust: true });
    } finally {
        holder.remove();
    }
}
