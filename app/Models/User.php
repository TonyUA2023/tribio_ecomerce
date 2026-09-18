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
        return $this->store()->exists();
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
    public function store()
    {
        return $this->hasOne(Store::class);
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
