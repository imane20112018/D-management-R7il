<?php

use Illuminate\Http\Request;
use Illuminate\Types\Relations\Role;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('register', [UserController::class, 'register']);
Route::post('login', [UserController::class, 'login']);
Route::post('logout', [UserController::class, 'logout'])->middleware('auth:sanctum');




//transporteur routes
Route::prefix('transporteur')->group(function () {
    Route::middleware('guest')->post('/register', [AuthController::class, 'register']);
    Route::middleware('guest')->post('/login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->get('/profil', [AuthController::class, 'user']);
    Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
});
