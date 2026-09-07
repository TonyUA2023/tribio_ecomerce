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
            return response()->json([
                'message' => 'Las credenciales proporcionadas son incorrectas.',
                'errors' => [
                    'email' => ['Las credenciales no coinciden con nuestros registros.']
                ]
            ], 422);
        }

        // Check if user is store_owner or super_admin
        if (!$user->isStoreOwner() && !$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'No tienes permiso para acceder a esta aplicación.',
            ], 403);
        }

        $tokenName = $request->device_name ?? 'mobile-app';
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone,
            ],
            'store' => $user->store ? [
                'id' => $user->store->id,
                'name' => $user->store->name,
                'slug' => $user->store->slug,
                'status' => $user->store->status,
                'logo_url' => $user->store->logo_url,
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
