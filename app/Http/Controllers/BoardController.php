<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Board;
use App\Models\Column;
use App\Models\Card;
use App\Models\User;
use App\Models\Category;

class BoardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->role == 'admin') {
            return Inertia::render('Admin/Dashboard', [
                'boardCount' => Board::count(),
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
                        'cards' => $column->cards->map(function ($card) {
                            return [
                                'id' => $card->id,
                                'title' => $card->title,
                                'description' => $card->description,
                                'points' => $card->points,
                                'status' => $card->status,
                                'user_id' => $card->user_id,
                                'status_updated_at' => $card->status_updated_at,
                                'category_id' => $card->category_id,
                                'created_at' => $card->created_at,
                                'updated_at' => $card->updated_at,
                            ];
                        }),
                        'is_done_column' => $column->is_done_column,
                        'status' => $column->status,
                        'user_created' => $column->user_created,
                        'sprint_checked' => $column->sprint_checked,
                    ];
                }),
                'categories' => Category::all(),
                'users' => $board->users,
                'freeDates' => json_encode($board->nonWorkingDays()),
                'weekdays' => json_encode($board->weekdays),
                'currentUser' => $user,
                'currentSprint' => $board->currentSprint(),
            ]);
        };

        return redirect('/');
    }

    public function showUserBoards($userId) 
    {
        $loginCheck = parent::checkUserLogin();
        $loggedInUser = Auth::user(); 

        if (!$loggedInUser || $loggedInUser->role !== 'admin') {
            return redirect('/')->with('error', 'Unauthorized access.'); 
        }

        $user = User::find($userId);

        $boards = $user->boards->map(function($board) {
            return [
                'id' => $board->id,
                'title' => $board->title,
                'description' => $board->description,
                'status' => $board->status,
                'created_at' => $board->created_at,
                'updated_at' => $board->updated_at,
                'user_count' => $board->users()->count()
            ];
        });

        if ($user) {
            return Inertia::render('Admin/UserBoards', [
                'boards' => $boards,
                'user' => $user,
            ]);
        }

        return redirect()->route('admin.dashboard')->with('error', 'User not found.'); 
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
            'weekdays' =>'required|string',
            'status' =>'required|string',
        ]);

        [$sprints, $nonWorkingDays, $weekdays] = $this->formatBoardInputs(
            $validatedData['sprints'], 
            $validatedData['non_working_days'], 
            $validatedData['weekdays']
        );

        $board = Board::factory()->create([
            'title' => $validatedData['title'],
            'description' => $validatedData['description'],
            'start_date' => date("Y-m-d H:i:s", strtotime($validatedData['startDate'])),
            'end_date' => date("Y-m-d H:i:s", strtotime($validatedData['endDate'])),
            'sprints' => json_encode($sprints) ?? null,
            'non_working_days' => json_encode($nonWorkingDays),
            'weekdays' => json_encode($weekdays),
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

    public function updateBoard(Request $request)
    {
        $user = parent::checkUserLogin();

        $board = Board::find($request->boardId);
        if (!$board) {
            return response()->json(['error' => 'Board not found']);
        }
        // Allow admin to bypass ownership check
        if ($user->role!== 'admin' && ($board->creator_id!== $user->id ||!$user)) {
            return response()->json(['error' => 'Unauthorized']);
        }
        $validatedData = $request->validate([
            'title' =>'required|string|max:255',
            'description' =>'required|string',
            'endDate' => 'nullable|string',
            'non_working_days' =>'required|string',
            'weekdays' =>'required|string',
            'status' =>'required|string',
        ]);

        [$_, $nonWorkingDays, $weekdays] = $this->formatBoardInputs(
            null,
            $validatedData['non_working_days'], 
            $validatedData['weekdays']
        );

        $board->title = $validatedData['title'];
        $board->description = $validatedData['description'];
        $board->non_working_days = json_encode($nonWorkingDays);
        $board->weekdays = json_encode($weekdays);
        $board->status = $validatedData['status'];
        
        $endDateChanged = false;
        if (isset($validatedData['endDate']) && !empty($validatedData['endDate'])) {
            $newEndDate = date("Y-m-d H:i:s", strtotime($validatedData['endDate'] . ' +1 day -1 second'));
            if ($board->end_date != $newEndDate) {
                $board->end_date = $newEndDate;
                $endDateChanged = true;
            }
        }
        
        // If end date changed, recalculate non-working days based on weekday settings
        if ($endDateChanged) {
        $nonWorkingDaysArray = [];
        $weekdaysSettings = $weekdays;
        
        $boardStart = new \DateTime($board->start_date);
        $boardEnd = new \DateTime($board->end_date);
        
        $currentDay = clone $boardStart;
        while ($currentDay <= $boardEnd) {
            $dayOfWeek = strtolower($currentDay->format('l'));
            
            foreach ($weekdaysSettings as $weekdaySetting) {
                $weekdaySetting = (array)$weekdaySetting;
                $dayKey = key($weekdaySetting);
                
                if ($dayKey === $dayOfWeek && $weekdaySetting[$dayKey] === 1) {
                    $nonWorkingDaysArray[] = $currentDay->format('Y-m-d');
                    break;
                }
            }
            
            $currentDay->modify('+1 day');
        }
        
        // Update the non-working days
        $board->non_working_days = json_encode($nonWorkingDaysArray);
        } else {
        $board->non_working_days = json_encode($nonWorkingDays);
        }
        
        if ($endDateChanged && $board->sprints) {
            $sprints = json_decode($board->sprints, true);
            if (count($sprints) > 0) {
                $boardStart = strtotime($board->start_date);
                $boardEnd = strtotime($board->end_date);
                $totalDays = ($boardEnd - $boardStart) / (24 * 60 * 60);
                
                $sprintDuration = floor($totalDays / count($sprints));
                
                foreach ($sprints as $index => &$sprint) {
                    $sprintStartDate = date('Y-m-d', $boardStart + ($index * $sprintDuration * 24 * 60 * 60));
                    $sprintEndDate = date('Y-m-d', $boardStart + (($index + 1) * $sprintDuration * 24 * 60 * 60) - (24 * 60 * 60));
                    
                    if ($index === count($sprints) - 1) {
                        $sprintEndDate = date('Y-m-d', $boardEnd);
                    }
                    
                    $sprint['start_date'] = $sprintStartDate;
                    $sprint['end_date'] = $sprintEndDate;
                }
                
                $board->sprints = json_encode($sprints);
            }
        }
        
        $board->save();

        return response()->json([
            'message' => 'Board updated successfully!',
            'board' => $board,
        ]);
    }

    public function deleteBoard(Request $request)
    {
        $user = Auth::user();

        $board = Board::find($request->boardId);
        if (!$board) {
            return response()->json(['error' => 'Board not found']);
        }

        // Allow admin to bypass ownership check
        if ($user->role !== 'admin' && ($board->creator_id !== $user->id || !$user)) {
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
            // Update the basic sprint data for all users
            $sprints[$sprintIndex]['title'] = $request->title;
            $sprints[$sprintIndex]['start_date'] = $request->start_date;
            $sprints[$sprintIndex]['end_date'] = $request->end_date;
            
            // Only admins can update status
            if($user->role == 'admin') {
                $originalStatus = $sprints[$sprintIndex]['status'];
                $sprints[$sprintIndex]['status'] = $request->status; // Set the status explicitly

                if ($originalStatus == 'locked' && $request->status == 'checked') {
                    // Find the next chronological sprint based on start_date
                    $currentSprintEndDate = strtotime($sprints[$sprintIndex]['end_date']);
                    $nextChronologicalSprint = null;
                    $nextSprintIndex = null;
                    
                    foreach ($sprints as $idx => $sprint) {
                        $sprintStartDate = strtotime($sprint['start_date']);
                        // Find the sprint that starts after the current sprint ends
                        if ($sprintStartDate > $currentSprintEndDate && 
                            ($nextChronologicalSprint === null || $sprintStartDate < strtotime($nextChronologicalSprint['start_date']))) {
                            $nextChronologicalSprint = $sprint;
                            $nextSprintIndex = $idx;
                        }
                    }
                    
                    // Update the next chronological sprint's status if found
                    if ($nextSprintIndex !== null) {
                        $sprints[$nextSprintIndex]['status'] = 'planning';
                    }
                }
            }
            
            // Save updated sprints back to the board
            $board->sprints = json_encode($sprints);
            $board->save();

            $columns = $board->columns;
            foreach ($columns as $column) {
                if ($board->currentSprint() && $board->currentSprint()['status'] === 'locked') {
                    $column->status = 'locked';
                } else if ($board->currentSprint() && $board->currentSprint()['status'] === 'planning') {
                    if ($column->title === 'Sprint Backlog' || $column->title === 'Project Backlog') {
                        $column->status = 'active';
                    } else {
                        $column->status = 'locked';
                    }
                } else {
                    $column->status = 'active';
                }
                $column->save();
            }
            
            return response()->json([
                'message' => 'Sprint updated successfully',
                'sprints' => $sprints,
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
                })
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
            // Store the original statuses before removing the sprint
            $originalStatuses = [];
            foreach ($sprints as $sprint) {
                $originalStatuses[$sprint['id']] = $sprint['status'];
            }
            
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
                    
                    // Preserve the original status if it exists, otherwise determine based on date
                    if (isset($originalStatuses[$sprint['id']])) {
                        $sprint['status'] = $originalStatuses[$sprint['id']];
                    } else {
                        // Fallback to date-based status only if we don't have the original status
                        $currentDate = time();
                        $sprint['status'] = ($currentDate >= strtotime($sprintStartDate) && 
                        $currentDate <= strtotime($sprintEndDate)) 
                            ? 'active' 
                            : 'inactive';
                    }
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

    public function searchAll(Request $request)
    {
        $user = parent::checkUserLogin();
        $searchTerm = $request->input('searchTerm');
        $boards = Board::where('title', 'like', "%$searchTerm%")
            ->orWhere('description', 'like', "%$searchTerm%")
            ->get();
        $users = User::where('name', 'like', "%$searchTerm%")
            ->get();
        return response()->json([
            'boards' => $boards,
            'users' => $users,
        ]);
    }

    private function formatBoardInputs ($sprints, $nonWorkingDays, $weekdays)
    {
        if ($sprints !== null) {
            $sprintsInput = json_decode($sprints);
            $sprints = [];
            foreach ($sprintsInput as $index => $sprint) {
                $status = ($index === 0) ? 'planning' : 'inactive';
                    
                $sprints[] = [
                    'id' => count($sprints) + 1,
                    'title' => $sprint->name,
                    'start_date' => $sprint->start_date,
                    'end_date' => $sprint->end_date,
                    'status' => $status,
                ];
            }
        } else {
            $sprints = [];
        }

        $nonWorkingDaysInput = json_decode($nonWorkingDays);
        $nonWorkingDays = [];
        foreach ($nonWorkingDaysInput as $nonWorkingDay) {
            $nonWorkingDays[] = $nonWorkingDay;
        }

        $weekdaysInput = json_decode($weekdays);
        $weekdays = [];
        foreach ($weekdaysInput as $weekday) {
            $weekdays[] = $weekday;
        }

        return [
            $sprints,
            $nonWorkingDays,
            $weekdays,
        ];
    }
}