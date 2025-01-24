<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Board;
use Illuminate\Support\Facades\Auth;

class BoardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            return view('admin.boards.index' ,[
                'boards' => Board::all(),
            ]);
        }

        return view('boards.index', [
            'boards' => $user->groups->map->boards->flatten(),
        ]);
    }

    public function show(Board $board)
    {
        return view('boards.show', [
            'board' => $board,
        ]);
    }
}
