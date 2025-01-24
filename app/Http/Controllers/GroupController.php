<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            return view('group.admin' ,[
                'groups' => Group::all(),
            ]);
        }

        return view('group.index', [
            'groups' => $user->groups,
        ]);
    }
}
