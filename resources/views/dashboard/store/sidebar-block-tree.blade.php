@foreach($blocks as $index => $block)
    <div class="p-4 bg-white border rounded-xl relative shadow-sm transition-all duration-300 mb-4 {{ (isset($activeBlockIndex) && $activeBlockIndex === $index) ? 'border-primary ring-2 ring-primary bg-blue-50/5' : 'border-gray-200 hover:border-gray-300' }}" 
         id="block-editor-{{ $block['id'] }}" 
         @click.stop="activeBlockIndex = '{{ $block['id'] }}'; highlightBlockInIframe('{{ $block['id'] }}')">
        
        <div class="flex justify-between items-center mb-3">
            <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest bg-gray-100 px-2 py-1 rounded">{{ $block['type'] }}</span>
            <div class="flex items-center gap-1">
                <div class="drag-handle-block cursor-move p-1 text-gray-400 hover:text-gray-700 bg-gray-50 rounded hover:bg-gray-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </div>
                <button type="button" @click.stop="removeBlock('{{ $block['id'] }}')" class="text-red-400 hover:text-red-600 p-1 ml-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                </button>
            </div>
        </div>

        @if($block['type'] === 'container')
            <!-- CONTAINER CONTROLS -->
            <div class="mb-3">
                <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Layout</label>
                <select x-model="getBlockById('{{ $block['id'] }}').layout" @change="saveActiveSection(false)" class="w-full px-3 py-2 text-sm text-gray-900 border-gray-300 rounded focus:ring-1 focus:ring-primary">
                    <option value="flex-col">Vertical (Columna)</option>
                    <option value="flex-row">Horizontal (Fila)</option>
                    <option value="grid">Grid (Rejilla)</option>
                </select>
            </div>
            
            <div class="grid grid-cols-2 gap-2 mb-3">
                <div>
                    <label class="block text-[9px] font-bold text-gray-400 mb-1">Padding X (px)</label>
                    <input type="number" x-model="getBlockById('{{ $block['id'] }}').padding_x" @blur="saveActiveSection(false)" class="w-full px-2 py-1 text-xs border-gray-300 rounded focus:ring-1 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-[9px] font-bold text-gray-400 mb-1">Padding Y (px)</label>
                    <input type="number" x-model="getBlockById('{{ $block['id'] }}').padding_y" @blur="saveActiveSection(false)" class="w-full px-2 py-1 text-xs border-gray-300 rounded focus:ring-1 focus:ring-primary">
                </div>
            </div>
            
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Fondo</label>
                <input type="color" x-model="getBlockById('{{ $block['id'] }}').background_color" @change="saveActiveSection(false)" class="w-full h-8 p-0 border-0 rounded cursor-pointer shadow-sm">
            </div>

            <!-- NESTED BLOCKS LIST -->
            <div class="mt-4 p-3 border border-dashed border-gray-300 bg-gray-50 rounded-lg min-h-[60px]">
                <div class="text-[10px] text-gray-400 font-bold uppercase mb-2 text-center">Soltar bloques aquí</div>
                
                <div class="nested-sortable-list space-y-2" data-parent-id="{{ $block['id'] }}" x-init="
                    Sortable.create($el, {
                        group: 'nested-blocks',
                        animation: 150,
                        fallbackOnBody: true,
                        swapThreshold: 0.65,
                        handle: '.drag-handle-block',
                        onEnd: (evt) => {
                            handleNestedBlockDrop(evt);
                        }
                    });
                ">
                    @if(isset($block['blocks']) && count($block['blocks']) > 0)
                        <!-- ESTO SOLO RENDERIZARÁ LA UI INICIAL, LUEGO ALPINE TOMA EL CONTROL -->
                        <template x-for="childBlock in getBlockById('{{ $block['id'] }}').blocks || []" :key="childBlock.id">
                            <!-- Aquí iría una réplica en Alpine del HTML de arriba o dependemos de la recarga.
                                 Dado que Alpine requiere plantillas para la recursividad completa y Blade se renderiza en backend,
                                 para mantenerlo simple, recargaremos el sidebar completo desde Alpine o usaremos x-html.
                            -->
                            <div class="text-xs text-primary font-bold">Elemento anidado (Refrescar para ver)</div>
                        </template>
                    @endif
                </div>
            </div>
            
        @else
            <!-- STANDARD BLOCKS (Text, Image, etc) -->
            <template x-if="getBlockById('{{ $block['id'] }}').content !== undefined">
                <div class="mb-3">
                    <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Contenido</label>
                    <input type="text" x-model="getBlockById('{{ $block['id'] }}').content" @blur="saveActiveSection(false)" class="w-full px-3 py-2 text-sm text-gray-900 border-gray-300 rounded focus:ring-1 focus:ring-primary">
                </div>
            </template>
            <!-- Add other block properties here based on previous builder.blade.php -->
        @endif
        
    </div>
@endforeach
