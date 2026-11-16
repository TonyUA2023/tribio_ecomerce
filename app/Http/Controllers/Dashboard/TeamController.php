<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\StoreMember;
use App\Models\User;
use App\Services\Team\AccessLog;
use App\Services\Team\TeamAccess;
use App\Services\Team\TeamManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** Equipo: who works in the business with their own Tribio Pass, and what they may open. */
class TeamController extends Controller
{
    public function __construct(private TeamAccess $team, private TeamManager $manager)
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
        $request->validate(['email' => ['required', 'email:rfc', 'max:160']], ['email.required' => 'Escribe el correo de tu trabajador.', 'email.email' => 'Ese correo no es válido.']);
        $data = $this->validated($request);

        try {
            $member = $this->manager->invite($store, Auth::user(), $data + ['email' => $request->input('email')]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('dashboard.team.index')->with('success', $member->user_id
            ? $member->name . ' ya tiene su Tribio Pass: entrará a ' . $store->name . ' desde su cuenta.'
            : 'Listo. Pídele a esa persona que cree su Tribio Pass con Google usando ' . $member->email . ': al entrar verá «Ingresar a ' . $store->name . '».');
    }

    public function update(Request $request, StoreMember $member)
    {
        $this->manager->update($this->business(), Auth::user(), $member, $this->validated($request), $request->boolean('is_active'));

        return redirect()->route('dashboard.team.index')->with('success', 'Cambios guardados.');
    }

    public function destroy(StoreMember $member)
    {
        $store = $this->business();
        $this->manager->remove($store, Auth::user(), $member);

        return redirect()->route('dashboard.team.index')->with('success', 'Esa persona ya no tiene acceso a ' . $store->name . '.');
    }

    /** @return array{name: ?string, role: string, modules: array} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name'      => ['nullable', 'string', 'max:80'],
            'role'      => ['required', Rule::in([StoreMember::SELLER, StoreMember::ADMIN])],
            'modules'   => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(array_keys(TeamAccess::MODULES))],
        ]) + ['modules' => []];
    }

    private function business()
    {
        $store = Auth::user()->currentStore();
        abort_unless($store && $this->team->manages(Auth::user(), $store), 403);

        return $store;
    }
}
