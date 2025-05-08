<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\ColumnController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VacationController;
use App\Http\Controllers\BoardController;

Route::middleware(['web', 'auth', 'verified'])->group(function () {
    //Board API Routes
    Route::get('/{columnId}/cards', [BoardController::class, 'getColumnCards']);
    Route::post('/boards/storeBoard', [BoardController::class,'storeBoard']);
    Route::post('/boards/deleteBoard', [BoardController::class,'deleteBoard']);
    Route::post('/boards/updateBoard', [BoardController::class,'updateBoard']);
    Route::post('/boards/setNewOwner', [BoardController::class,'setNewOwner']);
    Route::post('/admin/search-all', [BoardController::class, 'searchAll']);

    //Card API Routes
    Route::post('/addCardToColumn/{columnId}', [CardController::class, 'addCardToColumn']);
    Route::post('/updateCard/{cardId}', [CardController::class, 'updateCard']);
    Route::post('/deleteCard/{cardId}', [CardController::class, 'deleteCard']);
    Route::post('/cards/{cardId}/move', [CardController::class, 'moveCardToColumn']);
    Route::post('/cards/{card}/assign', [CardController::class, 'assignUser']);

    //Column API Routes
    Route::post('/columns/toggleSprintChecked', [ColumnController::class,'toggleSprintChecked']);
    Route::post('/addColumn', [ColumnController::class, 'addColumn']);
    Route::post('/deleteColumn', [ColumnController::class, 'deleteColumn']);
    Route::post('/updateColumn', [ColumnController::class, 'updateColumn']);

    //User API Routes
    Route::post('/users/searchUsers', [UserController::class,'searchUsers']);
    Route::post('/users/addUsersToBoard', [UserController::class,'addUsersToBoard']);
    Route::post('/users/removeUserFromBoard', [UserController::class, 'removeUser']);

    //Vacation API Routes
    Route::post('/vacations/getVacation', [VacationController::class,'getVacation']);
    Route::post('/vacations/createVacation', [VacationController::class,'createVacation']);
    Route::post('/vacations/deleteVacation', [VacationController::class,'deleteVacation']);
    Route::post('/vacations/editVacation', [VacationController::class,'editVacation']);
    Route::post('/vacations/setActiveVacation', [VacationController::class,'setActiveVacation']);

    //Other API Routes
    Route::post('/disableLoginMessage', [AuthenticatedSessionController::class,'disableLoginMessage']);
    Route::post('/updateSprint', [BoardController::class, 'updateSprint']);
    Route::post('/deleteSprint', [BoardController::class, 'deleteSprint']);
    Route::post('/createSprint', [BoardController::class, 'createSprint']);
});

require __DIR__.'/auth.php';