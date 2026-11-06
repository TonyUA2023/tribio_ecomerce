<?php

namespace App\Support;

use App\Models\Store;
use App\Services\Pos\PosSchema;

/**
 * The dashboard's side menu for each account type. A POS-only business gets a short, task-ordered
 * menu (Inicio, Vender, Productos y stock, Mi negocio); the online store keeps its full one.
 * Items are hidden, never disabled, when they do not apply.
 */
class DashboardNav
{
    /**
     * @return list<array{route: string, icon: string, label: string, group: ?string, match: list<string>, except: list<string>}>
     */
    public static function items(?Store $store): array
    {
        $pos = app(PosSchema::class);
        $items = $store && Store::modesReady() && $store->isPosOnly() ? self::posSystem($pos) : self::full($store, $pos);

        return array_map(fn (array $item) => $item + ['match' => [], 'except' => []], $items);
    }

    /** Is this item the page being shown? */
    public static function active(array $item): bool
    {
        if ($item['except'] && request()->routeIs(...$item['except'])) {
            return false;
        }
        $patterns = $item['match'] ?: [$item['route'] === 'dashboard.index' ? $item['route'] : substr($item['route'], 0, strrpos($item['route'], '.')) . '.*'];

        return request()->routeIs(...$patterns);
    }

    private static function item(string $route, string $icon, string $label, ?string $group = null, array $extra = []): array
    {
        return ['route' => $route, 'icon' => $icon, 'label' => $label, 'group' => $group] + $extra;
    }

    /**
     * Online store, alone or with the POS. With both, each system has its own group — Punto de venta
     * (Caja, Ventas) and Tienda virtual (Pedidos…) — while catalog, stock, customers and reports are shared.
     */
    private static function full(?Store $store, PosSchema $pos): array
    {
        $withPos = $pos->ready() && (!$store || $store->hasPos());

        $items = [self::item('dashboard.index', 'grid', 'Dashboard', 'Resumen')];

        if ($withPos) {
            $items[] = self::item('dashboard.pos.index', 'pos', 'Caja', 'Punto de venta', ['match' => ['dashboard.pos.*', 'dashboard.quotes.*', 'dashboard.returns.*', 'dashboard.price-lists.*'], 'except' => ['dashboard.pos.documents.*']]);
            $items[] = self::item('dashboard.ventas.index', 'receipt', 'Ventas');
            if ($pos->creditReady()) {
                $items[] = self::item('dashboard.receivables.index', 'coins', 'Por cobrar');
            }
        }

        $items = array_merge($items, [
            self::item('dashboard.pedidos.index', 'cart', 'Pedidos', $withPos ? 'Tienda virtual' : 'Operación'),
            self::item('dashboard.resenas.index', 'star', 'Reseñas'),
            self::item('dashboard.mensajes.index', 'mail', 'Mensajes'),
            self::item('dashboard.shipping.index', 'truck', 'Zonas de envío'),
            self::item('dashboard.gateway.edit', 'wallet', 'Pasarela de pago'),
            self::item('dashboard.marketing.index', 'megaphone', 'Marketing'),
            self::item('dashboard.galeria.index', 'image', 'Galería'),
            self::item('dashboard.plantillas.index', 'palette', 'Plantillas'),
            self::item('dashboard.store.edit', 'store', 'Mi tienda'),

            self::item('dashboard.productos.index', 'box', 'Productos', 'Productos y stock'),
            self::item('dashboard.categorias.index', 'folder', 'Categorías'),
            self::item('dashboard.marcas.index', 'tag', 'Marcas'),
            self::item('dashboard.inventario.index', 'inventory', 'Inventario'),
            self::item('dashboard.compras.index', 'receipt', 'Compras'),

            self::item('dashboard.clientes.index', 'users', 'Clientes', 'Clientes y reportes'),
            self::item('dashboard.whatsapp.index', 'chat', 'WhatsApp'),
            self::item('dashboard.ganancias.index', 'trend', 'Ganancias y pérdidas'),
        ]);

        return $items;
    }

    /** The point-of-sale system on its own. */
    private static function posSystem(PosSchema $pos): array
    {
        $items = [self::item('dashboard.index', 'grid', 'Inicio', 'Hoy')];

        if ($pos->ready()) {
            $items[] = self::item('dashboard.pos.index', 'pos', 'Vender', 'Vender', ['match' => ['dashboard.pos.*', 'dashboard.quotes.*', 'dashboard.returns.*', 'dashboard.price-lists.*'], 'except' => ['dashboard.pos.documents.*']]);
            $items[] = self::item('dashboard.ventas.index', 'receipt', 'Ventas');
            if ($pos->creditReady()) {
                $items[] = self::item('dashboard.receivables.index', 'coins', 'Por cobrar');
            }
        }
        $items[] = self::item('dashboard.clientes.index', 'users', 'Clientes');
        $items[] = self::item('dashboard.productos.index', 'box', 'Productos', 'Productos y stock');
        $items[] = self::item('dashboard.categorias.index', 'folder', 'Categorías');
        $items[] = self::item('dashboard.inventario.index', 'inventory', 'Inventario');
        $items[] = self::item('dashboard.compras.index', 'truck', 'Compras');
        $items[] = self::item('dashboard.ganancias.index', 'trend', 'Ganancias y pérdidas', 'Mi negocio');
        if ($pos->ready()) {
            $items[] = self::item('dashboard.pos.documents.edit', 'store', 'Datos y comprobantes');
        }

        return $items;
    }
}
