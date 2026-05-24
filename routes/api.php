<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkoutController;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// Buscar desafío por código (no requiere login)
Route::get('/challenges/code/{code}', [ChallengeController::class, 'findByCode']);

// Rutas protegidas (requieren token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    Route::get('/challenges',               [ChallengeController::class, 'index']);
    Route::post('/challenges',              [ChallengeController::class, 'store']);
    Route::get('/challenges/mine',          [ChallengeController::class, 'mine']);
    Route::post('/challenges/join/{code}',  [ChallengeController::class, 'join']);

    Route::get('/workouts',    [WorkoutController::class, 'index']);
    Route::post('/workouts',   [WorkoutController::class, 'store']);
    Route::get('/user/stats',    [WorkoutController::class, 'stats']);
    Route::get('/user/weekly',   [WorkoutController::class, 'weekly']);
    Route::post('/user/profile', [UserController::class, 'updateProfile']);
});
