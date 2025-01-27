<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->role == 'admin') {
            return Inertia::render('Admin/Groups/Index');
        } else {
            return Inertia::render('Groups/Index', [
                'groups' => $user->groups
            ]);
        }
    }

    public function show ($id)
    {
        $user = Auth::user();

        return Inertia::render('Groups/Show', [
            'group' => $user->groups->find($id),
            'users' => $user->groups->find($id)->users,
            'boards' => $user->groups->find($id)->boards
        ]);
    }
}
