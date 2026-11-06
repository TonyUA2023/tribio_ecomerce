<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\StoreSettingsController;
use App\Http\Controllers\Dashboard\ProductController;
use App\Http\Controllers\Dashboard\CategoryController;
use App\Http\Controllers\Dashboard\BrandController;
use App\Http\Controllers\Dashboard\GalleryController;
use App\Http\Controllers\Dashboard\OrderController as DashboardOrderController;
use App\Http\Controllers\Dashboard\ReviewController as DashboardReviewController;
use App\Http\Controllers\Dashboard\InventoryController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminStoreController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\Dashboard\StoreBuilderController;
use App\Http\Controllers\Dashboard\TemplateController;

// ═══════════════════════════════════════════════════════════════
//  PORTAL PÚBLICO TRIBIO
// ═══════════════════════════════════════════════════════════════
Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/buscar', [\App\Http\Controllers\DirectoryController::class, 'legacySearch'])->name('search');
Route::get('/negocios', [\App\Http\Controllers\DirectoryController::class, 'index'])->name('directory');
Route::get('/negocios/buscar', [\App\Http\Controllers\DirectoryController::class, 'search'])->name('directory.search')->middleware('throttle:60,1');
Route::get('/terminos-y-condiciones', [PublicController::class, 'terms'])->name('legal.terms');
Route::get('/privacidad', [PublicController::class, 'privacy'])->name('legal.privacy');
Route::get('/reembolsos', [PublicController::class, 'refunds'])->name('legal.refunds');
Route::post('/plan/checkout', [SubscriptionController::class, 'checkout'])->name('plan.checkout');
Route::get('/plan/{store}/pagar', [SubscriptionController::class, 'pay'])->middleware('auth')->name('plan.pay');
Route::post('/plan/{store}/suscribir', [SubscriptionController::class, 'subscribe'])->middleware('auth')->name('plan.subscribe');
Route::get('/plan/{store}/retorno', [SubscriptionController::class, 'return'])->middleware('auth')->name('plan.return');
Route::post('/plan/webhook', [SubscriptionController::class, 'webhook'])->name('plan.webhook');

// WhatsApp Cloud API (ERP Fase 3a): one URL per connected number, signed by its app secret.
Route::get('/webhooks/whatsapp/{key}', [\App\Http\Controllers\Webhooks\WhatsappWebhookController::class, 'verify'])
    ->where('key', '[A-Za-z0-9]{40}')->middleware('throttle:60,1')->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp/{key}', [\App\Http\Controllers\Webhooks\WhatsappWebhookController::class, 'receive'])
    ->where('key', '[A-Za-z0-9]{40}')->middleware('throttle:1200,1')->name('webhooks.whatsapp.receive');

// ────────── TRIBIO PASS (portal unificado comprador + negocio) ──────────
Route::get('/tribio-pass', [\App\Http\Controllers\TribioPassController::class, 'index'])->name('tribio-pass');
// "Calificar mi compra" desde Mis Compras (sesión web; el móvil usa POST /api/customer/reviews).
// Fuera de customer/* a propósito: ese prefijo está exento de CSRF y esto escribe contenido público.
Route::post('/tribio-pass/resenas', [\App\Http\Controllers\CustomerReviewController::class, 'store'])->middleware(['auth', 'throttle:20,1'])->name('tribio-pass.reviews.store');

// ═══════════════════════════════════════════════════════════════
//  AUTENTICACIÓN
// ═══════════════════════════════════════════════════════════════
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Legacy standalone store registration — retired 2026-09-21: it created an active
// store with a free 30-day trial and never checked for an existing Tribio Pass
// session, bypassing both plan billing and identity unification. Every "Crear mi
// tienda" link now points straight at the pricing flow (#precios); this redirect
// is only a safety net for old bookmarks/indexed links.
Route::get('/registro', fn () => redirect(route('home') . '#precios'))->name('register');

// Google Sign-In — one button, find-or-creates a Tribio Pass account (see CustomerIdentityService).
Route::prefix('auth/google')->name('auth.google.')->group(function () {
    Route::get('/redirect', [GoogleAuthController::class, 'redirect'])->middleware('guest')->name('redirect');
    Route::get('/callback', [GoogleAuthController::class, 'callback'])->name('callback');
});

