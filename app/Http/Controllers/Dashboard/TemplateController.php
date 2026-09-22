<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Storefront\ApplyStoreTemplate;
use App\Actions\Storefront\SaveTemplateSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateTemplateSettingsRequest;
use App\Models\Store;
use App\Services\LogoPaletteService;
use App\Services\Storefront\StorefrontHomeData;
use App\Services\Storefront\StorefrontTheme;
use App\Services\Storefront\TemplateRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Dashboard "Plantillas" module: browse the template catalog, preview any available
 * design dressed with the store's own data, apply it, and customize the active one.
 * Always scoped to the owner's current store; locked (bespoke) stores are read-only here.
 */
class TemplateController extends Controller
{
    public function __construct(private readonly TemplateRegistry $registry)
    {
    }

    public function index()
    {
        $store = $this->store();
        $catalog = $this->registry->catalog();
        $current = $this->registry->find($store->template_name);

        return view('dashboard.templates.index', [
            'store' => $store,
            'current' => $current,
            'currentKey' => $store->template_name,
            'locked' => !$this->registry->canManage($store),
            'available' => array_filter($catalog, fn ($t, $key) => $this->registry->isSelectable($key), ARRAY_FILTER_USE_BOTH),
            'upcoming' => array_filter($catalog, fn ($t, $key) => !$this->registry->isSelectable($key), ARRAY_FILTER_USE_BOTH),
            'canPreviewCurrent' => $this->canFrame($store, $store->template_name),
            'customizable' => $this->registry->isCustomizable($store->template_name),
        ]);
    }

    public function preview(string $template)
    {
        $store = $this->store();
        if ($redirect = $this->guardManageable($store)) {
            return $redirect;
        }
        abort_unless($this->canFrame($store, $template), 404);

        return view('dashboard.templates.preview', [
            'store' => $store,
            'templateKey' => $template,
            'template' => $this->registry->find($template),
            'isCurrent' => $store->template_name === $template,
            'selectable' => $this->registry->isSelectable($template),
        ]);
    }

    /**
     * The storefront home rendered in $template for the current store, for the dashboard's
     * iframes only: nothing is saved, no visit is counted, navigation is neutralized and
     * the page cannot be framed by any other origin.
     */
    public function frame(Request $request, string $template): Response
    {
        $store = $this->store();
        abort_unless($this->registry->canManage($store) && $this->canFrame($store, $template), 404);

        // Separate in-memory instance: its template_name is swapped for rendering and it is never saved.
        $previewStore = Store::findOrFail($store->id);
        $previewStore->template_name = $template;

        // A preview is never a "first visit": skip the country picker and render in Spanish,
        // the language the customizer's placeholders are written in.
        foreach (['user_country' => 'PE', 'store_currency' => 'PEN'] as $cookie => $fallback) {
            if (!$request->cookies->has($cookie)) {
                $request->cookies->set($cookie, $fallback);
            }
        }
        $request->query->set('lang', 'es');

        $data = app(StorefrontHomeData::class)->build($previewStore) + ['templatePreview' => true];
        if ($this->registry->isCustomizable($template)) {
            $data['storefrontTheme'] = StorefrontTheme::for($previewStore, $template, [], 'es');
        }

        $html = view("templates.{$template}.store", $data)->render();
        $bridge = view('dashboard.templates._preview-bridge', ['thumbnail' => $request->boolean('thumb')])->render();
        $html = str_contains($html, '</body>') ? substr_replace($html, $bridge . '</body>', strrpos($html, '</body>'), 7) : $html . $bridge;

        return response($html)
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function apply(string $template, ApplyStoreTemplate $apply): RedirectResponse
    {
        $store = $this->store();
        if ($redirect = $this->guardManageable($store)) {
            return $redirect;
        }

        $apply->handle($store, $template);
        $name = $this->registry->find($template)['name'];

        return $this->registry->isCustomizable($template)
            ? redirect()->route('dashboard.plantillas.customize')->with('success', "¡Listo! Tu tienda ahora usa {$name}. Dale tu toque: colores, textos y portada.")
            : redirect()->route('dashboard.plantillas.index')->with('success', "¡Listo! Tu tienda ahora usa {$name}.");
    }

    public function customize(LogoPaletteService $palettes)
    {
        $store = $this->store();
        if ($redirect = $this->guardManageable($store)) {
            return $redirect;
        }
        if (!$this->registry->isCustomizable($store->template_name)) {
            return redirect()->route('dashboard.plantillas.index')
                ->with('info', 'Tu diseño actual no tiene opciones de personalización todavía. Elige una plantilla disponible para personalizarla.');
        }

        $key = $store->template_name;
        $theme = StorefrontTheme::for($store, $key, [], 'es');

        return view('dashboard.templates.customize', [
            'store' => $store,
            'templateKey' => $key,
            'template' => $this->registry->find($key),
            'fields' => $this->registry->fields($key),
            'values' => $theme->formValues(),
            'defaults' => collect($this->registry->fields($key))->map(fn ($f, $path) => $theme->defaultFor($path))->all(),
            'fonts' => $this->registry->fontOptions(),
            'logoPalette' => $this->logoPalette($store, $palettes),
            'hasSaved' => !empty($store->template_settings[$key] ?? null),
        ]);
    }

    public function update(UpdateTemplateSettingsRequest $request, SaveTemplateSettings $save): RedirectResponse
    {
        $store = $this->store();
        $save->handle($store, $store->template_name, $request->validated('settings') ?? []);

        return redirect()->route('dashboard.plantillas.customize')->with('success', 'Cambios publicados. Tu tienda ya luce así.');
    }

    public function reset(SaveTemplateSettings $save): RedirectResponse
    {
        $store = $this->store();
        if ($redirect = $this->guardManageable($store)) {
            return $redirect;
        }
        $save->reset($store, $store->template_name);

        return redirect()->route('dashboard.plantillas.customize')->with('success', 'Restablecimos el diseño original de la plantilla.');
    }

    private function store(): Store
    {
        $store = Auth::user()->currentStore();
        abort_unless($store, 404, 'Primero necesitas una tienda.');

        return $store;
    }

    private function guardManageable(Store $store): ?RedirectResponse
    {
        return $this->registry->canManage($store)
            ? null
            : redirect()->route('dashboard.plantillas.index')->with('info', 'Tu tienda tiene un diseño exclusivo protegido. Escríbenos si quieres cambiarlo.');
    }

    /** Previewable: any selectable template, or the store's own current design (it is already live anyway). */
    private function canFrame(Store $store, string $template): bool
    {
        if ($this->registry->isSelectable($template)) {
            return true;
        }

        return $template === $store->template_name
            && $this->registry->exists($template)
            && !in_array('store', $this->registry->missingViews($template), true);
    }

    /** Colors sampled from the uploaded logo, offered as one-click swatches. */
    private function logoPalette(Store $store, LogoPaletteService $palettes): ?array
    {
        if (!$store->logo_path) {
            return null;
        }
        $disk = Storage::disk('public');
        if (!$disk->exists($store->logo_path)) {
            return null;
        }

        return $palettes->extract($disk->path($store->logo_path));
    }
}
