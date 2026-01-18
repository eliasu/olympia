<?php

use Illuminate\Support\Facades\Route;


// League Routes
Route::post('/league/generate-plan', [\App\Http\Controllers\LeagueController::class, 'generatePlan']);
Route::post('/league/update-players', [\App\Http\Controllers\LeagueController::class, 'updatePlayers']);
Route::post('/league/update-score', [\App\Http\Controllers\LeagueController::class, 'updateScore']);
Route::post('/league/finish-gameday', [\App\Http\Controllers\LeagueController::class, 'finishGameday']);

// Route::statamic('example', 'example-view', [
//    'title' => 'Example'
// ]);
