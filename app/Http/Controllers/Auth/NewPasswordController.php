<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;


class NewPasswordController extends Controller
{
    //

public function store(Request $request)
{
    $request->validate([
        'token' => 'required',
        'email' => 'required|email',
        'password' => 'required|min:6|confirmed',
    ]);

    $status = Password::broker('transporteurs')->reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function ($user, $password) {
            $user->forceFill([
                'password' => bcrypt($password)
            ])->save();
        }
    );

    if ($status == Password::PASSWORD_RESET) {
        return response()->json(['message' => '🔒 Mot de passe réinitialisé avec succès.']);
    }

    throw ValidationException::withMessages([
        'email' => [__($status)],
    ]);
}
}
