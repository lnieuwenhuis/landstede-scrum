<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Board;
use App\Models\Column;

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
}