<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateAccountPasswordRequest;
use App\Http\Requests\Dashboard\UpdateAccountEmailRequest;
use App\Mail\AccountEmailChanged;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Mi cuenta: the owner's Tribio Pass login (password and e-mail). */
class AccountController extends Controller
{
    public function show(): JsonResponse
    {
        $user = request()->user();

        return response()->json(['account' => [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role'  => $user->role,
            // A Google-linked account signs in with Google: no password/e-mail to change here.
            'uses_google' => (bool) $user->google_id,
        ]]);
    }

    public function updatePassword(UpdateAccountPasswordRequest $request): JsonResponse
    {
        $request->user()->update(['password' => $request->password]);

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }

    /** The previous address is told about the change, like on the web. */
    public function updateEmail(UpdateAccountEmailRequest $request): JsonResponse
    {
        $user = $request->user();
        $previous = $user->email;
        $user->forceFill(['email' => $request->validated('email')])->save();

        if ($previous !== $user->email) {
            try {
                Mail::to($previous)->queue(new AccountEmailChanged($user->name, $user->email));
            } catch (\Throwable $e) {
                Log::warning('No se pudo avisar del cambio de correo al correo anterior.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'message' => "Listo: desde ahora inicias sesión con {$user->email}.",
            'email'   => $user->email,
        ]);
    }
}
