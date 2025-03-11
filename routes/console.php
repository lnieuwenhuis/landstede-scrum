<?php

use App\Models\Board;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('CheckBoardSprints', function () {
    $boards = Board::all();
    foreach ($boards as $board) {
        $board->checkSprints();
    }
})->purpose('Check and change board sprint statuses');