// ═══════════════════════════════════════════════════════════════
//  DASHBOARD - VENDEDOR
// ═══════════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:store_owner,super_admin', 'pos.kiosk', 'store.modules'])->prefix('dashboard')->name('dashboard.')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('index');
    Route::post('/tienda/cambiar/{store}', [DashboardController::class, 'switchStore'])->name('store.switch');

    // Configuración de la tienda
    Route::get('/tienda/configuracion', [StoreSettingsController::class, 'edit'])->name('store.edit');
    Route::post('/tienda/configuracion', [StoreSettingsController::class, 'update'])->name('store.update');
    Route::post('/tienda/logo', [StoreSettingsController::class, 'uploadLogo'])->name('store.logo');
    Route::post('/tienda/logo/paleta', [StoreSettingsController::class, 'refreshLogoPalette'])->name('store.logo.palette');
    Route::post('/tienda/portada', [StoreSettingsController::class, 'uploadCover'])->name('store.cover');
    // Antigua pantalla de plantillas: reemplazada por el módulo Plantillas (se conserva para enlaces viejos).
    Route::get('/tienda/plantillas', fn () => redirect()->route('dashboard.plantillas.index'))->name('store.templates');

    // Plantillas: catálogo de diseños, vista previa con los datos de la tienda y personalización
    Route::prefix('plantillas')->name('plantillas.')->controller(TemplateController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/personalizar', 'customize')->name('customize');
        Route::put('/personalizar', 'update')->name('update');
        Route::delete('/personalizar', 'reset')->name('reset');
        Route::get('/{template}/vista-previa', 'preview')->name('preview')->where('template', '[a-z0-9-]+');
        Route::get('/{template}/lienzo', 'frame')->name('frame')->where('template', '[a-z0-9-]+')->middleware('throttle:120,1');
        Route::post('/{template}/aplicar', 'apply')->name('apply')->where('template', '[a-z0-9-]+');
    });

    // Cuenta
    Route::get('/mi-cuenta/contrasena', [\App\Http\Controllers\Dashboard\PasswordController::class, 'edit'])->name('password.edit');
    Route::post('/mi-cuenta/contrasena', [\App\Http\Controllers\Dashboard\PasswordController::class, 'update'])->name('password.update');
    // Mensajes de la tienda (contacto, Libro de Reclamaciones, suscripciones).
    Route::get('/mensajes', [\App\Http\Controllers\Dashboard\MessageController::class, 'index'])->name('mensajes.index');
    Route::get('/mensajes/{message}', [\App\Http\Controllers\Dashboard\MessageController::class, 'show'])->whereNumber('message')->name('mensajes.show');
    Route::patch('/mensajes/{message}/no-leido', [\App\Http\Controllers\Dashboard\MessageController::class, 'markUnread'])->whereNumber('message')->name('mensajes.unread');
    Route::post('/mi-cuenta/correo', [\App\Http\Controllers\Dashboard\PasswordController::class, 'updateEmail'])->name('account.email.update')->middleware('throttle:6,1');
    Route::get('/mi-cuenta/google/conectar', [\App\Http\Controllers\Dashboard\GoogleAccountController::class, 'connect'])->name('google.connect');
    Route::get('/mi-cuenta/google/callback', [\App\Http\Controllers\Dashboard\GoogleAccountController::class, 'callback'])->name('google.callback');

    // Shipping
    Route::get('/tienda/envios', [\App\Http\Controllers\Dashboard\ShippingController::class, 'index'])->name('shipping.index');
    Route::post('/tienda/envios', [\App\Http\Controllers\Dashboard\ShippingController::class, 'store'])->name('shipping.store');
    Route::delete('/tienda/envios/{shipping}', [\App\Http\Controllers\Dashboard\ShippingController::class, 'destroy'])->name('shipping.destroy');

    // Pasarela de pago
    Route::get('/tienda/pasarela-de-pago', [\App\Http\Controllers\Dashboard\PaymentGatewayController::class, 'edit'])->name('gateway.edit');
    Route::post('/tienda/pasarela-de-pago', [\App\Http\Controllers\Dashboard\PaymentGatewayController::class, 'update'])->name('gateway.update');
    Route::post('/tienda/pagos-por-confirmacion', [\App\Http\Controllers\Dashboard\ManualPaymentsController::class, 'update'])->name('manual-payments.update');
    Route::post('/tienda/cobertura-de-envio', [\App\Http\Controllers\Dashboard\ManualPaymentsController::class, 'coverage'])->name('delivery-coverage.update');

    // Constructor visual
    Route::get('/tienda/constructor', [StoreBuilderController::class, 'index'])->name('store.builder');
    Route::post('/tienda/constructor/secciones', [StoreBuilderController::class, 'store'])->name('store.builder.store');
    Route::put('/tienda/constructor/secciones/{id}', [StoreBuilderController::class, 'update'])->name('store.builder.update');
    Route::delete('/tienda/constructor/secciones/{id}', [StoreBuilderController::class, 'destroy'])->name('store.builder.destroy');
    Route::post('/tienda/constructor/publicar', [StoreBuilderController::class, 'publish'])->name('store.builder.publish');
    Route::post('/tienda/constructor/reordenar', [StoreBuilderController::class, 'reorder'])->name('store.builder.reorder');
    Route::post('/tienda/constructor/cargar-plantilla', [StoreBuilderController::class, 'loadFromTemplate'])->name('store.builder.load_template');
    Route::post('/tienda/constructor/render-preview', [StoreBuilderController::class, 'renderPreview'])->name('store.builder.render_preview');
    Route::post('/tienda/constructor/upload-image', [StoreBuilderController::class, 'uploadImage'])->name('store.builder.upload_image');

    // Productos CRUD
    Route::get('/productos/simulador-comisiones', \App\Http\Controllers\Dashboard\FeeSimulatorController::class)->name('productos.fee-simulator');
    Route::get('/productos/rapido', [\App\Http\Controllers\Dashboard\QuickProductController::class, 'create'])->name('productos.rapido');
    Route::post('/productos/rapido', [\App\Http\Controllers\Dashboard\QuickProductController::class, 'store'])->middleware('throttle:30,1')->name('productos.rapido.store');
    Route::resource('productos', ProductController::class)->parameters(['productos' => 'product']);
    Route::post('/productos/{product}/toggle-home-video', [ProductController::class, 'toggleHomeVideo'])->name('productos.toggle-home-video');

    // Categorías
    Route::resource('categorias', CategoryController::class)->parameters(['categorias' => 'category']);
    Route::post('/categorias/reordenar', [CategoryController::class, 'reorder'])->name('categorias.reorder');
    Route::post('/categorias/{category}/toggle-header', [CategoryController::class, 'toggleHeader'])->name('categorias.toggle-header');
    Route::post('/categorias/{category}/toggle-featured', [CategoryController::class, 'toggleFeatured'])->name('categorias.toggle-featured');

    // Marcas
    Route::resource('marcas', BrandController::class)->parameters(['marcas' => 'brand']);

    // Galería
    Route::get('/galeria', [GalleryController::class, 'index'])->name('galeria.index');
    Route::post('/galeria', [GalleryController::class, 'store'])->name('galeria.store');
    Route::delete('/galeria/{item}', [GalleryController::class, 'destroy'])->name('galeria.destroy');
    Route::post('/galeria/reordenar', [GalleryController::class, 'reorder'])->name('galeria.reorder');
    Route::post('/galeria/{item}/hero', [GalleryController::class, 'toggleHero'])->name('galeria.toggle-hero');

    // Pedidos
    // Clientes (ERP Fase 0): contactos armados desde los pedidos
    Route::get('/clientes', [\App\Http\Controllers\Dashboard\ContactController::class, 'index'])->name('clientes.index');
    Route::get('/clientes/nuevo', [\App\Http\Controllers\Dashboard\ContactController::class, 'create'])->name('clientes.create');
    Route::post('/clientes', [\App\Http\Controllers\Dashboard\ContactController::class, 'store'])->name('clientes.store');
    Route::get('/clientes/seguimientos', [\App\Http\Controllers\Dashboard\ContactTaskController::class, 'index'])->name('clientes.tasks.index');
    Route::post('/clientes/seguimientos', [\App\Http\Controllers\Dashboard\ContactTaskController::class, 'store'])->name('clientes.tasks.store');
    Route::post('/clientes/seguimientos/{task}/hecho', [\App\Http\Controllers\Dashboard\ContactTaskController::class, 'complete'])->whereNumber('task')->name('clientes.tasks.complete');
    Route::delete('/clientes/seguimientos/{task}', [\App\Http\Controllers\Dashboard\ContactTaskController::class, 'destroy'])->whereNumber('task')->name('clientes.tasks.destroy');
    // Por cobrar: fiado y cobranzas (ERP Fase 4b)
    Route::get('/cobranzas', [\App\Http\Controllers\Dashboard\ReceivableController::class, 'index'])->name('receivables.index');
    Route::get('/cobranzas/{contact}', [\App\Http\Controllers\Dashboard\ReceivableController::class, 'show'])->whereNumber('contact')->name('receivables.show');
    Route::post('/cobranzas/{contact}/cobrar', [\App\Http\Controllers\Dashboard\ReceivableController::class, 'collect'])->whereNumber('contact')->middleware('throttle:30,1')->name('receivables.collect');
    Route::put('/cobranzas/{contact}/limite', [\App\Http\Controllers\Dashboard\ReceivableController::class, 'limit'])->whereNumber('contact')->middleware('throttle:30,1')->name('receivables.limit');
    // Proformas, devoluciones y listas de precios (ERP Fase 4c)
    Route::get('/caja/proformas', [\App\Http\Controllers\Dashboard\QuoteController::class, 'index'])->name('quotes.index');
    Route::post('/caja/proformas', [\App\Http\Controllers\Dashboard\QuoteController::class, 'store'])->middleware('throttle:60,1')->name('quotes.store');
    Route::get('/caja/proformas/{quote}', [\App\Http\Controllers\Dashboard\QuoteController::class, 'show'])->whereNumber('quote')->name('quotes.show');
    Route::post('/caja/proformas/{quote}/anular', [\App\Http\Controllers\Dashboard\QuoteController::class, 'cancel'])->whereNumber('quote')->name('quotes.cancel');
    Route::get('/caja/devoluciones', [\App\Http\Controllers\Dashboard\ReturnController::class, 'index'])->name('returns.index');
    Route::get('/caja/devoluciones/{return}', [\App\Http\Controllers\Dashboard\ReturnController::class, 'show'])->whereNumber('return')->name('returns.show');
    Route::get('/caja/ventas/{order}/devolver', [\App\Http\Controllers\Dashboard\ReturnController::class, 'create'])->whereNumber('order')->name('returns.create');
    Route::post('/caja/ventas/{order}/devolver', [\App\Http\Controllers\Dashboard\ReturnController::class, 'store'])->whereNumber('order')->middleware('throttle:30,1')->name('returns.store');
    Route::get('/listas-de-precios', [\App\Http\Controllers\Dashboard\PriceListController::class, 'index'])->name('price-lists.index');
    Route::put('/listas-de-precios', [\App\Http\Controllers\Dashboard\PriceListController::class, 'update'])->middleware('throttle:30,1')->name('price-lists.update');
    Route::post('/listas-de-precios/porcentaje', [\App\Http\Controllers\Dashboard\PriceListController::class, 'percent'])->middleware('throttle:10,1')->name('price-lists.percent');
    // Personal de caja, modo caja y venta sin conexión (ERP Fase 4d)
    Route::get('/caja/personal', [\App\Http\Controllers\Dashboard\PosStaffController::class, 'index'])->name('pos.staff.index');
    Route::post('/caja/personal', [\App\Http\Controllers\Dashboard\PosStaffController::class, 'store'])->middleware('throttle:20,1')->name('pos.staff.store');
    Route::put('/caja/personal/{person}', [\App\Http\Controllers\Dashboard\PosStaffController::class, 'update'])->whereNumber('person')->middleware('throttle:20,1')->name('pos.staff.update');
    Route::post('/caja/modo-caja', [\App\Http\Controllers\Dashboard\PosStaffController::class, 'enableKiosk'])->name('pos.kiosk.enable');
    Route::post('/caja/modo-caja/salir', [\App\Http\Controllers\Dashboard\PosStaffController::class, 'disableKiosk'])->middleware('throttle:20,1')->name('pos.kiosk.disable');
    Route::post('/caja/desbloquear', [\App\Http\Controllers\Dashboard\PosStaffController::class, 'unlock'])->middleware('throttle:30,1')->name('pos.unlock');
    Route::post('/caja/bloquear', [\App\Http\Controllers\Dashboard\PosStaffController::class, 'lock'])->name('pos.lock');
    Route::get('/caja/token', [\App\Http\Controllers\Dashboard\PosStaffController::class, 'token'])->middleware('throttle:60,1')->name('pos.token');
    Route::get('/caja/catalogo', [\App\Http\Controllers\Dashboard\PosStaffController::class, 'catalog'])->middleware('throttle:30,1')->name('pos.catalog');
    // Caja / POS (ERP Fase 4a)
    Route::get('/caja', [\App\Http\Controllers\Dashboard\PosController::class, 'index'])->name('pos.index');
    Route::get('/caja/productos', [\App\Http\Controllers\Dashboard\PosController::class, 'products'])->middleware('throttle:300,1')->name('pos.products');
    Route::get('/caja/clientes', [\App\Http\Controllers\Dashboard\PosController::class, 'customers'])->middleware('throttle:120,1')->name('pos.customers');
    Route::get('/caja/ticket/{order}', [\App\Http\Controllers\Dashboard\PosController::class, 'receipt'])->whereNumber('order')->name('pos.receipt');
    Route::post('/caja/cajas', [\App\Http\Controllers\Dashboard\RegisterSessionController::class, 'storeRegister'])->middleware('throttle:20,1')->name('pos.registers.store');
    Route::post('/caja/cajas/{register}/abrir', [\App\Http\Controllers\Dashboard\RegisterSessionController::class, 'open'])->whereNumber('register')->middleware('throttle:20,1')->name('pos.registers.open');
    Route::get('/caja/turnos', [\App\Http\Controllers\Dashboard\RegisterSessionController::class, 'index'])->name('pos.sessions.index');
    Route::get('/caja/comprobantes', [\App\Http\Controllers\Dashboard\SalesDocumentsController::class, 'edit'])->name('pos.documents.edit');
    Route::post('/caja/comprobantes', [\App\Http\Controllers\Dashboard\SalesDocumentsController::class, 'update'])->middleware('throttle:20,1')->name('pos.documents.update');
    // Sedes, cajas y series; facturación electrónica con SUNAT.
    Route::controller(\App\Http\Controllers\Dashboard\BranchController::class)->prefix('/caja/comprobantes')->name('pos.documents.')->group(function () {
        Route::get('/sedes', 'index')->name('branches');
        Route::post('/sedes', 'store')->middleware('throttle:30,1')->name('branches.store');
        Route::put('/sedes/{branch}', 'update')->whereNumber('branch')->name('branches.update');
        Route::delete('/sedes/{branch}', 'destroy')->whereNumber('branch')->name('branches.destroy');
        Route::put('/cajas/{register}/sede', 'assign')->whereNumber('register')->name('registers.branch');
        Route::post('/series', 'storeSeries')->middleware('throttle:30,1')->name('series.store');
        Route::delete('/series/{series}', 'destroySeries')->whereNumber('series')->name('series.destroy');
    });
    Route::controller(\App\Http\Controllers\Dashboard\SunatController::class)->prefix('/caja/comprobantes')->name('pos.documents.')->group(function () {
        Route::get('/sunat', 'edit')->name('sunat');
        Route::post('/sunat', 'update')->middleware('throttle:20,1')->name('sunat.update');
        Route::post('/sunat/certificado', 'certificate')->middleware('throttle:10,1')->name('sunat.certificate');
        Route::delete('/sunat/certificado', 'removeCertificate')->name('sunat.certificate.remove');
        Route::post('/sunat/probar', 'test')->middleware('throttle:10,1')->name('sunat.test');
        Route::get('/electronicos', 'documents')->name('electronic');
        Route::post('/electronicos/{document}/reenviar', 'retry')->whereNumber('document')->middleware('throttle:30,1')->name('electronic.retry');
        Route::get('/electronicos/{document}/{format}', 'download')->whereNumber('document')->whereIn('format', ['pdf', 'xml', 'cdr'])->name('electronic.download');
    });
    Route::get('/caja/turnos/{session}', [\App\Http\Controllers\Dashboard\RegisterSessionController::class, 'show'])->whereNumber('session')->name('pos.sessions.show');
    Route::post('/caja/turnos/{session}/ventas', [\App\Http\Controllers\Dashboard\PosController::class, 'sell'])->whereNumber('session')->middleware('throttle:120,1')->name('pos.sell');
    Route::post('/caja/turnos/{session}/movimientos', [\App\Http\Controllers\Dashboard\RegisterSessionController::class, 'movement'])->whereNumber('session')->middleware('throttle:30,1')->name('pos.sessions.movement');
    Route::get('/caja/turnos/{session}/cerrar', [\App\Http\Controllers\Dashboard\RegisterSessionController::class, 'closeForm'])->whereNumber('session')->name('pos.sessions.close');
    Route::post('/caja/turnos/{session}/cerrar', [\App\Http\Controllers\Dashboard\RegisterSessionController::class, 'close'])->whereNumber('session')->middleware('throttle:20,1')->name('pos.sessions.close.store');
    Route::get('/whatsapp', [\App\Http\Controllers\Dashboard\WhatsappInboxController::class, 'index'])->name('whatsapp.index');
    Route::get('/whatsapp/conexion', [\App\Http\Controllers\Dashboard\WhatsappConnectionController::class, 'edit'])->name('whatsapp.connection.edit');
    Route::put('/whatsapp/conexion', [\App\Http\Controllers\Dashboard\WhatsappConnectionController::class, 'update'])->middleware('throttle:10,1')->name('whatsapp.connection.update');
    Route::post('/whatsapp/conexion/probar', [\App\Http\Controllers\Dashboard\WhatsappConnectionController::class, 'test'])->middleware('throttle:10,1')->name('whatsapp.connection.test');
    Route::delete('/whatsapp/conexion', [\App\Http\Controllers\Dashboard\WhatsappConnectionController::class, 'destroy'])->name('whatsapp.connection.destroy');
    Route::get('/whatsapp/plantillas', [\App\Http\Controllers\Dashboard\WhatsappTemplateController::class, 'index'])->name('whatsapp.templates.index');
    Route::post('/whatsapp/plantillas/sincronizar', [\App\Http\Controllers\Dashboard\WhatsappTemplateController::class, 'sync'])->middleware('throttle:10,1')->name('whatsapp.templates.sync');
    Route::post('/whatsapp/plantillas', [\App\Http\Controllers\Dashboard\WhatsappTemplateController::class, 'submit'])->middleware('throttle:20,1')->name('whatsapp.templates.submit');
    Route::delete('/whatsapp/plantillas/{template}', [\App\Http\Controllers\Dashboard\WhatsappTemplateController::class, 'destroy'])->whereNumber('template')->name('whatsapp.templates.destroy');
    Route::get('/whatsapp/difusiones', [\App\Http\Controllers\Dashboard\WhatsappBroadcastController::class, 'index'])->name('whatsapp.broadcasts.index');
    Route::get('/whatsapp/difusiones/nueva', [\App\Http\Controllers\Dashboard\WhatsappBroadcastController::class, 'create'])->name('whatsapp.broadcasts.create');
    Route::post('/whatsapp/difusiones/estimar', [\App\Http\Controllers\Dashboard\WhatsappBroadcastController::class, 'estimate'])->middleware('throttle:60,1')->name('whatsapp.broadcasts.estimate');
    Route::post('/whatsapp/difusiones', [\App\Http\Controllers\Dashboard\WhatsappBroadcastController::class, 'store'])->middleware('throttle:10,1')->name('whatsapp.broadcasts.store');
    Route::put('/whatsapp/difusiones/presupuesto', [\App\Http\Controllers\Dashboard\WhatsappBroadcastController::class, 'budget'])->middleware('throttle:20,1')->name('whatsapp.broadcasts.budget');
    Route::get('/whatsapp/difusiones/{broadcast}', [\App\Http\Controllers\Dashboard\WhatsappBroadcastController::class, 'show'])->whereNumber('broadcast')->name('whatsapp.broadcasts.show');
    Route::post('/whatsapp/difusiones/{broadcast}/cancelar', [\App\Http\Controllers\Dashboard\WhatsappBroadcastController::class, 'cancel'])->whereNumber('broadcast')->name('whatsapp.broadcasts.cancel');
    Route::post('/whatsapp/cliente/{contact}', [\App\Http\Controllers\Dashboard\WhatsappInboxController::class, 'start'])->whereNumber('contact')->name('whatsapp.start');
    Route::get('/whatsapp/archivo/{message}', [\App\Http\Controllers\Dashboard\WhatsappInboxController::class, 'media'])->whereNumber('message')->middleware('throttle:240,1')->name('whatsapp.media');
    Route::post('/whatsapp/{conversation}/plantilla', [\App\Http\Controllers\Dashboard\WhatsappInboxController::class, 'sendTemplate'])->whereNumber('conversation')->middleware('throttle:30,1')->name('whatsapp.template');
    Route::get('/whatsapp/{conversation}', [\App\Http\Controllers\Dashboard\WhatsappInboxController::class, 'show'])->whereNumber('conversation')->name('whatsapp.show');
    Route::post('/whatsapp/{conversation}/responder', [\App\Http\Controllers\Dashboard\WhatsappInboxController::class, 'reply'])->whereNumber('conversation')->middleware('throttle:30,1')->name('whatsapp.reply');
    Route::get('/whatsapp/{conversation}/nuevos', [\App\Http\Controllers\Dashboard\WhatsappInboxController::class, 'poll'])->whereNumber('conversation')->middleware('throttle:120,1')->name('whatsapp.poll');
    Route::get('/clientes/oportunidades', [\App\Http\Controllers\Dashboard\DealController::class, 'index'])->name('clientes.deals.index');
    Route::post('/clientes/oportunidades', [\App\Http\Controllers\Dashboard\DealController::class, 'store'])->name('clientes.deals.store');
    Route::put('/clientes/oportunidades/{deal}', [\App\Http\Controllers\Dashboard\DealController::class, 'update'])->whereNumber('deal')->name('clientes.deals.update');
    Route::post('/clientes/oportunidades/{deal}/mover', [\App\Http\Controllers\Dashboard\DealController::class, 'move'])->whereNumber('deal')->name('clientes.deals.move');
    Route::post('/clientes/oportunidades/{deal}/ganar', [\App\Http\Controllers\Dashboard\DealController::class, 'win'])->whereNumber('deal')->name('clientes.deals.win');
    Route::post('/clientes/oportunidades/{deal}/perder', [\App\Http\Controllers\Dashboard\DealController::class, 'lose'])->whereNumber('deal')->name('clientes.deals.lose');
    Route::post('/clientes/oportunidades/{deal}/reabrir', [\App\Http\Controllers\Dashboard\DealController::class, 'reopen'])->whereNumber('deal')->name('clientes.deals.reopen');
    Route::delete('/clientes/oportunidades/{deal}', [\App\Http\Controllers\Dashboard\DealController::class, 'destroy'])->whereNumber('deal')->name('clientes.deals.destroy');
    Route::get('/clientes/plantillas', [\App\Http\Controllers\Dashboard\MessageTemplateController::class, 'index'])->name('clientes.templates.index');
    Route::post('/clientes/plantillas', [\App\Http\Controllers\Dashboard\MessageTemplateController::class, 'store'])->name('clientes.templates.store');
    Route::put('/clientes/plantillas/{key}', [\App\Http\Controllers\Dashboard\MessageTemplateController::class, 'update'])->where('key', '[a-z_]+|propia-\d+')->name('clientes.templates.update');
    Route::delete('/clientes/plantillas/{key}', [\App\Http\Controllers\Dashboard\MessageTemplateController::class, 'destroy'])->where('key', '[a-z_]+|propia-\d+')->name('clientes.templates.destroy');
    Route::get('/clientes/{contact}/whatsapp', [\App\Http\Controllers\Dashboard\ContactWhatsappController::class, 'open'])->whereNumber('contact')->middleware('throttle:60,1')->name('clientes.whatsapp');
    Route::get('/clientes/{contact}', [\App\Http\Controllers\Dashboard\ContactController::class, 'show'])->whereNumber('contact')->name('clientes.show');
    Route::put('/clientes/{contact}', [\App\Http\Controllers\Dashboard\ContactController::class, 'update'])->whereNumber('contact')->name('clientes.update');
    Route::post('/clientes/{contact}/historia', [\App\Http\Controllers\Dashboard\ContactController::class, 'logEvent'])->whereNumber('contact')->name('clientes.events.store');

    Route::get('/pedidos', [DashboardOrderController::class, 'index'])->name('pedidos.index');
    Route::get('/pedidos/{order}', [DashboardOrderController::class, 'show'])->name('pedidos.show');
    Route::patch('/pedidos/{order}/status', [DashboardOrderController::class, 'updateStatus'])->name('pedidos.status');
    Route::patch('/pedidos/{order}/produccion', [DashboardOrderController::class, 'updateProduction'])->name('pedidos.production');
    Route::post('/pedidos/{order}/pagos', [DashboardOrderController::class, 'recordPayment'])->name('pedidos.payments.store');
    Route::post('/pedidos/{order}/pago/confirmar', [DashboardOrderController::class, 'confirmPayment'])->name('pedidos.payment.confirm');
    Route::post('/pedidos/{order}/pago/rechazar', [DashboardOrderController::class, 'rejectPayment'])->name('pedidos.payment.reject');
    Route::get('/pedidos/{order}/whatsapp', [DashboardOrderController::class, 'whatsappRedirect'])->name('pedidos.whatsapp');

    // Reseñas de productos: ver, ocultar y responder públicamente
    Route::get('/resenas', [DashboardReviewController::class, 'index'])->name('resenas.index');
    Route::patch('/resenas/{review}/visibilidad', [DashboardReviewController::class, 'toggleVisibility'])->name('resenas.visibility');
    Route::put('/resenas/{review}/respuesta', [DashboardReviewController::class, 'reply'])->name('resenas.reply');
    Route::delete('/resenas/{review}/respuesta', [DashboardReviewController::class, 'destroyReply'])->name('resenas.reply.destroy');

    // Inventario
    Route::get('/inventario', [InventoryController::class, 'index'])->name('inventario.index');
    // Compras (ERP Fase 1c): proveedores y órdenes de compra; recibir mete el stock con su costo
    Route::get('/compras', [\App\Http\Controllers\Dashboard\PurchaseOrderController::class, 'index'])->name('compras.index');
    Route::get('/compras/nueva', [\App\Http\Controllers\Dashboard\PurchaseOrderController::class, 'create'])->name('compras.create');
    Route::get('/compras/productos', [\App\Http\Controllers\Dashboard\PurchaseOrderController::class, 'products'])->name('compras.products');
    Route::post('/compras', [\App\Http\Controllers\Dashboard\PurchaseOrderController::class, 'store'])->name('compras.store');
    Route::get('/compras/{order}', [\App\Http\Controllers\Dashboard\PurchaseOrderController::class, 'show'])->whereNumber('order')->name('compras.show');
    Route::get('/compras/{order}/editar', [\App\Http\Controllers\Dashboard\PurchaseOrderController::class, 'edit'])->whereNumber('order')->name('compras.edit');
    Route::put('/compras/{order}', [\App\Http\Controllers\Dashboard\PurchaseOrderController::class, 'update'])->whereNumber('order')->name('compras.update');
    Route::post('/compras/{order}/recibir', [\App\Http\Controllers\Dashboard\PurchaseOrderController::class, 'receive'])->whereNumber('order')->name('compras.receive');
    Route::post('/compras/{order}/cancelar', [\App\Http\Controllers\Dashboard\PurchaseOrderController::class, 'cancel'])->whereNumber('order')->name('compras.cancel');
    Route::get('/proveedores', [\App\Http\Controllers\Dashboard\SupplierController::class, 'index'])->name('proveedores.index');
    Route::post('/proveedores', [\App\Http\Controllers\Dashboard\SupplierController::class, 'store'])->name('proveedores.store');
    Route::get('/proveedores/{supplier}', [\App\Http\Controllers\Dashboard\SupplierController::class, 'edit'])->whereNumber('supplier')->name('proveedores.edit');
    Route::put('/proveedores/{supplier}', [\App\Http\Controllers\Dashboard\SupplierController::class, 'update'])->whereNumber('supplier')->name('proveedores.update');
    Route::delete('/proveedores/{supplier}', [\App\Http\Controllers\Dashboard\SupplierController::class, 'destroy'])->whereNumber('supplier')->name('proveedores.destroy');

    // whereNumber: sin él, /inventario/exportar caía en la ruta del producto (404).
    Route::get('/inventario/exportar', [InventoryController::class, 'export'])->name('inventario.export');
    Route::get('/inventario/rentabilidad', [InventoryController::class, 'profit'])->name('inventario.profit');
    Route::get('/ganancias', [\App\Http\Controllers\Dashboard\ProfitLossController::class, 'index'])->name('ganancias.index');
    // Modo simple: vender desde el celular en dos toques, y ver el día.
    Route::get('/vender', [\App\Http\Controllers\Dashboard\SimpleController::class, 'index'])->name('simple.index');
    Route::post('/vender', [\App\Http\Controllers\Dashboard\SimpleController::class, 'sell'])->middleware('throttle:120,1')->name('simple.sell');
    Route::get('/hoy', [\App\Http\Controllers\Dashboard\SimpleController::class, 'today'])->name('simple.today');
    Route::get('/ventas', [\App\Http\Controllers\Dashboard\SalesController::class, 'index'])->name('ventas.index');
    Route::get('/inventario/reposicion', [\App\Http\Controllers\Dashboard\ReorderController::class, 'index'])->name('inventario.reorder');
    Route::post('/inventario/reposicion/compra', [\App\Http\Controllers\Dashboard\ReorderController::class, 'purchase'])->name('inventario.reorder.purchase');
    Route::get('/inventario/conteos', [\App\Http\Controllers\Dashboard\StockCountController::class, 'index'])->name('inventario.conteos.index');
    Route::post('/inventario/conteos', [\App\Http\Controllers\Dashboard\StockCountController::class, 'store'])->name('inventario.conteos.store');
    Route::get('/inventario/conteos/{count}', [\App\Http\Controllers\Dashboard\StockCountController::class, 'show'])->whereNumber('count')->name('inventario.conteos.show');
    Route::patch('/inventario/conteos/{count}/linea', [\App\Http\Controllers\Dashboard\StockCountController::class, 'record'])->whereNumber('count')->middleware('throttle:240,1')->name('inventario.conteos.record');
    Route::post('/inventario/conteos/{count}/aplicar', [\App\Http\Controllers\Dashboard\StockCountController::class, 'apply'])->whereNumber('count')->name('inventario.conteos.apply');
    Route::post('/inventario/conteos/{count}/cancelar', [\App\Http\Controllers\Dashboard\StockCountController::class, 'cancel'])->whereNumber('count')->name('inventario.conteos.cancel');
    Route::get('/inventario/{product}', [InventoryController::class, 'product'])->whereNumber('product')->name('inventario.product');
    Route::post('/inventario/{product}/ajuste', [InventoryController::class, 'adjust'])->whereNumber('product')->name('inventario.adjust');
    Route::post('/inventario/{product}/presentaciones', [\App\Http\Controllers\Dashboard\ProductStructureController::class, 'storeUnit'])->whereNumber('product')->name('inventario.units.store');
    Route::delete('/inventario/{product}/presentaciones/{unit}', [\App\Http\Controllers\Dashboard\ProductStructureController::class, 'destroyUnit'])->whereNumber(['product', 'unit'])->name('inventario.units.destroy');
    Route::put('/inventario/{product}/recompra', [\App\Http\Controllers\Dashboard\ProductReorderController::class, 'update'])->whereNumber('product')->name('inventario.reorder-days.update');
    Route::put('/inventario/{product}/combo', [\App\Http\Controllers\Dashboard\ProductStructureController::class, 'updateKit'])->whereNumber('product')->name('inventario.kit.update');

    // Marketing: conexión con Meta (Píxel, Conversions API y catálogo de anuncios)
    Route::get('/marketing', [\App\Http\Controllers\Dashboard\MarketingController::class, 'index'])->name('marketing.index');
    Route::get('/marketing/meta', [\App\Http\Controllers\Dashboard\MetaIntegrationController::class, 'edit'])->name('marketing.meta.edit');
    Route::put('/marketing/meta', [\App\Http\Controllers\Dashboard\MetaIntegrationController::class, 'update'])->name('marketing.meta.update');
    Route::post('/marketing/meta/evento-prueba', [\App\Http\Controllers\Dashboard\MetaIntegrationController::class, 'testEvent'])
        ->middleware('throttle:10,1')->name('marketing.meta.test-event');
    Route::get('/marketing/google', [\App\Http\Controllers\Dashboard\GoogleIntegrationController::class, 'edit'])->name('marketing.google.edit');
    Route::put('/marketing/google', [\App\Http\Controllers\Dashboard\GoogleIntegrationController::class, 'update'])->name('marketing.google.update');
    Route::post('/marketing/google/publicar', [\App\Http\Controllers\Dashboard\GoogleIntegrationController::class, 'publishViaTribio'])->name('marketing.google.publish');

    // Marketing → Contenido con IA: kit de marca, copys con IA y flyers con precios reales
    Route::get('/marketing/contenido', [\App\Http\Controllers\Dashboard\AiContentController::class, 'index'])->name('marketing.content.index');
    Route::put('/marketing/contenido/marca', [\App\Http\Controllers\Dashboard\AiContentController::class, 'updateBrandKit'])->name('marketing.content.brand');
    Route::post('/marketing/contenido/textos', [\App\Http\Controllers\Dashboard\AiContentController::class, 'generateCopy'])
        ->middleware('throttle:10,1')->name('marketing.content.copy');
    Route::get('/marketing/contenido/flyer', [\App\Http\Controllers\Dashboard\AiContentController::class, 'flyer'])->name('marketing.content.flyer');
    Route::post('/marketing/contenido/flyer', [\App\Http\Controllers\Dashboard\AiContentController::class, 'storeFlyer'])
        ->middleware('throttle:20,1')->name('marketing.content.flyer.store');
    Route::delete('/marketing/contenido/{asset}', [\App\Http\Controllers\Dashboard\AiContentController::class, 'destroyAsset'])
        ->whereNumber('asset')->name('marketing.content.assets.destroy');
});

