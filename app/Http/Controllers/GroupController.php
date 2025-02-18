<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\User;

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

    public function show($id)
    {
        $user = Auth::user();
        $group = $user->groups->find($id);

        return Inertia::render('Groups/Show', [
            'group' => $group,
            'users' => $group->users,
            'board' => $group->board, 
        ]);
    }


    public function addUser($groupId, $userString)
    {
        $group = Group::find($groupId);
        $user = User::where('email', $userString)->first() ?? User::where('name', $userString)->first();
    
        if (!$user) {
            return response()->json(['error' => 'User not found']);
        }

        if ($group->users->contains($user)) {
            return response()->json(['error' => 'User already in group']);
        }

        $group->addUser($user);

        return response()->json(['message' => 'User added to group', 'user' => $user]);
    }

    public function removeUser(Request $request, $groupId)
    {
        $group = Group::find($groupId);
        $user = User::find($request->user_id);

        if (!$group || !$user) {
            return response()->json(['error' => 'Group or user not found']);
        }

        $group->removeUser($user);

        return response()->json(['message' => 'User removed from group']);
    }
}
