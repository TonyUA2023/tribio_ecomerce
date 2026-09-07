<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Editor Visual — Tribio</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-outfit antialiased bg-gray-50 overflow-hidden" x-data="storeBuilder()">
    
    <!-- Topbar del Editor -->
    <header class="h-14 bg-white border-b border-gray-200 flex items-center justify-between px-4 shrink-0">
        <div class="flex items-center gap-4">
            <a href="{{ route('dashboard.index') }}" class="text-slate-600 hover:text-gray-900 flex items-center gap-2 text-sm font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Salir
            </a>
            <div class="h-6 w-px bg-gray-200"></div>
            <span class="font-bold text-gray-800">{{ $store->name }} <span class="text-xs text-gray-400 font-normal ml-2">Editor Visual Automático</span></span>
            @if($store->build_mode === 'custom_code')
                <div class="ml-4 px-3 py-1 bg-yellow-100 text-yellow-800 text-xs font-bold rounded flex items-center gap-1">
                    ⚠️ Modo Código Activado (Los cambios visuales aquí no se verán en la web pública)
                </div>
            @endif
        </div>
        <div class="flex items-center gap-4">
            <span x-show="isSaving" style="display: none;" class="text-xs text-gray-400 font-bold flex items-center gap-1">
                Borrador guardado
            </span>
            <button @click="publishChanges" class="px-4 py-1.5 bg-green-500 hover:bg-green-600 text-white text-xs font-bold rounded-lg shadow-sm transition-colors flex items-center gap-2">
                <span x-show="!isPublishing">🚀 Publicar Cambios</span>
                <span x-show="isPublishing">Publicando...</span>
            </button>
            <button @click="loadTemplateBlocks" class="text-xs font-bold text-slate-700 hover:text-primary transition-colors flex items-center gap-1">
                🔄 Restablecer Plantilla
            </button>
            <a href="{{ route('store.show', $store->slug) }}" target="_blank" class="text-xs font-bold text-slate-700 hover:text-primary transition-colors flex items-center gap-1">
                👁️ Ver tienda pública
            </a>
        </div>
    </header>

    <div class="flex h-[calc(100vh-3.5rem)]">
        <!-- Sidebar Controles (Izquierda) -->
        <aside class="w-[350px] bg-white border-r border-gray-200 flex flex-col shrink-0">
            <!-- Pestañas o Header del Sidebar -->
            <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                <h2 class="font-bold text-gray-800" x-text="activeSection ? 'Editando Sección' : 'Tus Secciones'"></h2>
                <button x-show="activeSection" @click="closeEditor" class="text-primary hover:text-blue-700 text-xs font-black uppercase tracking-wider px-2 py-1 bg-blue-50 rounded">
                    ← Volver
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-4">
                
                <div x-show="!activeSection" class="space-y-4">
                    <template x-if="sections.length === 0">
                        <div class="p-6 text-center text-gray-400 text-sm font-semibold">
                            No hay secciones configuradas. Agrega una o restablece a la plantilla.
                        </div>
                    </template>

                    <div class="space-y-2" id="sections-list" x-init="
                        Sortable.create($el, {
                            animation: 150,
                            handle: '.drag-handle',
                            onEnd: (evt) => {
                                const oldIndex = evt.oldIndex;
                                const newIndex = evt.newIndex;
                                if(oldIndex === newIndex) return;
                                
                                const itemEl = evt.item;
                                if (oldIndex < newIndex) {
                                    evt.to.insertBefore(itemEl, evt.to.children[oldIndex]);
                                } else {
                                    evt.to.insertBefore(itemEl, evt.to.children[oldIndex + 1]);
                                }
                                
                                moveSectionDrag(oldIndex, newIndex);
                            }
                        });
                    ">
                        <template x-for="(section, index) in sortedSections" :key="section.id">
                            <div class="p-3 bg-white rounded-lg border border-gray-200 flex items-center justify-between group cursor-pointer hover:border-primary hover:shadow-sm transition-all"
                                 @click="editSection(section)">
                                <div class="flex items-center gap-3">
                                    <div class="flex flex-col gap-0.5 drag-handle cursor-move p-1 bg-gray-50 rounded hover:bg-gray-100">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                                    </div>
                                    <div>
                                        <span class="block font-bold text-sm text-slate-800 capitalize" x-text="section.type.replace('_', ' ')"></span>
                                        <span class="text-[10px] uppercase font-bold tracking-wider" :class="section.is_active ? 'text-green-500' : 'text-gray-400'" x-text="section.is_active ? 'Visible' : 'Oculto'"></span>
                                    </div>
                                </div>
                                <button @click.stop="deleteSection(section.id)" class="text-red-400 hover:text-red-600 opacity-0 group-hover:opacity-100 transition-opacity p-1 bg-red-50 rounded">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </template>
                    </div>
                    
                    <button @click="showAddModal = true" class="w-full py-3 border-2 border-dashed border-gray-300 rounded-lg text-slate-600 font-bold hover:border-primary hover:text-primary hover:bg-blue-50 transition-colors text-sm">
                        + Añadir nueva sección
                    </button>
                </div>

                <!-- ESTADO 2: Edición de Sección -->
                <div x-show="activeSection" class="space-y-5" style="display:none;">
                    <template x-if="activeSection">
                        <div>
                            <div class="mb-5 pb-5 border-b border-gray-100">
                                <label class="flex items-center gap-2 cursor-pointer bg-gray-50 p-3 rounded-lg border border-gray-200 hover:bg-gray-100 transition-colors">
                                    <input type="checkbox" x-model="activeSection.is_active" @change="saveActiveSection" class="form-checkbox text-primary rounded border-gray-300">
                                    <span class="text-sm font-bold text-slate-800">Sección visible públicamente</span>
                                </label>
                            </div>

                            <div class="space-y-4">
                                <template x-for="(value, key) in activeSection.data" :key="key">
                                    <div class="flex flex-col gap-1.5" x-show="key !== 'blocks'">
                                        <label class="text-[10px] font-black text-slate-600 uppercase tracking-widest" x-text="key.replace(/_/g, ' ')"></label>
                                        
                                        <template x-if="key === 'alignment'">
                                            <div class="flex bg-white border border-gray-300 rounded overflow-hidden">
                                                <button type="button" class="flex-1 py-1.5 text-sm transition-colors border-r border-gray-200" :class="activeSection.data[key] === 'left' ? 'bg-gray-800 text-white font-bold' : 'text-slate-700 hover:bg-gray-50'" @click="activeSection.data[key] = 'left'; saveActiveSection()">Izquierda</button>
                                                <button type="button" class="flex-1 py-1.5 text-sm transition-colors border-r border-gray-200" :class="activeSection.data[key] === 'center' ? 'bg-gray-800 text-white font-bold' : 'text-slate-700 hover:bg-gray-50'" @click="activeSection.data[key] = 'center'; saveActiveSection()">Centro</button>
                                                <button type="button" class="flex-1 py-1.5 text-sm transition-colors" :class="activeSection.data[key] === 'right' ? 'bg-gray-800 text-white font-bold' : 'text-slate-700 hover:bg-gray-50'" @click="activeSection.data[key] = 'right'; saveActiveSection()">Derecha</button>
                                            </div>
                                        </template>

                                        <template x-if="key === 'size'">
                                            <div class="flex bg-white border border-gray-300 rounded overflow-hidden">
                                                <button type="button" class="flex-1 py-1.5 text-sm transition-colors border-r border-gray-200" :class="activeSection.data[key] === 'normal' ? 'bg-gray-800 text-white font-bold' : 'text-slate-700 hover:bg-gray-50'" @click="activeSection.data[key] = 'normal'; saveActiveSection()">Normal</button>
                                                <button type="button" class="flex-1 py-1.5 text-sm transition-colors border-r border-gray-200" :class="activeSection.data[key] === 'large' ? 'bg-gray-800 text-white font-bold' : 'text-slate-700 hover:bg-gray-50'" @click="activeSection.data[key] = 'large'; saveActiveSection()">Grande</button>
                                                <button type="button" class="flex-1 py-1.5 text-sm transition-colors" :class="activeSection.data[key] === 'full' ? 'bg-gray-800 text-white font-bold' : 'text-slate-700 hover:bg-gray-50'" @click="activeSection.data[key] = 'full'; saveActiveSection()">Completo</button>
                                            </div>
                                        </template>
                                        
                                        <template x-if="key === 'layout'">
                                            <div class="flex bg-white border border-gray-300 rounded overflow-hidden">
                                                <button type="button" class="flex-1 py-1.5 text-sm transition-colors border-r border-gray-200 flex items-center justify-center gap-2" :class="activeSection.data[key] === 'grid' ? 'bg-gray-800 text-white font-bold' : 'text-slate-700 hover:bg-gray-50'" @click="activeSection.data[key] = 'grid'; saveActiveSection()">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                                                    Grid
                                                </button>
                                                <button type="button" class="flex-1 py-1.5 text-sm transition-colors flex items-center justify-center gap-2" :class="activeSection.data[key] === 'carousel' ? 'bg-gray-800 text-white font-bold' : 'text-slate-700 hover:bg-gray-50'" @click="activeSection.data[key] = 'carousel'; saveActiveSection()">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17l-5-5 5-5M15 7l5 5-5 5"></path></svg>
                                                    Carrusel
                                                </button>
                                            </div>
                                        </template>

                                        <template x-if="key === 'carousel_images'">
                                            <div class="flex flex-col gap-2">
                                                <template x-for="(img, idx) in activeSection.data[key]" :key="idx">
                                                    <div class="relative w-full h-24 rounded-lg overflow-hidden border border-gray-300 bg-gray-100 flex items-center justify-center shadow-sm">
                                                        <img :src="img" class="w-full h-full object-cover">
                                                        <button @click="activeSection.data[key].splice(idx, 1); saveActiveSection()" class="absolute top-1 right-1 bg-white rounded-full p-1 shadow hover:text-red-500">
                                                            ✕
                                                        </button>
                                                    </div>
                                                </template>
                                                <label class="w-full px-3 py-2 text-sm text-gray-900 bg-white border border-gray-300 rounded focus:ring-1 focus:ring-primary shadow-sm text-center cursor-pointer hover:bg-gray-50 flex flex-col items-center justify-center gap-1">
                                                    <span class="text-xl">+</span>
                                                    <span x-text="isUploadingImage ? 'Subiendo...' : 'Añadir Imagen'"></span>
                                                    <input type="file" accept="image/*" class="hidden" @change="uploadCarouselImage($event, key)" :disabled="isUploadingImage">
                                                </label>
                                            </div>
                                        </template>

                                        <template x-if="key.includes('image') && key !== 'carousel_images'">
                                            <div class="flex flex-col gap-2">
                                                <div x-show="activeSection.data[key]" class="relative w-full h-24 rounded-lg overflow-hidden border border-gray-300 bg-gray-100 flex items-center justify-center">
                                                    <img :src="activeSection.data[key]" class="w-full h-full object-cover">
                                                    <button @click="activeSection.data[key] = ''; saveActiveSection()" class="absolute top-1 right-1 bg-white rounded-full p-1 shadow hover:text-red-500">
                                                        ✕
                                                    </button>
                                                </div>
                                                <label class="w-full px-3 py-2 text-sm text-gray-900 bg-white border border-gray-300 rounded focus:ring-1 focus:ring-primary shadow-sm text-center cursor-pointer hover:bg-gray-50">
                                                    <span x-text="isUploadingImage ? 'Subiendo...' : 'Seleccionar Imagen'"></span>
                                                    <input type="file" accept="image/*" class="hidden" @change="uploadSectionImage($event, key)" :disabled="isUploadingImage">
                                                </label>
                                            </div>
                                        </template>
                                        
                                        <template x-if="key.includes('color') && !key.includes('image') && key !== 'carousel_images'">
                                            <div class="flex items-center gap-2">
                                                <input type="color" x-model="activeSection.data[key]" @change="saveActiveSection" class="h-8 w-8 p-0 border-0 rounded cursor-pointer shadow-sm">
                                                <input type="text" x-model="activeSection.data[key]" @blur="saveActiveSection" class="w-full px-3 py-1.5 text-sm text-gray-900 bg-white border border-gray-300 rounded focus:ring-1 focus:ring-primary focus:border-primary font-mono uppercase">
                                            </div>
                                        </template>
                                        
                                        <template x-if="!key.includes('color') && !key.includes('image') && !['content', 'alignment', 'size', 'carousel_images', 'layout', 'blocks', 'padding_x', 'padding_y', 'min_height'].includes(key)">
                                            <input type="text" x-model="activeSection.data[key]" @blur="saveActiveSection" class="w-full px-3 py-2 text-sm text-gray-900 bg-white border border-gray-300 rounded focus:ring-1 focus:ring-primary focus:border-primary shadow-sm">
                                        </template>
                                        
                                        <template x-if="key === 'content'">
                                            <textarea x-model="activeSection.data[key]" @blur="saveActiveSection" class="w-full px-3 py-2 text-sm text-gray-900 bg-white border border-gray-300 rounded focus:ring-1 focus:ring-primary focus:border-primary shadow-sm min-h-[120px]"></textarea>
                                        </template>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 mt-4">
                                        <div>
                                            <label class="block text-[9px] font-bold text-slate-600 mb-1">Padding X (px)</label>
                                            <input type="number" x-model="activeSection.data.padding_x" @blur="saveActiveSection" 
                                                   class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                        </div>
                                        <div>
                                            <label class="block text-[9px] font-bold text-slate-600 mb-1">Padding Y (px)</label>
                                            <input type="number" x-model="activeSection.data.padding_y" @blur="saveActiveSection" 
                                                   class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <label class="block text-[9px] font-bold text-slate-600 mb-1">Alto Mínimo (px)</label>
                                        <input type="number" x-model="activeSection.data.min_height" @blur="saveActiveSection" placeholder="Ej. 100"
                                               class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                    </div>
                                </template>
                            </div>

                            
                            <!-- Editores de Bloques (Al final de los campos) -->
                            <template x-if="activeSection.data.blocks && activeSection.data.blocks.length > 0">
                                <div class="mt-8 border-t border-gray-200 pt-6">
                                    <!-- Tabs Navigation -->
                                    <div class="flex bg-gray-100/80 p-1 rounded-lg mb-4 border border-gray-200">
                                        <button @click="sidebarTab = 'capas'" 
                                                :class="sidebarTab === 'capas' ? 'bg-white text-blue-600 shadow-sm border border-gray-200' : 'text-slate-600 hover:text-slate-900 hover:bg-gray-200/50'" 
                                                class="flex-1 py-1.5 rounded-md font-bold text-xs uppercase tracking-wider transition-all duration-200">Capas</button>
                                        <button @click="sidebarTab = 'propiedades'" 
                                                :class="sidebarTab === 'propiedades' ? 'bg-white text-blue-600 shadow-sm border border-gray-200' : 'text-slate-600 hover:text-slate-900 hover:bg-gray-200/50'" 
                                                class="flex-1 py-1.5 rounded-md font-bold text-xs uppercase tracking-wider transition-all duration-200">Propiedades</button>
                                    </div>
                                    
                                    <!-- TAB CAPAS -->
                                    <div x-show="sidebarTab === 'capas'" class="space-y-0.5" id="blocks-list">
                                        <template x-for="item in flattenedBlocks" :key="item.id">
                                            <div class="flex items-center gap-2 py-2 px-2 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer group"
                                                 :class="activeBlockPath === item.path ? 'bg-blue-100 ring-2 ring-blue-500' : ''"
                                                 :style="'padding-left: ' + (8 + item.depth * 14) + 'px'"
                                                 @click="activeBlockPath = item.path; activeBlock = getBlockByPath(item.path); highlightBlockInIframe(item.path); sidebarTab = 'propiedades'">
                                                
                                                <!-- Folder icon or bullet -->
                                                <div class="w-4 h-4 flex items-center justify-center shrink-0">
                                                    <template x-if="item.hasChildren">
                                                        <button @click.stop="item.block.isCollapsed = !item.block.isCollapsed; saveActiveSection(false)" 
                                                                class="text-slate-600 hover:text-gray-900 transition-colors">
                                                            <svg x-show="!item.block.isCollapsed" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                                            </svg>
                                                            <svg x-show="item.block.isCollapsed" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path>
                                                            </svg>
                                                        </button>
                                                    </template>
                                                    <template x-if="!item.hasChildren">
                                                        <div class="w-1.5 h-1.5 bg-gray-400 rounded-full mx-auto"></div>
                                                    </template>
                                                </div>
                                                
                                                <!-- Block type icon emoji + name -->
                                                <span class="text-[11px] font-bold text-slate-800 flex-1 truncate" 
                                                      :class="activeBlockPath === item.path ? 'text-blue-800 font-extrabold' : ''"
                                                      x-text="{ container: '📦 Container', button: '🔘 Button', paragraph: '📝 Párrafo', title: '🔠 Título', image: '🖼️ Imagen', store_logo: '🏷️ Logo', store_navbar: '🧭 Navbar', store_search: '🔍 Búsqueda' }[item.type] || ('⬛ ' + item.type)"></span>
                                                
                                                <!-- Delete button on hover -->
                                                <button type="button" @click.stop="removeBlockByPath(item.path)" 
                                                        class="text-gray-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </template>
                                        
                                        <div x-show="flattenedBlocks.length === 0" class="text-center py-6 text-slate-600 text-xs font-medium">
                                            No hay componentes en esta sección.
                                        </div>
                                    </div>
                                    
                                    <!-- TAB PROPIEDADES -->
                                    <div x-show="sidebarTab === 'propiedades'">
                                        <div x-show="!activeBlock.id" class="text-center py-8 text-slate-500 text-xs font-semibold leading-relaxed">
                                            <div class="text-2xl mb-2">👆</div>
                                            Selecciona una capa en el árbol<br>o haz clic en el diseño.
                                        </div>
                                        
                                        <!-- Properties panel: only renders when activeBlock is non-null -->
                                        <template x-if="activeBlock && activeBlock.id">
                                            <div class="space-y-4">
                                                <!-- Header: Type + Move buttons -->
                                                <div class="flex items-center justify-between bg-gray-100 rounded-lg px-3 py-2">
                                                    <span class="text-[11px] font-black text-slate-800 uppercase tracking-widest" x-text="activeBlock.type"></span>
                                                    <div class="flex items-center gap-1">
                                                        <button type="button" @click="moveBlockRelative(activeBlockPath, -1)" 
                                                                class="p-1 text-slate-700 hover:text-gray-900 bg-white border border-gray-300 rounded hover:bg-gray-50" title="Mover Arriba">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"></path></svg>
                                                        </button>
                                                        <button type="button" @click="moveBlockRelative(activeBlockPath, 1)" 
                                                                class="p-1 text-slate-700 hover:text-gray-900 bg-white border border-gray-300 rounded hover:bg-gray-50" title="Mover Abajo">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                                        </button>
                                                        <button type="button" @click="removeBlockByPath(activeBlockPath)" 
                                                                class="p-1 text-red-400 hover:text-red-600 bg-white border border-gray-300 rounded hover:bg-red-50" title="Eliminar">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                        </button>
                                                    </div>
                                                </div>

                                                <!-- Wrap in Container Option -->
                                                <template x-if="activeBlock && activeBlock.type && activeBlock.type !== 'container' && activeBlock.type !== 'card'">
                                                    <div class="mt-2">
                                                        <button type="button" @click="wrapActiveBlockInContainer()" 
                                                                class="w-full py-1.5 px-3 bg-slate-800 hover:bg-slate-900 text-white text-[10px] font-extrabold rounded flex items-center justify-center gap-1.5 transition-colors uppercase tracking-wider">
                                                            📦 Envolver en Contenedor (Columna)
                                                        </button>
                                                    </div>
                                                </template>

                                                <!-- Container/Card/Carousel: add inside button + layout -->
                                                <template x-if="['container', 'card', 'carousel'].includes(activeBlock.type)">
                                                    <div class="space-y-3 p-3 bg-blue-50 border border-blue-100 rounded-lg mb-4">
                                                        <div class="flex items-center justify-between">
                                                            <span class="text-[10px] font-black text-blue-700 uppercase tracking-wider">Añadir Bloque Dentro</span>
                                                            <button type="button" 
                                                                    @click="insertPosition = 'inside'; inserterPath = activeBlockPath; inserterX = window.innerWidth / 2; inserterY = window.innerHeight / 3; showInserter = true;"
                                                                    class="bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-bold px-2 py-1 rounded flex items-center gap-1 transition-colors">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                                                                + Bloque
                                                            </button>
                                                        </div>
                                                        <template x-if="activeBlock.type === 'container'">
                                                            <div>
                                                                <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-800 mb-1.5">Layout</label>
                                                                <select x-model="activeBlock.layout" @change="saveActiveSection(false)" 
                                                                        class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary bg-white">
                                                                    <option value="flex-col">⬇️ Vertical (Columna)</option>
                                                                    <option value="flex-row">➡️ Horizontal (Fila)</option>
                                                                    <option value="grid">⊞ Grid (Rejilla)</option>
                                                                </select>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </template>

                                                <template x-if="['container', 'carousel'].includes(activeBlock.type)">
                                                    <div class="space-y-3 mb-4 border-b border-gray-200 pb-4">
                                                        <div>
                                                            <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-800 mb-1.5">Alineación Horizontal</label>
                                                            <select x-model="activeBlock.alignment" @change="saveActiveSection(false)" 
                                                                    class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary bg-white">
                                                                <option value="center">Centro</option>
                                                                <option value="flex-start">Izquierda / Arriba</option>
                                                                <option value="flex-end">Derecha / Abajo</option>
                                                                <option value="space-between">Espacio entre</option>
                                                                <option value="space-around">Espacio alrededor</option>
                                                                <option value="space-evenly">Espacio uniforme</option>
                                                                <option value="stretch">Estirar</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div>
                                                            <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-800 mb-1.5">Alineación Vertical</label>
                                                            <select x-model="activeBlock.vertical_alignment" @change="saveActiveSection(false)" 
                                                                    class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary bg-white">
                                                                <option value="center">Centro</option>
                                                                <option value="flex-start">Arriba / Izquierda</option>
                                                                <option value="flex-end">Abajo / Derecha</option>
                                                                <option value="space-between">Espacio entre</option>
                                                                <option value="space-around">Espacio alrededor</option>
                                                                <option value="space-evenly">Espacio uniforme</option>
                                                                <option value="stretch">Estirar / Igualar Alto</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </template>

                                                <template x-if="activeBlock.type === 'carousel'">
                                                    <div class="space-y-3 mb-4 border-b border-gray-200 pb-4">
                                                        <label class="flex items-center gap-2 cursor-pointer">
                                                            <input type="checkbox" x-model="activeBlock.is_marquee" @change="saveActiveSection(false)" class="w-4 h-4 text-primary rounded border-gray-300 focus:ring-primary">
                                                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-800">Modo Marquesina (Animación Continua)</span>
                                                        </label>
                                                        <p class="text-[10px] text-gray-500 leading-tight">Activa esta opción para que los elementos se desplacen infinitamente, ideal para tiras de logotipos.</p>
                                                    </div>
                                                </template>
                                                
                                                <!-- Content editable -->
                                                <template x-if="activeBlock.content !== undefined">
                                                    <div>
                                                        <template x-if="activeBlock.type === 'image' || activeBlock.type === 'store_logo'">
                                                            <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-800 mb-1.5">Origen de la Imagen</label>
                                                        </template>
                                                        <template x-if="activeBlock.type !== 'image' && activeBlock.type !== 'store_logo'">
                                                            <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-800 mb-1.5">Contenido</label>
                                                        </template>
                                                        
                                                        <template x-if="['paragraph', 'title', 'link', 'badge', 'button'].includes(activeBlock.type)">
                                                            <div class="text-[11px] text-slate-700 bg-gray-50 border border-gray-200 rounded px-3 py-2 text-center font-medium">
                                                                ✎ Edita el texto directamente en el lienzo.
                                                            </div>
                                                        </template>
                                                        <template x-if="activeBlock.type === 'image' || activeBlock.type === 'store_logo'">
                                                            <div class="relative flex items-center gap-2">
                                                                <input type="text" x-model="activeBlock.content" @blur="saveActiveSection(false)" placeholder="URL de la imagen..."
                                                                       class="w-full px-3 py-2 text-sm text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                <button type="button" @click="$refs.blockImageUpload.click()" class="bg-gray-100 hover:bg-gray-200 border border-gray-300 text-gray-700 px-3 py-2 rounded flex items-center justify-center shrink-0" title="Subir Imagen">
                                                                    <svg x-show="!isUploadingImage" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v7m0 0l-3-3m3 3l3-3"></path></svg>
                                                                    <svg x-show="isUploadingImage" class="animate-spin w-4 h-4 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                                </button>
                                                                <input type="file" x-ref="blockImageUpload" accept="image/*" class="hidden" @change="uploadBlockImage($event, 'content')" :disabled="isUploadingImage">
                                                            </div>
                                                        </template>
                                                        <template x-if="!['paragraph', 'title', 'link', 'badge', 'button', 'image', 'store_logo'].includes(activeBlock.type)">
                                                            <input type="text" x-model="activeBlock.content" @blur="saveActiveSection(false)" 
                                                                   class="w-full px-3 py-2 text-sm text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                        </template>
                                                    </div>
                                                </template>

                                                <!-- Image/Logo/Button Specific Properties -->
                                                <template x-if="['image', 'store_logo', 'button'].includes(activeBlock.type)">
                                                    <div class="space-y-4 mt-4 pt-4 border-t border-gray-200">
                                                        <!-- Dimensión y Escalado -->
                                                        <div>
                                                            <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-800 mb-2">Dimensión y Escalado</label>
                                                            <div class="grid grid-cols-2 gap-2 mb-2">
                                                                <div>
                                                                    <label class="block text-[9px] font-bold text-slate-600 mb-1">Ancho (Width)</label>
                                                                    <input type="text" x-model="activeBlock.width" @blur="saveActiveSection(false)" placeholder="Ej. 100%, 300px, auto" class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                </div>
                                                                <div>
                                                                    <label class="block text-[9px] font-bold text-slate-600 mb-1">Alto (Height)</label>
                                                                    <input type="text" x-model="activeBlock.height" @blur="saveActiveSection(false)" placeholder="Ej. 200px, auto" class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                </div>
                                                            </div>
                                                            <div class="grid grid-cols-2 gap-2">
                                                                <div>
                                                                    <label class="block text-[9px] font-bold text-slate-600 mb-1">Ancho Máx (max-width)</label>
                                                                    <input type="text" x-model="activeBlock.max_width" @blur="saveActiveSection(false)" placeholder="Ej. 100%, 500px" class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                </div>
                                                                <div>
                                                                    <label class="block text-[9px] font-bold text-slate-600 mb-1">Proporción (Aspect Ratio)</label>
                                                                    <select x-model="activeBlock.aspect_ratio" @change="saveActiveSection(false)" class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                        <option value="auto">Auto</option>
                                                                        <option value="1 / 1">1:1 (Cuadrado)</option>
                                                                        <option value="16 / 9">16:9 (Video)</option>
                                                                        <option value="4 / 3">4:3 (Foto)</option>
                                                                        <option value="3 / 2">3:2 (Clásico)</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Ajuste y Posición -->
                                                        <template x-if="['image', 'store_logo'].includes(activeBlock.type)">
                                                            <div>
                                                                <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-800 mb-2">Ajuste y Posición</label>
                                                                <div class="grid grid-cols-2 gap-2">
                                                                    <div>
                                                                        <label class="block text-[9px] font-bold text-slate-600 mb-1">Ajuste (Object Fit)</label>
                                                                        <select x-model="activeBlock.object_fit" @change="saveActiveSection(false)" class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                            <option value="cover">Cover (Llenar)</option>
                                                                            <option value="contain">Contain (Adaptar)</option>
                                                                            <option value="fill">Fill (Estirar)</option>
                                                                            <option value="none">None (Real)</option>
                                                                        </select>
                                                                    </div>
                                                                    <div>
                                                                        <label class="block text-[9px] font-bold text-slate-600 mb-1">Posición (Object Position)</label>
                                                                        <input type="text" x-model="activeBlock.object_position" @blur="saveActiveSection(false)" placeholder="Ej. center, top center" class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </template>

                                                        <!-- Diseño y Caja -->
                                                        <div>
                                                            <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-800 mb-2">Diseño y Caja</label>
                                                            <div class="grid grid-cols-2 gap-2">
                                                                <div>
                                                                    <label class="block text-[9px] font-bold text-slate-600 mb-1">Comportamiento (Display)</label>
                                                                    <select x-model="activeBlock.display" @change="saveActiveSection(false)" class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                        <option value="block">Bloque (block)</option>
                                                                        <option value="inline-block">En línea (inline-block)</option>
                                                                        <option value="inline">Línea (inline)</option>
                                                                    </select>
                                                                </div>
                                                                <div>
                                                                    <label class="block text-[9px] font-bold text-slate-600 mb-1">Sombra (Box Shadow)</label>
                                                                    <input type="text" x-model="activeBlock.box_shadow" @blur="saveActiveSection(false)" placeholder="Ej. 0 4px 6px rgba(0,0,0,0.1)" class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Flujo y Efectos -->
                                                        <div>
                                                            <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-800 mb-2">Flujo y Efectos</label>
                                                            <div class="grid grid-cols-2 gap-2 mb-2">
                                                                <div>
                                                                    <label class="block text-[9px] font-bold text-slate-600 mb-1">Flotar (Float)</label>
                                                                    <select x-model="activeBlock.float" @change="saveActiveSection(false)" class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                        <option value="none">Ninguno</option>
                                                                        <option value="left">Izquierda</option>
                                                                        <option value="right">Derecha</option>
                                                                    </select>
                                                                </div>
                                                                <div>
                                                                    <label class="block text-[9px] font-bold text-slate-600 mb-1">Opacidad (0 a 1)</label>
                                                                    <input type="number" step="0.1" min="0" max="1" x-model="activeBlock.opacity" @blur="saveActiveSection(false)" placeholder="Ej. 1" class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <label class="block text-[9px] font-bold text-slate-600 mb-1">Filtro CSS (Filter)</label>
                                                                <input type="text" x-model="activeBlock.filter" @blur="saveActiveSection(false)" placeholder="Ej. grayscale(100%), blur(5px)" class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                                
                                                <!-- URL -->
                                                <template x-if="activeBlock.url !== undefined">
                                                    <div>
                                                        <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-800 mb-1.5">Link (URL)</label>
                                                        <input type="text" x-model="activeBlock.url" @blur="saveActiveSection(false)" 
                                                               class="w-full px-3 py-2 text-sm text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                    </div>
                                                </template>

                                                <!-- Tag (Titles) -->
                                                <template x-if="activeBlock.tag !== undefined">
                                                    <div>
                                                        <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-800 mb-1.5">Tipo de Título</label>
                                                        <select x-model="activeBlock.tag" @change="saveActiveSection(false)" 
                                                                class="w-full px-3 py-2 text-sm text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                            <option value="h1">Título 1 (H1)</option>
                                                            <option value="h2">Título 2 (H2)</option>
                                                            <option value="h3">Título 3 (H3)</option>
                                                            <option value="h4">Título 4 (H4)</option>
                                                            <option value="h5">Título 5 (H5)</option>
                                                            <option value="h6">Título 6 (H6)</option>
                                                        </select>
                                                    </div>
                                                </template>
                                                
                                                <!-- Colors row -->
                                                <div class="grid grid-cols-2 gap-2">
                                                    <template x-if="activeBlock.color !== undefined">
                                                        <div>
                                                            <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-800 mb-1.5">Color Texto</label>
                                                            <input type="color" x-model="activeBlock.color" @change="saveActiveSection(false)" 
                                                                   class="w-full h-9 p-0.5 border border-gray-300 rounded cursor-pointer bg-white">
                                                        </div>
                                                    </template>
                                                    <template x-if="activeBlock.text_color !== undefined && activeBlock.color === undefined">
                                                        <div>
                                                            <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-800 mb-1.5">Color Texto</label>
                                                            <input type="color" x-model="activeBlock.text_color" @change="saveActiveSection(false)" 
                                                                   class="w-full h-9 p-0.5 border border-gray-300 rounded cursor-pointer bg-white">
                                                        </div>
                                                    </template>
                                                    <template x-if="activeBlock.background_color !== undefined">
                                                        <div>
                                                            <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-800 mb-1.5">Fondo</label>
                                                            <input type="color" x-model="activeBlock.background_color" @change="saveActiveSection(false)" 
                                                                   class="w-full h-9 p-0.5 border border-gray-300 rounded cursor-pointer bg-white">
                                                        </div>
                                                    </template>
                                                </div>
                                                
                                                <!-- Dimensiones y Espaciado -->
                                                <div>
                                                    <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-800 mb-2">Dimensiones y Espaciado</label>
                                                    
                                                    <div class="grid grid-cols-2 gap-2 mb-2">
                                                        <div>
                                                            <label class="block text-[9px] font-bold text-slate-600 mb-1">Padding X (px)</label>
                                                            <input type="number" x-model="activeBlock.padding_x" @blur="saveActiveSection(false)" 
                                                                   class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                        </div>
                                                        <div>
                                                            <label class="block text-[9px] font-bold text-slate-600 mb-1">Padding Y (px)</label>
                                                            <input type="number" x-model="activeBlock.padding_y" @blur="saveActiveSection(false)" 
                                                                   class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                        </div>
                                                    </div>

                                                    <div class="grid grid-cols-2 gap-2 mb-2">
                                                        <div>
                                                            <label class="block text-[9px] font-bold text-slate-600 mb-1">Margen Superior (px)</label>
                                                            <input type="number" x-model="activeBlock.margin_top" @blur="saveActiveSection(false)" placeholder="Ej. 10"
                                                                   class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary font-medium">
                                                        </div>
                                                        <div>
                                                            <label class="block text-[9px] font-bold text-slate-600 mb-1">Margen Inferior (px)</label>
                                                            <input type="number" x-model="activeBlock.margin_bottom" @blur="saveActiveSection(false)" placeholder="Ej. 10"
                                                                   class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary font-medium">
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <div>
                                                            <label class="block text-[9px] font-bold text-slate-600 mb-1">Alto Mínimo (px)</label>
                                                            <input type="number" x-model="activeBlock.min_height" @blur="saveActiveSection(false)" placeholder="Ej. 200"
                                                                   class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                        </div>
                                                        <template x-if="['container', 'card', 'carousel'].includes(activeBlock.type)">
                                                            <div>
                                                                <label class="block text-[9px] font-bold text-slate-600 mb-1">Gap Interno (px)</label>
                                                                <input type="number" x-model="activeBlock.gap" @blur="saveActiveSection(false)" placeholder="Ej. 16"
                                                                       class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                            </div>
                                                        </template>
                                                    </div>
                                                    
                                                    <template x-if="['container', 'carousel'].includes(activeBlock.type)">
                                                        <div class="mt-3 flex items-center mb-4">
                                                            <input type="checkbox" id="fullWidthToggle" x-model="activeBlock.full_width" @change="saveActiveSection(false)" class="w-3 h-3 text-primary bg-gray-100 border-gray-300 rounded focus:ring-primary focus:ring-1">
                                                            <label for="fullWidthToggle" class="ml-2 text-[10px] font-bold text-slate-700 cursor-pointer">Ancho Total (Pantalla completa)</label>
                                                        </div>
                                                    </template>
                                                    
                                                    <template x-if="['container', 'carousel'].includes(activeBlock.type)">
                                                        <div class="p-3 bg-indigo-50/50 rounded-lg border border-indigo-100">
                                                            <label class="block text-[10px] font-extrabold uppercase tracking-wider text-indigo-800 mb-2">Imágenes Internas (Masivo)</label>
                                                            <p class="text-[9px] text-indigo-600/80 mb-2 leading-tight">Aplica este tamaño a todos los logos o imágenes dentro de este bloque automáticamente.</p>
                                                            <div class="grid grid-cols-2 gap-2 mb-2">
                                                                <div>
                                                                    <label class="block text-[9px] font-bold text-slate-600 mb-1">Ancho Fijo (Ej. 100px)</label>
                                                                    <input type="text" x-model="activeBlock.child_image_width" @blur="saveActiveSection(false)" placeholder="auto, 100px, 50%..."
                                                                           class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                </div>
                                                                <div>
                                                                    <label class="block text-[9px] font-bold text-slate-600 mb-1">Alto Fijo (Ej. 100px)</label>
                                                                    <input type="text" x-model="activeBlock.child_image_height" @blur="saveActiveSection(false)" placeholder="auto, 100px..."
                                                                           class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <label class="block text-[9px] font-bold text-slate-600 mb-1">Ajuste (Object Fit)</label>
                                                                <select x-model="activeBlock.child_image_fit" @change="saveActiveSection(false)" class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                    <option value="">Por defecto</option>
                                                                    <option value="contain">Contain (Encajar todo)</option>
                                                                    <option value="cover">Cover (Llenar y recortar)</option>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <!-- Tipografía y Texto -->
                                                        <template x-if="['button', 'paragraph', 'title'].includes(activeBlock.type)">
                                                            <div>
                                                                <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-800 mb-2">Tipografía y Texto</label>
                                                                <div class="grid grid-cols-2 gap-2 mb-2">
                                                                    <div>
                                                                        <label class="block text-[9px] font-bold text-slate-600 mb-1">Familia Fuente</label>
                                                                        <input type="text" x-model="activeBlock.font_family" @blur="saveActiveSection(false)" placeholder="Ej. Inter, sans-serif" class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                    </div>
                                                                    <div>
                                                                        <label class="block text-[9px] font-bold text-slate-600 mb-1">Tamaño (Size)</label>
                                                                        <input type="text" x-model="activeBlock.font_size" @blur="saveActiveSection(false)" placeholder="Ej. 16px, 1.25rem" class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                    </div>
                                                                </div>
                                                                <div class="grid grid-cols-2 gap-2 mb-2">
                                                                    <div>
                                                                        <label class="block text-[9px] font-bold text-slate-600 mb-1">Grosor (Weight)</label>
                                                                        <select x-model="activeBlock.font_weight" @change="saveActiveSection(false)" class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                            <option value="">Normal</option>
                                                                            <option value="300">Ligero (300)</option>
                                                                            <option value="400">Regular (400)</option>
                                                                            <option value="500">Medio (500)</option>
                                                                            <option value="600">Semibold (600)</option>
                                                                            <option value="700">Negrita (700)</option>
                                                                            <option value="800">Extrabold (800)</option>
                                                                        </select>
                                                                    </div>
                                                                    <div>
                                                                        <label class="block text-[9px] font-bold text-slate-600 mb-1">Estilo (Style)</label>
                                                                        <select x-model="activeBlock.font_style" @change="saveActiveSection(false)" class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                            <option value="normal">Normal</option>
                                                                            <option value="italic">Cursiva (Italic)</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="grid grid-cols-2 gap-2">
                                                                    <div>
                                                                        <label class="block text-[9px] font-bold text-slate-600 mb-1">Transformar</label>
                                                                        <select x-model="activeBlock.text_transform" @change="saveActiveSection(false)" class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                            <option value="">Ninguno</option>
                                                                            <option value="uppercase">MAYÚSCULAS</option>
                                                                            <option value="lowercase">minúsculas</option>
                                                                            <option value="capitalize">Capitalizado</option>
                                                                        </select>
                                                                    </div>
                                                                    <div>
                                                                        <label class="block text-[9px] font-bold text-slate-600 mb-1">Espaciado Letras</label>
                                                                        <input type="text" x-model="activeBlock.letter_spacing" @blur="saveActiveSection(false)" placeholder="Ej. 1px, 0.05em" class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </template>

                                                    </div>
                                                </template>
                                                
                                                <!-- Borders for specific types -->
                                                <template x-if="['button', 'card', 'container', 'store_navbar'].includes(activeBlock.type)">
                                                    <div>
                                                        <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-800 mb-2">Bordes</label>
                                                        <div class="grid grid-cols-3 gap-2">
                                                            <div>
                                                                <label class="block text-[9px] font-bold text-slate-600 mb-1">Grosor</label>
                                                                <input type="number" x-model="activeBlock.border_width" @blur="saveActiveSection(false)" 
                                                                       class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                            </div>
                                                            <div>
                                                                <label class="block text-[9px] font-bold text-slate-600 mb-1">Radio</label>
                                                                <input type="number" x-model="activeBlock.border_radius" @blur="saveActiveSection(false)" 
                                                                       class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                            </div>
                                                            <div>
                                                                <label class="block text-[9px] font-bold text-slate-600 mb-1">Color</label>
                                                                <input type="color" x-model="activeBlock.border_color" @change="saveActiveSection(false)" 
                                                                       class="w-full h-[30px] p-0 border-0 rounded cursor-pointer">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                                
                                                <!-- BG Image -->
                                                <template x-if="['card', 'container', 'hero', 'carousel'].includes(activeBlock.type)">
                                                    <div>
                                                        <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-800 mb-1.5">Imagen de Fondo</label>
                                                        <div class="flex gap-2">
                                                            <div class="flex-1 relative">
                                                                <input type="text" x-model="activeBlock.bg_image" @blur="saveActiveSection(false)" placeholder="URL de la imagen..." 
                                                                       class="w-full px-3 py-2 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary pr-8">
                                                                <template x-if="activeBlock.bg_image">
                                                                    <button @click="activeBlock.bg_image=''; saveActiveSection(false)" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500">
                                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                                    </button>
                                                                </template>
                                                            </div>
                                                            <label class="cursor-pointer shrink-0 flex items-center justify-center w-9 h-9 bg-gray-100 border border-gray-300 rounded hover:bg-gray-200 transition-colors relative" title="Subir Imagen">
                                                                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                                                <input type="file" accept="image/*" class="hidden" @change="uploadBlockImage($event, 'bg_image')" :disabled="isUploadingImage">
                                                                <template x-if="isUploadingImage">
                                                                    <div class="absolute inset-0 bg-white/80 rounded flex items-center justify-center">
                                                                        <svg class="animate-spin h-3 w-3 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                                    </div>
                                                                </template>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </template>
                                                
                                                <!-- Flex / Grid Item Alignment -->
                                                 <div class="pt-3 border-t border-gray-200">
                                                     <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-800 mb-2">Alineación en Contenedor (Flex/Grid)</label>
                                                     <div class="space-y-3">
                                                         <!-- Flex Box settings -->
                                                         <div class="p-2.5 bg-slate-50 border border-slate-100 rounded-lg">
                                                             <span class="text-[9px] font-extrabold text-slate-700 uppercase tracking-wider block mb-2">Propiedades Flex (Eje Fila/Columna)</span>
                                                             <div class="grid grid-cols-3 gap-1.5 mb-2">
                                                                 <div>
                                                                     <label class="block text-[8px] font-bold text-slate-500 mb-0.5">Crecer (Grow)</label>
                                                                     <input type="number" x-model="activeBlock.flex_grow" @blur="saveActiveSection(false)" placeholder="0" class="w-full px-1.5 py-1 text-[11px] text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                 </div>
                                                                 <div>
                                                                     <label class="block text-[8px] font-bold text-slate-500 mb-0.5">Encoger (Shrink)</label>
                                                                     <input type="number" x-model="activeBlock.flex_shrink" @blur="saveActiveSection(false)" placeholder="1" class="w-full px-1.5 py-1 text-[11px] text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                 </div>
                                                                 <div>
                                                                     <label class="block text-[8px] font-bold text-slate-500 mb-0.5">Orden (Order)</label>
                                                                     <input type="number" x-model="activeBlock.order" @blur="saveActiveSection(false)" placeholder="0" class="w-full px-1.5 py-1 text-[11px] text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                                 </div>
                                                             </div>
                                                             <div class="grid grid-cols-2 gap-1.5">
                                                                 <div>
                                                                     <label class="block text-[8px] font-bold text-slate-500 mb-0.5">Base (Flex Basis)</label>
                                                                     <input type="text" x-model="activeBlock.flex_basis" @blur="saveActiveSection(false)" placeholder="Ej. auto, 200px" class="w-full px-1.5 py-1 text-[11px] text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary font-medium">
                                                                 </div>
                                                                 <div>
                                                                     <label class="block text-[8px] font-bold text-slate-500 mb-0.5">Alinearse (Self)</label>
                                                                     <select x-model="activeBlock.align_self" @change="saveActiveSection(false)" class="w-full px-1.5 py-1 text-[11px] text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary bg-white">
                                                                         <option value="">Auto</option>
                                                                         <option value="flex-start">Start (Arriba/Izq)</option>
                                                                         <option value="center">Center (Centro)</option>
                                                                         <option value="flex-end">End (Abajo/Der)</option>
                                                                         <option value="stretch">Stretch (Estirar)</option>
                                                                     </select>
                                                                 </div>
                                                             </div>
                                                         </div>

                                                         <!-- Grid settings -->
                                                         <div class="p-2.5 bg-slate-50 border border-slate-100 rounded-lg">
                                                             <span class="text-[9px] font-extrabold text-slate-700 uppercase tracking-wider block mb-2">Propiedades Grid (Rejilla)</span>
                                                             <div class="grid grid-cols-2 gap-1.5 mb-2">
                                                                 <div>
                                                                     <label class="block text-[8px] font-bold text-slate-500 mb-0.5">Columna (Col Span)</label>
                                                                     <input type="text" x-model="activeBlock.grid_column" @blur="saveActiveSection(false)" placeholder="Ej. 1 / 3, span 2" class="w-full px-1.5 py-1 text-[11px] text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary font-medium">
                                                                 </div>
                                                                 <div>
                                                                     <label class="block text-[8px] font-bold text-slate-500 mb-0.5">Fila (Row Span)</label>
                                                                     <input type="text" x-model="activeBlock.grid_row" @blur="saveActiveSection(false)" placeholder="Ej. span 2" class="w-full px-1.5 py-1 text-[11px] text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary font-medium">
                                                                 </div>
                                                             </div>
                                                             <div class="grid grid-cols-2 gap-1.5">
                                                                 <div>
                                                                     <label class="block text-[8px] font-bold text-slate-500 mb-0.5">Justificar (Self)</label>
                                                                     <select x-model="activeBlock.justify_self" @change="saveActiveSection(false)" class="w-full px-1.5 py-1 text-[11px] text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary bg-white">
                                                                         <option value="">Auto</option>
                                                                         <option value="start">Start</option>
                                                                         <option value="center">Center</option>
                                                                         <option value="end">End</option>
                                                                         <option value="stretch">Stretch</option>
                                                                     </select>
                                                                 </div>
                                                                 <div>
                                                                     <label class="block text-[8px] font-bold text-slate-500 mb-0.5">Colocar (Place Self)</label>
                                                                     <input type="text" x-model="activeBlock.place_self" @blur="saveActiveSection(false)" placeholder="Ej. center center" class="w-full px-1.5 py-1 text-[11px] text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary font-medium">
                                                                 </div>
                                                             </div>
                                                         </div>
                                                     </div>
                                                 </div>

                                                <!-- Layers / Position -->
                                                <div class="pt-3 border-t border-gray-200">
                                                    <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-800 mb-2">Diseño Libre (Capas)</label>
                                                    <div class="flex items-center gap-2 mb-2">
                                                        <input type="checkbox" x-model="activeBlock.is_absolute" @change="saveActiveSection(false)" 
                                                               id="cb-is-absolute" class="form-checkbox rounded text-primary border-gray-300">
                                                        <label for="cb-is-absolute" class="text-xs text-slate-800 font-bold cursor-pointer">Activar Posición Libre</label>
                                                    </div>
                                                    
                                                    <template x-if="activeBlock.is_absolute">
                                                        <div class="grid grid-cols-3 gap-2">
                                                            <div>
                                                                <label class="block text-[9px] font-bold text-slate-600 mb-1">X (px)</label>
                                                                <input type="number" x-model="activeBlock.x_pos" @blur="saveActiveSection(false)" 
                                                                       class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                            </div>
                                                            <div>
                                                                <label class="block text-[9px] font-bold text-slate-600 mb-1">Y (px)</label>
                                                                <input type="number" x-model="activeBlock.y_pos" @blur="saveActiveSection(false)" 
                                                                       class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                            </div>
                                                            <div>
                                                                <label class="block text-[9px] font-bold text-slate-600 mb-1">Z-Index</label>
                                                                <input type="number" x-model="activeBlock.z_index" @blur="saveActiveSection(false)" 
                                                                       class="w-full px-2 py-1.5 text-xs text-gray-900 border border-gray-300 rounded focus:ring-1 focus:ring-primary">
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                                
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            
                            </div> <!-- /Cierre del div raíz de template x-if=activeSection -->
                    </template>
                </div>
            </div>
        </aside>

        <!-- Preview Iframe (Derecha) -->
        <main class="flex-1 bg-slate-100 p-4 lg:p-6 flex items-center justify-center relative overflow-hidden">
            <!-- Toolbar de Formato (Word-like) -->
            <div x-show="isTextBlockActive" x-transition.opacity 
                 class="absolute top-8 left-1/2 -translate-x-1/2 z-[60] bg-white rounded-xl shadow-xl border border-gray-200 p-1.5 flex items-center gap-1"
                 style="display: none;">
                 
                <template x-if="activeBlock?.type === 'title'">
                    <div class="flex items-center border-r border-gray-200 pr-1 mr-1">
                        <select x-model="activeBlock.tag" @change="saveActiveSection()" class="text-xs border-0 py-1 pl-2 pr-6 bg-transparent font-bold text-slate-800 cursor-pointer focus:ring-0">
                            <option value="h1">Título 1 (H1)</option>
                            <option value="h2">Título 2 (H2)</option>
                            <option value="h3">Título 3 (H3)</option>
                            <option value="h4">Título 4 (H4)</option>
                            <option value="h5">Título 5 (H5)</option>
                            <option value="h6">Título 6 (H6)</option>
                        </select>
                    </div>
                </template>

                <!-- Negrita -->
                <button type="button" @click="toggleBlockFormat('bold')" 
                        class="p-1.5 rounded hover:bg-gray-100 transition-colors"
                        :class="activeBlock?.is_bold ? 'bg-gray-200 text-black font-black' : 'text-slate-700'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"></path></svg>
                </button>
                
                <!-- Cursiva -->
                <button type="button" @click="toggleBlockFormat('italic')" 
                        class="p-1.5 rounded hover:bg-gray-100 transition-colors"
                        :class="activeBlock?.is_italic ? 'bg-gray-200 text-black italic font-serif' : 'text-slate-700'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4-4-4-4M6 16l-4 4 4 4"></path></svg>
                </button>

                <!-- Separador -->
                <div class="w-px h-5 bg-gray-200 mx-1"></div>

                <!-- Alineación -->
                <button type="button" @click="setBlockAlign('left')" class="p-1.5 rounded hover:bg-gray-100 transition-colors" :class="activeBlock?.text_align === 'left' ? 'bg-gray-200 text-black' : 'text-slate-700'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h16"></path></svg>
                </button>
                <button type="button" @click="setBlockAlign('center')" class="p-1.5 rounded hover:bg-gray-100 transition-colors" :class="activeBlock?.text_align === 'center' ? 'bg-gray-200 text-black' : 'text-slate-700'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M7 12h10M4 18h16"></path></svg>
                </button>
                <button type="button" @click="setBlockAlign('right')" class="p-1.5 rounded hover:bg-gray-100 transition-colors" :class="activeBlock?.text_align === 'right' ? 'bg-gray-200 text-black' : 'text-slate-700'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M10 12h10M4 18h16"></path></svg>
                </button>

                <!-- Separador -->
                <div class="w-px h-5 bg-gray-200 mx-1"></div>

                <!-- Color -->
                <div class="relative flex items-center justify-center p-1 rounded hover:bg-gray-100 cursor-pointer overflow-hidden group">
                    <input type="color" 
                           :value="activeBlock?.color || activeBlock?.text_color || '#000000'" 
                           @input="let val = $event.target.value; if(activeBlock.text_color !== undefined) { activeBlock.text_color = val; } else { activeBlock.color = val; }"
                           @change="saveActiveSection()" 
                           class="opacity-0 absolute inset-0 w-full h-full cursor-pointer">
                    <div class="w-5 h-5 rounded-sm shadow-inner border border-gray-300 pointer-events-none" :style="'background-color: ' + (activeBlock?.color || activeBlock?.text_color || '#000000')"></div>
                </div>
            </div>

            <div class="w-full h-full max-w-[1280px] bg-white rounded-xl shadow-[0_20px_50px_-12px_rgba(0,0,0,0.15)] overflow-hidden border border-gray-200 flex flex-col relative transition-all">
                <!-- Browser mockup top -->
                <div class="h-8 bg-gray-50 border-b border-gray-200 flex items-center px-4 gap-2 shrink-0">
                    <div class="w-2.5 h-2.5 rounded-full bg-red-400"></div>
                    <div class="w-2.5 h-2.5 rounded-full bg-yellow-400"></div>
                    <div class="w-2.5 h-2.5 rounded-full bg-green-400"></div>
                    <div class="ml-4 flex-1 flex justify-center">
                        <div class="bg-white text-[10px] text-gray-400 px-4 py-0.5 rounded-md border border-gray-200 w-1/2 text-center truncate shadow-sm">
                            {{ route('store.show', $store->slug) }}
                        </div>
                    </div>
                </div>
                <!-- The Iframe -->
                <iframe id="preview-iframe" src="{{ route('store.show', $store->slug) }}?editor=1" class="w-full flex-1 border-0" title="Store Preview"></iframe>
                
                <!-- Floating In-line Toolbar -->
                <div x-show="showToolbar" x-transition.opacity
                     class="fixed z-[100] bg-white border border-gray-200 shadow-xl rounded-lg flex items-center p-1 gap-1 -translate-x-1/2 -translate-y-full mb-3 pointer-events-auto"
                     :style="`left: ${toolbarX}px; top: ${toolbarY}px;`"
                     style="display: none;"
                     @click.away="showToolbar = false">
                     
                     <template x-if="toolbarType === 'title' || toolbarType === 'paragraph' || toolbarType === 'text'">
                         <div class="flex items-center gap-1 border-r border-gray-200 pr-1 mr-1">
                             <button @click="toggleBlockFormat('bold')" class="p-1.5 rounded hover:bg-gray-100 text-slate-700 transition-colors" title="Negrita">
                                 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"></path></svg>
                             </button>
                             <button @click="toggleBlockFormat('italic')" class="p-1.5 rounded hover:bg-gray-100 text-slate-700 transition-colors" title="Cursiva">
                                 <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                             </button>
                         </div>
                     </template>
                     
                     <div class="flex items-center gap-1 border-r border-gray-200 pr-1 mr-1">
                         <button @click="setBlockAlignment('left')" class="p-1.5 rounded hover:bg-gray-100 text-slate-700 transition-colors" title="Alinear Izquierda">
                             <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h16"></path></svg>
                         </button>
                         <button @click="setBlockAlignment('center')" class="p-1.5 rounded hover:bg-gray-100 text-slate-700 transition-colors" title="Centrar">
                             <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M7 12h10M4 18h16"></path></svg>
                         </button>
                         <button @click="setBlockAlignment('right')" class="p-1.5 rounded hover:bg-gray-100 text-slate-700 transition-colors" title="Alinear Derecha">
                             <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M10 12h10M4 18h16"></path></svg>
                         </button>
                     </div>

                     <button @click="deleteActiveBlock()" class="p-1.5 rounded hover:bg-red-50 text-red-500 transition-colors" title="Eliminar">
                         <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                     </button>
                </div>
                
                <!-- Block Inserter Popup -->
            <div x-show="showInserter" @click.away="showInserter = false"
                 class="fixed z-[100] bg-white rounded shadow-2xl border border-gray-200 w-[320px] overflow-hidden"
                 :style="'left: ' + inserterX + 'px; top: ' + inserterY + 'px; transform: translate(-50%, 10px);'"
                 style="display: none;">
                
                <!-- Search bar -->
                <div class="p-4 pb-2">
                    <div class="relative">
                        <svg class="w-5 h-5 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <input type="text" x-model="searchBlockQuery" placeholder="Buscar" class="w-full pl-10 pr-3 py-2 border border-blue-600 rounded focus:outline-none focus:ring-1 focus:ring-blue-600 text-sm">
                        <button x-show="searchBlockQuery" @click="searchBlockQuery = ''" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            ✕
                        </button>
                    </div>
                </div>

                <!-- Icons Grid -->
                <div class="p-4 grid grid-cols-3 gap-y-6 gap-x-2 max-h-[300px] overflow-y-auto">
                    <template x-for="block in popoverFilteredBlocks" :key="block.id">
                        <button @click="addBlock(block.type, block.overrides); showInserter = false;" class="flex flex-col items-center justify-start text-center hover:bg-gray-50 rounded transition p-2 group">
                            <div x-html="block.icon"></div>
                            <span class="text-[11px] text-slate-800 leading-tight" x-text="block.name"></span>
                        </button>
                    </template>
                </div>
                
                <!-- Bottom button -->
                <button x-show="!showAllBlocks && !searchBlockQuery" @click="showAllBlocks = true" class="w-full bg-[#1e1e1e] hover:bg-black text-white text-sm py-3 transition-colors font-medium">
                    Ver todos
                </button>
            </div>

                
                <!-- Loading Overlay -->
                <div x-show="isSaving" style="display: none;" class="absolute inset-0 bg-white/40 backdrop-blur-[2px] z-50 flex items-center justify-center transition-opacity">
                    <div class="bg-gray-900 px-5 py-3 rounded-xl shadow-2xl text-xs font-bold text-white flex items-center gap-3">
                        <svg class="animate-spin h-5 w-5 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Sincronizando cambios...
                    </div>
                </div>
            </div>
        </main>
        
        <!-- Sidebar Librería de Bloques (Derecha) -->
        <aside x-show="activeSection" style="display: none;" class="w-[280px] bg-white border-l border-gray-200 flex flex-col shrink-0 relative z-20">
            <div class="p-4 border-b border-gray-100 bg-gray-50/50">
                <h2 class="font-bold text-gray-800">Librería de Bloques</h2>
                <p class="text-[11px] text-slate-600 mt-1 font-semibold">Arrastra o haz clic para agregar</p>
                <div class="mt-3 relative">
                    <svg class="w-4 h-4 absolute left-2.5 top-1/2 transform -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" x-model="searchBlockQuery" placeholder="Buscar bloque..." class="w-full pl-8 pr-3 py-1.5 border border-gray-200 rounded-md focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-xs">
                </div>
            </div>
            
            <div class="flex-1 overflow-y-auto p-3">
                <div class="grid grid-cols-2 gap-2">
                    <template x-for="block in filteredBlocks" :key="block.id">
                        <button type="button" draggable="true" 
                                @dragstart="e => { e.dataTransfer.setData('block_type', block.type); e.dataTransfer.setData('block_overrides', JSON.stringify(block.overrides || {})); }" 
                                @click="addBlock(block.type, block.overrides)" 
                                class="flex items-center gap-2 p-2 border border-gray-100 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition-colors text-slate-700 bg-white shadow-sm cursor-grab active:cursor-grabbing text-left group">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0 opacity-70 group-hover:opacity-100 group-hover:text-indigo-600 transition-opacity" x-html="block.icon"></div>
                            <span class="text-[10px] font-semibold leading-tight flex-1" x-text="block.name"></span>
                        </button>
                    </template>
                </div>
                
                <div x-show="filteredBlocks.length === 0" class="text-center py-8 text-gray-400 text-xs">
                    No se encontraron bloques
                </div>
            </div>
        </aside>
    </div>

    <!-- Modal Agregar Sección -->
    <div x-show="showAddModal" class="fixed inset-0 z-[100] overflow-y-auto" style="display: none;" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen p-4 text-center sm:p-0">
            <div x-show="showAddModal" x-transition.opacity class="fixed inset-0 bg-black/20 backdrop-blur-md transition-opacity" @click="showAddModal = false"></div>
            
            <div x-show="showAddModal" 
                 x-transition:enter="ease-out duration-300" 
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave="ease-in duration-200" 
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
                 class="relative inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full z-[101]">
                
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-center mb-5">
                        <h3 class="text-lg leading-6 font-bold text-gray-900" id="modal-title">Agregar nueva sección</h3>
                        <button @click="showAddModal = false" class="text-gray-400 hover:text-slate-600">
                            <span class="sr-only">Cerrar</span>
                            ✕
                        </button>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <template x-for="type in availableTypes" :key="type.id">
                            <button @click="addSection(type.id)" 
                                    class="flex flex-col items-center justify-center p-4 border border-gray-200 rounded-xl hover:border-primary hover:bg-blue-50 transition-colors group">
                                <span class="text-3xl mb-2 group-hover:scale-110 transition-transform" x-text="type.icon"></span>
                                <span class="text-sm font-bold text-slate-800" x-text="type.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('storeBuilder', () => ({
                sections: @json($sections),
                activeSection: null,
                activeSectionId: null,
                activeBlockPath: null,
                activeBlock: {},
                sidebarTab: 'capas',
                getBlockByPath(path) {
                    if (path === null || path === undefined) return {};
                    const keys = String(path).split('.');
                    let current = this.activeSection.data;
                    for (let i = 0; i < keys.length; i++) {
                        if (keys[i] === 'blocks') {
                            if (!current.blocks) current.blocks = [];
                            current = current.blocks;
                        } else {
                            current = current[parseInt(keys[i])];
                        }
                        if (!current) return {};
                    }
                    return current;
                },
                expandParents(path) {
                    if (!path) return;
                    const keys = String(path).split('.');
                    let currentPath = '';
                    for (let i = 0; i < keys.length - 1; i++) {
                        if (keys[i] === 'blocks') {
                            currentPath += (currentPath ? '.' : '') + 'blocks';
                        } else {
                            currentPath += '.' + keys[i];
                            const block = this.getBlockByPath(currentPath);
                            if (block && block.blocks) {
                                block.isCollapsed = false;
                            }
                        }
                    }
                },
                activeBlockIndex: null, // Keep for backward compatibility with root dragging for now
                showInserter: false,
                inserterX: 0,
                inserterY: 0,
                inserterPath: null,
                showAddModal: false,
                isSaving: false,
                isPublishing: false,
                isUploadingImage: false,
                searchBlockQuery: '',
                showAllBlocks: false,
                availableBlocks: [
                    { id: 'paragraph', name: 'Párrafo', icon: '<svg class="w-5 h-5 mx-auto mb-2 text-gray-800" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4v16h2V6h2v14h2V6h4V4H10zm-2 0H5v2h3v14h2V4z"/></svg>', keywords: 'texto parrafo p paragraph', type: 'paragraph', overrides: {} },
                    { id: 'encabezado', name: 'Encabezado', icon: '<svg class="w-5 h-5 mx-auto mb-2 text-gray-800" fill="currentColor" viewBox="0 0 24 24"><path d="M17 3H7c-1.1 0-2 .9-2 2v16l7-3 7 3V5c0-1.1-.9-2-2-2z"/></svg>', keywords: 'titulo general title header', type: 'title', overrides: { tag: 'h2' } },
                    { id: 'h2', name: 'H2 Encabezado 2', icon: '<div class="font-bold text-gray-800 text-lg mb-2 text-center">H2</div>', keywords: 'titulo h2 encabezado medio', type: 'title', overrides: { tag: 'h2' } },
                    { id: 'h1', name: 'H1 Encabezado 1', icon: '<div class="font-bold text-gray-800 text-lg mb-2 text-center">H1</div>', keywords: 'titulo h1 encabezado grande', type: 'title', overrides: { tag: 'h1' } },
                    { id: 'h3', name: 'H3 Encabezado 3', icon: '<div class="font-bold text-gray-800 text-lg mb-2 text-center">H3</div>', keywords: 'titulo h3 encabezado pequeño', type: 'title', overrides: { tag: 'h3' } },
                    { id: 'h4', name: 'H4 Encabezado 4', icon: '<div class="font-bold text-gray-800 text-lg mb-2 text-center">H4</div>', keywords: 'titulo h4 encabezado pequeño', type: 'title', overrides: { tag: 'h4' } },
                    { id: 'h5', name: 'H5 Encabezado 5', icon: '<div class="font-bold text-gray-800 text-lg mb-2 text-center">H5</div>', keywords: 'titulo h5 encabezado pequeño', type: 'title', overrides: { tag: 'h5' } },
                    { id: 'h6', name: 'H6 Encabezado 6', icon: '<div class="font-bold text-gray-800 text-lg mb-2 text-center">H6</div>', keywords: 'titulo h6 encabezado pequeño', type: 'title', overrides: { tag: 'h6' } },
                    { id: 'encabezado_primordial', name: 'Encabezado primordial', icon: '<svg class="w-5 h-5 mx-auto mb-2 text-purple-600" fill="currentColor" viewBox="0 0 24 24"><path d="M4 6h16v12H4z"/></svg>', keywords: 'titulo principal gigante hero', type: 'title', overrides: { tag: 'h1', color: '#6366f1' } },
                    { id: 'toc', name: 'Tabla de contenidos', icon: '<svg class="w-5 h-5 mx-auto mb-2 text-gray-800" fill="currentColor" viewBox="0 0 24 24"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg>', keywords: 'indice tabla contenidos toc', type: 'toc', overrides: {} },
                    { id: 'container', name: 'Contenedor', icon: '<svg class="w-5 h-5 mx-auto mb-2 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>', keywords: 'contenedor fila row horizontal', type: 'container', overrides: {} },
                    { id: 'columns', name: 'Columnas', icon: '<svg class="w-5 h-5 mx-auto mb-2 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 4v16M15 4v16M4 4h16v16H4V4z"/></svg>', keywords: 'contenedor columnas columns vertical', type: 'container', overrides: { direction: 'row' } },
                    { id: 'store_navbar', name: 'Navegación', icon: '<svg class="w-5 h-5 mx-auto mb-2 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>', keywords: 'menu navegacion navbar links', type: 'store_navbar', overrides: {} },
                    { id: 'image', name: 'Imagen', icon: '<svg class="w-5 h-5 mx-auto mb-2 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>', keywords: 'foto imagen logo img', type: 'image', overrides: {} },
                    { id: 'button', name: 'Botones', icon: '<svg class="w-5 h-5 mx-auto mb-2 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="8" width="18" height="8" rx="2" stroke-width="2"/></svg>', keywords: 'boton enlace click', type: 'button', overrides: {} },
                    { id: 'card', name: 'Tarjeta', icon: '<svg class="w-5 h-5 mx-auto mb-2 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path></svg>', keywords: 'card tarjeta contenedor borde', type: 'card', overrides: {} },
                    { id: 'badge', name: 'Cinta', icon: '<svg class="w-5 h-5 mx-auto mb-2 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>', keywords: 'badge etiqueta cinta descuento', type: 'badge', overrides: {} },
                    { id: 'store_search', name: 'Buscador', icon: '<svg class="w-5 h-5 mx-auto mb-2 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>', keywords: 'buscar search lupa', type: 'store_search', overrides: {} },
                    { id: 'store_cart', name: 'Carrito', icon: '<svg class="w-5 h-5 mx-auto mb-2 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>', keywords: 'carrito compras cart', type: 'store_cart', overrides: {} },
                    { id: 'carousel', name: 'Carrusel', icon: '<svg class="w-5 h-5 mx-auto mb-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17l-5-5 5-5m6 10l5-5-5-5"></path></svg>', keywords: 'carrusel slider fotos imagenes slider', type: 'carousel', overrides: {} }
                ],
                
                get filteredBlocks() {
                    let q = this.searchBlockQuery.toLowerCase();
                    if (!q) {
                        return this.availableBlocks;
                    }
                    return this.availableBlocks.filter(b => b.name.toLowerCase().includes(q) || b.keywords.toLowerCase().includes(q));
                },
                get popoverFilteredBlocks() {
                    let q = this.searchBlockQuery.toLowerCase();
                    if (!q) {
                        return this.showAllBlocks ? this.availableBlocks : this.availableBlocks.slice(0, 6);
                    }
                    return this.availableBlocks.filter(b => b.name.toLowerCase().includes(q) || b.keywords.toLowerCase().includes(q));
                },
                availableTypes: [
                    { id: 'hero', name: 'Portada (Hero)', icon: '🖼️' },
                    { id: 'products_carousel', name: 'Carrusel Prod.', icon: '🛍️' },
                    { id: 'categories', name: 'Categorías', icon: '🗂️' },
                    { id: 'gallery', name: 'Galería', icon: '📸' },
                    { id: 'text', name: 'Texto Libre', icon: '📝' },
                    { id: 'header', name: 'Cabecera (Header)', icon: '🔝' },
                    { id: 'footer', name: 'Pie (Footer)', icon: '⬇️' }
                ],
                
                // Toolbar in-line state
                showToolbar: false,
                toolbarX: 0,
                toolbarY: 0,
                toolbarType: '',

                get sortedSections() {
                    return this.sections.slice().sort((a, b) => a.order - b.order);
                },

                get flattenedBlocks() {
                    let result = [];
                    if (!this.activeSection || !this.activeSection.data || !this.activeSection.data.blocks) return result;
                    
                    const traverse = (blocks, prefix, depth) => {
                        blocks.forEach((b, i) => {
                            let path = prefix + i;
                            
                            // Initialize collapsed state if it doesn't exist
                            if (b.isCollapsed === undefined) {
                                b.isCollapsed = false;
                            }
                            
                            // Patch missing IDs for backward compatibility
                            if (!b.id) {
                                b.id = 'block_' + Math.random().toString(36).substr(2, 9);
                            }
                            
                            result.push({ 
                                block: b, 
                                path: path, 
                                depth: depth, 
                                id: b.id,
                                type: b.type,
                                hasChildren: b.blocks && b.blocks.length > 0
                            });
                            
                            if (b.blocks && b.blocks.length > 0 && !b.isCollapsed) {
                                traverse(b.blocks, path + '.blocks.', depth + 1);
                            }
                        });
                    };
                    
                    traverse(this.activeSection.data.blocks, 'blocks.', 0);
                    return result;
                },

                get isTextBlockActive() {
                    if (!this.activeSection || !this.activeBlock || !this.activeBlock.id) return false;
                    return ['paragraph', 'title'].includes(this.activeBlock.type);
                },

                toggleBlockFormat(format) {
                    if (!this.isTextBlockActive) return;
                    const block = this.activeBlock;
                    if (format === 'bold') block.is_bold = !block.is_bold;
                    if (format === 'italic') block.is_italic = !block.is_italic;
                    if (format === 'underline') block.is_underline = !block.is_underline;
                    this.saveActiveSection();
                },

                setBlockAlign(align) {
                    if (!this.isTextBlockActive) return;
                    this.activeBlock.text_align = align;
                    this.saveActiveSection();
                },

                setBlockAlignment(align) {
                    if (!this.activeSection || !this.activeBlock || !this.activeBlock.id) return;
                    const block = this.activeBlock;
                    if (block) {
                        if (block.type === 'text' || block.type === 'paragraph' || block.type === 'title') {
                            block.text_align = align;
                        } else {
                            block.alignment = align;
                        }
                        this.saveActiveSection(false);
                    }
                },

                selectParentBlock() {
                    if (!this.activeBlockPath || !String(this.activeBlockPath).includes('.')) return;
                    
                    const keys = String(this.activeBlockPath).split('.');
                    keys.pop(); // remove index
                    keys.pop(); // remove 'blocks'
                    
                    const newPath = keys.join('.');
                    this.activeBlockPath = newPath;
                    this.activeBlock = this.getBlockByPath(newPath);
                    this.expandParents(newPath);
                    this.sidebarTab = 'propiedades';
                    this.highlightBlockInIframe(newPath);
                },

                moveActiveBlock(direction) {
                    if (!this.activeSection || this.activeBlockPath === null) return;
                    
                    const keys = String(this.activeBlockPath).split('.');
                    let current = this.activeSection.data;
                    let parentArray = current.blocks;
                    let lastIndex = 0;
                    
                    for (let i = 0; i < keys.length; i++) {
                        if (keys[i] === 'blocks') {
                            parentArray = current.blocks;
                            current = current.blocks;
                        } else {
                            lastIndex = parseInt(keys[i]);
                            current = current[lastIndex];
                        }
                    }
                    
                    const newIndex = lastIndex + direction;
                    if (newIndex < 0 || newIndex >= parentArray.length) return; // out of bounds
                    
                    // Swap elements
                    const temp = parentArray[lastIndex];
                    parentArray[lastIndex] = parentArray[newIndex];
                    parentArray[newIndex] = temp;
                    
                    // Update activeBlockPath
                    keys[keys.length - 1] = newIndex;
                    const newPath = keys.join('.');
                    this.activeBlockPath = newPath;
                    this.activeBlock = this.getBlockByPath(newPath);
                    
                    this.saveActiveSection(false);
                    
                    // Re-highlight after a short delay so DOM can update
                    setTimeout(() => {
                        this.highlightBlockInIframe(newPath);
                    }, 100);
                },

                deleteActiveBlock() {
                    if (!this.activeSection || this.activeBlockPath === null) return;
                    if (confirm('¿Estás seguro de eliminar este elemento?')) {
                        let current = this.activeSection.data;
                        const keys = String(this.activeBlockPath).split('.');
                        let parentArray = current.blocks;
                        let lastIndex = 0;
                        for (let i = 0; i < keys.length; i++) {
                            if (keys[i] === 'blocks') {
                                parentArray = current.blocks;
                                current = current.blocks;
                            } else {
                                lastIndex = parseInt(keys[i]);
                                current = current[lastIndex];
                            }
                        }
                        
                        parentArray.splice(lastIndex, 1);
                        
                        this.showToolbar = false;
                        this.activeBlockIndex = null;
                        this.activeBlockPath = null;
                        this.activeBlock = {};
                        this.saveActiveSection(false);
                    }
                },

                init() {
                    const iframe = document.getElementById('preview-iframe');
                    if (iframe) {
                        iframe.addEventListener('load', () => {
                            if (this.activeSectionId) {
                                iframe.contentWindow.postMessage({
                                    action: 'active_section',
                                    id: this.activeSectionId
                                }, '*');
                                
                                // Restaurar el bloque activo si había uno
                                if (this.activeBlockIndex !== null) {
                                    setTimeout(() => {
                                        this.highlightBlockInIframe(this.activeBlockIndex);
                                    }, 150);
                                }
                                
                                setTimeout(() => {
                                    if (iframe.contentWindow) {
                                        iframe.contentWindow.postMessage({
                                            action: 'scroll_to_section',
                                            id: this.activeSectionId
                                        }, '*');
                                    }
                                }, 100);
                            }
                        });
                    }

                    // Escuchar mensajes del Iframe para auto-seleccionar secciones
                    window.addEventListener('message', (event) => {
                        if(event.data && event.data.action === 'select_section') {
                            const sectionId = event.data.id;
                            const section = this.sections.find(s => s.id === sectionId);
                            if(section) {
                                this.activeBlockIndex = null;
                                this.editSection(section);
                            }
                        } else if(event.data && event.data.action === 'insert_section_after') {
                            // Añade una nueva sección de tipo 'custom' en el orden especificado
                            this.addSection('custom', event.data.order);
                        } else if(event.data && event.data.action === 'resize_section') {
                            if (this.activeSection && this.activeSection.id === parseInt(event.data.id)) {
                                this.activeSection.data.padding_y = event.data.padding;
                                this.saveActiveSection(true);
                            }
                        } else if(event.data && event.data.action === 'update_block_size') {
                            if (this.activeSection && this.activeSection.id === parseInt(event.data.id)) {
                                const block = this.activeSection.data.blocks[event.data.block_index];
                                if (block) {
                                    block.width = event.data.width;
                                    block.height = event.data.height;
                                    this.saveActiveSection(true);
                                }
                            }
                        } else if(event.data && event.data.action === 'update_block_position') {
                            if (this.activeSection && this.activeSection.id === parseInt(event.data.id)) {
                                const block = this.activeSection.data.blocks[event.data.block_index];
                                if (block) {
                                    block.x_pos = event.data.x;
                                    block.y_pos = event.data.y;
                                    this.saveActiveSection(true);
                                }
                            }
                        } else if(event.data && event.data.action === 'request_add_block') {
                            if (event.data.section_id) {
                                this.activeSection = this.sections.find(s => s.id === event.data.section_id);
                            }
                            this.insertPosition = event.data.insert_position || 'inside';
                            this.inserterPath = event.data.path;
                            this.activeBlockPath = event.data.path;
                            this.activeBlock = this.getBlockByPath(this.activeBlockPath);
                            
                            const rect = event.data.rect;
                            if (rect) {
                                const iframe = document.getElementById('preview-iframe');
                                const iframeRect = iframe.getBoundingClientRect();
                                this.inserterX = iframeRect.left + rect.left + (rect.width / 2);
                                this.inserterY = iframeRect.top + rect.bottom;
                            } else {
                                this.inserterX = window.innerWidth / 2;
                                this.inserterY = window.innerHeight / 2;
                            }
                            this.showInserter = true;
                        } else if(event.data && event.data.action === 'add_block_drop') {
                            if (this.activeSection && this.activeSection.id === parseInt(event.data.id)) {
                                this.addBlock(event.data.type, event.data.overrides || {});
                            }
                        } else if(event.data && event.data.action === 'select_block') {
                            const newSectionId = parseInt(event.data.id);
                            if (!this.activeSection || this.activeSection.id !== newSectionId) {
                                const newSection = this.sections.find(s => s.id === newSectionId);
                                if (newSection) {
                                    this.editSection(newSection);
                                }
                            }
                            
                            // Usamos $nextTick para asegurar que Alpine haya actualizado el DOM si cambiamos de sección
                            this.$nextTick(() => {
                                this.activeBlockPath = event.data.block_path;
                                this.activeBlock = this.getBlockByPath(this.activeBlockPath);
                                this.expandParents(this.activeBlockPath);
                                this.sidebarTab = 'propiedades';
                                // Also update index for root blocks to keep layers panel working
                                this.activeBlockIndex = this.activeBlockPath && !String(this.activeBlockPath).includes('.') ? parseInt(this.activeBlockPath) : null;
                                
                                // Scroll tree item into view in sidebar
                                this.$nextTick(() => {
                                    const activeEl = document.querySelector('#blocks-list .bg-blue-100');
                                    if (activeEl) {
                                        activeEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                    }
                                });
                                
                                // Set toolbar state
                                if (event.data.rect && this.activeSection && this.activeBlock) {
                                    const rect = event.data.rect;
                                    const iframe = document.getElementById('preview-iframe');
                                    if(iframe) {
                                        const iframeRect = iframe.getBoundingClientRect();
                                        this.toolbarX = iframeRect.left + rect.left + (rect.width / 2);
                                        this.toolbarY = iframeRect.top + rect.top - 10;
                                        
                                        this.toolbarType = this.activeBlock.type;
                                        this.showToolbar = true;
                                    }
                                } else {
                                    this.showToolbar = false;
                                }
                                
                                // Scroll al editor en el panel lateral
                                setTimeout(() => {
                                    const editorEl = document.getElementById('block-editor-' + this.activeBlockIndex);
                                    if(editorEl) {
                                        editorEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                        editorEl.classList.add('ring-2', 'ring-primary');
                                        setTimeout(() => editorEl.classList.remove('ring-2', 'ring-primary'), 1500);
                                    }
                                }, 50); // Pequeño retraso para que el DOM se dibuje
                                
                                // Enviar de vuelta highlight_block al iframe
                                document.getElementById('preview-iframe').contentWindow.postMessage({
                                    action: 'highlight_block',
                                    index: this.activeBlockIndex
                                }, '*');
                            });
                        } else if(event.data && event.data.action === 'update_block_content') {
                            if (this.activeSection && this.activeSection.id === parseInt(event.data.id)) {
                                const block = this.getBlockByPath(event.data.block_path);
                                if (block) {
                                    block.content = event.data.content;
                                    // Save quietly without triggering a full re-render which destroys the text cursor
                                    this.saveActiveSection(true);
                                }
                            }
                        } else if (event.data && event.data.action === 'reorder_blocks') {
                            if (this.activeSection && this.activeSection.id === parseInt(event.data.id)) {
                                const oldIndex = event.data.oldIndex;
                                const newIndex = event.data.newIndex;
                                const blocks = this.activeSection.data.blocks;
                                
                                if (oldIndex >= 0 && oldIndex < blocks.length && newIndex >= 0 && newIndex < blocks.length) {
                                    // Mover el elemento en el array
                                    const movedItem = blocks.splice(oldIndex, 1)[0];
                                    blocks.splice(newIndex, 0, movedItem);
                                    
                                    // Actualizar índices activos si es necesario
                                    if (this.activeBlockIndex === oldIndex) {
                                        this.activeBlockIndex = newIndex;
                                    } else if (this.activeBlockIndex > oldIndex && this.activeBlockIndex <= newIndex) {
                                        this.activeBlockIndex--;
                                    } else if (this.activeBlockIndex < oldIndex && this.activeBlockIndex >= newIndex) {
                                        this.activeBlockIndex++;
                                    }
                                    
                                    this.saveActiveSection(true);
                                }
                            }
                        } else if (event.data && event.data.action === 'move_block') {
                            if (this.activeSection && this.activeSection.id === parseInt(event.data.id)) {
                                const index = event.data.block_index;
                                if (this.activeSection.data.blocks[index]) {
                                    this.activeSection.data.blocks[index].is_absolute = true;
                                    this.activeSection.data.blocks[index].x_pos = event.data.x;
                                    this.activeSection.data.blocks[index].y_pos = event.data.y;
                                    this.saveActiveSection(true);
                                }
                            }
                        } else if (event.data && event.data.action === 'reorder_block') {
                            if (this.activeSection && this.activeSection.id === parseInt(event.data.id)) {
                                const oldIndex = event.data.oldIndex;
                                const newIndex = event.data.newIndex;
                                const movedItem = this.activeSection.data.blocks.splice(oldIndex, 1)[0];
                                this.activeSection.data.blocks.splice(newIndex, 0, movedItem);
                                this.saveActiveSection(true);
                            }
                        } else if (event.data && event.data.action === 'resize_block') {
                            if (this.activeSection && this.activeSection.id === parseInt(event.data.id)) {
                                const index = event.data.block_index;
                                if (this.activeSection.data.blocks[index]) {
                                    this.activeSection.data.blocks[index].width = event.data.width;
                                    this.activeSection.data.blocks[index].height = event.data.height;
                                    this.saveActiveSection(true);
                                }
                            }
                        }
                    });

                    // Capturar mouseup fuera del iframe para evitar que se quede pegado el drag
                    window.addEventListener('mouseup', () => {
                        const iframe = document.getElementById('preview-iframe');
                        if(iframe && iframe.contentWindow) {
                            iframe.contentWindow.postMessage({ action: 'parent_mouseup' }, '*');
                        }
                    });
                },

                editSection(section) {
                    this.activeBlockIndex = null;
                    
                    // Guardar referencia para reenviar al recargar
                    this.activeSectionId = section.id;
                    
                    let clonedSection = JSON.parse(JSON.stringify(section));
                    if (clonedSection.data.blocks) {
                        if (!Array.isArray(clonedSection.data.blocks)) {
                            clonedSection.data.blocks = Object.values(clonedSection.data.blocks);
                        }
                    } else {
                        clonedSection.data.blocks = [];
                    }
                    this.activeSection = clonedSection;
                    this.activeSectionId = section.id;
                    // Mandar mensaje al iframe para scrollear a esta sección
                    const iframe = document.getElementById('preview-iframe');
                    if(iframe && iframe.contentWindow) {
                        iframe.contentWindow.postMessage({
                            action: 'scroll_to_section',
                            id: section.id
                        }, '*');
                        
                        iframe.contentWindow.postMessage({
                            action: 'active_section',
                            id: section.id
                        }, '*');
                        
                        iframe.contentWindow.postMessage({
                            action: 'enable_section_edit',
                            id: section.id
                        }, '*');
                    }
                },

                closeEditor() {
                    this.activeSection = null;
                    this.activeSectionId = null;
                    this.activeBlockPath = null;
                    this.activeBlock = {};
                    this.showToolbar = false;
                    
                    const iframe = document.getElementById('preview-iframe');
                    if(iframe && iframe.contentWindow) {
                        iframe.contentWindow.postMessage({
                            action: 'disable_section_edit'
                        }, '*');
                        iframe.contentWindow.postMessage({
                            action: 'active_section',
                            id: null
                        }, '*');
                    }
                },

                async loadTemplateBlocks() {
                    if(!confirm('¿Estás seguro de restablecer la plantilla? Se borrarán tus bloques actuales.')) return;
                    
                    try {
                        const response = await fetch('{{ route("dashboard.store.builder.load_template") }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                        });
                        
                        if(response.ok) {
                            window.location.reload();
                        } else {
                            alert('Error al cargar la plantilla');
                        }
                    } catch (error) {
                        console.error(error);
                        alert('Error de conexión');
                    }
                },

                async uploadSectionImage(event, key) {
                    const file = event.target.files[0];
                    if(!file) return;

                    this.isUploadingImage = true;
                    const formData = new FormData();
                    formData.append('image', file);

                    try {
                        const response = await fetch('{{ route("dashboard.store.builder.upload_image") }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: formData
                        });

                        const textResponse = await response.text();
                        let data;
                        try {
                            data = JSON.parse(textResponse);
                        } catch (e) {
                            console.error("Error no-JSON del servidor:", textResponse);
                            if (response.status === 413) {
                                alert('La imagen es demasiado pesada. El tamaño máximo es 2MB.');
                            } else if (response.status === 419) {
                                alert('Tu sesión ha expirado. Por favor recarga la página.');
                            } else {
                                alert('Error inesperado del servidor (' + response.status + '). Revisa la consola para más detalles.');
                            }
                            return;
                        }

                        if(response.ok) {
                            this.activeSection.data[key] = data.url;
                            this.saveActiveSection();
                        } else {
                            if (data.errors && data.errors.image) {
                                alert(data.errors.image[0]);
                            } else {
                                alert('Error al subir imagen: ' + (data.message || 'Desconocido'));
                            }
                        }
                    } catch (error) {
                        console.error(error);
                        alert('Error de conexión');
                    } finally {
                        this.isUploadingImage = false;
                        event.target.value = ''; // clear input
                    }
                },

                async uploadBlockImage(event, propertyName) {
                    const file = event.target.files[0];
                    if(!file) return;

                    this.isUploadingImage = true;
                    const formData = new FormData();
                    formData.append('image', file);

                    try {
                        const response = await fetch('{{ route("dashboard.store.builder.upload_image") }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: formData
                        });

                        const textResponse = await response.text();
                        let data;
                        try {
                            data = JSON.parse(textResponse);
                        } catch (e) {
                            console.error("Error no-JSON del servidor:", textResponse);
                            if (response.status === 413) {
                                alert('La imagen es demasiado pesada. El tamaño máximo es 2MB.');
                            } else if (response.status === 419) {
                                alert('Tu sesión ha expirado. Por favor recarga la página.');
                            } else {
                                alert('Error inesperado del servidor (' + response.status + '). Revisa la consola para más detalles.');
                            }
                            return;
                        }

                        if(response.ok) {
                            this.activeBlock[propertyName] = data.url;
                            this.saveActiveSection(false);
                        } else {
                            if (data.errors && data.errors.image) {
                                alert(data.errors.image[0]);
                            } else {
                                alert('Error al subir imagen: ' + (data.message || 'Desconocido'));
                            }
                        }
                    } catch (error) {
                        console.error(error);
                        alert('Error de conexión');
                    } finally {
                        this.isUploadingImage = false;
                        event.target.value = ''; // clear input
                    }
                },

                async uploadCarouselImage(event, key) {
                    const file = event.target.files[0];
                    if(!file) return;

                    this.isUploadingImage = true;
                    const formData = new FormData();
                    formData.append('image', file);

                    try {
                        const response = await fetch('{{ route("dashboard.store.builder.upload_image") }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: formData
                        });

                        const textResponse = await response.text();
                        let data;
                        try {
                            data = JSON.parse(textResponse);
                        } catch (e) {
                            console.error("Error no-JSON del servidor:", textResponse);
                            if (response.status === 413) {
                                alert('La imagen es demasiado pesada. El tamaño máximo es 2MB.');
                            } else if (response.status === 419) {
                                alert('Tu sesión ha expirado. Por favor recarga la página.');
                            } else {
                                alert('Error inesperado del servidor (' + response.status + '). Revisa la consola para más detalles.');
                            }
                            return;
                        }

                        if(response.ok) {
                            if (!Array.isArray(this.activeSection.data[key])) {
                                this.activeSection.data[key] = [];
                            }
                            this.activeSection.data[key].push(data.url);
                            this.saveActiveSection();
                        } else {
                            if (data.errors && data.errors.image) {
                                alert(data.errors.image[0]);
                            } else {
                                alert('Error al subir imagen: ' + (data.message || 'Desconocido'));
                            }
                        }
                    } catch (error) {
                        console.error(error);
                        alert('Error de conexión');
                    } finally {
                        this.isUploadingImage = false;
                        event.target.value = ''; // clear input
                    }
                },

                async addSection(type, order = null) {
                    this.showAddModal = false;
                    try {
                        const payload = { type: type };
                        if (order !== null) payload.order = order;
                        
                        const response = await fetch('{{ route("dashboard.store.builder.store") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(payload)
                        });
                        
                        const data = await response.json();
                        if(response.ok) {
                            if (order !== null) {
                                // Find the right index to splice into
                                // sortedSections is a getter, sections is the source of truth
                                // Instead of mutating directly by index, we insert and then re-assign orders
                                this.sections.splice(order, 0, data.section);
                                this.sortedSections.forEach((sec, idx) => {
                                    const original = this.sections.find(s => s.id === sec.id);
                                    if (original) original.order = idx;
                                });
                                this.sections = [...this.sections]; // trigger reactivity
                                
                                // Opcional: sincronizar ordenes en DB
                                this.updateSectionsOrder();
                            } else {
                                this.sections.push(data.section);
                            }
                            
                            this.editSection(data.section);
                            const iframe = document.getElementById('preview-iframe');
                            if(iframe && iframe.contentWindow && data.html) {
                                iframe.contentWindow.postMessage({ 
                                    action: 'add_section', 
                                    section: data.section, 
                                    html: data.html,
                                    order: order
                                }, '*');
                            }
                        }
                    } catch (error) {
                        console.error(error);
                    }
                },

                async updateSectionsOrder() {
                    try {
                        await fetch('{{ route("dashboard.store.builder.reorder") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                sections: this.sections.map(s => ({ id: s.id, order: s.order }))
                            })
                        });
                    } catch(e) { console.error(e); }
                },

                async saveActiveSection(skipReload = false) {
                    if(!this.activeSection) return;
                    this.isSaving = true;
                    
                    // Solo generamos un objeto limpio para enviar por fetch
                    const cleanData = JSON.parse(JSON.stringify(this.activeSection.data));
                    
                    try {
                        // 1. Guardar en BD
                        const response = await fetch(`{{ url('dashboard/tienda/constructor/secciones') }}/${this.activeSection.id}`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                data: cleanData,
                                is_active: this.activeSection.is_active
                            })
                        });
                        
                        const data = await response.json();
                        if(response.ok) {
                            if(!skipReload && data.html) {
                                const iframe = document.getElementById('preview-iframe');
                                if(iframe && iframe.contentWindow) {
                                    iframe.contentWindow.postMessage({
                                        action: 'update_section_html',
                                        id: this.activeSection.id,
                                        html: data.html
                                    }, '*');
                                    
                                    if (this.activeBlockPath !== null) {
                                        setTimeout(() => {
                                            this.highlightBlockInIframe(this.activeBlockPath);
                                        }, 100);
                                    }
                                }
                            }
                        }
                    } catch (error) {
                        console.error(error);
                    } finally {
                        this.isSaving = false;
                    }
                },
                
                async publishChanges() {
                    this.isPublishing = true;
                    try {
                        const response = await fetch('{{ route("dashboard.store.builder.publish") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });
                        const data = await response.json();
                        if(response.ok) {
                            alert(data.message);
                        } else {
                            alert('Hubo un error al publicar.');
                        }
                    } catch (error) {
                        console.error(error);
                        alert('Error de conexión al publicar.');
                    } finally {
                        this.isPublishing = false;
                    }
                },

                async moveUp(index) {
                    if (index === 0) return;
                    this.swapSections(index, index - 1);
                },

                async moveDown(index) {
                    if (index === this.sections.length - 1) return;
                    this.swapSections(index, index + 1);
                },

                async moveSectionDrag(oldIndex, newIndex) {
                    const sorted = this.sortedSections;
                    const movedItem = sorted.splice(oldIndex, 1)[0];
                    sorted.splice(newIndex, 0, movedItem);

                    // Reasignar el orden
                    sorted.forEach((sec, idx) => {
                        const original = this.sections.find(s => s.id === sec.id);
                        if (original) original.order = idx;
                    });
                    
                    this.sections = [...this.sections]; // trigger reactivity
                    
                    // Actualizar BD
                    try {
                        await fetch('{{ route("dashboard.store.builder.reorder") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                sections: this.sections.map(s => ({ id: s.id, order: s.order }))
                            })
                        });
                        const iframe = document.getElementById('preview-iframe');
                        if (iframe && iframe.contentWindow) {
                            iframe.contentWindow.postMessage({ action: 'reorder_sections', sections: this.sortedSections.map(s => s.id) }, '*');
                        }
                    } catch(e) { console.error(e); }
                },

                moveBlockDrag(oldIndex, newIndex) {
                    if (!this.activeSection || !this.activeSection.data.blocks) return;
                    
                    let newBlocks = JSON.parse(JSON.stringify(this.activeSection.data.blocks));
                    const movedItem = newBlocks.splice(oldIndex, 1)[0];
                    newBlocks.splice(newIndex, 0, movedItem);
                    this.activeSection.data.blocks = newBlocks;
                    
                    if (this.activeBlockIndex === oldIndex) {
                        this.activeBlockIndex = newIndex;
                    } else if (this.activeBlockIndex !== null) {
                        this.activeBlockIndex = null;
                    }
                    
                    this.saveActiveSection(false);
                },

                async swapSections(idx1, idx2) {
                    const sorted = this.sortedSections;
                    const tempOrder = sorted[idx1].order;
                    sorted[idx1].order = sorted[idx2].order;
                    sorted[idx2].order = tempOrder;
                    
                    this.sections = [...this.sections]; // trigger reactivity
                    
                    // Actualizar BD
                    try {
                        await fetch('{{ route("dashboard.store.builder.reorder") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                sections: this.sections.map(s => ({ id: s.id, order: s.order }))
                            })
                        });
                        const iframe = document.getElementById('preview-iframe');
                        if (iframe && iframe.contentWindow) {
                            iframe.contentWindow.postMessage({ action: 'reorder_sections', sections: this.sortedSections.map(s => s.id) }, '*');
                        }
                    } catch(e) { console.error(e); }
                },

                async deleteSection(id) {
                    if(!confirm('¿Eliminar esta sección?')) return;
                    
                    try {
                        const response = await fetch(`{{ url('dashboard/tienda/constructor/secciones') }}/${id}`, {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                        });
                        
                        if(response.ok) {
                            this.sections = this.sections.filter(s => s.id !== id);
                            if(this.activeSection && this.activeSection.id === id) {
                                this.activeSection = null;
                            }
                            const iframe = document.getElementById('preview-iframe');
                            if (iframe && iframe.contentWindow) {
                                iframe.contentWindow.postMessage({ action: 'delete_section', id: id }, '*');
                            }
                        }
                    } catch(e) { console.error(e); }
                },

                // -------- BLOCKS MANAGEMENT --------
                
                removeBlockByPath(path) {
                    if (!this.activeSection || !confirm('¿Estás seguro de eliminar este componente?')) return;
                    let newData = JSON.parse(JSON.stringify(this.activeSection.data));
                    const keys = String(path).split('.');
                    let current = newData;
                    let parentArray = newData.blocks;
                    let indexToRemove = -1;
                    
                    for (let i = 0; i < keys.length; i++) {
                        if (keys[i] === 'blocks') {
                            if (!current.blocks) current.blocks = [];
                            parentArray = current.blocks;
                            current = current.blocks;
                        } else {
                            if (i === keys.length - 1) {
                                indexToRemove = parseInt(keys[i]);
                            } else {
                                current = current[parseInt(keys[i])];
                            }
                        }
                    }
                    
                    if (indexToRemove !== -1 && parentArray) {
                        parentArray.splice(indexToRemove, 1);
                        this.activeSection.data = newData;
                        this.activeBlockPath = null;
                        this.activeBlock = {};
                        this.saveActiveSection();
                    }
                },

                wrapActiveBlockInContainer() {
                    if (!this.activeSection || !this.activeBlockPath) return;
                    let newData = JSON.parse(JSON.stringify(this.activeSection.data));
                    const keys = String(this.activeBlockPath).split('.');
                    let current = newData;
                    let parentArray = newData.blocks;
                    let targetIndex = -1;
                    
                    for (let i = 0; i < keys.length; i++) {
                        if (keys[i] === 'blocks') {
                            if (!current.blocks) current.blocks = [];
                            parentArray = current.blocks;
                            current = current.blocks;
                        } else {
                            if (i === keys.length - 1) {
                                targetIndex = parseInt(keys[i]);
                            } else {
                                current = current[parseInt(keys[i])];
                            }
                        }
                    }
                    
                    if (targetIndex !== -1 && parentArray) {
                        let originalBlock = parentArray[targetIndex];
                        let newContainer = {
                            id: 'block_' + Math.random().toString(36).substr(2, 9),
                            type: 'container',
                            layout: 'flex-col',
                            background_color: 'transparent',
                            padding_x: 0,
                            padding_y: 0,
                            blocks: [originalBlock]
                        };
                        parentArray[targetIndex] = newContainer;
                        this.activeSection.data = newData;
                        
                        // Select the new container
                        let newPath = keys.slice(0, -1).join('.') + (keys.length > 1 ? '.' : '') + targetIndex;
                        this.activeBlockPath = newPath;
                        this.activeBlock = newContainer;
                        
                        this.saveActiveSection(true);
                    }
                },

                moveBlockRelative(path, direction) {
                    if (!this.activeSection || !path) return;
                    let newData = JSON.parse(JSON.stringify(this.activeSection.data));
                    const keys = String(path).split('.');
                    let current = newData;
                    let parentArray = newData.blocks;
                    let indexToMove = -1;
                    
                    for (let i = 0; i < keys.length; i++) {
                        if (keys[i] === 'blocks') {
                            if (!current.blocks) current.blocks = [];
                            parentArray = current.blocks;
                            current = current.blocks;
                        } else {
                            if (i === keys.length - 1) {
                                indexToMove = parseInt(keys[i]);
                            } else {
                                current = current[parseInt(keys[i])];
                            }
                        }
                    }
                    
                    if (indexToMove !== -1 && parentArray) {
                        const newIndex = indexToMove + direction;
                        if (newIndex >= 0 && newIndex < parentArray.length) {
                            const item = parentArray.splice(indexToMove, 1)[0];
                            parentArray.splice(newIndex, 0, item);
                            
                            keys[keys.length - 1] = newIndex.toString();
                            this.activeBlockPath = keys.join('.');
                            this.activeBlock = this.getBlockByPath(this.activeBlockPath);
                            
                            this.activeSection.data = newData;
                            this.saveActiveSection();
                        }
                    }
                },

                                addBlock(type, overrides = {}) {
                    if (!this.activeSection) return;
                    
                    const newId = 'block_' + Math.random().toString(36).substr(2, 9);
                    let block = { id: newId, type: type };
                    
                    // Defaults based on type
                    if (type === 'container') {
                        block.content = undefined;
                        block.layout = 'flex-col';
                        block.background_color = 'transparent';
                        block.padding_x = 16;
                        block.padding_y = 16;
                        block.blocks = [];
                        if (this.insertPosition === 'before' || this.insertPosition === 'after') {
                            block.flex = '1';
                        }
                    } else if (type === 'paragraph') {
                        block.content = 'Escribe tu texto aquí...';
                        block.color = '#374151';
                    } else if (type === 'title') {
                        block.content = 'Nuevo Título';
                        block.color = '#111827';
                        block.tag = 'h2';
                        block.font_weight = 'bold';
                    } else if (type === 'toc') {
                        block.content = 'Tabla de Contenidos';
                        block.color = '#111827';
                    } else if (type === 'link') {
                        block.content = 'Click aquí';
                        block.url = '#';
                        block.color = '#3b82f6';
                    } else if (type === 'button') {
                        block.content = 'Click aquí';
                        block.background_color = '#000000';
                        block.color = '#ffffff';
                        block.padding_x = 24;
                        block.padding_y = 12;
                        block.border_radius = 4;
                    } else if (type === 'card') {
                        block.background_color = '#ffffff';
                        block.border_radius = 8;
                        block.padding_x = 20;
                        block.padding_y = 20;
                        block.blocks = [];
                    } else if (type === 'store_logo') {
                        block.color = '#374151';
                    } else if (type === 'store_navbar') {
                        block.color = '#374151';
                        block.link_1 = 'INICIO';
                        block.link_2 = 'CATÁLOGO';
                        block.link_3 = 'CONTACTO';
                    } else if (type === 'store_search') {
                        block.background_color = '#E50914';
                        block.text_color = '#FFFFFF';
                    } else if (type === 'store_cart') {
                        block.background_color = '#E50914';
                        block.text_color = '#FFFFFF';
                        block.icon_color = '#374151';
                    } else if (type === 'image') {
                        block.content = 'https://placehold.co/50x50';
                        block.width = '100%';
                        block.height = 'auto';
                        block.object_fit = 'cover';
                        block.display = 'block';
                    }
                    
                    // Apply any overrides passed (e.g. tag: 'h1' for titles)
                    if (overrides) {
                        Object.assign(block, overrides);
                    }
                    
                    if (!this.activeSection.data.blocks) {
                        this.activeSection.data.blocks = [];
                    }
                    
                    let targetArray = this.activeSection.data.blocks;
                    let targetIndex = -1;
                    let parentObj = this.activeSection.data;
                    
                    if (this.activeBlockPath !== null) {
                        let current = this.activeSection.data;
                        const keys = String(this.activeBlockPath).split('.');
                        let parentArray = this.activeSection.data.blocks;
                        let valid = true;
                        
                        for (let i = 0; i < keys.length; i++) {
                            if (keys[i] === 'blocks') {
                                if (!current.blocks) current.blocks = [];
                                parentArray = current.blocks;
                                parentObj = current;
                                current = current.blocks;
                            } else {
                                current = current[parseInt(keys[i])];
                            }
                            if (!current) { valid = false; break; }
                        }
                        
                        if (valid && ['container', 'carousel', 'card'].includes(current.type) && !['before', 'after', 'top', 'bottom', 'left', 'right'].includes(this.insertPosition)) {
                            if (!current.blocks) current.blocks = [];
                            targetArray = current.blocks;
                        } else if (valid && keys.length >= 1) {
                            // Insert as sibling
                            targetArray = parentArray;
                            targetIndex = parseInt(keys[keys.length - 1]);
                        }
                    }
                    
                    if (targetIndex !== -1 && ['top', 'bottom', 'left', 'right'].includes(this.insertPosition)) {
                        let isParentRow = (parentObj && (parentObj.layout === 'flex-row' || parentObj.type === 'carousel'));
                        
                        if (this.insertPosition === 'left' || this.insertPosition === 'right') {
                            if (isParentRow) {
                                if (this.insertPosition === 'left') targetArray.splice(targetIndex, 0, block);
                                else targetArray.splice(targetIndex + 1, 0, block);
                            } else {
                                let currentBlock = targetArray[targetIndex];
                                let newContainer = { id: 'block_' + Math.random().toString(36).substr(2, 9), type: 'container', layout: 'flex-row', blocks: this.insertPosition === 'left' ? [block, currentBlock] : [currentBlock, block] };
                                targetArray.splice(targetIndex, 1, newContainer);
                            }
                        } else if (this.insertPosition === 'top' || this.insertPosition === 'bottom') {
                            if (!isParentRow) {
                                if (this.insertPosition === 'top') targetArray.splice(targetIndex, 0, block);
                                else targetArray.splice(targetIndex + 1, 0, block);
                            } else {
                                let currentBlock = targetArray[targetIndex];
                                let newContainer = { id: 'block_' + Math.random().toString(36).substr(2, 9), type: 'container', layout: 'flex-col', blocks: this.insertPosition === 'top' ? [block, currentBlock] : [currentBlock, block] };
                                targetArray.splice(targetIndex, 1, newContainer);
                            }
                        }
                    } else if (targetIndex !== -1 && (this.insertPosition === 'before' || this.insertPosition === 'after')) {
                        if (this.insertPosition === 'before') {
                            targetArray.splice(targetIndex, 0, block);
                        } else {
                            targetArray.splice(targetIndex + 1, 0, block);
                        }
                    } else {
                        targetArray.push(block);
                    }
                    
                    // Reset positions for next add
                    this.insertPosition = 'inside';
                    this.saveActiveSection();
                },

                highlightBlockInIframe(index) {
                    const iframe = document.getElementById('preview-iframe');
                    if (iframe && iframe.contentWindow) {
                        iframe.contentWindow.postMessage({
                            action: 'highlight_block',
                            index: index
                        }, '*');
                    }
                },

                removeBlock(index) {
                    if(confirm('¿Seguro que deseas eliminar este bloque?')) {
                        this.activeSection.data.blocks.splice(index, 1);
                        this.saveActiveSection(true);
                        
                        const iframe = document.getElementById('preview-iframe');
                        if (iframe && iframe.contentWindow) {
                            iframe.contentWindow.postMessage({
                                action: 'delete_block_from_dom',
                                index: index
                            }, '*');
                        }
                    }
                },
            }));
        });
    </script>
</body>
</html>
