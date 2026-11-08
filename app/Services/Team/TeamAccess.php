<?php

namespace App\Services\Team;

use App\Models\Store;
use App\Models\StoreMember;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Who may do what inside a business. The owner (stores.user_id) and the admins open everything;
 * a seller opens only the modules the owner ticked. Inert until the store_members migration ran
 * (a deploy never migrates): then everybody is the owner, exactly as before.
 */
class TeamAccess
{
    /**
     * module key => label, what it holds, route patterns and whether a new seller gets it by default.
     * "Vender" is the register / selling screen (and quotes, returns, credit collections).
     */
    public const MODULES = [
        'vender'     => ['label' => 'Vender', 'hint' => 'Caja, proformas, devoluciones y cobranzas', 'default' => true,
            'routes' => ['dashboard.pos.*', 'dashboard.quotes.*', 'dashboard.returns.*', 'dashboard.receivables.*', 'dashboard.price-lists.*', 'dashboard.simple.*']],
        'ventas'     => ['label' => 'Ventas', 'hint' => 'Historial de ventas y su detalle', 'default' => true,
            'routes' => ['dashboard.ventas.*', 'dashboard.pedidos.*']],
        'clientes'   => ['label' => 'Clientes', 'hint' => 'Lista y fichas de clientes', 'default' => true,
            'routes' => ['dashboard.clientes.*']],
        'productos'  => ['label' => 'Productos', 'hint' => 'Ver y editar productos, categorías y marcas', 'default' => false,
            'routes' => ['dashboard.productos.*', 'dashboard.categorias.*', 'dashboard.marcas.*']],
        'inventario' => ['label' => 'Inventario', 'hint' => 'Stock, conteos y movimientos', 'default' => false,
            'routes' => ['dashboard.inventario.*']],
        'compras'    => ['label' => 'Compras y proveedores', 'hint' => 'Órdenes de compra y proveedores', 'default' => false,
            'routes' => ['dashboard.compras.*', 'dashboard.proveedores.*']],
        'ganancias'  => ['label' => 'Ganancias y costos', 'hint' => 'Ver cuánto se gana y cuánto cuesta cada producto', 'default' => false,
            'routes' => ['dashboard.ganancias.*']],
    ];

    /** Open to every member, whatever their modules. */
    private const ALWAYS = ['dashboard.index', 'dashboard.store.switch', 'dashboard.password.*', 'dashboard.account.*', 'dashboard.google.*'];

    /** Owner / admin only even inside a seller's module: money setup, staff PINs, SUNAT, series. */
    private const MANAGER_ONLY = ['dashboard.pos.staff.*', 'dashboard.pos.documents.*', 'dashboard.pos.registers.store', 'dashboard.pos.kiosk.*'];

    public static function ready(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasTable('store_members');
    }

    /** @return list<string> */
    public static function defaultModules(): array
    {
        return array_keys(array_filter(self::MODULES, fn (array $m) => $m['default']));
    }

    public function member(User $user, Store $store): ?StoreMember
    {
        if (!self::ready()) {
            return null;
        }

        return StoreMember::where('store_id', $store->id)->where('user_id', $user->id)->where('is_active', true)->first();
    }

    /** owner | admin | seller | null (no access to this business). */
    public function roleIn(User $user, Store $store): ?string
    {
        if ($user->isSuperAdmin() || (int) $store->user_id === (int) $user->id) {
            return 'owner';
        }

        return $this->member($user, $store)?->role;
    }

    /** Owner or admin: manages the business, the team included. */
    public function manages(User $user, Store $store): bool
    {
        return in_array($this->roleIn($user, $store), ['owner', StoreMember::ADMIN], true);
    }

    /** @return list<string> the module keys a seller of this store may open */
    public function modulesOf(User $user, Store $store): array
    {
        $member = $this->member($user, $store);

        return array_values(array_intersect(array_keys(self::MODULES), $member?->modules ?? self::defaultModules()));
    }

    /** May this person open this named route inside this business? */
    public function allows(User $user, Store $store, string $routeName): bool
    {
        $role = $this->roleIn($user, $store);
        if ($role === null) {
            return false;
        }
        if ($role !== StoreMember::SELLER) {
            return true;
        }
        if (self::matches($routeName, self::ALWAYS)) {
            return true;
        }
        if (self::matches($routeName, self::MANAGER_ONLY)) {
            return false;
        }
        foreach ($this->modulesOf($user, $store) as $key) {
            if (self::matches($routeName, self::MODULES[$key]['routes'])) {
                return true;
            }
        }

        return false;
    }

    /** Where a seller lands: the first thing they may do. */
    public function landing(User $user, Store $store): string
    {
        foreach (['dashboard.simple.index', 'dashboard.pos.index', 'dashboard.ventas.index', 'dashboard.clientes.index', 'dashboard.productos.index', 'dashboard.inventario.index', 'dashboard.compras.index'] as $route) {
            if (\Illuminate\Support\Facades\Route::has($route) && $this->allows($user, $store, $route)) {
                if ($route === 'dashboard.simple.index' && !$store->isSimple()) {
                    continue;
                }
                if ($route === 'dashboard.pos.index' && $store->isSimple()) {
                    continue;
                }

                return $route;
            }
        }

        return 'dashboard.password.edit';
    }

    /**
     * Every business this account works in: the ones it owns and the ones it was let into.
     *
     * @return Collection<int, array{store: Store, role: string}>
     */
    public function workspaces(User $user): Collection
    {
        $items = $user->stores()->oldest()->get()->map(fn (Store $s) => ['store' => $s, 'role' => 'owner']);

        if (self::ready()) {
            $members = StoreMember::with('store')->where('user_id', $user->id)->where('is_active', true)->oldest('id')->get();
            foreach ($members as $member) {
                if ($member->store && $member->store->status === 'active' && !$items->contains(fn ($i) => $i['store']->id === $member->store_id)) {
                    $items->push(['store' => $member->store, 'role' => $member->role]);
                }
            }
        }

        return $items->values();
    }

    /** Links the invitations waiting for this e-mail to the account that just proved to own it. */
    public function claimInvites(User $user): int
    {
        if (!self::ready() || !$user->email || (!$user->google_id && !$user->email_verified_at)) {
            return 0;
        }

        $claimed = 0;
        foreach (StoreMember::whereNull('user_id')->whereRaw('LOWER(email) = ?', [mb_strtolower($user->email)])->get() as $invite) {
            $invite->update(['user_id' => $user->id, 'accepted_at' => now(), 'name' => $invite->name ?: $user->name]);
            $claimed++;
        }

        return $claimed;
    }

    private static function matches(string $route, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (\Illuminate\Support\Str::is($pattern, $route)) {
                return true;
            }
        }

        return false;
    }
}
