<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    //
    public function store(Request $request)
    {
        $request->validate(['email' => 'required|email']);

       $status = Password::broker('transporteurs')->sendResetLink(
    $request->only('email')
);


        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Un lien de réinitialisation a été envoyé à votre adresse email.'])
            : response()->json(['message' => 'Erreur lors de l’envoi du lien.'], 400);
    }
}
