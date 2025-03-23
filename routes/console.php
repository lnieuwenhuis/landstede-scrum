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
        $changedSprints = $board->checkSprints();
        
        // Only include boards that had sprint status changes
        if (!empty($changedSprints)) {
            $boardResults[] = [
                'board_id' => $board->id,
                'board_title' => $board->title,
                'changes' => $changedSprints
            ];
        }
    }

    // Only create a log if there were any changes
    if (!empty($boardResults)) {
        Log::create([
            'name' => 'SprintCheck',
            'description' => 'Sprint status changes detected and updated',
            'type' => 'Routine',
            'data' => json_encode($boardResults)
        ]);
    }

    return $this->comment(json_encode($boardResults));
})->purpose('Check and change board sprint statuses every day');
