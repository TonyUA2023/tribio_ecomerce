@if(!empty($isEditor) && !defined('TRIBIO_BLOCKS_STYLE_LOADED'))
    @php
        define('TRIBIO_BLOCKS_STYLE_LOADED', true);
    @endphp
    <style>
        .tribio-block-border {
            position: absolute;
            inset: 0;
            border: 2px dashed transparent;
            z-index: 60;
            pointer-events: none;
            transition: border-color 0.15s ease-in-out;
        }
        .tribio-block:hover > .tribio-block-border {
            border-color: rgba(59, 130, 246, 0.45) !important;
        }
        .tribio-block.is-active > .tribio-block-border {
            border-color: #2563eb !important;
            border-style: solid !important;
        }
        .tribio-block-label {
            position: absolute;
            top: -18px;
            left: -2px;
            background-color: #2563eb;
            color: #ffffff;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 2px 6px;
            border-radius: 3px 3px 0 0;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.15s ease-in-out;
            line-height: 1.2;
            box-shadow: 0 -2px 4px rgba(0,0,0,0.05);
        }
        .tribio-block:hover > .tribio-block-border > .tribio-block-label,
        .tribio-block.is-active > .tribio-block-border > .tribio-block-label {
            opacity: 1 !important;
        }
    </style>
