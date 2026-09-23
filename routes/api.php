<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\PledgeController;
use App\Http\Controllers\SalaBattleController;
use App\Http\Controllers\SocialBetController;
use App\Http\Controllers\WeeklyCommitmentController;
use App\Http\Controllers\WrappedController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\JourneyPhotoController;
use App\Http\Controllers\RoomMessageController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkoutController;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::post('/register',     [AuthController::class, 'register']);
Route::post('/login',        [AuthController::class, 'login']);
Route::post('/auth/google',  [AuthController::class, 'googleLogin']);

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
    Route::get('/challenges/{id}/leaderboard', [ChallengeController::class, 'leaderboard']);
    Route::put('/challenges/{id}',    [ChallengeController::class, 'update']);
    Route::post('/challenges/{id}',   [ChallengeController::class, 'update']); // multipart/form-data para cover_image
    Route::delete('/challenges/{id}', [ChallengeController::class, 'destroy']);

    // Solicitudes de ingreso — salas privadas
    Route::get('/challenges/{id}/requests',                  [ChallengeController::class, 'joinRequests']);
    Route::post('/challenges/{id}/requests/{requestId}/approve', [ChallengeController::class, 'approveRequest']);
    Route::post('/challenges/{id}/requests/{requestId}/reject',  [ChallengeController::class, 'rejectRequest']);

    // Journey — galería de progreso por sala
    Route::get('/challenges/{id}/journey',  [JourneyPhotoController::class, 'index']);
    Route::post('/challenges/{id}/journey', [JourneyPhotoController::class, 'store']);
    Route::delete('/journey/{id}',          [JourneyPhotoController::class, 'destroy']);

    // Asistencia — check-in por ubicación
    Route::post('/challenges/{id}/attend',        [AttendanceController::class, 'store']);
    Route::get('/challenges/{id}/my-attendance',  [AttendanceController::class, 'myAttendance']);
    Route::post('/challenges/{id}/gym-location',  [AttendanceController::class, 'setGymLocation']);

    Route::get('/workouts',    [WorkoutController::class, 'index']);
    Route::post('/workouts',   [WorkoutController::class, 'store']);
    Route::get('/user/stats',     [WorkoutController::class, 'stats']);
    Route::get('/user/weekly',    [WorkoutController::class, 'weekly']);
    Route::post('/user/profile',     [UserController::class, 'updateProfile']);
    Route::post('/user/password',    [UserController::class, 'changePassword']);
    Route::post('/user/push-token',  [UserController::class, 'savePushToken']);
    Route::get('/users',             [UserController::class, 'index']);
    Route::get('/users/{id}',        [UserController::class, 'show']);

    Route::post('/challenges/{id}/attend-camera',        [AttendanceController::class, 'attendCamera']);
    Route::get('/challenges/{id}/pending-attendances',   [AttendanceController::class, 'pendingAttendances']);
    Route::post('/attendances/{id}/confirm',             [AttendanceController::class, 'confirmAttendance']);
    Route::post('/attendances/{id}/reject',              [AttendanceController::class, 'rejectAttendance']);

    // Mensajes de sala
    Route::get('/challenges/{id}/messages',  [RoomMessageController::class, 'index']);
    Route::post('/challenges/{id}/messages', [RoomMessageController::class, 'store']);
    Route::get('/messages/inbox',            [RoomMessageController::class, 'inbox']);
    Route::post('/messages/{id}/read',       [RoomMessageController::class, 'markRead']);
    Route::post('/messages/read-all',        [RoomMessageController::class, 'markAllRead']);

    // Journey — fotos de toda la sala + reacciones
    Route::get('/challenges/{id}/journey/all', [JourneyPhotoController::class, 'allPhotos']);
    Route::post('/journey/{id}/react',         [JourneyPhotoController::class, 'react']);

    // Feed de actividad
    Route::get('/feed',        [UserController::class, 'feed']);

    // Badges / Logros
    Route::get('/user/badges', [BadgeController::class, 'index']);

    // Wrapped
    Route::get('/challenges/{id}/wrapped', [WrappedController::class, 'show']);

    // La Apuesta del Mes
    Route::get('/challenges/{id}/pledges',  [PledgeController::class, 'index']);
    Route::post('/challenges/{id}/pledges', [PledgeController::class, 'store']);

    // Apuestas sociales entre miembros
    Route::get('/challenges/{id}/social-bets',   [SocialBetController::class, 'index']);
    Route::post('/challenges/{id}/social-bets',  [SocialBetController::class, 'store']);
    Route::post('/social-bets/{id}/resolve',     [SocialBetController::class, 'resolve']);
    Route::delete('/social-bets/{id}',           [SocialBetController::class, 'destroy']);

    // Compromiso Semanal
    Route::get('/challenges/{id}/commitments',  [WeeklyCommitmentController::class, 'index']);
    Route::post('/challenges/{id}/commitments', [WeeklyCommitmentController::class, 'store']);

    // Sala vs Sala
    Route::get('/battles',           [SalaBattleController::class, 'index']);
    Route::post('/battles',          [SalaBattleController::class, 'store']);
    Route::post('/battles/{id}/accept', [SalaBattleController::class, 'accept']);
    Route::get('/battles/{id}',      [SalaBattleController::class, 'show']);

    // Admin — solo usuarios con is_admin = true
    Route::middleware('is_admin')->prefix('admin')->group(function () {
        Route::get('/stats',    [AdminController::class, 'stats']);
        Route::get('/weekly',   [AdminController::class, 'weekly']);
        Route::get('/workouts', [AdminController::class, 'workouts']);
        Route::post('/workouts', [AdminController::class, 'storeWorkout']);
        Route::put('/workouts/{id}', [AdminController::class, 'updateWorkout']);
        Route::get('/users/{id}/challenges', [AdminController::class, 'userChallenges']);
        Route::get('/users/{id}/attendance', [AdminController::class, 'userAttendance']);
        Route::post('/users', [AdminController::class, 'storeUser']);
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::post('/challenges', [AdminController::class, 'storeChallenge']);
        Route::put('/challenges/{id}', [AdminController::class, 'updateChallenge']);
        Route::delete('/challenges/{id}', [AdminController::class, 'destroyChallenge']);
        Route::delete('/workouts/{id}', [AdminController::class, 'destroyWorkout']);
        Route::delete('/challenges/{challengeId}/members/{userId}', [AdminController::class, 'removeMember']);
        Route::get('/attendance', [AdminController::class, 'attendanceIndex']);
        Route::delete('/attendance/{id}', [AdminController::class, 'destroyAttendance']);
    });
});
