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
        } else {
            return Inertia::render('Dashboard', [
                'boards' => $user->boards
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
                    'cards' => $column->cards,
                    'is_done_column' => $column->is_done_column,
                ];
            }),
            'users' => $board->users,
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
    
    public function addCardToColumn(Request $request, $columnId)
    {
        $user = Auth::user();
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
        $card = Card::find($cardId);

        if (!$card) {
            return response()->json(['error' => 'Card not found']);
        }

        $card->update($request->all());

        return response()->json(['message' => 'Card updated', 'card' => $card]);
    }

    public function deleteCard($cardId)
    {
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
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'Not Logged In!']);
        }
        $board = Board::find($request->board_id);

        $title = $request->input('title');
        $done = $request->input('done');

        if (!$board) {
            return response()->json(['error' => 'Board not found']);
        }

        $column = Column::factory()->create([
            'title' => $title,
            'is_done_column' => $done,
            'board_id' => $board->id,
        ]);

        $column->save();

        return response()->json([
            'message' => 'Column added',
            'column' => $column
        ]);
    }

    public function deleteColumn(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'Not Logged In!']);
        }
        $column = Column::find($request->column_id);

        if (!$column) {
            return response()->json(['error' => 'Column not found']);
        }

        $column->cards()->delete();
        $column->delete();

        return response()->json(['message' => 'Column deleted']);
    }
}