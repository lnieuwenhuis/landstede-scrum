<?php

use App\Http\Controllers\BoardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\VacationController;
use App\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::get('/login/azure', [\App\Http\Controllers\Auth\LoginController::class, 'redirectToProvider'])->name('auth.login');
Route::get('/login/azure/callback', [\App\Http\Controllers\Auth\LoginController::class, 'handleProviderCallback'])->name('auth.login.callback');

Route::get('/dashboard', [BoardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('boards', BoardController::class);

    Route::get('/admin/vacations', [VacationController::class, 'index'])->name('admin.vacations');
    Route::get('/admin/categories', [CategoryController::class,'index'])->name('admin.categories');

    Route::get('/admin/users/{userId}/boards', [BoardController::class, 'showUserBoards'])->name('admin.users.showBoards');
});

require __DIR__.'/auth.php';