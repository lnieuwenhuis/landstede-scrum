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

    public function show ($id)
    {
        $user = Auth::user();

        return Inertia::render('Groups/Show', [
            'group' => $user->groups->find($id),
            'users' => $user->groups->find($id)->users,
            'boards' => $user->groups->find($id)->boards
        ]);
    }

    public function addUser($groupId, $email)
    {
        $group = Group::find($groupId);
        $user = User::where('email', $email)->first();
    
        $group->addUser($user);

        return response()->json(['message' => 'User added to group', 'user' => $user]);
    }

    public function removeUser($groupId, $userId)
    {
        $group = Group::find($groupId);
        $user = User::find($userId);

        $group->users()->detach($user);

        return response()->json(['message' => 'User removed from group', 'user' => $user]);
    }
}
