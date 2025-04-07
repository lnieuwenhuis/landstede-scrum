<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\ColumnController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VacationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
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
    Route::post('/api/boards/storeBoard', [BoardController::class,'storeBoard']);
    Route::post('/api/boards/deleteBoard', [BoardController::class,'deleteBoard']);

    //Card API Routes
    Route::post('/api/addCardToColumn/{columnId}', [CardController::class, 'addCardToColumn']);
    Route::post('/api/updateCard/{cardId}', [CardController::class, 'updateCard']);
    Route::post('/api/deleteCard/{cardId}', [CardController::class, 'deleteCard']);
    Route::post('/api/cards/{cardId}/move', [CardController::class, 'moveCardToColumn']);

    //Column API Routes
    Route::post('/api/columns/toggleSprintChecked', [ColumnController::class,'toggleSprintChecked']);
    Route::post('/api/addColumn', [ColumnController::class, 'addColumn']);
    Route::post('/api/deleteColumn', [ColumnController::class, 'deleteColumn']);
    Route::post('/api/updateColumn', [ColumnController::class, 'updateColumn']);

    //User API Routes
    Route::post('/api/users/searchUsers', [UserController::class,'searchUsers']);
    Route::post('/api/users/addUsersToBoard', [UserController::class,'addUsersToBoard']);
    Route::post('/api/users/removeUserFromBoard', [UserController::class, 'removeUser']);

    //Vacation API Routes
    Route::post('/api/vacations/getVacation', [VacationController::class,'getVacation']);
    Route::post('/api/vacations/createVacation', [VacationController::class,'createVacation']);
    Route::post('/api/vacations/deleteVacation', [VacationController::class,'deleteVacation']);
    Route::post('/api/vacations/editVacation', [VacationController::class,'editVacation']);

    //Other API Routes
    Route::post('/api/disableLoginMessage', [AuthenticatedSessionController::class,'disableLoginMessage']);
    Route::post('/api/updateSprint', [BoardController::class, 'updateSprint']);
    Route::post('/api/deleteSprint', [BoardController::class, 'deleteSprint']);
    Route::post('/api/createSprint', [BoardController::class, 'createSprint']);
});

require __DIR__.'/auth.php';