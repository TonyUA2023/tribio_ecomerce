<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\StoreMember;
use App\Models\User;
use App\Services\Team\AccessLog;
use App\Services\Team\TeamAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** Equipo: who works in the business with their own Tribio Pass, and what they may open. */
class TeamController extends Controller
{
    public function __construct(private TeamAccess $team)
    {
    }

    public function index(Request $request, AccessLog $log)
    {
        $store = $this->business();
        $members = StoreMember::where('store_id', $store->id)->with('user:id,name,email')->orderBy('id')->get();
        $ownerId = (int) $store->user_id;
        $stats = $log->summary($store, array_values(array_filter(array_merge([$ownerId], $members->pluck('user_id')->all()))));
        $only = $request->integer('persona') ?: null;

        return view('dashboard.team.index', [
            'store'    => $store,
            'members'  => $members,
            'modules'  => TeamAccess::MODULES,
            'owner'    => $store->user,
            'isOwner'  => $this->team->roleIn(Auth::user(), $store) === 'owner',
            'passUrl'  => route('tribio-pass'),
            'stats'    => $stats,
            'activity' => $log->recent($store, $only, 40),
            'only'     => $only,
            'logReady' => AccessLog::ready(),
            'summary'  => [
                'people' => $members->count() + 1,
                'today'  => collect($stats)->filter(fn ($s) => AccessLog::health($s['last_at']) === 'today')->count(),
                'idle'   => $members->filter(fn ($m) => $m->is_active && $m->user_id && in_array(AccessLog::health($stats[$m->user_id]['last_at'] ?? null), ['stale', 'never'], true))->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $store = $this->business();
        $data = $this->validated($request, $store);
        $email = mb_strtolower(trim($request->input('email')));

        $request->validate(['email' => ['required', 'email:rfc', 'max:160']], ['email.required' => 'Escribe el correo de tu trabajador.', 'email.email' => 'Ese correo no es válido.']);
        if ($store->user && mb_strtolower((string) $store->user->email) === $email) {
            return back()->withInput()->withErrors(['email' => 'Ese correo es el del dueño del negocio: ya tiene todo el acceso.']);
        }
        if (StoreMember::where('store_id', $store->id)->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            return back()->withInput()->withErrors(['email' => 'Esa persona ya está en tu equipo.']);
        }

        $member = StoreMember::create($data + ['store_id' => $store->id, 'email' => $email, 'invited_by' => Auth::id()]);
        if ($existing = User::whereRaw('LOWER(email) = ?', [$email])->first()) {
            $this->team->claimInvites($existing);
        }

        $member->refresh();

        return redirect()->route('dashboard.team.index')->with('success', $member->user_id
            ? $member->name . ' ya tiene su Tribio Pass: entrará a ' . $store->name . ' desde su cuenta.'
            : 'Listo. Pídele a esa persona que cree su Tribio Pass con Google usando ' . $email . ': al entrar verá «Ingresar a ' . $store->name . '».');
    }

    public function update(Request $request, StoreMember $member)
    {
        $store = $this->business();
        abort_unless((int) $member->store_id === (int) $store->id, 404);

        $member->update($this->validated($request, $store, $member) + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('dashboard.team.index')->with('success', 'Cambios guardados.');
    }

    public function destroy(StoreMember $member)
    {
        $store = $this->business();
        abort_unless((int) $member->store_id === (int) $store->id, 404);

        if ($member->isAdmin() && $this->team->roleIn(Auth::user(), $store) !== 'owner') {
            abort(403, 'Solo el dueño puede quitar a un administrador.');
        }
        $member->delete();

        return redirect()->route('dashboard.team.index')->with('success', 'Esa persona ya no tiene acceso a ' . $store->name . '.');
    }

    /** @return array{name: ?string, role: string, modules: list<string>} */
    private function validated(Request $request, $store, ?StoreMember $member = null): array
    {
        $isOwner = $this->team->roleIn(Auth::user(), $store) === 'owner';
        $request->validate([
            'name'      => ['nullable', 'string', 'max:80'],
            'role'      => ['required', Rule::in([StoreMember::SELLER, StoreMember::ADMIN])],
            'modules'   => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(array_keys(TeamAccess::MODULES))],
        ]);

        $role = $request->input('role');
        if ($role === StoreMember::ADMIN && !$isOwner) {
            // Only the owner hands out the admin role (an edit that keeps it stays as it was).
            $role = $member && $member->isAdmin() ? StoreMember::ADMIN : StoreMember::SELLER;
        }

        return [
            'name'    => $request->filled('name') ? trim($request->input('name')) : null,
            'role'    => $role,
            'modules' => array_values(array_intersect(array_keys(TeamAccess::MODULES), (array) $request->input('modules', []))),
        ];
    }

    private function business()
    {
        $store = Auth::user()->currentStore();
        abort_unless($store && $this->team->manages(Auth::user(), $store), 403);

        return $store;
    }
}
