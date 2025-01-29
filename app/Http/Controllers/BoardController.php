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
            return Inertia::render('Admin/Boards/Index');
        } else {
            return Inertia::render('Boards/Index', [
                'boards' => $user->boards,
            ]);
        }
    }

    public function show($id)
    {
        $user = Auth::user();
        $board = Board::findOrFail($id);

        return Inertia::render('Boards/Show', [
            'board' => $board,
            'columns' => $board->columns->map(function ($column) {
                return [
                    'id' => $column->id,
                    'title' => $column->title,
                    'cards' => $column->cards
                ];
            }),
            'users' => $board->group->users,
        ]);
    }

    public function getColumnCards($columnId)
    {
        $column = Column::find($columnId);

        if (!$column) {
            return response()->json(['error' => 'Column not found']);
        }

        $cards = $column->cards;

        return response()->json($cards);
    }
    
    public function addCardToColumn($title, $description, $points, $columnId)
    {
        $user = Auth::user();
        $column = Column::find($columnId);

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

    public function moveCardToColumn($cardId, $columnId)
    {
        $card = Card::find($cardId);
        $column = Column::find($columnId);

        if (!$card) {
            return response()->json(['error' => 'Card not found']);
        }

        if (!$column) {
            return response()->json(['error' => 'Column not found']);
        }

        $card->status_updated_at = now();
        $card->column()->associate($column);
        $card->save();

        return response()->json(['message' => 'Card moved to column', 'card' => $card]);
    }
}