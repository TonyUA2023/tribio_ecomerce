<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoreMember;
use App\Services\Team\AccessLog;
use App\Services\Team\TeamAccess;
use App\Services\Team\TeamManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Equipo from the phone: the same rules as the web screen (TeamManager), JSON out. */
class TeamController extends Controller
{
    public function __construct(private TeamAccess $team, private TeamManager $manager)
    {
    }

    public function index(Request $request, AccessLog $log): JsonResponse
    {
        $store = $this->business($request);
        $owner = $store->user;
        $members = StoreMember::where('store_id', $store->id)->with('user:id,name,email')->orderBy('id')->get();
        $stats = $log->summary($store, array_values(array_filter(array_merge([(int) $store->user_id], $members->pluck('user_id')->all()))));

        $person = fn (?int $userId, array $base) => $base + $this->access($userId ? ($stats[$userId] ?? null) : null, (bool) $userId);

        return response()->json([
            'owner'   => $person($store->user_id, ['name' => $owner?->name ?? 'Dueño', 'email' => $owner?->email, 'role' => 'owner']),
            'members' => $members->map(fn (StoreMember $m) => $person($m->user_id, [
                'id' => $m->id, 'name' => $m->user?->name ?? $m->name ?? $m->email, 'email' => $m->email,
                'role' => $m->role, 'modules' => $m->modules ?? TeamAccess::defaultModules(), 'is_active' => $m->is_active,
                'has_account' => (bool) $m->user_id,
            ]))->values(),
            'summary' => [
                'people' => $members->count() + 1,
                'today'  => collect($stats)->filter(fn ($s) => AccessLog::health($s['last_at']) === 'today')->count(),
            ],
            'modules' => collect(TeamAccess::MODULES)->map(fn (array $m, string $k) => ['key' => $k, 'label' => $m['label'], 'hint' => $m['hint'], 'default' => $m['default']])->values(),
            'can_create_admin' => $this->team->roleIn($request->user(), $store) === 'owner',
            'log_ready' => AccessLog::ready(),
        ]);
    }

    public function activity(Request $request, AccessLog $log): JsonResponse
    {
        $store = $this->business($request);
        $rows = $log->recent($store, $request->integer('persona') ?: null, 40);

        return response()->json(['data' => $rows->map(fn ($r) => [
            'id' => $r->id, 'user' => $r->user?->name ?? 'Usuario', 'type' => $r->type, 'device' => $r->device,
            'at' => $r->created_at?->toIso8601String(), 'label' => AccessLog::when($r->created_at),
        ])->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $this->business($request);
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:160']] + $this->rules());
        $member = $this->manager->invite($store, $request->user(), $data);

        return response()->json([
            'message' => $member->user_id
                ? ($member->name ?: $member->email) . ' ya tiene su Tribio Pass: entrará a ' . $store->name . ' desde su cuenta.'
                : 'Listo. Pídele que cree su Tribio Pass con Google usando ' . $member->email . '.',
            'id' => $member->id,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $store = $this->business($request);
        $member = StoreMember::where('store_id', $store->id)->findOrFail($id);
        $data = $request->validate($this->rules());
        $this->manager->update($store, $request->user(), $member, $data, $request->boolean('is_active', true));

        return response()->json(['message' => 'Cambios guardados.']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $store = $this->business($request);
        $member = StoreMember::where('store_id', $store->id)->findOrFail($id);
        try {
            $this->manager->remove($store, $request->user(), $member);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json(['message' => 'Esa persona ya no tiene acceso a ' . $store->name . '.']);
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'name'      => ['nullable', 'string', 'max:80'],
            'role'      => ['required', Rule::in([StoreMember::SELLER, StoreMember::ADMIN])],
            'modules'   => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(array_keys(TeamAccess::MODULES))],
        ];
    }

    /** Last access of a person as the phone shows it. */
    private function access(?array $stat, bool $hasAccount): array
    {
        $at = $stat['last_at'] ?? null;

        return [
            'status'     => $hasAccount ? AccessLog::health($at) : 'pending',
            'last_label' => $hasAccount ? AccessLog::when($at) : null,
            'last_at'    => $at?->toIso8601String(),
            'days_7d'    => $stat['days_7d'] ?? 0,
        ];
    }

    private function business(Request $request)
    {
        $store = $request->user()->currentStore();
        abort_unless($store && $this->team->manages($request->user(), $store), 403, 'Solo el dueño o un administrador puede ver el equipo.');

        return $store;
    }
}