// Catálogo de productos para Meta (Commerce Manager lo lee cada hora) y copias JPG de
// las fotos WebP, que Meta no acepta. Ver App\Services\Marketing\Meta\CatalogFeed.
Route::get('/feed-img/{path}', [\App\Http\Controllers\MarketingFeedController::class, 'image'])
    ->where('path', '.+\.webp\.jpg')->middleware('throttle:120,1')->name('marketing.feed-image');
// Catálogo combinado para la cuenta de Merchant Center de Tribio (marketplace). El token
// sale de GOOGLE_MARKETPLACE_FEED_TOKEN; sin token, 404. Ver Google\MarketplaceFeed.
Route::get('/feeds/google-marketplace/{token}.xml', [\App\Http\Controllers\MarketingFeedController::class, 'googleMarketplace'])
    ->where('token', '[A-Za-z0-9_\-]{16,128}')->middleware('throttle:30,1')->name('marketing.google-marketplace-feed');

// ═══════════════════════════════════════════════════════════════
//  SUPER ADMIN
// ═══════════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {

    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');

    // Tiendas
    Route::get('/tiendas', [AdminStoreController::class, 'index'])->name('tiendas.index');
    Route::get('/tiendas/{store}', [AdminStoreController::class, 'show'])->name('tiendas.show');
    Route::patch('/tiendas/{store}/status', [AdminStoreController::class, 'updateStatus'])->name('tiendas.status');
    Route::patch('/tiendas/{store}/featured', [AdminStoreController::class, 'toggleFeatured'])->name('tiendas.featured');
    Route::patch('/tiendas/{store}/mode', [AdminStoreController::class, 'updateMode'])->name('tiendas.mode');
    Route::delete('/tiendas/{store}', [AdminStoreController::class, 'destroy'])->name('tiendas.destroy');

    // Usuarios
    Route::get('/usuarios', [AdminUserController::class, 'index'])->name('usuarios.index');
    Route::get('/usuarios/{user}', [AdminUserController::class, 'show'])->name('usuarios.show');

    // Analytics globales (API)
    Route::get('/analytics', [AdminController::class, 'analytics'])->name('analytics');
    Route::get('/analytics/data', [AdminController::class, 'analyticsData'])->name('analytics.data');
});

