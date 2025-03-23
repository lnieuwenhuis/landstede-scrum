<?php

use App\Models\Board;
use App\Models\Log;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('CheckBoardSprints', function () {
    $boards = Board::all();

    $boardResults = [];
    foreach ($boards as $board) {
        $sprintChanges = $board->checkSprints();
        if (!empty($sprintChanges)) {
            $boardResults[] = [
                'board_id' => $board->id,
                'board_title' => $board->title,
                'changes' => $sprintChanges
            ];
        }
    }

    if (!empty($boardResults)) {
        Log::create([
            'name' => 'SprintCheck',
            'description' => 'Automatic check and update of sprint statuses across all boards',
            'type' => 'Routine',
            'data' => json_encode($boardResults)
        ]);
    }

    return $this->comment(json_encode($boardResults));
})->purpose('Check and change board sprint statuses every day');
