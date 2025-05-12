<?php

use App\Models\Board;
use App\Models\Log;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
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
    
    // Create a log entry for the board results
    Log::create([
        'name' => "Daily Board Status Check",
        'description' => "Daily board status check",
        'type' => 'board',
        'data' => json_encode($boardResults) || ["No changes"]
    ]);
})->daily();