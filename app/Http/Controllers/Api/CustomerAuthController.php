<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CustomerIdentityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Tribio Pass buyer identity for the mobile app — the token-based counterpart
 * to the web's session-based CustomerPortalController. Kept separate from
 * Api\AuthController (the store-owner/super_admin-only mobile dashboard door)
 * so that door's contract never changes: `login()` here works for ANY
 * canUseCustomerPortal() account, including an existing store_owner shopping
 * as a buyer (the actual "unified identity" part of Tribio Pass).
 */
class CustomerAuthController extends Controller
{
    public function sendOtp(Request $request, CustomerIdentityService $identity)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'phone'    => 'nullable|string|max:25',
            'password' => 'required|string|min:6',
        ]);

        $identity->sendRegistrationOtp($request->name, trim(strtolower($request->email)));

        return response()->json([
            'success' => true,
            'message' => 'Código enviado. Por favor revisa tu correo electrónico.',
        ]);
    }

    public function verifyOtp(Request $request, CustomerIdentityService $identity)
    {
        $request->validate([
            'email'    => 'required|email|max:255',
            'token'    => 'required|string|size:6',
            'name'     => 'required|string|max:255',
            'phone'    => 'nullable|string|max:25',
            'password' => 'required|string|min:6',
        ]);

        $email = trim(strtolower($request->email));

        if (!$identity->verifyRegistrationOtp($email, $request->token)) {
            return response()->json([
                'success' => false,
                'message' => 'El código ingresado es incorrecto o ha expirado.',
            ], 422);
        }

        if (User::where('email', $email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'El correo electrónico ya está registrado.',
            ], 422);
        }

        $user = $identity->registerCustomer([
            'name'     => $request->name,
            'email'    => $email,
            'phone'    => $request->phone,
            'password' => $request->password,
        ]);

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
                'phone' => $user->phone,
            ],
            'store' => null,
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'       => 'required|email',
            'password'    => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        $user = User::where('email', trim(strtolower($request->email)))->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Las credenciales proporcionadas son incorrectas.',
                'errors'  => [
                    'email' => ['Las credenciales no coinciden con nuestros registros.'],
                ],
            ], 422);
        }

        if (!$user->canUseCustomerPortal()) {
            return response()->json([
                'message' => 'Esta cuenta pertenece a la administración de la plataforma.',
            ], 403);
        }

        $tokenName = $request->device_name ?? 'mobile-app';
        $token = $user->createToken($tokenName)->plainTextToken;
        $store = $user->currentStore();

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
                'phone' => $user->phone,
            ],
            'store' => $store ? [
                'id'       => $store->id,
                'name'     => $store->name,
                'slug'     => $store->slug,
                'status'   => $store->status,
                'logo_url' => $store->logo_url,
            ] : null,
        ]);
    }

    public function orders(Request $request, CustomerIdentityService $identity)
    {
        if (!$request->user()->canUseCustomerPortal()) {
            return response()->json(['success' => false, 'message' => 'No autenticado como cliente.', 'orders' => []], 403);
        }

        return response()->json([
            'success' => true,
            'orders'  => $identity->ordersFor($request->user()),
        ]);
    }
}
