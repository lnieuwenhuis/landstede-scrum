<?php

namespace App\Http\Controllers;

use App\Models\Vacation;
use Illuminate\Http\Request;
use Inertia\Inertia;


class VacationController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Vacations', ['vacations' => Vacation::all()->sortBy('schoolyear')]);
    }

    public function createVacation(Request $request)
    {
        $vacation = new Vacation();
        $vacation->schoolyear = $request->schoolyear;
        $vacation->vacation_dates = $request->vacation_dates;
        $vacation->save();
        return response()->json(['vacation' => $vacation]);
    }

    public function editVacation(Request $request)
    {
        $vacation = Vacation::find($request->vacationId);
        $vacation->schoolyear = $request->schoolyear;
        $vacation->vacation_dates = $request->vacation_dates;
        $vacation->save();
        return response()->json(['vacation' => $vacation]);
    }

    public function deleteVacation(Request $request)
    {
        $vacation = Vacation::find($request->vacationId);
        $vacation->delete();
        return response()->json(['vacation' => $vacation, 'message' => 'Vacation deleted successfully']);
    }

    public function getVacation(Request $request)
    {
        $vacation = Vacation::find($request->vacationId);
        return response()->json($vacation);
    }
}
