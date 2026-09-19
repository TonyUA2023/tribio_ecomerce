<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'avatar',
        'current_store_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ─── Roles ───────────────────────────────────────────────────
    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_STORE_OWNER = 'store_owner';
    public const ROLE_CLIENTE     = 'cliente';

    // ─── Helpers ────────────────────────────────────────────────
    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isStoreOwner(): bool
    {
        return $this->role === self::ROLE_STORE_OWNER;
    }

    public function isCliente(): bool
    {
        return in_array($this->role, [self::ROLE_CLIENTE, 'customer']);
    }

    public function isCustomer(): bool
    {
        return $this->isCliente();
    }

    // ─── Tribio Pass capabilities ──────────────────────────────────
    // `role` alone used to gate access exclusively (a store owner could never
    // shop, a cliente could never own a store). Tribio Pass unifies identity:
    // capability checks below decide what a logged-in account can reach,
    // independent of the legacy `role` value. See the vault ADR on this.
    public function hasStore(): bool
    {
        return $this->stores()->exists();
    }

    /**
     * The dashboard's "active" store — one Tribio Pass account can now own several
     * (see the multi-store ADR). Resolution order: the account's remembered
     * `current_store_id` (survives across devices/logins) if it still belongs to
     * this user, else their oldest store, else null if they own none yet. Never
     * throws — every dashboard controller can call this instead of the old
     * `Auth::user()->store` and get either a valid Store or null, exactly like before.
     */
    public function currentStore(): ?Store
    {
        if ($this->current_store_id) {
            $store = $this->stores()->find($this->current_store_id);
            if ($store) {
                return $store;
            }
        }

        return $this->stores()->oldest()->first();
    }

    /**
     * Switches the account's remembered current store. Caller must have already
     * verified $store belongs to this user (see DashboardController::switchStore()).
     */
    public function switchToStore(Store $store): void
    {
        $this->update(['current_store_id' => $store->id]);
    }

    public function hasPurchaseHistory(): bool
    {
        return Order::where('user_id', $this->id)
            ->orWhere('customer_email', $this->email)
            ->exists();
    }

    public function canUseCustomerPortal(): bool
    {
        return !$this->isSuperAdmin();
    }

    // ─── Relationships ───────────────────────────────────────────
    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function customerAddresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function customerOrders()
    {
        return $this->hasMany(Order::class)->latest();
    }
}
