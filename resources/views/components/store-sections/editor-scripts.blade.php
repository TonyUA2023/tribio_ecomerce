<style>
    .tribio-sortable-ghost {
        opacity: 1 !important;
        background: #3b82f6 !important;
        height: 4px !important;
        min-height: 4px !important;
        width: 100% !important;
        margin: 4px 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
        border: none !important;
        border-radius: 9999px !important;
        pointer-events: none !important;
    }
    .tribio-sortable-ghost * {
        display: none !important;
    }
</style>
<script>
    let activeSectionId = null;
    
    // Resize variables for Sections
    let isResizing = false; // Para secciones

    // Smart Guides elements
    let guideX = null;
    let guideY = null;

    function initSmartGuides() {
        if (!document.getElementById('tribio-guide-x')) {
            guideX = document.createElement('div');
            guideX.id = 'tribio-guide-x';
            guideX.className = 'absolute top-0 bottom-0 w-px bg-pink-500 z-[9999] pointer-events-none opacity-0 transition-opacity duration-150';
            document.body.appendChild(guideX);
        } else {
            guideX = document.getElementById('tribio-guide-x');
        }
        
        if (!document.getElementById('tribio-guide-y')) {
            guideY = document.createElement('div');
            guideY.id = 'tribio-guide-y';
            guideY.className = 'absolute left-0 right-0 h-px bg-pink-500 z-[9999] pointer-events-none opacity-0 transition-opacity duration-150';
            document.body.appendChild(guideY);
        } else {
            guideY = document.getElementById('tribio-guide-y');
        }
    }

    function checkAlignments(currentX, currentY, width, height, activeBlock) {
        const threshold = 8;
        let snappedX = currentX;
        let snappedY = currentY;
        let showGuideX = false;
        let showGuideY = false;
        let guideXPos = 0;
        let guideYPos = 0;

        const sectionWrapper = activeBlock.closest('.tribio-section-wrapper');
        if (!sectionWrapper) return { x: snappedX, y: snappedY };

        const sectionRect = sectionWrapper.getBoundingClientRect();
        
        const myLeft = currentX;
        const myRight = currentX + width;
        const myCenterX = currentX + width / 2;
        
        const myTop = currentY;
        const myBottom = currentY + height;
        const myCenterY = currentY + height / 2;

        const otherBlocks = Array.from(sectionWrapper.querySelectorAll('.tribio-block')).filter(b => b !== activeBlock && b.getAttribute('data-is-absolute') === 'true');

        let minDx = threshold;
        let minDy = threshold;

        otherBlocks.forEach(block => {
            const left = parseFloat(block.style.left) || 0;
            const top = parseFloat(block.style.top) || 0;
            const w = block.offsetWidth;
            const h = block.offsetHeight;
            
            const right = left + w;
            const centerX = left + w / 2;
            const bottom = top + h;
            const centerY = top + h / 2;

            const xAligns = [
                { myEdge: myLeft, target: left, alignTo: 'left', drawAt: left },
                { myEdge: myLeft, target: right, alignTo: 'left', drawAt: right },
                { myEdge: myRight, target: left, alignTo: 'right', drawAt: left },
                { myEdge: myRight, target: right, alignTo: 'right', drawAt: right },
                { myEdge: myCenterX, target: centerX, alignTo: 'center', drawAt: centerX }
            ];

            xAligns.forEach(align => {
                const dx = Math.abs(align.myEdge - align.target);
                if (dx < minDx) {
                    minDx = dx;
                    showGuideX = true;
                    guideXPos = align.drawAt;
                    if (align.alignTo === 'left') snappedX = align.target;
                    if (align.alignTo === 'right') snappedX = align.target - width;
                    if (align.alignTo === 'center') snappedX = align.target - width / 2;
                }
            });

            const yAligns = [
                { myEdge: myTop, target: top, alignTo: 'top', drawAt: top },
                { myEdge: myTop, target: bottom, alignTo: 'top', drawAt: bottom },
                { myEdge: myBottom, target: top, alignTo: 'bottom', drawAt: top },
                { myEdge: myBottom, target: bottom, alignTo: 'bottom', drawAt: bottom },
                { myEdge: myCenterY, target: centerY, alignTo: 'center', drawAt: centerY }
            ];

            yAligns.forEach(align => {
                const dy = Math.abs(align.myEdge - align.target);
                if (dy < minDy) {
                    minDy = dy;
                    showGuideY = true;
                    guideYPos = align.drawAt;
                    if (align.alignTo === 'top') snappedY = align.target;
                    if (align.alignTo === 'bottom') snappedY = align.target - height;
                    if (align.alignTo === 'center') snappedY = align.target - height / 2;
                }
            });
        });

        if (showGuideX && guideX) {
            const absoluteX = sectionRect.left + guideXPos + window.scrollX;
            guideX.style.left = absoluteX + 'px';
            guideX.style.opacity = '1';
        } else if (guideX) {
            guideX.style.opacity = '0';
        }

        if (showGuideY && guideY) {
            const absoluteY = sectionRect.top + guideYPos + window.scrollY;
            guideY.style.top = absoluteY + 'px';
            guideY.style.opacity = '1';
        } else if (guideY) {
            guideY.style.opacity = '0';
        }

        return { x: snappedX, y: snappedY };
    }

    function checkResizeAlignments(currentX, currentY, currentW, currentH, activeBlock) {
        const threshold = 8;
        let snappedW = currentW;
        let snappedH = currentH;
        let showGuideX = false;
        let showGuideY = false;
        let guideXPos = 0;
        let guideYPos = 0;

        const sectionWrapper = activeBlock.closest('.tribio-section-wrapper');
        if (!sectionWrapper) return { w: snappedW, h: snappedH };

        const sectionRect = sectionWrapper.getBoundingClientRect();
        
        const myRight = currentX + currentW;
        const myCenterX = currentX + currentW / 2;
        
        const myBottom = currentY + currentH;
        const myCenterY = currentY + currentH / 2;

        const otherBlocks = Array.from(sectionWrapper.querySelectorAll('.tribio-block')).filter(b => b !== activeBlock && b.getAttribute('data-is-absolute') === 'true');

        let minDx = threshold;
        let minDy = threshold;

        otherBlocks.forEach(block => {
            const left = parseFloat(block.style.left) || 0;
            const top = parseFloat(block.style.top) || 0;
            const w = block.offsetWidth;
            const h = block.offsetHeight;
            
            const right = left + w;
            const centerX = left + w / 2;
            const bottom = top + h;
            const centerY = top + h / 2;

            const xAligns = [
                { myEdge: myRight, target: left, alignTo: 'right', drawAt: left },
                { myEdge: myRight, target: right, alignTo: 'right', drawAt: right },
                { myEdge: myCenterX, target: centerX, alignTo: 'center', drawAt: centerX }
            ];

            xAligns.forEach(align => {
                const dx = Math.abs(align.myEdge - align.target);
                if (dx < minDx) {
                    minDx = dx;
                    showGuideX = true;
                    guideXPos = align.drawAt;
                    if (align.alignTo === 'right') snappedW = align.target - currentX;
                    if (align.alignTo === 'center') snappedW = (align.target - currentX) * 2;
                }
            });

            const yAligns = [
                { myEdge: myBottom, target: top, alignTo: 'bottom', drawAt: top },
                { myEdge: myBottom, target: bottom, alignTo: 'bottom', drawAt: bottom },
                { myEdge: myCenterY, target: centerY, alignTo: 'center', drawAt: centerY }
            ];

            yAligns.forEach(align => {
                const dy = Math.abs(align.myEdge - align.target);
                if (dy < minDy) {
                    minDy = dy;
                    showGuideY = true;
                    guideYPos = align.drawAt;
                    if (align.alignTo === 'bottom') snappedH = align.target - currentY;
                    if (align.alignTo === 'center') snappedH = (align.target - currentY) * 2;
                }
            });
        });

        if (showGuideX && guideX) {
            const absoluteX = sectionRect.left + guideXPos + window.scrollX;
            guideX.style.left = absoluteX + 'px';
            guideX.style.opacity = '1';
        } else if (guideX) {
            guideX.style.opacity = '0';
        }

        if (showGuideY && guideY) {
            const absoluteY = sectionRect.top + guideYPos + window.scrollY;
            guideY.style.top = absoluteY + 'px';
            guideY.style.opacity = '1';
        } else if (guideY) {
            guideY.style.opacity = '0';
        }

        return { w: snappedW, h: snappedH };
    }
    
    function hideGuides() {
        if (guideX) guideX.style.opacity = '0';
        if (guideY) guideY.style.opacity = '0';
    }
    
    // Función para manejar PostMessages desde el padre
    let startY = 0;
    let startPadding = 0;
    let activeSectionElement = null;

    // Resize variables for Blocks
    let isResizingBlock = false;
    let activeBlockElement = null;
    let activeBlockIndex = null;
    let startBlockW = 0;
    let startBlockH = 0;
    let startMouseX = 0;
    let startMouseY = 0;

    // Move variables for Blocks
    let isMovingBlock = false;
    let startBlockX = 0;
    let startBlockY = 0;

    function setActiveBlock(blockEl) {
        document.querySelectorAll('.tribio-block').forEach(block => {
            block.classList.remove('is-active');
        });
        
        if (blockEl) {
            blockEl.classList.add('is-active');
            
            // Ocultar el marco de la sección para que no parezcan 2 cosas seleccionadas
            document.querySelectorAll('.tribio-section-wrapper .inset-0').forEach(el => {
                el.style.opacity = '0';
            });
            dragHandle.style.display = 'none';
        } else {
            // Mostrar los marcos de las secciones de nuevo
            document.querySelectorAll('.tribio-section-wrapper .inset-0').forEach(el => {
                el.style.opacity = '';
            });
            // Restaurar el marco de la sección si no hay bloque seleccionado
            positionDragHandle();
        }
    }

    // Crear handle de drag para la sección
    const dragHandle = document.createElement('div');
    dragHandle.className = 'absolute bottom-0 left-1/2 -translate-x-1/2 w-24 h-5 bg-indigo-600 hover:bg-indigo-500 rounded-t-xl cursor-ns-resize flex justify-center items-center opacity-0 transition-opacity z-[60] shadow-lg group';
    dragHandle.innerHTML = '<div class="w-8 h-1 bg-white/80 rounded-full group-hover:bg-white transition-colors"></div>';
    dragHandle.style.display = 'none';

    dragHandle.addEventListener('mousedown', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Evitar click en la seccion que recargaria el sidebar
        isResizing = true;
        startY = e.clientY;
        
        activeSectionElement = document.querySelector('.tribio-section-wrapper[data-section-id="' + activeSectionId + '"] > .tribio-section-content > section');
        if(!activeSectionElement) return;

        const style = window.getComputedStyle(activeSectionElement);
        startPadding = parseFloat(style.paddingTop);

        document.body.style.cursor = 'ns-resize';
        dragHandle.classList.add('bg-indigo-800'); // color activo
        
        const iframes = document.querySelectorAll('iframe');
        iframes.forEach(i => i.style.pointerEvents = 'none');
    });

    // ---------------- DRAG & DROP & CLICK EVENTS PARA BLOQUES ----------------

    document.addEventListener('dragover', function(e) {
        if(activeSectionId) {
            e.preventDefault(); // Permitir drop
        }
    });
    
    document.addEventListener('dragenter', function(e) {
        if(activeSectionId) {
            e.preventDefault();
        }
    });

    document.addEventListener('drop', function(e) {
        if(!activeSectionId) return;
        const blockType = e.dataTransfer.getData('block_type');
        const blockOverridesStr = e.dataTransfer.getData('block_overrides');
        let blockOverrides = {};
        if (blockOverridesStr) {
            try { blockOverrides = JSON.parse(blockOverridesStr); } catch(e) {}
        }
        
        if(blockType) {
            e.preventDefault();
            window.parent.postMessage({
                action: 'add_block_drop',
                id: activeSectionId,
                type: blockType,
                overrides: blockOverrides
            }, '*');
        }
    });

    // Delegación de eventos para clics y mousedown en bloques
    document.addEventListener('mousedown', function(e) {
        // Clic en el resize handle del bloque
        if(e.target.closest('.tribio-block-resize-handle')) {
            e.preventDefault();
            isResizingBlock = true;
            activeBlockElement = e.target.closest('.tribio-block');
            activeBlockIndex = parseInt(activeBlockElement.getAttribute('data-block-index'));
            
            // Inicializar variables de resize
            startMouseX = e.clientX;
            startMouseY = e.clientY;
            startBlockW = activeBlockElement.offsetWidth;
            startBlockH = activeBlockElement.offsetHeight;
            
            document.body.style.cursor = 'se-resize';
            const iframes = document.querySelectorAll('iframe');
            iframes.forEach(i => i.style.pointerEvents = 'none');
            return;
        }

        // Clic en el move handle del bloque
        if(e.target.closest('.tribio-block-move-handle')) {
            const blockEl = e.target.closest('.tribio-block');
            if (blockEl && blockEl.getAttribute('data-is-absolute') === 'true') {
                e.preventDefault();
                isMovingBlock = true;
                activeBlockElement = blockEl;
                activeBlockIndex = parseInt(activeBlockElement.getAttribute('data-block-index'));
                
                // Posición inicial del ratón
                startMouseX = e.clientX;
                startMouseY = e.clientY;
                
                // Posición inicial del bloque
                startBlockX = parseFloat(activeBlockElement.style.left) || 0;
                startBlockY = parseFloat(activeBlockElement.style.top) || 0;
                
                document.body.style.cursor = 'move';
                const iframes = document.querySelectorAll('iframe');
                iframes.forEach(i => i.style.pointerEvents = 'none');
                return;
            }
            // Si NO es absoluto, dejamos que SortableJS capture el evento para reordenar
        }

        // Clic en el fondo de una sección o en un bloque
        const sectionWrapper = e.target.closest('.tribio-section-wrapper');
        const sectionId = sectionWrapper ? sectionWrapper.getAttribute('data-section-id') : null;

        if (sectionId && activeSectionId != sectionId) {
            // Si la sección no está activa, el primer clic SIEMPRE selecciona la sección completa primero.
            window.parent.postMessage({
                action: 'select_section',
                id: sectionId
            }, '*');
            return; // No seleccionamos ningún bloque todavía
        }

        // Si la sección YA está activa, permitimos seleccionar bloques
        const blockEls = [];
        let currEl = e.target;
        while (currEl && currEl !== sectionWrapper) {
            if (currEl.classList && currEl.classList.contains('tribio-block')) {
                blockEls.push(currEl); // [0] is innermost, [length-1] is outermost
            }
            currEl = currEl.parentNode;
        }

        if(blockEls.length > 0) {
            e.stopPropagation();
            
            // Lógica de "Drill-down": Si el contenedor padre ya está seleccionado, seleccionamos el hijo.
            // Por simplicidad, actualmente la jerarquía la podemos manejar directamente: seleccionamos el bloque en el que hicimos clic exacto,
            // PERO si es un clic en un contenedor vacío, blockEls[0] será el contenedor.
            // Si el bloque actual es el innermost:
            let targetBlock = blockEls[0];
            
            // Si quieres que un clic seleccione el padre primero, comparamos si el padre ya estaba activo
            // Para mantener fluidez tipo Gutenberg, al dar clic exacto a un hijo, seleccionamos el hijo.
            
            setActiveBlock(targetBlock);
            const blockPath = targetBlock.getAttribute('data-block-path');
            
            if(sectionWrapper) {
                const rect = targetBlock.getBoundingClientRect();
                window.parent.postMessage({
                    action: 'select_block',
                    id: sectionId,
                    block_path: blockPath, // Ahora usamos path
                    rect: {
                        top: rect.top,
                        left: rect.left,
                        width: rect.width,
                        height: rect.height,
                        y: rect.top + window.scrollY
                    }
                }, '*');
            }
        } else if (sectionWrapper) {
            window.parent.postMessage({
                action: 'select_section',
                id: sectionId
            }, '*');
        }
    }); // End mousedown listener

    // Clic para editar (ahora es con un clic, o si ya estaba activo)
    document.addEventListener('mousedown', function(e) {
        const editable = e.target.closest('[data-editable="true"]');
        if (editable) {
            e.stopPropagation();
            
            // Hacerlo editable
            if (editable.getAttribute('contenteditable') !== 'true') {
                editable.setAttribute('contenteditable', 'true');
                setTimeout(() => editable.focus(), 10);
            }
            
            // Seleccionar el contenido para facilidad
            document.execCommand('selectAll', false, null);
            
            // Cuando termine de editar
            const onBlur = function() {
                editable.setAttribute('contenteditable', 'false');
                window.getSelection().removeAllRanges();
                editable.removeEventListener('blur', onBlur);
                
                // Forzar guardado disparando un evento input si no se disparó
                editable.dispatchEvent(new Event('input', { bubbles: true }));
            };
            editable.addEventListener('blur', onBlur);
        }
    });

    // --------------------------------------------------------------------------

    window.addEventListener('mousemove', function(e) {
        if(isResizing && activeSectionElement) {
            const deltaY = e.clientY - startY;
            const newPadding = Math.max(0, startPadding + deltaY); 
            activeSectionElement.style.paddingTop = newPadding + 'px';
            activeSectionElement.style.paddingBottom = newPadding + 'px';
        }
        
        if(isResizingBlock && activeBlockElement) {
            const deltaX = e.clientX - startMouseX;
            const deltaY = e.clientY - startMouseY;
            let newWidth = Math.max(50, startBlockW + deltaX); // min 50px
            let newHeight = Math.max(20, startBlockH + deltaY); // min 20px
            
            const currentX = parseFloat(activeBlockElement.style.left) || 0;
            const currentY = parseFloat(activeBlockElement.style.top) || 0;
            
            if (activeBlockElement.getAttribute('data-is-absolute') === 'true') {
                const snapped = checkResizeAlignments(currentX, currentY, newWidth, newHeight, activeBlockElement);
                newWidth = snapped.w;
                newHeight = snapped.h;
            }
            
            activeBlockElement.style.width = newWidth + 'px';
            activeBlockElement.style.height = newHeight + 'px';
        }
        
        if(isMovingBlock && activeBlockElement) {
            const deltaX = e.clientX - startMouseX;
            const deltaY = e.clientY - startMouseY;
            let newX = startBlockX + deltaX;
            let newY = startBlockY + deltaY;
            
            const w = activeBlockElement.offsetWidth;
            const h = activeBlockElement.offsetHeight;
            const snapped = checkAlignments(newX, newY, w, h, activeBlockElement);
            newX = snapped.x;
            newY = snapped.y;
            
            activeBlockElement.style.left = newX + 'px';
            activeBlockElement.style.top = newY + 'px';
        }
    });

    window.addEventListener('mouseup', function(e) {
        if(isResizing) {
            isResizing = false;
            document.body.style.cursor = '';
            dragHandle.classList.remove('bg-indigo-800');
            
            const iframes = document.querySelectorAll('iframe');
            iframes.forEach(i => i.style.pointerEvents = '');

            if(activeSectionElement) {
                const finalPadding = parseFloat(activeSectionElement.style.paddingTop);
                window.parent.postMessage({
                    action: 'resize_section', 
                    id: activeSectionId, 
                    padding: finalPadding 
                }, '*');
            }
        }
        
        if(isResizingBlock) {
            isResizingBlock = false;
            document.body.style.cursor = '';
            hideGuides();
            const iframes = document.querySelectorAll('iframe');
            iframes.forEach(i => i.style.pointerEvents = '');
            
            if(activeBlockElement && activeBlockIndex !== null) {
                const finalW = parseFloat(activeBlockElement.style.width);
                const finalH = parseFloat(activeBlockElement.style.height);
                const sectionId = activeBlockElement.closest('.tribio-section-wrapper').getAttribute('data-section-id');
                
                window.parent.postMessage({
                    action: 'update_block_size',
                    id: sectionId,
                    block_index: activeBlockIndex,
                    width: finalW,
                    height: finalH
                }, '*');
            }
        }
        
        if(isMovingBlock) {
            isMovingBlock = false;
            document.body.style.cursor = '';
            hideGuides();
            const iframes = document.querySelectorAll('iframe');
            iframes.forEach(i => i.style.pointerEvents = '');
            
            if(activeBlockElement && activeBlockIndex !== null) {
                const finalX = parseFloat(activeBlockElement.style.left) || 0;
                const finalY = parseFloat(activeBlockElement.style.top) || 0;
                const sectionId = activeBlockElement.closest('.tribio-section-wrapper').getAttribute('data-section-id');
                
                window.parent.postMessage({
                    action: 'update_block_position',
                    id: sectionId,
                    block_index: activeBlockIndex,
                    x: finalX,
                    y: finalY
                }, '*');
            }
        }
    });

    window.addEventListener('message', function(event) {
        if(event.data.action === 'update_section_html') {
            const sectionEl = document.querySelector('.tribio-section-wrapper[data-section-id="' + event.data.id + '"]');
            if(sectionEl) {
                const contentWrapper = sectionEl.querySelector('.tribio-section-content');
                if (contentWrapper) {
                    contentWrapper.innerHTML = event.data.html;
                } else {
                    // Si se había destruido antes, lo recreamos pero mantenemos el wrapper principal
                    sectionEl.innerHTML = '<div class="tribio-section-content" style="pointer-events: auto;">' + event.data.html + '</div>';
                }
                initSortables();
                
                // Highlight inicial
                const urlParams = new URLSearchParams(window.location.search);
                if(urlParams.has('highlight')) {
                    const section = document.querySelector('.tribio-section-wrapper[data-section-id="' + urlParams.get('highlight') + '"]');
                    if(section) {
                        section.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
                
                initSmartGuides();
            }
        } else if(event.data.action === 'reorder_sections') {
            const container = document.querySelector('body > div'); // Adjust if there's a specific wrapper, usually it's direct body or first div
            if (event.data.sections && event.data.sections.length > 0) {
                // Determine the container from the first section
                const firstSection = document.querySelector('.tribio-section-wrapper[data-section-id="' + event.data.sections[0] + '"]');
                if (firstSection && firstSection.parentNode) {
                    const parent = firstSection.parentNode;
                    event.data.sections.forEach(id => {
                        const sectionEl = document.querySelector('.tribio-section-wrapper[data-section-id="' + id + '"]');
                        if (sectionEl) {
                            parent.appendChild(sectionEl); // Moving element to the end of parent, sorting them correctly
                        }
                    });
                }
            }
        } else if(event.data.action === 'scroll_to_section') {
            const section = document.querySelector('.tribio-section-wrapper[data-section-id="' + event.data.id + '"]');
            if(section) {
                section.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        } else if(event.data.action === 'active_section') {
            activeSectionId = event.data.id;
            
            positionDragHandle();
            setActiveBlock(null);
        } else if(event.data.action === 'highlight_block') {
            const blockEl = document.querySelector('.tribio-block[data-block-path="' + event.data.index + '"]');
            if (blockEl) {
                setActiveBlock(blockEl);
                blockEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        } else if(event.data.action === 'enable_section_edit') {
            const sectionEl = document.querySelector('.tribio-section-wrapper[data-section-id="' + event.data.id + '"]');
            if (sectionEl) {
                const overlay = sectionEl.querySelector('.tribio-section-overlay');
                if (overlay) overlay.style.display = 'none';
                const content = sectionEl.querySelector('.tribio-section-content');
                if (content) content.style.pointerEvents = 'auto';
            }
        } else if(event.data.action === 'disable_section_edit') {
            document.querySelectorAll('.tribio-section-wrapper').forEach(sectionEl => {
                const overlay = sectionEl.querySelector('.tribio-section-overlay');
                if (overlay) overlay.style.display = 'block';
                const content = sectionEl.querySelector('.tribio-section-content');
                if (content) content.style.pointerEvents = 'none';
            });
        } else if(event.data.action === 'parent_mouseup') {
            if(isResizingBlock || isMovingBlock || isResizing) {
                // Simular mouseup para destrabar el drag
                const ev = new MouseEvent('mouseup');
                document.dispatchEvent(ev);
            }
        } else if(event.data.action === 'delete_block_from_dom') {
            const blockEl = document.querySelector('.tribio-block[data-block-index="' + event.data.index + '"]');
            if (blockEl) {
                blockEl.remove();
                document.querySelectorAll('.tribio-block').forEach((block, idx) => {
                    block.setAttribute('data-block-index', idx);
                });
                setActiveBlock(null);
            }
        } else if(event.data.action === 'add_section') {
            const container = document.querySelector('body > div'); // o el wrapper principal si existe
            const template = document.createElement('div');
            template.innerHTML = `
                <div class="tribio-section-wrapper relative group" data-section-id="${event.data.section.id}" id="section-${event.data.section.id}">
                    <div class="tribio-section-content" style="pointer-events: auto;">
                        ${event.data.html}
                    </div>
                </div>
            `;
            const newSectionEl = template.firstElementChild;
            
            if (event.data.order !== undefined && event.data.order !== null) {
                // Find where to insert it
                const wrappers = Array.from(document.querySelectorAll('.tribio-section-wrapper'));
                if (event.data.order < wrappers.length) {
                    wrappers[event.data.order].parentNode.insertBefore(newSectionEl, wrappers[event.data.order]);
                } else if (wrappers.length > 0) {
                    const lastWrapper = wrappers[wrappers.length - 1];
                    lastWrapper.parentNode.insertBefore(newSectionEl, lastWrapper.nextSibling);
                } else {
                    document.body.appendChild(newSectionEl);
                }
            } else {
                // Just append to the end
                const wrappers = document.querySelectorAll('.tribio-section-wrapper');
                if (wrappers.length > 0) {
                    const lastWrapper = wrappers[wrappers.length - 1];
                    lastWrapper.parentNode.insertBefore(newSectionEl, lastWrapper.nextSibling);
                } else {
                    document.body.appendChild(newSectionEl);
                }
            }
            
            initSortables();
            initSectionInserters();
            initSmartGuides();
        }
    });

    function positionDragHandle() {
        if(!activeSectionId) {
            dragHandle.style.display = 'none';
            return;
        }
        
        const wrapper = document.querySelector('.tribio-section-wrapper[data-section-id="' + activeSectionId + '"]');
        if(wrapper) {
            const overlay = wrapper.querySelector('.inset-0');
            if(overlay) {
                overlay.classList.remove('border-transparent');
                overlay.classList.add('border-indigo-500', 'bg-indigo-500/5'); 
                
                wrapper.appendChild(dragHandle);
                dragHandle.style.display = 'flex';
                setTimeout(() => dragHandle.classList.remove('opacity-0'), 10);
            }
        }
    }

    // Prevenir navegación accidental de links en el editor
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        if (link && !link.hasAttribute('contenteditable')) {
            e.preventDefault();
        }
    });

    // --------------------------------------------------------------------------
    // INLINE TEXT EDITING (contenteditable)
    // --------------------------------------------------------------------------
    
    // (Focus hack removed to allow native text selection)

    document.addEventListener('input', function(e) {
        const editable = e.target.closest('[contenteditable]');
        if(editable) {
            const blockEl = editable.closest('.tribio-block');
            if(blockEl) {
                const blockPath = blockEl.getAttribute('data-block-path');
                const sectionWrapper = blockEl.closest('.tribio-section-wrapper');
                if(sectionWrapper) {
                    const sectionId = sectionWrapper.getAttribute('data-section-id');
                    // Usamos innerText en lugar de innerHTML para enviar saltos de línea (\n)
                    // en lugar de etiquetas HTML (<div>, <br>). Luego Blade usará nl2br(e()).
                    const content = editable.innerText;
                    
                    window.parent.postMessage({
                        action: 'update_block_content',
                        id: sectionId,
                        block_path: blockPath,
                        content: content
                    }, '*');
                }
            }
        }
    });

    // On paste, convert to plain text to avoid weird styles but keep linebreaks
    document.addEventListener('paste', function(e) {
        const editable = e.target.closest('[contenteditable]');
        if(editable) {
            e.preventDefault();
            const text = (e.originalEvent || e).clipboardData.getData('text/plain');
            document.execCommand('insertText', false, text);
        }
    });

    function initSortables() {
        document.querySelectorAll('.tribio-blocks-container').forEach(container => {
            if (typeof Sortable !== 'undefined' && !container.sortableInstance) {
                container.sortableInstance = new Sortable(container, {
                    animation: 150,
                    forceFallback: true, // Evita HTML5 D&D que inyecta draggable="true" y rompe el cursor de texto
                    fallbackOnBody: true,
                    handle: '.tribio-block-move-handle',
                    filter: '[data-is-absolute="true"], [contenteditable="true"], [contenteditable="true"] *', // No arrastrar posicionamiento libre ni textos editando
                    preventOnFilter: false, // Permitir click en el texto
                    ghostClass: 'tribio-sortable-ghost',
                    onEnd: function(evt) {
                        if (evt.oldIndex === evt.newIndex) return;
                        const sectionId = container.closest('.tribio-section-wrapper').getAttribute('data-section-id');
                        window.parent.postMessage({
                            action: 'reorder_block',
                            id: sectionId,
                            oldIndex: evt.oldIndex,
                            newIndex: evt.newIndex
                        }, '*');
                        
                        // Actualizar índices visualmente
                        container.querySelectorAll('.tribio-block').forEach((block, idx) => {
                            block.setAttribute('data-block-index', idx);
                        });
                    }
                });
            }
        });
    }

    function initSectionInserters() {
        document.querySelectorAll('.tribio-section-wrapper').forEach(wrapper => {
            // Avoid duplicate inserters
            if (wrapper.querySelector('.tribio-section-inserter')) return;
            
            const inserter = document.createElement('div');
            inserter.className = 'tribio-section-inserter absolute -bottom-4 left-1/2 -translate-x-1/2 z-[60] opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center';
            inserter.innerHTML = `
                <button class="bg-indigo-600 text-white rounded-full w-8 h-8 flex items-center justify-center shadow-lg hover:scale-110 hover:bg-indigo-700 transition-transform" title="Añadir nueva sección aquí">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                </button>
            `;
            
            inserter.querySelector('button').addEventListener('click', function(e) {
                e.stopPropagation();
                const sectionId = wrapper.getAttribute('data-section-id');
                // Calculate order based on DOM position
                const wrappers = Array.from(document.querySelectorAll('.tribio-section-wrapper'));
                const currentIndex = wrappers.indexOf(wrapper);
                
                window.parent.postMessage({
                    action: 'insert_section_after',
                    id: sectionId,
                    order: currentIndex + 1
                }, '*');
            });
            
            wrapper.appendChild(inserter);
        });
    }

    // Inicializar SortableJS para Drag & Drop nativo de bloques (Reordenamiento)
    document.addEventListener('DOMContentLoaded', () => {
        initSortables();
        initSectionInserters();
        initSmartGuides();
        
        // Highlight inicial
    });
</script>
