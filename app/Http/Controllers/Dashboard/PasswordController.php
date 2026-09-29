<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateAccountEmailRequest;
use App\Mail\AccountEmailChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PasswordController extends Controller
{
    public function edit()
    {
        return view('dashboard.password.edit', [
            'googleReady' => filled(config('services.google.client_id')),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update(['password' => $request->password]);

        return back()->with('success', 'Contraseña actualizada correctamente.');
    }

    /** The owner's login e-mail; the previous address is told about the change. */
    public function updateEmail(UpdateAccountEmailRequest $request)
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

        return back()->with('success', "Listo: desde ahora inicias sesión con {$user->email}.");
    }
}
