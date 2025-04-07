<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Column;
use Illuminate\Http\Request;

class ColumnController extends Controller
{
    public function toggleSprintChecked(Request $request)
    {
        $column_ids = $request->input('column_ids');

        foreach ($column_ids as $column_id) {
            $column = Column::find($column_id);
            $column->sprint_checked = !$column->sprint_checked;
            $column->save();
        }
    }

    public function addColumn(Request $request)
    {
        $user = parent::checkUserLogin();

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
            'status'=> "active"
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
}