@endif
@if(!empty($data['blocks']) && count($data['blocks']) > 0)
    @php
        $parentLayout = $parentLayout ?? 'flex-col';
        $parentAlign = $parentAlign ?? 'flex-start';
        $parentGap = isset($parentGap) && $parentGap !== '' ? $parentGap : ($parentLayout === 'flex-col' ? 8 : 16);
        
        $parentVerticalAlign = $parentVerticalAlign ?? 'center';
        
        $containerClasses = 'relative min-h-[60px] w-full h-full tribio-blocks-container';
        $containerStyles = "gap: {$parentGap}px;";
        
        if ($parentLayout === 'flex-row') {
            $containerClasses .= ' flex flex-row flex-wrap';
            
            // Vertical alignment (align-items)
            if ($parentVerticalAlign === 'stretch') $containerClasses .= ' items-stretch';
            elseif ($parentVerticalAlign === 'flex-start' || $parentVerticalAlign === 'top') $containerClasses .= ' items-start';
            elseif ($parentVerticalAlign === 'flex-end' || $parentVerticalAlign === 'bottom') $containerClasses .= ' items-end';
            else $containerClasses .= ' items-center';
            
            // Horizontal alignment (justify-content)
            if ($parentAlign === 'center') $containerClasses .= ' justify-center';
            elseif ($parentAlign === 'flex-end' || $parentAlign === 'right') $containerClasses .= ' justify-end';
            elseif ($parentAlign === 'space-between' || $parentAlign === 'between') $containerClasses .= ' justify-between';
            elseif ($parentAlign === 'space-around' || $parentAlign === 'around') $containerClasses .= ' justify-around';
            elseif ($parentAlign === 'space-evenly' || $parentAlign === 'evenly') $containerClasses .= ' justify-evenly';
            else $containerClasses .= ' justify-start';
        } elseif ($parentLayout === 'grid') {
            $containerClasses .= ' grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3';
        } elseif ($parentLayout === 'carousel') {
            $containerClasses .= ' flex flex-nowrap items-center w-max min-w-full';
            if ($parentAlign === 'center') $containerClasses .= ' justify-center';
            elseif ($parentAlign === 'flex-end' || $parentAlign === 'right') $containerClasses .= ' justify-end';
            elseif ($parentAlign === 'space-between' || $parentAlign === 'between') $containerClasses .= ' justify-between';
            elseif ($parentAlign === 'space-around' || $parentAlign === 'around') $containerClasses .= ' justify-around';
            elseif ($parentAlign === 'space-evenly' || $parentAlign === 'evenly') $containerClasses .= ' justify-evenly';
            else $containerClasses .= ' justify-start';
        } else {
            $containerClasses .= ' flex flex-col';
            
            // Horizontal alignment for flex-col (align-items)
            if ($parentAlign === 'flex-start' || $parentAlign === 'left') $containerClasses .= ' items-start';
            elseif ($parentAlign === 'flex-end' || $parentAlign === 'right') $containerClasses .= ' items-end';
            elseif ($parentAlign === 'stretch') $containerClasses .= ' items-stretch';
            else $containerClasses .= ' items-center';
            
            // Vertical alignment for flex-col (justify-content)
            if ($parentVerticalAlign === 'flex-start' || $parentVerticalAlign === 'top') $containerClasses .= ' justify-start';
            elseif ($parentVerticalAlign === 'flex-end' || $parentVerticalAlign === 'bottom') $containerClasses .= ' justify-end';
            elseif ($parentVerticalAlign === 'space-between' || $parentVerticalAlign === 'between') $containerClasses .= ' justify-between';
            elseif ($parentVerticalAlign === 'space-around' || $parentVerticalAlign === 'around') $containerClasses .= ' justify-around';
            elseif ($parentVerticalAlign === 'space-evenly' || $parentVerticalAlign === 'evenly') $containerClasses .= ' justify-evenly';
            else $containerClasses .= ' justify-center';
        }
    @endphp
    <div class="{{ $containerClasses }}" style="{{ $containerStyles }}">
        @php
            $pathPrefix = $pathPrefix ?? 'blocks.';
        @endphp
        @foreach($data['blocks'] as $block)
            @php
                $isEditor = isset($isEditor) ? $isEditor : (request()->query('editor') || request()->query('preview'));
                $currentPath = $pathPrefix . $loop->index;
                $parentLayout = $parentLayout ?? 'flex-col';
                
                // Base dimensions
                $widthStyle = !empty($block['width']) ? (is_numeric($block['width']) ? "width: {$block['width']}px;" : "width: {$block['width']};") : '';
                $heightStyle = !empty($block['height']) ? (is_numeric($block['height']) ? "height: {$block['height']}px;" : "height: {$block['height']};") : '';
                $minHeightStyle = !empty($block['min_height']) ? (is_numeric($block['min_height']) ? "min-height: {$block['min_height']}px;" : "min-height: {$block['min_height']};") : '';
                
                // Advanced Positioning (Layers)
                $isAbsolute = !empty($block['is_absolute']);
                $xPos = isset($block['x_pos']) ? "left: {$block['x_pos']}px;" : '';
                $yPos = isset($block['y_pos']) ? "top: {$block['y_pos']}px;" : '';
                $zIndex = isset($block['z_index']) ? "z-index: {$block['z_index']};" : '';
                
                $positionStyle = $isAbsolute ? "position: absolute; {$xPos} {$yPos} {$zIndex}" : "position: relative; {$zIndex}";
                
                // Margins
                $marginTop = isset($block['margin_top']) && $block['margin_top'] !== '' ? "margin-top: {$block['margin_top']}px !important;" : '';
                $marginBottom = isset($block['margin_bottom']) && $block['margin_bottom'] !== '' ? "margin-bottom: {$block['margin_bottom']}px !important;" : '';
                $marginStyles = "{$marginTop} {$marginBottom}";
                
                // Paddings
                $paddingX = isset($block['padding_x']) ? "padding-left: {$block['padding_x']}px !important; padding-right: {$block['padding_x']}px !important;" : '';
                $paddingY = isset($block['padding_y']) ? "padding-top: {$block['padding_y']}px !important; padding-bottom: {$block['padding_y']}px !important;" : '';
                $paddingStyles = "{$paddingX} {$paddingY}";
                
                // Flex sizing
                $flexStyle = !empty($block['flex']) ? "flex: {$block['flex']};" : '';
                
                // Flex item properties
                $flexGrow = isset($block['flex_grow']) && $block['flex_grow'] !== '' ? "flex-grow: {$block['flex_grow']};" : '';
                $flexShrink = isset($block['flex_shrink']) && $block['flex_shrink'] !== '' ? "flex-shrink: {$block['flex_shrink']};" : '';
                $flexBasis = isset($block['flex_basis']) && $block['flex_basis'] !== '' ? "flex-basis: {$block['flex_basis']};" : '';
                $alignSelf = !empty($block['align_self']) ? "align-self: {$block['align_self']};" : '';
                $order = isset($block['order']) && $block['order'] !== '' ? "order: {$block['order']};" : '';
                
                // Grid item properties
                $gridColumn = !empty($block['grid_column']) ? "grid-column: {$block['grid_column']};" : '';
                $gridRow = !empty($block['grid_row']) ? "grid-row: {$block['grid_row']};" : '';
                $justifySelf = !empty($block['justify_self']) ? "justify-self: {$block['justify_self']};" : '';
                $placeSelf = !empty($block['place_self']) ? "place-self: {$block['place_self']};" : '';
                
                $layoutStyles = "{$flexGrow} {$flexShrink} {$flexBasis} {$alignSelf} {$order} {$gridColumn} {$gridRow} {$justifySelf} {$placeSelf}";
                
                $baseStyles = "{$widthStyle} {$heightStyle} {$minHeightStyle} {$positionStyle} {$flexStyle} {$marginStyles} {$layoutStyles}";
                
                // Borders
                $borderWidth = isset($block['border_width']) ? "border-width: {$block['border_width']}px;" : '';
                $borderColor = !empty($block['border_color']) ? "border-color: {$block['border_color']}; border-style: solid;" : '';
                $borderRadius = isset($block['border_radius']) ? "border-radius: {$block['border_radius']}px;" : '';
                $borderStyles = "{$borderWidth} {$borderColor} {$borderRadius}";
                
                // Background Image
                $bgImage = !empty($block['bg_image']) ? "background-image: url('{$block['bg_image']}'); background-size: cover; background-position: center;" : '';
                
                // Default width classes
                $defaultWidthClass = 'w-full';
                $flexClasses = '';
                if (empty($block['width']) && empty($block['full_width']) && !$isAbsolute) {
                    if (in_array($block['type'], ['paragraph', 'title', 'text', 'toc'])) {
                        $defaultWidthClass = 'w-full max-w-[1200px] mx-auto px-4'; // Full width for text blocks so text-align works
                    } elseif (in_array($block['type'], ['button', 'badge', 'image', 'store_logo', 'store_navbar', 'store_search', 'store_cart'])) {
                        $defaultWidthClass = 'w-fit max-w-[1200px] px-4'; // Shrink to fit content
                    } elseif ($block['type'] === 'card') {
                        if ($parentLayout === 'flex-row') {
                            $defaultWidthClass = ''; // Let flex take over
                            $flexClasses = 'flex-1 min-w-[250px]';
                        } else {
                            $defaultWidthClass = 'w-full max-w-[1200px] mx-auto px-4'; // Full width
                        }
                    } elseif (in_array($block['type'], ['container', 'carousel'])) {
                        if (!empty($block['full_width'])) {
                            $defaultWidthClass = 'w-full'; // Entire screen width
                        } else {
                            $defaultWidthClass = 'w-full max-w-[1200px] mx-auto px-4'; // Responsive container
                        }
                    }
                }
            @endphp
            
            <div class="tribio-block group/block {{ $defaultWidthClass }} {{ !empty($block['full_width']) ? 'w-full' : '' }} {{ $flexClasses }}" 
                 data-block-path="{{ $currentPath }}" 
                 data-block-index="{{ $loop->index }}"
                 data-is-absolute="{{ $isAbsolute ? 'true' : 'false' }}"
                 style="{{ $baseStyles }}">
                
                @if($isEditor)
                    <div class="tribio-block-border">
                        <!-- Label indicating block type (only visible on hover or active) -->
                        <div class="tribio-block-label">
                            {{ $block['type'] === 'container' ? 'Container' : ($block['type'] === 'store_logo' ? 'Logo' : ($block['type'] === 'store_navbar' ? 'Navbar' : ($block['type'] === 'paragraph' ? 'Párrafo' : ($block['type'] === 'title' ? 'Título' : ($block['type'] === 'button' ? 'Botón' : $block['type']))))) }}
                        </div>
                    </div>
                    
                    <!-- Botón Insertar Antes (Arriba) -->
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 w-6 h-6 bg-indigo-600 text-white flex items-center justify-center cursor-pointer opacity-0 group-[.is-active]/block:opacity-100 group-hover/block:opacity-100 z-[80] hover:bg-indigo-700 hover:scale-110 transition-all shadow-md rounded-full group/add" title="Añadir un bloque arriba" onclick="const sectionId = this.closest('.tribio-section-wrapper').getAttribute('data-section-id'); const rect = this.getBoundingClientRect(); window.parent.postMessage({action: 'request_add_block', insert_position: 'top', section_id: parseInt(sectionId), path: '{{ $currentPath }}', rect: {top: rect.top, left: rect.left, bottom: rect.bottom, right: rect.right, width: rect.width, height: rect.height} }, '*'); event.stopPropagation();">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v12m-6-6h12"></path></svg>
                        <div class="absolute bottom-full mb-1 left-1/2 -translate-x-1/2 bg-black text-white text-[10px] px-2 py-1 rounded whitespace-nowrap opacity-0 group-hover/add:opacity-100 pointer-events-none font-bold">Añadir arriba</div>
                    </div>
                    
                    <!-- Botón Insertar Después (Abajo) -->
                    <div class="absolute -bottom-3 left-1/2 -translate-x-1/2 w-6 h-6 bg-indigo-600 text-white flex items-center justify-center cursor-pointer opacity-0 group-[.is-active]/block:opacity-100 group-hover/block:opacity-100 z-[80] hover:bg-indigo-700 hover:scale-110 transition-all shadow-md rounded-full group/add" title="Añadir un bloque abajo" onclick="const sectionId = this.closest('.tribio-section-wrapper').getAttribute('data-section-id'); const rect = this.getBoundingClientRect(); window.parent.postMessage({action: 'request_add_block', insert_position: 'bottom', section_id: parseInt(sectionId), path: '{{ $currentPath }}', rect: {top: rect.top, left: rect.left, bottom: rect.bottom, right: rect.right, width: rect.width, height: rect.height} }, '*'); event.stopPropagation();">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v12m-6-6h12"></path></svg>
                        <div class="absolute top-full mt-1 left-1/2 -translate-x-1/2 bg-black text-white text-[10px] px-2 py-1 rounded whitespace-nowrap opacity-0 group-hover/add:opacity-100 pointer-events-none font-bold">Añadir abajo</div>
                    </div>

                    <!-- Botón Insertar Antes (Izquierda) -->
                    <div class="absolute top-1/2 -left-3 -translate-y-1/2 w-6 h-6 bg-indigo-600 text-white flex items-center justify-center cursor-pointer opacity-0 group-[.is-active]/block:opacity-100 group-hover/block:opacity-100 z-[80] hover:bg-indigo-700 hover:scale-110 transition-all shadow-md rounded-full group/add" title="Añadir un bloque a la izquierda" onclick="const sectionId = this.closest('.tribio-section-wrapper').getAttribute('data-section-id'); const rect = this.getBoundingClientRect(); window.parent.postMessage({action: 'request_add_block', insert_position: 'left', section_id: parseInt(sectionId), path: '{{ $currentPath }}', rect: {top: rect.top, left: rect.left, bottom: rect.bottom, right: rect.right, width: rect.width, height: rect.height} }, '*'); event.stopPropagation();">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v12m-6-6h12"></path></svg>
                        <div class="absolute right-full mr-1 top-1/2 -translate-y-1/2 bg-black text-white text-[10px] px-2 py-1 rounded whitespace-nowrap opacity-0 group-hover/add:opacity-100 pointer-events-none font-bold">Añadir izquierda</div>
                    </div>

                    <!-- Botón Insertar Después (Derecha) -->
                    <div class="absolute top-1/2 -right-3 -translate-y-1/2 w-6 h-6 bg-indigo-600 text-white flex items-center justify-center cursor-pointer opacity-0 group-[.is-active]/block:opacity-100 group-hover/block:opacity-100 z-[80] hover:bg-indigo-700 hover:scale-110 transition-all shadow-md rounded-full group/add" title="Añadir un bloque a la derecha" onclick="const sectionId = this.closest('.tribio-section-wrapper').getAttribute('data-section-id'); const rect = this.getBoundingClientRect(); window.parent.postMessage({action: 'request_add_block', insert_position: 'right', section_id: parseInt(sectionId), path: '{{ $currentPath }}', rect: {top: rect.top, left: rect.left, bottom: rect.bottom, right: rect.right, width: rect.width, height: rect.height} }, '*'); event.stopPropagation();">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v12m-6-6h12"></path></svg>
                        <div class="absolute left-full ml-1 top-1/2 -translate-y-1/2 bg-black text-white text-[10px] px-2 py-1 rounded whitespace-nowrap opacity-0 group-hover/add:opacity-100 pointer-events-none font-bold">Añadir derecha</div>
                    </div>
                @endif

                @if($block['type'] === 'paragraph')
                    @php 
                        $textAlign = isset($block['text_align']) ? "text-align: {$block['text_align']};" : '';
                        $textBold = !empty($block['is_bold']) ? "font-weight: bold;" : '';
                        $textItalic = !empty($block['is_italic']) ? "font-style: italic;" : '';
                    @endphp
                    <p {!! $isEditor ? 'data-editable="true" contenteditable="false" spellcheck="false"' : '' !!} style="color: {{ $block['color'] ?? '#374151' }}; {{ $heightStyle }} {{ $paddingStyles }} {{ $textAlign }} {{ $textBold }} {{ $textItalic }} {{ $isEditor ? 'pointer-events-auto relative z-50' : '' }}" class="text-base leading-relaxed m-0 w-full h-full outline-none focus:ring-0 {{ $isEditor ? 'cursor-pointer group-[.is-active]/block:cursor-text' : '' }}">
                        {!! nl2br(e($block['content'] ?? '')) !!}
                    </p>
                
                @elseif($block['type'] === 'title')
                    @php 
                        $tag = $block['tag'] ?? 'h2'; 
                        $textAlign = isset($block['text_align']) ? "text-align: {$block['text_align']};" : '';
                        $textBold = !empty($block['is_bold']) ? "font-weight: 800;" : '';
                        $textItalic = !empty($block['is_italic']) ? "font-style: italic;" : '';
                        $baseClass = '';
                        if($tag === 'h1') $baseClass = 'text-4xl sm:text-5xl md:text-6xl font-extrabold tracking-tight';
                        elseif($tag === 'h2') $baseClass = 'text-3xl font-bold';
                        elseif($tag === 'h3') $baseClass = 'text-xl font-bold';
                        elseif($tag === 'h4') $baseClass = 'text-lg font-bold';
                        elseif($tag === 'h5') $baseClass = 'text-base font-bold';
                        elseif($tag === 'h6') $baseClass = 'text-sm font-bold uppercase tracking-wide';
                        else $baseClass = 'text-lg font-semibold';
                    @endphp
                    <{{ $tag }} {!! $isEditor ? 'data-editable="true" contenteditable="false" spellcheck="false"' : '' !!} style="color: {{ $block['color'] ?? '#111827' }}; {{ $heightStyle }} {{ $paddingStyles }} {{ $textAlign }} {{ $textBold }} {{ $textItalic }} {{ $isEditor ? 'pointer-events-auto relative z-50' : '' }}" class="m-0 w-full h-full {{ $baseClass }} outline-none focus:ring-0 {{ $isEditor ? 'cursor-pointer group-[.is-active]/block:cursor-text' : '' }}">
                        {!! nl2br(e($block['content'] ?? '')) !!}
                    </{{ $tag }}>
                
                @elseif($block['type'] === 'image' || $block['type'] === 'store_logo')
                    @php
                        // Dimensión y Escalado
                        $imgWidth = isset($block['width']) && $block['width'] !== '' ? "width: {$block['width']};" : '';
                        $imgHeight = isset($block['height']) && $block['height'] !== '' ? "height: {$block['height']};" : '';
                        $imgMaxWidth = isset($block['max_width']) && $block['max_width'] !== '' ? "max-width: {$block['max_width']};" : 'max-width: 100%;';
                        $imgAspect = isset($block['aspect_ratio']) && $block['aspect_ratio'] !== '' ? "aspect-ratio: {$block['aspect_ratio']};" : '';
                        
                        // Ajuste y Posición
                        $imgFit = isset($block['object_fit']) && $block['object_fit'] !== '' ? "object-fit: {$block['object_fit']};" : '';
                        $imgPosition = isset($block['object_position']) && $block['object_position'] !== '' ? "object-position: {$block['object_position']};" : '';
                        
                        // Diseño y Caja
                        $imgDisplay = isset($block['display']) && $block['display'] !== '' ? "display: {$block['display']};" : 'display: inline-block;';
                        $imgShadow = isset($block['box_shadow']) && $block['box_shadow'] !== '' ? "box-shadow: {$block['box_shadow']};" : '';
                        
                        // Flujo y Efectos
                        $imgFloat = isset($block['float']) && $block['float'] !== '' && $block['float'] !== 'none' ? "float: {$block['float']};" : '';
                        $imgOpacity = isset($block['opacity']) && $block['opacity'] !== '' ? "opacity: {$block['opacity']};" : '';
                        $imgFilter = isset($block['filter']) && $block['filter'] !== '' ? "filter: {$block['filter']};" : '';
                        
                        $imgStyles = trim("{$imgWidth} {$imgHeight} {$imgMaxWidth} {$imgAspect} {$imgFit} {$imgPosition} {$imgDisplay} {$imgShadow} {$imgFloat} {$imgOpacity} {$imgFilter} {$borderStyles}");
                    @endphp
                    <img src="{{ $block['type'] === 'store_logo' ? ($store->logo_url ?? '') : ($block['content'] ?? 'https://placehold.co/50x50') }}" 
                         {!! $isEditor ? 'data-editable="true"' : '' !!}
                         style="{{ $imgStyles }}"
                         class="{{ $isEditor ? 'pointer-events-auto relative z-50 cursor-pointer group-[.is-active]/block:ring-2 group-[.is-active]/block:ring-blue-500' : '' }}" 
                         alt="{{ $block['type'] }}">

                @elseif($block['type'] === 'toc')
                    <div {!! $isEditor ? 'data-editable="true" contenteditable="false" spellcheck="false"' : '' !!} style="color: {{ $block['color'] ?? '#111827' }}; background-color: {{ $block['background_color'] ?? '#f9fafb' }}; border: 1px solid #e5e7eb; border-radius: 0.5rem; {{ $paddingStyles }} {{ $isEditor ? 'pointer-events-auto relative z-50' : '' }}" class="w-full p-6 outline-none focus:ring-0 {{ $isEditor ? 'cursor-pointer group-[.is-active]/block:cursor-text' : '' }}">
                        <div class="font-bold mb-4 text-lg border-b pb-2 border-gray-200">
                            {!! nl2br(e($block['content'] ?? 'Tabla de Contenidos')) !!}
                        </div>
                        <ul class="list-disc pl-5 space-y-2 opacity-80">
                            <li><a href="#" class="hover:underline">Sección 1</a></li>
                            <li><a href="#" class="hover:underline">Sección 2</a></li>
                            <li><a href="#" class="hover:underline">Sección 3</a></li>
                        </ul>
                    </div>
                
                @elseif($block['type'] === 'link')
                    <a href="{{ $isEditor ? 'javascript:void(0)' : ($block['url'] ?? '#') }}" {!! $isEditor ? 'data-editable="true" contenteditable="false" spellcheck="false"' : '' !!} style="color: {{ $block['color'] ?? '#3b82f6' }}; {{ $heightStyle }} {{ $paddingStyles }} {{ $isEditor ? 'pointer-events-auto relative z-50' : '' }}" class="inline-flex font-semibold hover:opacity-80 transition-opacity hover:underline items-center justify-center w-full h-full outline-none focus:ring-0 {{ $isEditor ? 'cursor-pointer group-[.is-active]/block:cursor-text' : '' }}">
                        {!! nl2br(e($block['content'] ?? '')) !!}
                    </a>
                
                @elseif($block['type'] === 'button')
                    @php
                        // Button typography
                        $btnFontFamily = !empty($block['font_family']) ? "font-family: {$block['font_family']};" : '';
                        $btnFontSize = isset($block['font_size']) && $block['font_size'] !== '' ? (is_numeric($block['font_size']) ? "font-size: {$block['font_size']}px;" : "font-size: {$block['font_size']};") : '';
                        $btnFontWeight = !empty($block['font_weight']) ? "font-weight: {$block['font_weight']};" : '';
                        $btnFontStyle = !empty($block['font_style']) ? "font-style: {$block['font_style']};" : '';
                        $btnTextTransform = !empty($block['text_transform']) ? "text-transform: {$block['text_transform']};" : '';
                        $btnLetterSpacing = isset($block['letter_spacing']) && $block['letter_spacing'] !== '' ? (is_numeric($block['letter_spacing']) ? "letter-spacing: {$block['letter_spacing']}px;" : "letter-spacing: {$block['letter_spacing']};") : '';
                        $btnTextAlign = !empty($block['text_align']) ? "text-align: {$block['text_align']};" : '';
                        
                        // Button advanced styling
                        $btnShadow = !empty($block['box_shadow']) ? "box-shadow: {$block['box_shadow']};" : '';
                        $btnOpacity = isset($block['opacity']) && $block['opacity'] !== '' ? "opacity: {$block['opacity']};" : '';
                        $btnTransition = !empty($block['transition']) ? "transition: {$block['transition']};" : '';
                        $btnTransform = !empty($block['transform']) ? "transform: {$block['transform']};" : '';
                        $btnCursor = !empty($block['cursor']) ? "cursor: {$block['cursor']};" : '';
                        $btnOutline = !empty($block['outline']) ? "outline: {$block['outline']};" : '';
                        
                        $buttonStyles = "{$btnFontFamily} {$btnFontSize} {$btnFontWeight} {$btnFontStyle} {$btnTextTransform} {$btnLetterSpacing} {$btnTextAlign} {$btnShadow} {$btnOpacity} {$btnTransition} {$btnTransform} {$btnCursor} {$btnOutline}";
                        $hasBorderRadius = isset($block['border_radius']) && $block['border_radius'] !== '';
                    @endphp
                    <a href="{{ $isEditor ? 'javascript:void(0)' : ($block['url'] ?? '#') }}" {!! $isEditor ? 'data-editable="true" contenteditable="false" spellcheck="false"' : '' !!} style="background-color: {{ $block['background_color'] ?? '#111827' }}; color: {{ $block['text_color'] ?? $block['color'] ?? '#ffffff' }}; {{ $heightStyle }} {{ $borderStyles }} {{ $paddingStyles }} {{ $buttonStyles }} {{ $isEditor ? 'pointer-events-auto relative z-50' : '' }}" class="inline-flex items-center justify-center px-6 py-3 font-bold shadow-sm hover:shadow-md transition-all transform hover:-translate-y-0.5 text-center w-full h-full outline-none focus:ring-0 {{ $isEditor ? 'cursor-pointer group-[.is-active]/block:cursor-text' : '' }} {{ $hasBorderRadius ? '' : 'rounded-full' }}">
                        {!! nl2br(e($block['content'] ?? '')) !!}
                    </a>
                
                @elseif($block['type'] === 'badge')
                    <span {!! $isEditor ? 'data-editable="true" contenteditable="false" spellcheck="false"' : '' !!} style="background-color: {{ $block['background_color'] ?? '#fee2e2' }}; color: {{ $block['text_color'] ?? '#ef4444' }}; {{ $heightStyle }} {{ $paddingStyles }} {{ $isEditor ? 'pointer-events-auto relative z-50' : '' }}" class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-black uppercase tracking-wider w-full h-full outline-none focus:ring-0 {{ $isEditor ? 'cursor-pointer group-[.is-active]/block:cursor-text' : '' }}">
                        {!! nl2br(e($block['content'] ?? '')) !!}
                    </span>
                
                @elseif($block['type'] === 'card')
                    @php
                        $layoutClass = 'flex flex-col'; // default layout for card
                        if (($block['layout'] ?? '') === 'flex-row') {
                            $layoutClass = 'flex flex-row flex-wrap items-center gap-4';
                            $align = $block['alignment'] ?? 'flex-start';
                            if ($align === 'center') $layoutClass .= ' justify-center';
                            elseif ($align === 'flex-end' || $align === 'right') $layoutClass .= ' justify-end';
                            elseif ($align === 'space-between' || $align === 'between') $layoutClass .= ' justify-between';
                            elseif ($align === 'space-around' || $align === 'around') $layoutClass .= ' justify-around';
                            elseif ($align === 'space-evenly' || $align === 'evenly') $layoutClass .= ' justify-evenly';
                            else $layoutClass .= ' justify-start';
                        }
                        if (($block['layout'] ?? '') === 'grid') $layoutClass = 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4';
                    @endphp
                    <div style="background-color: {{ $block['background_color'] ?? '#f3f4f6' }}; {{ $bgImage ?? '' }} {{ $borderStyles ?? '' }} {{ $paddingStyles ?? '' }}" class="{{ $layoutClass }} p-6 shadow-sm w-full h-full relative overflow-hidden group/card {{ $isEditor ? 'min-h-[100px]' : '' }}">
                        @if(!empty($block['bg_image']))
                            <div class="absolute inset-0 bg-black/20 z-0 pointer-events-none"></div> <!-- overlay to make text readable -->
                        @endif
                        
                        <div class="relative z-10 w-full h-full">
                            @if(!empty($block['blocks']) && count($block['blocks']) > 0)
                                <!-- Recursive call -->
                                @include('components.store-sections.blocks', ['data' => ['blocks' => $block['blocks']], 'pathPrefix' => $currentPath . '.blocks.', 'parentLayout' => $block['layout'] ?? 'flex-col', 'parentAlign' => $block['alignment'] ?? 'flex-start', 'parentVerticalAlign' => $block['vertical_alignment'] ?? 'center', 'parentGap' => $block['gap'] ?? null])
                            @else
                                @if(!empty($block['content']))
                                    <p style="color: {{ $block['text_color'] ?? '#111827' }};" class="font-medium text-base mb-0">
                                        {{ $block['content'] ?? '' }}
                                    </p>
                                    @if(!empty($block['url']))
                                        <a href="{{ $block['url'] }}" class="inline-block mt-3 text-sm font-bold opacity-80 hover:opacity-100 transition-opacity" style="color: {{ $block['text_color'] ?? '#111827' }};">
                                            Saber más →
                                        </a>
                                    @endif
                                @endif
                                
                                @if($isEditor)
                                    <div class="w-full mt-4 min-h-[40px] flex items-center justify-center cursor-pointer border border-dashed border-gray-400 rounded hover:bg-gray-50/50 transition-colors" onclick="const sectionId = this.closest('.tribio-section-wrapper').getAttribute('data-section-id'); const rect = this.getBoundingClientRect(); window.parent.postMessage({action: 'request_add_block', section_id: parseInt(sectionId), path: '{{ $currentPath }}', rect: {top: rect.top, left: rect.left, bottom: rect.bottom, right: rect.right, width: rect.width, height: rect.height} }, '*'); event.stopPropagation();">
                                        <div class="w-6 h-6 bg-blue-600 text-white flex items-center justify-center hover:bg-blue-700 transition-colors rounded-sm shadow-sm">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                
                @elseif($block['type'] === 'container')
                    @php
                        $layoutClass = 'flex flex-col'; // default
                        if (($block['layout'] ?? '') === 'flex-row') {
                            $layoutClass = 'flex flex-row flex-wrap items-center gap-4';
                            $align = $block['alignment'] ?? 'flex-start';
                            if ($align === 'center') $layoutClass .= ' justify-center';
                            elseif ($align === 'flex-end' || $align === 'right') $layoutClass .= ' justify-end';
                            elseif ($align === 'space-between' || $align === 'between') $layoutClass .= ' justify-between';
                            elseif ($align === 'space-around' || $align === 'around') $layoutClass .= ' justify-around';
                            elseif ($align === 'space-evenly' || $align === 'evenly') $layoutClass .= ' justify-evenly';
                            else $layoutClass .= ' justify-start';
                        }
                        if (($block['layout'] ?? '') === 'grid') $layoutClass = 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4';
                    @endphp
                    @if(!empty($block['child_image_width']) || !empty($block['child_image_height']) || !empty($block['child_image_fit']))
                        @php
                            $cW = !empty($block['child_image_width']) ? (is_numeric($block['child_image_width']) ? $block['child_image_width'].'px' : $block['child_image_width']) : 'auto';
                            $cH = !empty($block['child_image_height']) ? (is_numeric($block['child_image_height']) ? $block['child_image_height'].'px' : $block['child_image_height']) : 'auto';
                        @endphp
                        <style>
                            .tribio-container-{{ md5($currentPath) }} img {
                                width: {{ $cW }} !important;
                                height: {{ $cH }} !important;
                                {{ !empty($block['child_image_fit']) ? "object-fit: {$block['child_image_fit']} !important;" : "" }}
                            }
                        </style>
                    @endif
                    <div style="background-color: {{ $block['background_color'] ?? 'transparent' }}; {{ $bgImage ?? '' }} {{ $borderStyles ?? '' }} {{ $paddingStyles ?? '' }}" class="{{ $layoutClass }} w-full h-full min-h-[60px] relative overflow-hidden max-w-full break-words tribio-container-{{ md5($currentPath) }}">
                        @if(!empty($block['blocks']) && count($block['blocks']) > 0)
                            <!-- Recursive call -->
                                @include('components.store-sections.blocks', ['data' => ['blocks' => $block['blocks']], 'pathPrefix' => $currentPath . '.blocks.', 'parentLayout' => $block['layout'] ?? 'flex-col', 'parentAlign' => $block['alignment'] ?? 'flex-start', 'parentVerticalAlign' => $block['vertical_alignment'] ?? 'center', 'parentGap' => $block['gap'] ?? null])
                        @else
                            @if($isEditor)
                                <div class="w-full h-full min-h-[60px] flex items-center justify-center cursor-pointer" onclick="const sectionId = this.closest('.tribio-section-wrapper').getAttribute('data-section-id'); const rect = this.getBoundingClientRect(); window.parent.postMessage({action: 'request_add_block', section_id: parseInt(sectionId), path: '{{ $currentPath }}', rect: {top: rect.top, left: rect.left, bottom: rect.bottom, right: rect.right, width: rect.width, height: rect.height} }, '*'); event.stopPropagation();">
                                    <div class="w-6 h-6 bg-blue-600 text-white flex items-center justify-center hover:bg-blue-700 transition-colors rounded-sm shadow-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                
                @elseif($block['type'] === 'carousel')
                    @if(!empty($block['child_image_width']) || !empty($block['child_image_height']) || !empty($block['child_image_fit']))
                        @php
                            $cW = !empty($block['child_image_width']) ? (is_numeric($block['child_image_width']) ? $block['child_image_width'].'px' : $block['child_image_width']) : 'auto';
                            $cH = !empty($block['child_image_height']) ? (is_numeric($block['child_image_height']) ? $block['child_image_height'].'px' : $block['child_image_height']) : 'auto';
                        @endphp
                        <style>
                            .tribio-carousel-{{ md5($currentPath) }} img {
                                width: {{ $cW }} !important;
                                height: {{ $cH }} !important;
                                {{ !empty($block['child_image_fit']) ? "object-fit: {$block['child_image_fit']} !important;" : "" }}
                            }
                        </style>
                    @endif
                    <div style="background-color: {{ $block['background_color'] ?? 'transparent' }}; {{ $bgImage ?? '' }} {{ $borderStyles ?? '' }} {{ $paddingStyles ?? '' }}" 
                         class="w-full h-full relative overflow-hidden group/carousel tribio-carousel-{{ md5($currentPath) }} {{ $isEditor ? 'min-h-[100px]' : '' }}"
                         @if(empty($block['is_marquee']))
                         x-data="{ 
                            scrollNext() { this.$refs.slider.scrollBy({ left: 300, behavior: 'smooth' }) }, 
                            scrollPrev() { this.$refs.slider.scrollBy({ left: -300, behavior: 'smooth' }) },
                            autoPlay: true,
                            init() {
                                setInterval(() => {
                                    if (this.autoPlay && this.$refs.slider) {
                                        let slider = this.$refs.slider;
                                        if (slider.scrollLeft + slider.clientWidth >= slider.scrollWidth - 10) {
                                            slider.scrollTo({ left: 0, behavior: 'smooth' });
                                        } else {
                                            this.scrollNext();
                                        }
                                    }
                                }, 3000);
                            }
                         }"
                         @mouseenter="autoPlay = false"
                         @mouseleave="autoPlay = true"
                         @endif
                         >
                         
                         @if(!empty($block['is_marquee']))
                             <style>
                                 @keyframes scroll-{{ md5($currentPath) }} {
                                     0% { transform: translateX(0); }
                                     100% { transform: translateX(calc(-50% - {{ ($block['gap'] ?? 16) / 2 }}px)); }
                                 }
                                 .marquee-{{ md5($currentPath) }} {
                                     display: flex;
                                     width: max-content;
                                     animation: scroll-{{ md5($currentPath) }} 20s linear infinite;
                                     gap: {{ $block['gap'] ?? 16 }}px;
                                 }
                                 .marquee-{{ md5($currentPath) }}:hover {
                                     animation-play-state: paused;
                                 }
                             </style>
                             <div class="marquee-{{ md5($currentPath) }} h-full">
                                 @if(!empty($block['blocks']) && count($block['blocks']) > 0)
                                     @include('components.store-sections.blocks', ['data' => ['blocks' => $block['blocks']], 'pathPrefix' => $currentPath . '.blocks.', 'parentLayout' => 'carousel', 'parentGap' => $block['gap'] ?? null, 'parentAlign' => $block['alignment'] ?? 'center', 'parentVerticalAlign' => $block['vertical_alignment'] ?? 'center'])
                                     @include('components.store-sections.blocks', ['data' => ['blocks' => $block['blocks']], 'pathPrefix' => $currentPath . '.blocks.dup.', 'parentLayout' => 'carousel', 'parentGap' => $block['gap'] ?? null, 'parentAlign' => $block['alignment'] ?? 'center', 'parentVerticalAlign' => $block['vertical_alignment'] ?? 'center'])
                                 @else
                                     @if($isEditor)
                                         <div class="w-full h-32 flex items-center justify-center cursor-pointer border-2 border-dashed border-gray-300 rounded-lg" onclick="const sectionId = this.closest('.tribio-section-wrapper').getAttribute('data-section-id'); const rect = this.getBoundingClientRect(); window.parent.postMessage({action: 'request_add_block', section_id: parseInt(sectionId), path: '{{ $currentPath }}', rect: {top: rect.top, left: rect.left, bottom: rect.bottom, right: rect.right, width: rect.width, height: rect.height} }, '*'); event.stopPropagation();">
                                             <div class="flex items-center gap-2 text-gray-500">
                                                 <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                                 <span class="font-medium text-sm">Añadir elementos a la marquesina</span>
                                             </div>
                                         </div>
                                     @endif
                                 @endif
                             </div>
                         @else
                             <div class="w-full h-full overflow-x-auto snap-x snap-mandatory flex-nowrap [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]" x-ref="slider">
                                 @if(!empty($block['blocks']) && count($block['blocks']) > 0)
                                     @include('components.store-sections.blocks', ['data' => ['blocks' => $block['blocks']], 'pathPrefix' => $currentPath . '.blocks.', 'parentLayout' => 'carousel', 'parentGap' => $block['gap'] ?? null, 'parentAlign' => $block['alignment'] ?? 'center', 'parentVerticalAlign' => $block['vertical_alignment'] ?? 'center'])
                                 @else
                                     @if($isEditor)
                                         <div class="w-full h-32 flex items-center justify-center cursor-pointer border-2 border-dashed border-gray-300 rounded-lg" onclick="const sectionId = this.closest('.tribio-section-wrapper').getAttribute('data-section-id'); const rect = this.getBoundingClientRect(); window.parent.postMessage({action: 'request_add_block', section_id: parseInt(sectionId), path: '{{ $currentPath }}', rect: {top: rect.top, left: rect.left, bottom: rect.bottom, right: rect.right, width: rect.width, height: rect.height} }, '*'); event.stopPropagation();">
                                             <div class="flex items-center gap-2 text-gray-500">
                                                 <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                                 <span class="font-medium text-sm">Añadir elementos al carrusel</span>
                                             </div>
                                         </div>
                                     @endif
                                 @endif
                             </div>
                             
                             @if(!empty($block['blocks']) && count($block['blocks']) > 1)
                                 <button @click="scrollPrev" class="absolute left-2 top-1/2 -translate-y-1/2 w-8 h-8 flex items-center justify-center bg-white/90 rounded-full shadow hover:bg-white z-[70] text-gray-800 opacity-0 group-hover/carousel:opacity-100 transition-opacity">
                                     <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                                 </button>
                                 <button @click="scrollNext" class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 flex items-center justify-center bg-white/90 rounded-full shadow hover:bg-white z-[70] text-gray-800 opacity-0 group-hover/carousel:opacity-100 transition-opacity">
                                     <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                 </button>
                             @endif
                         @endif
                     </div>
                
                @elseif($block['type'] === 'store_logo')
                    <div class="flex items-center justify-center w-full h-full {{ $isEditor ? 'pointer-events-auto relative z-50' : '' }}">
                        <a href="{{ $isEditor ? 'javascript:void(0)' : route('store.show', $store->slug ?? '') }}">
                            @if(isset($store) && $store->logo_path)
                                <img src="{{ $store->logo_url }}" alt="{{ $store->name }}" class="h-14 w-auto object-contain">
                            @else
                                <span class="text-2xl font-black tracking-tight text-gray-900" style="color: {{ $block['color'] ?? '#374151' }};">
                                    {{ $store->name ?? 'Mi Tienda' }}
                                </span>
                            @endif
                        </a>
                    </div>
                
                @elseif($block['type'] === 'store_navbar')
                    <nav class="flex items-center gap-4 sm:gap-7 text-xs font-black uppercase tracking-wider w-full h-full justify-center flex-wrap {{ $isEditor ? 'pointer-events-auto relative z-50' : '' }}" style="color: {{ $block['color'] ?? '#374151' }};">
                        <a href="{{ $isEditor ? 'javascript:void(0)' : route('store.show', $store->slug ?? '') }}" class="pb-1 border-b-2 border-transparent transition-all hover:opacity-70">{{ $block['link_1'] ?? 'INICIO' }}</a>
                        <a href="{{ $isEditor ? 'javascript:void(0)' : route('store.catalog', $store->slug ?? '') }}" class="pb-1 border-b-2 border-transparent transition-all hover:opacity-70">{{ $block['link_2'] ?? 'CATÁLOGO' }}</a>
                        <a href="{{ $isEditor ? 'javascript:void(0)' : '#contacto' }}" class="pb-1 border-b-2 border-transparent transition-all hover:opacity-70">{{ $block['link_3'] ?? 'CONTACTO' }}</a>
                    </nav>
                
                @elseif($block['type'] === 'store_search')
                    <div class="w-full h-full flex items-center justify-center {{ $isEditor ? 'pointer-events-auto relative z-50' : '' }}">
                        <form method="GET" action="{{ $isEditor ? 'javascript:void(0)' : route('store.catalog', $store->slug ?? '') }}" class="flex border border-gray-300 rounded overflow-hidden h-10 w-full max-w-[240px]">
                            <input type="text" name="q" placeholder="Buscar repuesto..." class="w-full px-3 text-xs focus:outline-none" {{ $isEditor ? 'disabled' : '' }}>
                            <button type="submit" style="background-color: {{ $block['background_color'] ?? '#E50914' }}; color: {{ $block['text_color'] ?? '#FFFFFF' }};" class="px-3 flex items-center justify-center hover:opacity-90 transition-opacity">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </button>
                        </form>
                    </div>

                @elseif($block['type'] === 'store_cart')
                    <div class="w-full h-full flex items-center justify-center {{ $isEditor ? 'pointer-events-auto relative z-50' : '' }}">
                        <div class="relative">
                            <button @click="openCartDropdown = !openCartDropdown" class="relative p-2.5 rounded-full bg-gray-50 border border-gray-100 hover:bg-gray-100 transition-colors shrink-0">
                                <svg class="w-5 h-5" style="color: {{ $block['icon_color'] ?? '#374151' }};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                                <span x-show="cartCount > 0" x-text="cartCount" style="background-color: {{ $block['background_color'] ?? '#E50914' }}; color: {{ $block['text_color'] ?? '#FFFFFF' }};" class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full text-[10px] font-bold flex items-center justify-center shadow-md animate-pulse"></span>
                            </button>

                            <!-- Mini Cart -->
                            <div x-show="openCartDropdown" @click.outside="openCartDropdown = false" style="display: none;" class="absolute right-0 mt-3 w-80 sm:w-96 bg-white border border-gray-200 rounded-2xl shadow-xl z-50 p-4 space-y-4 text-left">
                                <div x-show="checkoutStep === 1" class="space-y-4">
                                    <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                                        <h3 class="text-xs font-black uppercase tracking-wider text-gray-900">Mi carrito (<span x-text="cartCount"></span>)</h3>
                                        <button @click="openCartDropdown = false" class="text-gray-400 hover:text-gray-500 text-xs">✕</button>
                                    </div>
                                    <div class="max-h-60 overflow-y-auto divide-y divide-gray-100 pr-1">
                                        <template x-if="items.length === 0">
                                            <div class="text-center py-8 space-y-2">
                                                <p class="text-gray-500 font-bold text-[10px]">Tu carrito está vacío.</p>
                                            </div>
                                        </template>
                                        <template x-if="items.length > 0">
                                            <template x-for="item in items" :key="item.id">
                                                <div class="flex py-3 gap-3">
                                                    <div class="w-12 h-12 bg-gray-50 rounded border border-gray-100 shrink-0 flex items-center justify-center overflow-hidden">
                                                        <img x-show="item.image" :src="item.image" class="w-full h-full object-contain">
                                                    </div>
                                                    <div class="flex-1 flex flex-col justify-between min-w-0">
                                                        <div>
                                                            <h4 class="text-[10px] font-black text-gray-900 leading-tight truncate uppercase" x-text="item.name"></h4>
                                                            <span class="text-[10px] text-green-600 font-bold" x-text="'S/. ' + item.price.toFixed(2)"></span>
                                                        </div>
                                                        <div class="flex items-center justify-between mt-1">
                                                            <div class="flex items-center border border-gray-200 rounded bg-gray-50">
                                                                <button @click="updateQty(item.id, item.quantity - 1)" class="px-1.5 py-0.5 text-gray-500 hover:bg-gray-200 text-[10px]">-</button>
                                                                <span class="px-2 text-[10px] font-bold text-gray-700" x-text="item.quantity"></span>
                                                                <button @click="updateQty(item.id, item.quantity + 1)" class="px-1.5 py-0.5 text-gray-500 hover:bg-gray-200 text-[10px]">+</button>
                                                            </div>
                                                            <button @click="removeItem(item.id)" class="text-[10px] text-red-500 font-semibold hover:underline">Eliminar</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                        </template>
                                    </div>
                                    <template x-if="items.length > 0">
                                        <div class="pt-3 border-t border-gray-100 space-y-3">
                                            <div class="flex justify-between items-center text-xs">
                                                <span class="font-bold text-gray-600">Total:</span>
                                                <span class="font-black text-gray-900 text-sm" x-text="'S/. ' + totalSum().toFixed(2)"></span>
                                            </div>
                                            <div class="flex gap-2">
                                                <button @click="checkoutStep = 2" style="background-color: {{ $block['background_color'] ?? '#E50914' }}; color: {{ $block['text_color'] ?? '#FFFFFF' }};" class="flex-1 py-2 px-3 font-bold text-[10px] uppercase tracking-wider text-center rounded hover:opacity-90">Continuar pedido</button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                <div x-show="checkoutStep === 2" class="space-y-4">
                                    <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                                        <h3 class="text-xs font-black uppercase tracking-wider text-gray-900">Cotización</h3>
                                        <button @click="checkoutStep = 1" style="color: {{ $block['background_color'] ?? '#E50914' }};" class="hover:underline text-[10px] font-bold">Volver</button>
                                    </div>
                                    <div class="space-y-2">
                                        <div><label class="block text-[9px] font-bold text-gray-600 mb-0.5">Nombre *</label><input type="text" x-model="checkoutForm.customer_name" class="w-full px-2 py-1.5 rounded border text-[10px]"></div>
                                        <div><label class="block text-[9px] font-bold text-gray-600 mb-0.5">WhatsApp *</label><input type="text" x-model="checkoutForm.customer_phone" class="w-full px-2 py-1.5 rounded border text-[10px]"></div>
                                        <div><label class="block text-[9px] font-bold text-gray-600 mb-0.5">Dirección *</label><input type="text" x-model="checkoutForm.customer_address" class="w-full px-2 py-1.5 rounded border text-[10px]"></div>
                                    </div>
                                    <div class="pt-2 border-t border-gray-100 space-y-2">
                                        <button @click="submitOrder()" :disabled="submitting" style="background-color: {{ $block['background_color'] ?? '#E50914' }}; color: {{ $block['text_color'] ?? '#FFFFFF' }};" class="w-full py-2.5 px-3 font-bold text-[10px] uppercase text-center rounded hover:opacity-90">
                                            <span x-show="!submitting">Confirmar por WhatsApp</span><span x-show="submitting">Enviando...</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif


                
            </div>
        @endforeach
    </div>
@endif
