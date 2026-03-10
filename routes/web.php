<?php

use Illuminate\Support\Facades\Route;


// League Routes
Route::post('/league/generate-plan', [\App\Http\Controllers\LeagueController::class, 'generatePlan']);
Route::post('/league/update-players', [\App\Http\Controllers\LeagueController::class, 'updatePlayers']);
Route::post('/league/update-score', [\App\Http\Controllers\LeagueController::class, 'updateScore']);
Route::post('/league/finish-gameday', [\App\Http\Controllers\LeagueController::class, 'finishGameday']);

// User & Auth Routes
Route::statamic('/login', 'login', ['title' => 'Log In']);
Route::statamic('/register', 'register', ['title' => 'Register'])->middleware('guest');
Route::statamic('/edit-profile', 'edit-profile', ['title' => 'Edit Profile'])->middleware('auth');
Route::post('/upload-avatar', [\App\Http\Controllers\AvatarUploadController::class, 'upload'])->middleware('auth');

// Player Profile Route (Resolves User by slug)
Route::get('/players/{slug}', function ($slug) {
    $user = \Statamic\Facades\User::query()->where('slug', $slug)->first();
    
    if (!$user) {
        abort(404);
    }
    
    return (new \Statamic\View\View)
        ->template('players.show')
        ->layout('layout')
        ->with(['title' => $user->name, 'user' => $user->toAugmentedArray()])
        ->cascadeContent($user);
});
