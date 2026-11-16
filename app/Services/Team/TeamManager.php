<?php

namespace App\Services\Team;

use App\Models\Store;
use App\Models\StoreMember;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * The rules for adding, changing and removing the people of a business, shared by the web screen and the
 * mobile API. Callers validate the shape of the input; the business rules live here.
 */
class TeamManager
{
    public function __construct(private TeamAccess $team)
    {
    }

    /** @param array{email: string, name?: ?string, role?: string, modules?: array} $data */
    public function invite(Store $store, User $actor, array $data): StoreMember
    {
        $email = mb_strtolower(trim($data['email']));
        if ($store->user && mb_strtolower((string) $store->user->email) === $email) {
            throw ValidationException::withMessages(['email' => 'Ese correo es el del dueño del negocio: ya tiene todo el acceso.']);
        }
        if (StoreMember::where('store_id', $store->id)->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => 'Esa persona ya está en tu equipo.']);
        }

        $member = StoreMember::create($this->fields($store, $actor, $data) + ['store_id' => $store->id, 'email' => $email, 'invited_by' => $actor->id]);
        if ($existing = User::whereRaw('LOWER(email) = ?', [$email])->first()) {
            $this->team->claimInvites($existing);
        }

        return $member->refresh();
    }

    /** @param array{name?: ?string, role?: string, modules?: array} $data */
    public function update(Store $store, User $actor, StoreMember $member, array $data, bool $active): StoreMember
    {
        $this->assertOfStore($store, $member);
        $member->update($this->fields($store, $actor, $data, $member) + ['is_active' => $active]);

        return $member->refresh();
    }

    public function remove(Store $store, User $actor, StoreMember $member): void
    {
        $this->assertOfStore($store, $member);
        if ($member->isAdmin() && $this->team->roleIn($actor, $store) !== 'owner') {
            throw new AuthorizationException('Solo el dueño puede quitar a un administrador.');
        }
        $member->delete();
    }

    /** @return array{name: ?string, role: string, modules: list<string>} */
    private function fields(Store $store, User $actor, array $data, ?StoreMember $member = null): array
    {
        $role = $data['role'] ?? StoreMember::SELLER;
        if ($role === StoreMember::ADMIN && $this->team->roleIn($actor, $store) !== 'owner') {
            // Only the owner hands out the admin role (an edit that keeps it stays as it was).
            $role = $member && $member->isAdmin() ? StoreMember::ADMIN : StoreMember::SELLER;
        }

        return [
            'name'    => filled($data['name'] ?? null) ? trim($data['name']) : null,
            'role'    => $role,
            'modules' => array_values(array_intersect(array_keys(TeamAccess::MODULES), (array) ($data['modules'] ?? []))),
        ];
    }

    private function assertOfStore(Store $store, StoreMember $member): void
    {
        abort_unless((int) $member->store_id === (int) $store->id, 404);
    }
}
