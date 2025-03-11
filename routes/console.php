<?php

use App\Models\Board;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('CheckBoardSprints', function () {
    $boards = Board::all();
    foreach ($boards as $board) {
        $test = $board->checkSprints();
        return $this->comment($test);
    }
})->purpose('Check and change board sprint statuses');
