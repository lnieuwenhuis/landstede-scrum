<?php

use App\Http\Controllers\BoardController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome'); // Matches Welcome.vue
});

Route::resource('groups', GroupController::class)->middleware('auth');
Route::resource('boards', BoardController::class)->middleware('auth');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('groups', GroupController::class);

    Route::resource('boards', BoardController::class);
});


// API ROUTES
Route::middleware(['auth', 'verified'])->group(function () {
    //Board API Routes
    Route::get('/api/{columnId}/cards', [BoardController::class, 'getColumnCards']);
    Route::get('/api/addUserToGroup/{groupId}/{email}', [GroupController::class, 'addUser']);
    Route::get('/api/removeUserFromGroup/{groupId}/{userId}', [GroupController::class, 'removeUser']);
    Route::post('/api/addCardToColumn/{columnId}', [BoardController::class, 'addCardToColumn']);
    Route::post('/api/updateCard/{cardId}', [BoardController::class, 'updateCard']);
    Route::post('/api/deleteCard/{cardId}', [BoardController::class, 'deleteCard']);
    Route::post('/api/addColumn', [BoardController::class, 'addColumn']);
    Route::post('/api/deleteColumn', [BoardController::class, 'deleteColumn']);
    Route::post('/api/cards/{cardId}/move', [BoardController::class, 'moveCardToColumn']);

    //Group API Routes
    Route::get('/api/users/{groupId}', [GroupController::class, 'getUsers']);
    Route::post('/api/users/{groupId}/add', [GroupController::class, 'addUserToGroup']);
    Route::post('/api/users/{groupId}/remove', [GroupController::class, 'removeUser']);
});

require __DIR__.'/auth.php';