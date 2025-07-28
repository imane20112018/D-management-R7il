<?php

use App\Models\Transporteur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Password;
use App\Http\Controllers\ClientGoogleController;
use App\Http\Controllers\API\ReservationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\TransporteurGoogleController;

/*
|--------------------------------------------------------------------------
| 🔐 Authentification Admin (User)
|--------------------------------------------------------------------------
*/

Route::post('/login', [UserController::class, 'login'])->name('login');
Route::middleware('auth:sanctum')->get('/me', [UserController::class, 'me']);  //Returns user info using $request->user() and Knowing who's logged in, redirecting by role, etc.
Route::middleware('auth:sanctum')->post('/logout', [UserController::class, 'logout']);

Route::middleware(['auth:sanctum', 'admin'])->get('/admin/dashboard', function () {
    return response()->json(['message' => 'Welcome, Admin']);
});
//Route::middleware(['auth:sanctum', 'admin'])->get('/admin/clients', [AuthController::class, 'getClients']);
Route::get('/clients', [AuthController::class, 'getClients']);


/*
|--------------------------------------------------------------------------
| 👤 Authentification Transporteur (Vue.js Sanctum)
|--------------------------------------------------------------------------
*/

Route::prefix('transporteur')->group(function () {

    // Étape obligatoire pour Sanctum (CSRF cookie)
    Route::get('/sanctum/csrf-cookie', function () {
        return response()->json(['message' => 'CSRF cookie set']);
    });

    // 🔐 Auth publique
    Route::post('/register_client', [AuthController::class, 'register'])->middleware('guest');
    Route::post('/login_client', [AuthController::class, 'login']);

    // 🔐 Accès profil après authentification
    Route::middleware('auth:sanctum')->get('/profil_client', function (Request $request) {
        return $request->user();
    });

    // 🔓 Déconnexion
    Route::middleware('auth:sanctum')->post('/logout_client', [AuthController::class, 'logout']);
});


/*
|--------------------------------------------------------------------------
| 🌐 Auth Google (Transporteur & Client)
|--------------------------------------------------------------------------
*/

// Transporteur Google Auth
Route::get('transporteur/redirect', [TransporteurGoogleController::class, 'redirectToGoogle']);
Route::get('transporteur/callback', [TransporteurGoogleController::class, 'handleGoogleCallback']);

// Client Google Auth
Route::prefix('client')->group(function () {
    Route::get('/redirect', [ClientGoogleController::class, 'redirectToGoogle']);
    Route::get('/callback', [ClientGoogleController::class, 'handleGoogleCallback']);
});


/*
|--------------------------------------------------------------------------
| ✅ Vérification Email (Transporteur)
|--------------------------------------------------------------------------
*/

Route::get('/api/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
    $user = Transporteur::findOrFail($id);

    if (! hash_equals((string) $hash, sha1($user->email))) {
        return response()->json(['message' => 'Lien invalide'], 403);
    }

    if ($user->hasVerifiedEmail()) {
        return redirect('http://localhost:5173/login_client?verified=1');
    }

    $user->email_verified_at = now();
    $user->save();

    return redirect('http://localhost:5173/login_client?verified=1');
})->name('verification.verify');


/*
|--------------------------------------------------------------------------
| 🔁 Mot de passe oublié (Forgot Password - Transporteur)
|--------------------------------------------------------------------------
*/

// Envoi du lien de réinitialisation (API)
Route::post('/forgot-password', function (Request $request) {
    $request->validate(['email' => 'required|email']);

    $user = Transporteur::where('email', $request->email)->first();
    if (! $user) {
        return response()->json(['message' => '❌ Aucun transporteur trouvé avec cet email.'], 404);
    }

    $status = Password::broker('transporteurs')->sendResetLink(
        $request->only('email')
    );

    return $status === Password::RESET_LINK_SENT
        ? response()->json(['message' => '📧 Lien de réinitialisation envoyé.'])
        : response()->json(['message' => '❌ Erreur lors de l’envoi du lien.'], 400);
});

// Redirection frontend après clic sur le lien reçu par email
Route::get('/reset-password/{token}', function ($token, Request $request) {
    $email = $request->query('email');
    return redirect()->away("http://localhost:5173/reset_password?token={$token}&email={$email}");
})->name('password.reset');

// Réception du nouveau mot de passe + validation (API)
Route::post('/reset-password', [NewPasswordController::class, 'store']);
Route::middleware('auth:sanctum')->post('/transporteur/update_profil', [AuthController::class, 'updateProfil']);
Route::middleware('auth:sanctum')->post('/transporteur/update_status', [AuthController::class, 'updateStatus']);
<<<<<<< HEAD
=======


Route::post('/reservations', [ReservationController::class, 'store']);

Route::get('/reservations/client/{id}/exists', [ReservationController::class, 'hasClientReservation']);
Route::get('/reservations/client/{id}/latest', [ReservationController::class, 'latest']);

Route::middleware('auth:sanctum')->get('/reservations/client/all', [ReservationController::class, 'listByClient']);

Route::middleware('auth:sanctum')->get('/reservations/{id}', [ReservationController::class, 'show']);
Route::middleware('auth:sanctum')->put('/reservations/{id}', [ReservationController::class, 'update']);
Route::post('/reservation/client/update', [ReservationController::class, 'updateMyReservation']);
Route::delete('/reservation/client/destroy/{id}', [ReservationController::class, 'destroy']);




>>>>>>> 316e501 (Ajout de la modification réservation)
