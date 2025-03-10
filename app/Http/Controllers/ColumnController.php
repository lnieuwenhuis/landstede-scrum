<?php

namespace App\Http\Controllers;

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
}