// ═══════════════════════════════════════════════════════════════
//  TIENDAS PÚBLICAS EN DOMINIOS PROPIOS
// ═══════════════════════════════════════════════════════════════
$mainDomain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';
$excludedDomains = ['localhost', '127.0.0.1', '::1'];
if (!in_array($mainDomain, $excludedDomains)) {
    $excludedDomains[] = $mainDomain;
}
$excludedPattern = '^(?!(' . implode('|', array_map(function($d) {
    return preg_quote($d, '#') . '|.*?\.' . preg_quote($d, '#');
}, $excludedDomains)) . ')$).*';

Route::domain('{custom_domain}')
    ->where(['custom_domain' => $excludedPattern])
    ->group(function () {
        Route::get('/', [StoreController::class, 'show']);
        Route::get('/catalogo', [StoreController::class, 'catalog']);
        Route::get('/producto/{product}', [StoreController::class, 'product']);
        Route::get('/galeria', [StoreController::class, 'gallery']);
        Route::post('/checkout', [StoreController::class, 'checkout']);
        Route::post('/checkout/paypal/capturar', [StoreController::class, 'capturePaypalOrder']);
        Route::post('/checkout/paypal/webhook', [StoreController::class, 'paypalWebhook']);
        Route::get('/pedido/{order}/confirmacion', [StoreController::class, 'orderConfirmation']);
        Route::get('/checkout/retorno/{reference}', [StoreController::class, 'checkoutReturn']);
        Route::get('/contacto', [StoreController::class, 'contact']);
        Route::post('/contacto', [StoreController::class, 'submitContact'])->middleware('throttle:10,1');
        Route::post('/adjuntos', [\App\Http\Controllers\AttachmentController::class, 'store'])->middleware('throttle:12,1');
        Route::post('/producto/{product}/resena', [\App\Http\Controllers\StoreReviewController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/feed/facebook.xml', [\App\Http\Controllers\MarketingFeedController::class, 'meta'])->middleware('throttle:30,1');
        Route::get('/feed/google.xml', [\App\Http\Controllers\MarketingFeedController::class, 'google'])->middleware('throttle:30,1');
    });

// ────────── TIENDAS PÚBLICAS ESTÁNDAR ──────────
Route::prefix('tienda')->name('store.')->middleware('storefront')->group(function () {
    Route::get('/{slug}', [StoreController::class, 'show'])->name('show');
    Route::get('/{slug}/catalogo', [StoreController::class, 'catalog'])->name('catalog');
    Route::get('/{slug}/producto/{product}', [StoreController::class, 'product'])->name('product');
    // Reseña de un comprador con pedido entregado (la regla vive en ProductReviewService).
    Route::post('/{slug}/producto/{product}/resena', [\App\Http\Controllers\StoreReviewController::class, 'store'])->name('product.review')->middleware('throttle:10,1');
    Route::get('/{slug}/galeria', [StoreController::class, 'gallery'])->name('gallery');
    Route::get('/{slug}/feed/facebook.xml', [\App\Http\Controllers\MarketingFeedController::class, 'meta'])->middleware('throttle:30,1')->name('feed.meta');
    Route::get('/{slug}/feed/google.xml', [\App\Http\Controllers\MarketingFeedController::class, 'google'])->middleware('throttle:30,1')->name('feed.google');
    Route::post('/{slug}/checkout', [StoreController::class, 'checkout'])->name('checkout');
    Route::post('/{slug}/checkout/paypal/capturar', [StoreController::class, 'capturePaypalOrder'])->name('checkout.paypal.capture');
    Route::post('/{slug}/checkout/paypal/webhook', [StoreController::class, 'paypalWebhook'])->name('checkout.paypal.webhook');
    Route::get('/{slug}/pedido/{order}/confirmacion', [StoreController::class, 'orderConfirmation'])->name('order.confirmation');
    Route::get('/{slug}/checkout/retorno/{reference}', [StoreController::class, 'checkoutReturn'])->name('checkout.return');
    Route::get('/{slug}/contacto', [StoreController::class, 'contact'])->name('contact');
    Route::post('/{slug}/contacto', [StoreController::class, 'submitContact'])->name('contact.submit')->middleware('throttle:10,1');
    // Archivos del cliente para trabajos por encargo (logo a bordar, referencias).
    Route::post('/{slug}/adjuntos', [\App\Http\Controllers\AttachmentController::class, 'store'])->name('attachments.upload')->middleware('throttle:12,1');
    // Saldo de un pedido por encargo: enlace firmado que la tienda envía al cliente
    // (Order::balancePaymentUrl()); el retorno de Mercado Pago lleva un token propio.
    Route::get('/{slug}/pedido/{order}/saldo', [\App\Http\Controllers\BalancePaymentController::class, 'show'])->name('balance.show')->middleware('signed');
    Route::post('/{slug}/pedido/{order}/saldo/pagar', [\App\Http\Controllers\BalancePaymentController::class, 'start'])->name('balance.start')->middleware(['signed', 'throttle:6,1']);
    Route::get('/{slug}/saldo/retorno/{reference}/{token}', [\App\Http\Controllers\BalancePaymentController::class, 'return'])->name('balance.return');
});

// Archivos privados: solo con URL firmada y con fecha de vencimiento (Attachment::signedUrl()).
Route::get('/adjuntos/{attachment:token}', [\App\Http\Controllers\AttachmentController::class, 'show'])
    ->name('attachments.show')->middleware('signed');

// SEO: sitemap y robots (la plataforma y, en dominios propios, cada tienda)
Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemap-pages.xml', [\App\Http\Controllers\SitemapController::class, 'pages'])->name('sitemap.pages');
Route::get('/robots.txt', [\App\Http\Controllers\SitemapController::class, 'robots'])->name('robots');
Route::get('/tienda/{slug}/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'store'])->middleware('storefront')->name('store.sitemap');

// API geográfica (países y departamentos/estados) y costos de envío
Route::get('/api/geo/countries', [\App\Http\Controllers\GeoController::class, 'countries']);
Route::get('/api/geo/countries/{country}/states', [\App\Http\Controllers\GeoController::class, 'states'])->where('country', '[A-Za-z]{2}');
Route::get('/api/shipping-cost/{slug}', [\App\Http\Controllers\StoreController::class, 'getShippingCost']);
Route::get('/api/pedido-estado/{orderNumber}', [\App\Http\Controllers\StoreController::class, 'orderStatus']);
Route::get('/api/exchange-rates', function (\App\Services\ExchangeRateService $service) {
    return response()->json([
        'rates' => $service->getRates(),
        'base' => 'PEN',
        'updated_at' => now()->toIso8601String()
    ]);
});

// ────────── PORTAL DEL CLIENTE / COMPRADOR UNIVERSAL ──────────
Route::prefix('customer')->name('customer.')->group(function () {
    Route::post('/check-email', [\App\Http\Controllers\CustomerPortalController::class, 'checkEmail'])->name('check-email');
    Route::get('/current', [\App\Http\Controllers\CustomerPortalController::class, 'current'])->name('current');
    Route::post('/login', [\App\Http\Controllers\CustomerPortalController::class, 'login'])->name('login');
    Route::post('/register/send-otp', [\App\Http\Controllers\CustomerPortalController::class, 'sendOtp'])->name('register.send_otp');
    Route::post('/register/verify', [\App\Http\Controllers\CustomerPortalController::class, 'verifyAndRegister'])->name('register.verify');
    Route::post('/logout', [\App\Http\Controllers\CustomerPortalController::class, 'logout'])->name('logout');
    Route::get('/orders', [\App\Http\Controllers\CustomerPortalController::class, 'orders'])->name('orders');
    Route::match(['get', 'post'], '/track-order', [\App\Http\Controllers\CustomerPortalController::class, 'trackOrder'])->name('track-order');
    Route::get('/addresses', [\App\Http\Controllers\CustomerPortalController::class, 'addresses'])->name('addresses');
    Route::post('/addresses', [\App\Http\Controllers\CustomerPortalController::class, 'saveAddress'])->name('addresses.save');
    Route::delete('/addresses/{id}', [\App\Http\Controllers\CustomerPortalController::class, 'deleteAddress'])->name('addresses.delete');
});
