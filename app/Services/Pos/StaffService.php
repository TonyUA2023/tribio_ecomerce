<?php

namespace App\Services\Pos;

use App\Models\Store;
use App\Models\StoreStaff;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Register staff and their PINs (ERP Fase 4d). A cashier sells at list prices, saves quotes,
 * collects debts and puts cash in; anything that gives value away needs a manager's PIN.
 */
class StaffService
{
    public const ROLES = ['cajero' => 'Cajero', 'encargado' => 'Encargado'];

    /** What only a manager may do (a cashier needs a manager's PIN for it). */
    public const RESTRICTED = [
        'price'        => 'Cambiar el precio',
        'discount'     => 'Hacer un descuento',
        'credit'       => 'Fiar',
        'return'       => 'Registrar una devolución',
        'cash_out'     => 'Retirar efectivo',
        'close'        => 'Cerrar la caja',
        'cancel_quote' => 'Anular una proforma',
        'limit'        => 'Cambiar el límite de crédito',
    ];

    public const MAX_ATTEMPTS = 5;

    public function can(StoreStaff $staff, string $action): bool
    {
        return $staff->isManager() || !isset(self::RESTRICTED[$action]);
    }

    public function create(Store $store, string $name, string $role, string $pin): StoreStaff
    {
        $this->assertFreePin($store, $pin);

        return StoreStaff::create(['store_id' => $store->id, 'name' => trim($name), 'role' => $role, 'pin_hash' => Hash::make($pin), 'is_active' => true]);
    }

    public function update(StoreStaff $staff, array $data): StoreStaff
    {
        $changes = array_filter(['name' => isset($data['name']) ? trim($data['name']) : null, 'role' => $data['role'] ?? null]);
        if (filled($data['pin'] ?? null)) {
            $this->assertFreePin($staff->store, $data['pin'], $staff->id);
            $changes['pin_hash'] = Hash::make($data['pin']);
        }
        if (array_key_exists('is_active', $data)) {
            $changes['is_active'] = (bool) $data['is_active'];
        }
        $staff->forceFill($changes)->save();

        return $staff;
    }

    /**
     * The active person with that PIN. Throttled per store and device: after 5 wrong PINs
     * in a minute the register waits before trying again.
     */
    public function identify(Store $store, string $pin, string $throttleKey, ?string $role = null): StoreStaff
    {
        $key = "pos-pin:{$store->id}:{$throttleKey}";
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages(['pin' => 'Demasiados intentos. Espera ' . RateLimiter::availableIn($key) . ' segundos.']);
        }

        $staff = StoreStaff::where('store_id', $store->id)->where('is_active', true)->get()
            ->first(fn (StoreStaff $person) => Hash::check($pin, $person->pin_hash));
        if (!$staff || ($role && $staff->role !== $role)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['pin' => $staff ? 'Ese PIN no es de un encargado.' : 'PIN incorrecto.']);
        }

        RateLimiter::clear($key);
        $staff->forceFill(['last_used_at' => now()])->save();

        return $staff;
    }

    /** PINs identify the person, so two people of the same store can't share one. */
    private function assertFreePin(Store $store, string $pin, ?int $except = null): void
    {
        $taken = StoreStaff::where('store_id', $store->id)->when($except, fn ($q) => $q->whereKeyNot($except))->get()
            ->contains(fn (StoreStaff $person) => Hash::check($pin, $person->pin_hash));
        if ($taken) {
            throw ValidationException::withMessages(['pin' => 'Ese PIN ya lo usa otra persona de tu tienda. Elige otro.']);
        }
    }
}
