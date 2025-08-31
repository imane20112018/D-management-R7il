<?php

use App\Http\Controllers\AbonnementController;
use App\Models\Transporteur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Password;
use App\Http\Controllers\ClientGoogleController;
use App\Http\Controllers\API\ReservationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\TransporteurGoogleController;

/*
|--------------------------------------------------------------------------
| 🔐 Authentification Admin (User)
|--------------------------------------------------------------------------
*/

Route::post('/login', [UserController::class, 'login']);
Route::middleware('auth:sanctum')->get('/me', [AuthController::class, 'me']);
//Returns user info using $request->user() and Knowing who's logged in, redirecting by role, etc.
Route::middleware('auth:sanctum')->post('/logout', [UserController::class, 'logout']);
Route::middleware(['auth:sanctum', 'admin'])->get('/admin/dashboard', function () {
    return response()->json(['message' => 'Welcome, Admin']);
});
// Route::middleware(['auth:sanctum', 'admin'])->get('/admin/transporteurs/attente', [AuthController::class, 'getTransporteursEnAttente']);
// Route::middleware(['auth:sanctum', 'admin'])->get('/admin/transporteurs/incomplets', [AuthController::class, 'getTransporteursIncomplets']);
// Route::middleware(['auth:sanctum', 'admin'])->get('/admin/transporteurs/valide', [AuthController::class, 'getTransporteursValide']);

Route::get('admin/transporteurs/incomplets', [AuthController::class, 'getTransporteursIncomplets']);
Route::get('/admin/transporteurs/valides', [AuthController::class, 'getTransporteursValides']);
Route::get('/admin/transporteurs/refuses', [AuthController::class, 'refuses']);
Route::get('/admin/transporteurs/attente', [AuthController::class, 'getTransporteursEnAttente']);
Route::delete('/admin/transporteurs/incomplets/{id}', [AuthController::class, 'deleteTransporteurIncomplet']);
Route::delete('/admin/transporteurs/{id}', [AuthController::class, 'deleteTransporteurIncomplet']);

Route::get('/admin/transporteurs/{id}', [AuthController::class, 'showTransporteur']);
Route::put('/admin/transporteurs/{id}/statut', [AuthController::class, 'updateStatut']);
Route::get('admin/transporteurs/{id}', [AuthController::class, 'getTransporteurById']);
Route::post('admin/transporteurs/{id}/valider', [AuthController::class, 'validerTransporteur']);
Route::post('admin/transporteurs/{id}/refuser', [AuthController::class, 'refuserTransporteur']);
Route::post('/admin/transporteurs/{id}/en-attente', [AuthController::class, 'remettreEnAttente']); // ✅ ajoute ça

// --- Transporteur ---
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/demande-abonnement', [AbonnementController::class, 'demande']);
    Route::get('/abonnement-statut', [AbonnementController::class, 'statut']);
    Route::post('/abonnement/checkout', [AbonnementController::class, 'checkout']);
    
});

//--- Admin ---
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/admin/abonnements', [AbonnementController::class, 'index']);
    Route::post('/admin/abonnements/{id}/valider', [AbonnementController::class, 'valider']);
    Route::post('/admin/abonnements/{id}/refuser', [AbonnementController::class, 'refuser']);

    Route::get('/abonnements/valide', [AbonnementController::class, 'valide']);
    Route::patch('/abonnements/{id}/resilier', [AbonnementAdminController::class, 'resilier']);
});
// Route::post('/abonnements/demande', [AbonnementController::class, 'demande']);
//Route::middleware(['auth:sanctum', 'admin'])->get('/admin/clients', [AuthController::class, 'getClients']);
Route::get('/clients', [AuthController::class, 'getClients']);
Route::get('/clients/{id}', [AuthController::class, 'show']);

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


Route::post('/reservations', [ReservationController::class, 'store']);

Route::get('/reservations/client/{id}/exists', [ReservationController::class, 'hasClientReservation']);
Route::get('/reservations/client/{id}/latest', [ReservationController::class, 'latest']);

Route::middleware('auth:sanctum')->get('/reservations/client/all', [ReservationController::class, 'listByClient']);

Route::middleware('auth:sanctum')->get('/reservations/{id}', [ReservationController::class, 'show']);
Route::middleware('auth:sanctum')->put('/reservations/{id}', [ReservationController::class, 'update']);
Route::post('/reservation/client/update', [ReservationController::class, 'updateMyReservation']);
Route::delete('/reservation/client/destroy/{id}', [ReservationController::class, 'destroy']);
Route::middleware('auth:sanctum')->get('/transporteur/notifications', function (Request $request) {
    return $request->user()->notifications;
});
Route::middleware('auth:sanctum')->prefix('transporteur')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
});

Route::middleware('auth:sanctum')->prefix('transporteur')->group(function () {
    Route::get('/reservations-from-notifications', [NotificationController::class, 'getReservationsFromNotifications']);
});

Route::middleware('auth:sanctum')->delete('/delete/notifications/{id}', [NotificationController::class, 'destroy']);

Route::middleware('auth:sanctum')->get('/transporteur/reservations/historique', [NotificationController::class, 'historiqueReservations']);
Route::middleware('auth:sanctum')->put('/transporteur/historique/{id}', [NotificationController::class, 'update_statut']);


Route::middleware('auth:sanctum')->prefix('transporteur')->group(function () {
    Route::get('/reservations/{id}', [NotificationController::class, 'show']);
    Route::put('/reservations/{id}', [NotificationController::class, 'update']);
});

Route::get('/client/notifications', function (Request $request) {
    return $request->user()->notifications;
})->middleware('auth:sanctum');

