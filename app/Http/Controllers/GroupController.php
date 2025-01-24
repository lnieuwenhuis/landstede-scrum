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
            return view('admin.groups.index' ,[
                'groups' => Group::all(),
            ]);
        }

        return view('groups.index', [
            'groups' => $user->groups,
        ]);
    }

    public function show(Group $group)
    {
        return view('groups.show', [
            'group' => $group,
        ]);
    }
}
