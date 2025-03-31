<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Board;
use App\Models\Column;
use App\Models\Card;

class BoardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->role == 'admin') {
            return Inertia::render('Admin/Dashboard', [
                'boards' => Board::all()
            ]);
        } else if ($user) {
            return Inertia::render('Dashboard', [
                'boards' => $user->boards
            ]);
        }

        return redirect('/');
    }

    public function show($id)
    {
        $user = Auth::user();
        $board = Board::findOrFail($id);

        if ($user) {
            return Inertia::render('Boards/Show', [
                'board' => $board,
                'sprints' => collect($board->sprints())->map(function ($sprint) {
                    return [
                        'id' => $sprint['id'],
                        'title' => $sprint['title'],
                        'start_date' => $sprint['start_date'],
                        'end_date' => $sprint['end_date'],
                        'status' => $sprint['status'],
                    ];
                }),
                'columns' => $board->columns->map(function ($column) {
                    return [
                        'id' => $column->id,
                        'title' => $column->title,
                        'cards' => $column->cards,
                        'is_done_column' => $column->is_done_column,
                        'status' => $column->status,
                        'user_created' => $column->user_created,
                        'sprint_checked' => $column->sprint_checked,
                    ];
                }),
                'users' => $board->users,
                'freeDates' => json_encode($board->nonWorkingDays()),
                'currentUser' => $user,
            ]);
        };

        return redirect('/');
    }

    public function create()
    {
        $user = Auth::user();

        if ($user) {
            return Inertia::render('Boards/Create');
        };

        return redirect('/');
    }

    public function storeBoard(Request $request)
    {
        $user = parent::checkUserLogin();

        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'startDate' =>'required|string',
            'endDate' =>'required|string',       
            'sprints' => 'nullable|string',
            'non_working_days' => 'required|string',
            'status' =>'required|string',
        ]);

        $sprintsInput = json_decode($validatedData['sprints']);
        $sprints = [];
        foreach ($sprintsInput as $sprint) {
            $currentDate = time();
            $sprintStartDate = strtotime($sprint->start_date);
            $sprintEndDate = strtotime($sprint->end_date);
            
            // Check if current date is between start and end date
            $status = ($currentDate >= $sprintStartDate && $currentDate <= $sprintEndDate) 
                ? 'active' 
                : 'inactive';
                
            $sprints[] = [
                'id' => count($sprints) + 1,
                'title' => $sprint->name,
                'start_date' => $sprint->start_date,
                'end_date' => $sprint->end_date,
                'status' => $status,
            ];
        }

        $nonWorkingDaysInput = json_decode($validatedData['non_working_days']);
        $nonWorkingDays = [];
        foreach ($nonWorkingDaysInput as $nonWorkingDay) {
            $nonWorkingDays[] = $nonWorkingDay;
        }

        $board = Board::factory()->create([
            'title' => $validatedData['title'],
            'description' => $validatedData['description'],
            'start_date' => date("Y-m-d H:i:s", strtotime($validatedData['startDate'])),
            'end_date' => date("Y-m-d H:i:s", strtotime($validatedData['endDate'])),
            'sprints' => json_encode($sprints) ?? null,
            'non_working_days' => json_encode($nonWorkingDays),
            'status' => $validatedData['status'],
            'creator_id' => $user->id,
        ]);
        $board->sprints;
        $board->save();

        $board->addUser($user);

        $column = Column::factory()->create([
            'title' => 'Project Backlog',
            'is_done_column' => false,
            'board_id' => $board->id,
            'status' => 'active',
            'user_created' => false
        ]);
        $column->save();

        $column = Column::factory()->create([
            'title' => 'Sprint Backlog',
            'is_done_column' => false,
            'board_id' => $board->id,
            'status'=> 'active',
            'user_created' => false
        ]);
        $column->save();

        $column = Column::factory()->create([
            'title' => 'Done',
            'is_done_column' => true,
            'board_id' => $board->id,
            'status'=> 'active',
            'user_created' => false
        ]);
        $column->save();

        return response()->json([
            'message' => 'Board created',
            'board_id' => $board->id, 
            'status' => 'redirect',        
        ]);
    }

    public function deleteBoard(Request $request)
    {
        $user = Auth::user();

        $board = Board::find($request->board_id);
        if (!$board) {
            return response()->json(['error' => 'Board not found']);
        }

        if ($board->creator_id !== $user->id || ! $user) {
            return response()->json(['error' => 'Unauthorized']);
        }

        $board->delete();
        return response()->json(['message' => 'Board deleted', 'status' => 'redirect']);
    }

    public function getColumnCards($columnId)
    {
        $user = parent::checkUserLogin();

        $column = Column::find($columnId);

        if (!$column) {
            return response()->json(['error' => 'Column not found']);
        }

        $cards = $column->cards;

        return response()->json($cards);
    }
    
    public function addCardToColumn(Request $request, $columnId)
    {
        $user = parent::checkUserLogin();

        $column = Column::find($columnId);

        $title = $request->input('title');
        $description = $request->input('description');
        $points = $request->input('points');

        if (!$column) {
            return response()->json(['error' => 'Column not found']);
        }

        $card = Card::factory()->create([
            'title' => $title,
            'description' => $description,
            'points' => $points,
            'user_id' => $user->id,
            'column_id' => $column->id,
        ]);

        $card->save();

        return response()->json(['message' => 'Card added to column', 'card' => $card]);
    }

    public function updateCardInColumn($cardId, $title, $description, $points)
    {
        $user = parent::checkUserLogin();

        $card = Card::find($cardId);

        if (!$card) {
            return response()->json(['error' => 'Card not found']);
        }

        $card->title = $title;
        $card->description = $description;
        $card->points = $points;
        $card->save();

        return response()->json(['message' => 'Card updated', 'card' => $card]);
    }

    public function moveCardToColumn(Request $request, $cardId)
    {
        $user = parent::checkUserLogin();

        $card = Card::find($cardId);

        if (!$card) {
            return response()->json(['error' => 'Card not found']);
        }

        $card->column()->dissociate();
        $card->column()->associate(Column::find($request->column_id));
        $card->status_updated_at = \Carbon\Carbon::now();
        $card->save();

        return response()->json(['message' => 'Card moved to column', 'card' => $card]);
    }

    public function updateCard(Request $request, $cardId)
    {
        $user = parent::checkUserLogin();

        $card = Card::find($cardId);

        if (!$card) {
            return response()->json(['error' => 'Card not found']);
        }

        $card->update($request->all());

        return response()->json(['message' => 'Card updated', 'card' => $card]);
    }

    public function deleteCard($cardId)
    {
        $user = parent::checkUserLogin();

        $card = Card::find($cardId);

        if (!$card) {
            return response()->json(['error' => 'Card not found']);
        }

        $card->column()->dissociate();
        $card->delete();

        return response()->json(['message' => 'Card deleted']);
    }

    public function addColumn(Request $request)
    {
        $user = parent::checkUserLogin();

        $board = Board::find($request->board_id);

        $title = $request->input('title');
        $done = $request->input('done');
        $status = $request->input('status');

        if (!$board) {
            return response()->json(['error' => 'Board not found']);
        }

        $column = Column::factory()->create([
            'title' => $title,
            'is_done_column' => $done,
            'board_id' => $board->id,
            'status'=> $status
        ]);

        $column->save();

        return response()->json([
            'message' => 'Column added',
            'column' => $column
        ]);
    }

    public function deleteColumn(Request $request)
    {
        $user = parent::checkUserLogin();

        $column = Column::find($request->column_id);

        if (!$column) {
            return response()->json(['error' => 'Column not found']);
        }

        $column->cards()->delete();
        $column->delete();

        return response()->json(['message' => 'Column deleted']);
    }

    public function updateColumn(Request $request)
    {
        $user = parent::checkUserLogin();

        $request->validate([
            'column_id' => 'required|exists:columns,id',
            'title' => 'required|string|max:255',
        ]);

        $column = Column::findOrFail($request->column_id);
        $column->title = $request->title;
        $column->save();

        return response()->json([
            'message' => 'Column updated successfully',
            'column' => $column
        ]);
    }

    public function updateSprint(Request $request)
    {
        $user = parent::checkUserLogin();

        $board = Board::find($request->board_id);
        if (!$board) {
            return response()->json(['error' => 'Board not found']);
        }
        
        $sprints = json_decode($board->sprints, true);
        $sprintIndex = array_search($request->sprint_id, array_column($sprints, 'id'));
        
        if ($sprintIndex !== false) {
            // Update the sprint with new data
            $sprints[$sprintIndex]['title'] = $request->title;
            $sprints[$sprintIndex]['start_date'] = $request->start_date;
            $sprints[$sprintIndex]['end_date'] = $request->end_date;
            
            if($user->role == 'admin'){
                $sprints[$sprintIndex]['status'] = $request->status;
            }
            
            // Save updated sprints back to the board
            $board->sprints = json_encode($sprints);
            $board->save();
            
            return response()->json([
                'message' => 'Sprint updated successfully',
                'sprint' => $sprints[$sprintIndex]
            ]);
        }
        
        return response()->json(['error' => 'Sprint not found']);
    }

    public function createSprint(Request $request)
    {
        $user = parent::checkUserLogin();

        $board = Board::find($request->board_id);
        if (!$board) {
            return response()->json(['error' => 'Board not found']);
        }

        $sprints = json_decode($board->sprints, true) ?? [];
        $boardStart = strtotime($board->start_date);
        $boardEnd = strtotime($board->end_date);
        $totalDays = ($boardEnd - $boardStart) / (24 * 60 * 60);
        
        // Calculate new sprint duration based on total sprints after addition
        $sprintCount = count($sprints) + 1;
        $sprintDuration = floor($totalDays / $sprintCount);
        
        // Redistribute all sprints evenly
        foreach ($sprints as $index => &$sprint) {
            $sprintStartDate = date('Y-m-d', $boardStart + ($index * $sprintDuration * 24 * 60 * 60));
            $sprintEndDate = date('Y-m-d', $boardStart + (($index + 1) * $sprintDuration * 24 * 60 * 60) - (24 * 60 * 60));
            
            $sprint['start_date'] = $sprintStartDate;
            $sprint['end_date'] = $sprintEndDate;
        }
        
        // Create new sprint
        $newSprint = [
            'id' => $sprintCount,
            'title' => $request->title,
            'start_date' => date('Y-m-d', $boardStart + (($sprintCount - 1) * $sprintDuration * 24 * 60 * 60)),
            'end_date' => date('Y-m-d', $boardEnd),
            'status' => 'inactive'
        ];
        
        array_push($sprints, $newSprint);
        
        // Save updated sprints back to the board
        $board->sprints = json_encode($sprints);
        $board->save();
        
        return response()->json([
            'message' => 'Sprint created successfully',
            'sprints' => $sprints
        ]);
    }

    public function deleteSprint(Request $request)
    {
        $user = parent::checkUserLogin();

        $board = Board::find($request->board_id);
        if (!$board) {
            return response()->json(['error' => 'Board not found']);
        }
    
        $sprints = json_decode($board->sprints, true);
        $sprintIndex = array_search($request->sprint_id, array_column($sprints, 'id'));
        
        if ($sprintIndex !== false) {
            // Remove the sprint from the array
            array_splice($sprints, $sprintIndex, 1);
    
            if (count($sprints) > 0) {
                // Get board start and end dates
                $boardStart = strtotime($board->start_date);
                $boardEnd = strtotime($board->end_date);
                $totalDays = ($boardEnd - $boardStart) / (24 * 60 * 60);
                
                // Calculate sprint duration
                $sprintDuration = floor($totalDays / count($sprints));
                
                // Redistribute sprints evenly
                foreach ($sprints as $index => &$sprint) {
                    $sprintStartDate = date('Y-m-d', $boardStart + ($index * $sprintDuration * 24 * 60 * 60));
                    $sprintEndDate = date('Y-m-d', $boardStart + (($index + 1) * $sprintDuration * 24 * 60 * 60) - (24 * 60 * 60));
                    
                    // For the last sprint, use the board end date
                    if ($index === count($sprints) - 1) {
                        $sprintEndDate = date('Y-m-d', $boardEnd);
                    }
                    
                    $sprint['start_date'] = $sprintStartDate;
                    $sprint['end_date'] = $sprintEndDate;
                    
                    // Update sprint status based on current date
                    $currentDate = time();
                    $sprint['status'] = ($currentDate >= strtotime($sprintStartDate) && 
                    $currentDate <= strtotime($sprintEndDate)) 
                        ? 'active' 
                        : 'inactive';
                }
            }
    
            // Save updated sprints back to the board
            $board->sprints = json_encode($sprints);
            $board->save();
    
            return response()->json([
                'message' => 'Sprint deleted successfully',
                'sprints' => $sprints
            ]);
        }
    
        return response()->json(['error' => 'Sprint not found']);
    }
}