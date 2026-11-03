<?php

namespace App\Services\Pos;

use App\Models\StoreStaff;

/**
 * Who worked a register action and which manager approved it (ERP Fase 4d), as returned by
 * PosAccess::authorize(). Empty in owner mode or before the staff migration.
 */
final class Actor
{
    public function __construct(public readonly ?StoreStaff $staff = null, public readonly ?StoreStaff $approver = null)
    {
    }

    /** @param  array{staff?: ?StoreStaff, approver?: ?StoreStaff}  $access */
    public static function from(array $access): self
    {
        return new self($access['staff'] ?? null, $access['approver'] ?? null);
    }

    /** Columns to write, e.g. ['staff_id' => 3, 'approved_staff_id' => 1]; nothing when nobody is identified. */
    public function columns(string $staffColumn = 'staff_id', ?string $approverColumn = 'approved_staff_id'): array
    {
        if (!$this->staff && !$this->approver) {
            return [];
        }

        return [$staffColumn => $this->staff?->id] + ($approverColumn ? [$approverColumn => $this->approver?->id] : []);
    }
}
