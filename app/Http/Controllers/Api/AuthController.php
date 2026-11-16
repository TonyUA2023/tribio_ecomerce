<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            $googleOnly = $user && $user->google_id;

            return response()->json([
                'message' => $googleOnly
                    ? 'Esta cuenta usa Google para iniciar sesión. Por ahora, ingresa desde tribioshop.com con el botón de Google.'
                    : 'Las credenciales proporcionadas son incorrectas.',
                'errors' => [
                    'email' => [$googleOnly
                        ? 'Esta cuenta está conectada con Google — no tiene contraseña.'
                        : 'Las credenciales no coinciden con nuestros registros.']
                ]
            ], 422);
        }

        // Owners, super admins, anyone with a business of their own, and team members (sellers/admins of a business).
        if (!$user->isStoreOwner() && !$user->isSuperAdmin() && !$user->hasStore() && !$user->isTeamMember()) {
            return response()->json([
                'message' => 'No tienes permiso para acceder a esta aplicación.',
            ], 403);
        }

        $tokenName = $request->device_name ?? 'mobile-app';
        $token = $user->createToken($tokenName)->plainTextToken;
        // A sign-in from the phone counts in the access log of every business the person works in.
        foreach (app(\App\Services\Team\TeamAccess::class)->workspaces($user) as $workspace) {
            app(\App\Services\Team\AccessLog::class)->record($user, $workspace['store'], 'login', $request);
        }

        // Tribio Pass accounts can now own more than one store; the mobile app (not yet
        // updated for multi-store) still gets a single `store` key — the account's
        // current/default one — to stay backward-compatible.
        $currentStore = $user->currentStore();

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
            ],
            'store' => $currentStore ? [
                'id' => $currentStore->id,
                'name' => $currentStore->name,
                'slug' => $currentStore->slug,
                'status' => $currentStore->status,
                'logo_url' => $currentStore->logo_url,
            ] : null,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada con éxito.'
        ]);
    }
}
