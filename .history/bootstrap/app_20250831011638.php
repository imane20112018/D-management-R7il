<?php

use App\Http\Middleware\IsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule; // <-- ajoute cette ligne
use App\Models\Transporteur;
use App\Http\Middleware\CheckAbonnement;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => IsAdmin::class,
                'check.abonnement' => CheckAbonnement::class,

        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // ✅ Vérifier les abonnements expirés tous les jours à minuit
        $schedule->call(function () {
            // 1) Marquer les abonnements expirés
            \App\Models\Abonnement::where('statut', 'valide')
                ->whereDate('date_fin', '<', now())
                ->update(['statut' => 'expire']);

            // 2) Remettre les transporteurs sans pack actif en "en_attente"
            \App\Models\Transporteur::whereIn('abonnement_actif', ['free_14_days', 'pack_1_month', 'pack_6_months', 'pack_1_year'])
                ->whereDate('date_fin_essai', '<', now())
                ->update(['abonnement_actif' => 'en_attente']);
        })->daily();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
