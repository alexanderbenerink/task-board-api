<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BoardController;
use App\Http\Controllers\API\TaskController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('boards', BoardController::class);
    Route::apiResource('tasks', TaskController::class);
    Route::patch('/tasks/{task}/move', [TaskController::class, 'move']);
});
