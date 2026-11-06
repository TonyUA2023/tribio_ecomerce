<?php

namespace App\Services\Sunat;

use App\Models\Branch;
use App\Models\Register;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Sedes (fiscal establishments) of a business and the cash registers that sell from each one.
 * Stock stays one per business: a sede decides the address and the series printed on a comprobante,
 * not which shelf the goods come from.
 */
class Branches
{
    public const MAX = 30;

    /** The principal establishment (código 0000), created from the business data the first time. */
    public function main(Store $store): Branch
    {
        return Branch::firstOrCreate(
            ['store_id' => $store->id, 'sunat_code' => '0000'],
            ['name' => 'Sede principal', 'address' => $store->address, 'is_main' => true, 'is_active' => true, 'phone' => $store->phone],
        );
    }

    /** @return Collection<int, Branch> main first */
    public function all(Store $store): Collection
    {
        $this->main($store);

        return Branch::where('store_id', $store->id)->orderByDesc('is_main')->orderBy('sunat_code')->get();
    }

    public function save(Store $store, array $data, ?Branch $branch = null): Branch
    {
        if (!$branch && Branch::where('store_id', $store->id)->count() >= self::MAX) {
            throw ValidationException::withMessages(['name' => 'Llegaste al máximo de ' . self::MAX . ' sedes.']);
        }
        $code = $branch?->is_main ? '0000' : $this->code($store, $data['sunat_code'] ?? null, $branch);

        $fields = [
            'name'       => trim($data['name']),
            'sunat_code' => $code,
            'address'    => $this->clean($data['address'] ?? null),
            'ubigeo'     => $this->clean($data['ubigeo'] ?? null),
            'department' => $this->clean($data['department'] ?? null),
            'province'   => $this->clean($data['province'] ?? null),
            'district'   => $this->clean($data['district'] ?? null),
            'phone'      => $this->clean($data['phone'] ?? null),
            'is_active'  => $branch?->is_main ? true : (bool) ($data['is_active'] ?? true),
        ];

        return $branch ? tap($branch)->update($fields) : Branch::create($fields + ['store_id' => $store->id, 'is_main' => false]);
    }

    public function delete(Branch $branch): void
    {
        if ($branch->is_main) {
            throw ValidationException::withMessages(['branch' => 'La sede principal no se puede eliminar.']);
        }
        if (\App\Models\Order::where('branch_id', $branch->id)->exists()) {
            throw ValidationException::withMessages(['branch' => 'Esta sede ya emitió comprobantes: desactívala en vez de eliminarla.']);
        }
        Register::where('branch_id', $branch->id)->update(['branch_id' => null]);
        $branch->delete();
    }

    /** Puts a register in a sede (null = the principal one). */
    public function assign(Register $register, ?int $branchId): void
    {
        if ($branchId !== null && !Branch::where('store_id', $register->store_id)->whereKey($branchId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['branch_id' => 'Esa sede no existe o está desactivada.']);
        }
        $register->update(['branch_id' => $branchId]);
    }

    /** The sede a register sells from. */
    public function of(?Register $register, Store $store): Branch
    {
        return ($register?->branch_id ? Branch::find($register->branch_id) : null) ?? $this->main($store);
    }

    private function code(Store $store, ?string $code, ?Branch $current): string
    {
        $code = preg_replace('/\D+/', '', (string) $code);
        if ($code === '') {
            $used = Branch::where('store_id', $store->id)->pluck('sunat_code')->map(fn ($c) => (int) $c)->all();
            $next = 1;
            while (in_array($next, $used, true)) {
                $next++;
            }

            return str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        }
        $code = str_pad(substr($code, -4), 4, '0', STR_PAD_LEFT);
        $taken = Branch::where('store_id', $store->id)->where('sunat_code', $code)->when($current, fn ($q) => $q->where('id', '!=', $current->id))->exists();
        if ($code === '0000' || $taken) {
            throw ValidationException::withMessages(['sunat_code' => $code === '0000' ? 'El código 0000 es de la sede principal.' : "El código {$code} ya lo usa otra sede."]);
        }

        return $code;
    }

    private function clean(?string $value): ?string
    {
        return filled($value) ? trim($value) : null;
    }
}
