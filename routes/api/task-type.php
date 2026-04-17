<?php

use App\Http\Controllers\TaskTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt')->group(function () {
    Route::get('/', [TaskTypeController::class, 'index']);
    Route::get('/{id}', [TaskTypeController::class, 'show']);
    Route::post('/', [TaskTypeController::class, 'store']);
    Route::put('/{id}', [TaskTypeController::class, 'update']);
    Route::delete('/{id}', [TaskTypeController::class, 'delete']);
});
