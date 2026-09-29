<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Store;

/**
 * Every mobile dashboard endpoint works on the account's current store (multi-store,
 * ADR 0002). A missing store answers the same 404 JSON the older endpoints already use.
 */
trait ResolvesCurrentStore
{
    protected function currentStore(): Store
    {
        $store = request()->user()?->currentStore();
        abort_unless($store, 404, 'No tienes ninguna tienda configurada.');

        return $store;
    }
}
