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
Route::middleware('auth:sanctum')->post('/logout', [UserController::class, 'logout']);

// ✅ Admin protégé
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/admin/dashboard', fn() => response()->json(['message' => 'Welcome, Admin']));

    // --- Gestion transporteurs
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
    Route::post('/admin/transporteurs/{id}/en-attente', [AuthController::class, 'remettreEnAttente']);

    // --- Gestion abonnements
    Route::get('/admin/abonnements', [AbonnementController::class, 'index']);
    Route::post('/admin/abonnements/{id}/valider', [AbonnementController::class, 'valider']);
    Route::post('/admin/abonnements/{id}/refuser', [AbonnementController::class, 'refuser']);
});

/*
|--------------------------------------------------------------------------
| 👤 Transporteur (Abonnement / Profil)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/demande-abonnement', [AbonnementController::class, 'demande']);
    Route::get('/abonnement-statut', [AbonnementController::class, 'statut']);
    Route::post('/abonnement/checkout', [AbonnementController::class, 'checkout']);

    Route::post('/transporteur/update_profil', [AuthController::class, 'updateProfil']);
    Route::post('/transporteur/update_status', [AuthController::class, 'updateStatus']);
});
/*
|--------------------------------------------------------------------------
| 🚚 Transporteur routes protégées par abonnement
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'check.abonnement'])->prefix('transporteur')->group(function () {
    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // Réservations via notifications
    Route::get('/reservations-from-notifications', [NotificationController::class, 'getReservationsFromNotifications']);
    Route::get('/reservations/{id}', [NotificationController::class, 'show']);
    Route::put('/reservations/{id}', [NotificationController::class, 'update']);
    Route::get('/reservations/historique', [NotificationController::class, 'historiqueReservations']);
    Route::put('/historique/{id}', [NotificationController::class, 'update_statut']);
});

/*
|--------------------------------------------------------------------------
| 👤 Clients (Admin lite)
|--------------------------------------------------------------------------
*/
Route::get('/clients', [AuthController::class, 'getClients']);
Route::get('/clients/{id}', [AuthController::class, 'show']);

/*
|--------------------------------------------------------------------------
| 🌐 Auth Google (Transporteur & Client)
|--------------------------------------------------------------------------
*/
Route::get('transporteur/redirect', [TransporteurGoogleController::class, 'redirectToGoogle']);
Route::get('transporteur/callback', [TransporteurGoogleController::class, 'handleGoogleCallback']);
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
| 🔁 Mot de passe oublié (Transporteur)
|--------------------------------------------------------------------------
*/
Route::post('/forgot-password', function (Request $request) {
    $request->validate(['email' => 'required|email']);
    $user = Transporteur::where('email', $request->email)->first();
    if (! $user) {
        return response()->json(['message' => '❌ Aucun transporteur trouvé avec cet email.'], 404);
    }
    $status = Password::broker('transporteurs')->sendResetLink($request->only('email'));
    return $status === Password::RESET_LINK_SENT
        ? response()->json(['message' => '📧 Lien de réinitialisation envoyé.'])
        : response()->json(['message' => '❌ Erreur lors de l’envoi du lien.'], 400);
});
Route::get('/reset-password/{token}', function ($token, Request $request) {
    $email = $request->query('email');
    return redirect()->away("http://localhost:5173/reset_password?token={$token}&email={$email}");
})->name('password.reset');
Route::post('/reset-password', [NewPasswordController::class, 'store']);

/*
|--------------------------------------------------------------------------
| 📅 Réservations (Clients)
|--------------------------------------------------------------------------
*/
Route::post('/reservations', [ReservationController::class, 'store']);
Route::get('/reservations/client/{id}/exists', [ReservationController::class, 'hasClientReservation']);
Route::get('/reservations/client/{id}/latest', [ReservationController::class, 'latest']);
Route::middleware('auth:sanctum')->get('/reservations/client/all', [ReservationController::class, 'listByClient']);
Route::middleware('auth:sanctum')->get('/reservations/{id}', [ReservationController::class, 'show']);
Route::middleware('auth:sanctum')->put('/reservations/{id}', [ReservationController::class, 'update']);
Route::post('/reservation/client/update', [ReservationController::class, 'updateMyReservation']);
Route::delete('/reservation/client/destroy/{id}', [ReservationController::class, 'destroy']);

// Notifications côté client
Route::middleware('auth:sanctum')->get('/client/notifications', function (Request $request) {
    return $request->user()->notifications;
});
