<?php

namespace App\Http\Middleware;

use App\Services\Pos\PosAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Modo caja" (ERP Fase 4d): on a device handed to the staff only the register screens
 * open, and a locked register only shows its PIN screen. Leaving the mode needs a
 * manager's PIN (PosAccess::disable).
 */
class PosKioskGuard
{
    /** Screens a cashier uses. */
    private const ALLOWED = ['dashboard.pos.*', 'dashboard.quotes.*', 'dashboard.returns.*', 'dashboard.receivables.*'];

    /** Owner-only even inside those groups. */
    private const OWNER_ONLY = ['dashboard.pos.staff.*', 'dashboard.pos.registers.store'];

    /** Reachable while the register is locked. */
    private const WHEN_LOCKED = ['dashboard.pos.index', 'dashboard.pos.unlock', 'dashboard.pos.kiosk.disable', 'dashboard.pos.token'];

    public function __construct(private PosAccess $access)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $store = $request->user()?->currentStore();
        if (!$store || !$this->access->active($store)) {
            return $next($request);
        }

        if (!$request->routeIs(...self::ALLOWED) || $request->routeIs(...self::OWNER_ONLY)) {
            return $this->refuse($request, 'Modo caja activo: solo se puede usar la caja. Para salir, usa el PIN de un encargado.');
        }
        if (!$this->access->current($store) && !$request->routeIs(...self::WHEN_LOCKED)) {
            return $this->refuse($request, 'La caja está bloqueada: ingresa tu PIN.', true);
        }

        $this->access->touch($store);

        return $next($request);
    }

    private function refuse(Request $request, string $message, bool $locked = false): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'locked' => $locked, 'errors' => [$locked ? 'pin' : 'kiosk' => [$message]]], $locked ? 423 : 403);
        }

        return redirect()->route('dashboard.pos.index')->with('error', $message);
    }
}
