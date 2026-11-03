<?php

namespace App\Services\Pos;

use App\Models\Store;
use App\Models\StoreStaff;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * "Modo caja" (ERP Fase 4d): the owner hands a signed-in device to the staff. While it is
 * on, only the register screens open (PosKioskGuard), the register asks for a PIN, locks
 * itself after IDLE_MINUTES without use, and leaving needs a manager's PIN. The state lives
 * in the session, so it is per device; with the mode off the owner works as before.
 */
class PosAccess
{
    public const IDLE_MINUTES = 15;

    private const KEY = 'pos_kiosk';

    public function __construct(private Request $request, private StaffService $staff, private PosSchema $schema)
    {
    }

    public function active(?Store $store): bool
    {
        return $store && $this->schema->staffReady() && (int) ($this->request->session()->get(self::KEY)['store_id'] ?? 0) === (int) $store->id;
    }

    /** The person working the register right now; null when locked or idle too long. */
    public function current(Store $store): ?StoreStaff
    {
        if (!$this->active($store)) {
            return null;
        }
        $state = $this->request->session()->get(self::KEY);
        if (empty($state['staff_id']) || ($state['seen_at'] ?? 0) < now()->subMinutes(self::IDLE_MINUTES)->timestamp) {
            return null;
        }

        return StoreStaff::where('store_id', $store->id)->where('is_active', true)->find($state['staff_id']);
    }

    /** Keeps an unlocked register from locking while it is used. */
    public function touch(Store $store): void
    {
        if ($this->active($store) && !empty($this->request->session()->get(self::KEY)['staff_id'])) {
            $this->request->session()->put(self::KEY . '.seen_at', now()->timestamp);
        }
    }

    public function enable(Store $store): void
    {
        if (!StoreStaff::where('store_id', $store->id)->where('is_active', true)->where('role', 'encargado')->exists()) {
            throw ValidationException::withMessages(['kiosk' => 'Primero agrega al menos un encargado: su PIN es el que permite salir del modo caja.']);
        }
        $this->request->session()->put(self::KEY, ['store_id' => $store->id, 'staff_id' => null, 'seen_at' => now()->timestamp]);
    }

    public function disable(Store $store, string $pin): StoreStaff
    {
        $manager = $this->staff->identify($store, $pin, $this->throttleKey(), 'encargado');
        $this->request->session()->forget(self::KEY);

        return $manager;
    }

    public function unlock(Store $store, string $pin): StoreStaff
    {
        $person = $this->staff->identify($store, $pin, $this->throttleKey());
        $this->request->session()->put(self::KEY, ['store_id' => $store->id, 'staff_id' => $person->id, 'seen_at' => now()->timestamp]);

        return $person;
    }

    public function lock(Store $store): void
    {
        if ($this->active($store)) {
            $this->request->session()->put(self::KEY . '.staff_id', null);
        }
    }

    /**
     * Who does a register action and who approved it. Owner mode (no "modo caja") needs
     * nothing; a locked register refuses; a cashier needs a manager's PIN for RESTRICTED actions.
     *
     * `$action` null = any unlocked person may do it (it only records who).
     */
    public function authorize(Store $store, ?string $action, ?string $approverPin = null): Actor
    {
        return $this->authorizeMany($store, $action ? [$action] : [], $approverPin);
    }

    /**
     * Several actions at once (a sale with a typed price and credit): one manager PIN
     * approves all of them.
     *
     * @param  list<string>  $actions
     */
    public function authorizeMany(Store $store, array $actions, ?string $approverPin = null): Actor
    {
        $needed = array_values(array_filter(array_unique($actions)));
        if (!$this->active($store)) {
            return new Actor();
        }
        $staff = $this->current($store);
        if (!$staff) {
            throw ValidationException::withMessages(['pin' => 'La caja está bloqueada: ingresa tu PIN.']);
        }
        $blocked = array_values(array_filter($needed, fn ($action) => !$this->staff->can($staff, $action)));
        if ($blocked === []) {
            return new Actor($staff);
        }
        if (blank($approverPin)) {
            $what = implode(', ', array_map(fn ($a) => mb_strtolower(StaffService::RESTRICTED[$a]), $blocked));
            throw ValidationException::withMessages(['approval' => 'Para ' . $what . ' se necesita el PIN de un encargado.']);
        }

        return new Actor($staff, $this->staff->identify($store, $approverPin, $this->throttleKey(), 'encargado'));
    }

    private function throttleKey(): string
    {
        // The signed-in account and the device's address: the PIN pad is shared by everyone at that counter.
        return sha1($this->request->user()?->id . '|' . $this->request->ip());
    }
}
