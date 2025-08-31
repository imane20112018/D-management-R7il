
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
