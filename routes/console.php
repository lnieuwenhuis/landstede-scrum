<?php

use App\Models\Board;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Check vacation logic and lock appropriate columns
Schedule::call(function () {
    $boards = Board::all();
    foreach ($boards as $board) {
        $board->checkSprintLogic();
    }
})->dailyAt('00:00');
