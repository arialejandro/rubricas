<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Mi cuenta: nombre, correo y contraseña de la maestra en sesión. */
class AccountController extends Controller
{
    public function edit(): View
    {
        return view('account.edit', ['user' => Auth::user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users')->ignore($user->id)],
        ], ['email.unique' => 'Ese correo ya lo usa otra cuenta.']);

        $user->update($data);

        return back()->with('status', 'Datos actualizados.');
    }

    /** Cambiar contraseña: se pide la actual; las demás sesiones abiertas se cierran. */
    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ], [
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.confirmed' => 'La contraseña nueva y su confirmación no coinciden.',
            'password.different' => 'La contraseña nueva debe ser distinta de la actual.',
            'password.min' => 'La contraseña nueva debe tener al menos 8 caracteres.',
        ]);

        $request->user()->update(['password' => $data['password']]);
        Auth::logoutOtherDevices($data['password']);

        return back()->with('status', 'Contraseña cambiada. Las otras sesiones abiertas se cerraron.');
    }
}
