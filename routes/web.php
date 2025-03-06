<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VacationController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome'); // Matches Welcome.vue
});

Route::get('/dashboard', [BoardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('boards', BoardController::class);

    Route::get('/admin/vacations', [VacationController::class, 'index'])->name('admin.vacations');
});

// API ROUTES
Route::middleware(['auth', 'verified'])->group(function () {
    //Board API Routes
    Route::get('/api/{columnId}/cards', [BoardController::class, 'getColumnCards']);
    Route::get('/api/addUserToGroup/{groupId}/{email}', [GroupController::class, 'addUser']);
    Route::post('/api/addCardToColumn/{columnId}', [BoardController::class, 'addCardToColumn']);
    Route::post('/api/updateCard/{cardId}', [BoardController::class, 'updateCard']);
    Route::post('/api/deleteCard/{cardId}', [BoardController::class, 'deleteCard']);
    Route::post('/api/addColumn', [BoardController::class, 'addColumn']);
    Route::post('/api/deleteColumn', [BoardController::class, 'deleteColumn']);
    Route::post('/api/cards/{cardId}/move', [BoardController::class, 'moveCardToColumn']);
    Route::post('/api/boards/storeBoard', [BoardController::class,'storeBoard']);
    Route::post('/api/boards/deleteBoard', [BoardController::class,'deleteBoard']);

    //Group API Routes
    Route::get('/api/users/{groupId}', [GroupController::class, 'getUsers']);
    Route::post('/api/users/{groupId}/add', [GroupController::class, 'addUserToGroup']);
    Route::post('/api/users/{groupId}/remove', [GroupController::class, 'removeUser']);
    Route::post('/api/groups/createGroup', [GroupController::class,'store']);
    Route::post('/api/groups/deleteGroup', [GroupController::class,'destroy']);
    Route::post('/api/groups/removeUser', [GroupController::class, 'removeUser']);

    //Vacation API Routes
    Route::post('/api/vacations/getVacation', [VacationController::class,'getVacation']);
    Route::post('/api/vacations/createVacation', [VacationController::class,'createVacation']);
    Route::post('/api/vacations/deleteVacation', [VacationController::class,'deleteVacation']);
    Route::post('/api/vacations/editVacation', [VacationController::class,'editVacation']);

    //Other API Routes
    Route::post('/api/disableLoginMessage', [AuthenticatedSessionController::class,'disableLoginMessage']);
});

require __DIR__.'/auth.php';