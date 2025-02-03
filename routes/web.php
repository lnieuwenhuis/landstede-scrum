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
    return view('dashboard');
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
Route::get('/api/{columnId}/cards', [BoardController::class, 'getColumnCards']);

Route::get('/api/addUserToGroup/{groupId}/{email}', [GroupController::class, 'addUser']);
Route::get('api/removeUserFromGroup/{groupId}/{userId}', [GroupController::class, 'removeUser']);

Route::get('/api/addCardToColumn/{title}/{description}/{points}/{columnId}', [BoardController::class, 'addCardToColumn']);
Route::get('/api/updateCardInColumn/{cardId}/{title}/{description}/{points}', [BoardController::class, 'updateCardInColumn']);
Route::get('/api/deleteCard/{cardId}', [BoardController::class, 'deleteCard']);

Route::post('api/addColumn', [BoardController::class, 'addColumn']);

require __DIR__.'/auth.php';