<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\Board;
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

    public function create()
    {
        return Inertia::render('Groups/Create');
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'boardTitle' =>'required|string|max:255',
            'boardDescription' =>'required|string',
            'startDate' =>'required|date',
            'endDate' =>'required|date',       
        ]);

        $group = new Group([
            'title' => $validatedData['title'],
            'description' => $validatedData['description'],
            'creator_id' => $user->id,
        ]);
        $group->save();

        $board = Board::factory()->create([
            'title' => $validatedData['boardTitle'],
            'description' => $validatedData['boardDescription'],
            'start_date' => $validatedData['startDate'],
            'end_date' => $validatedData['endDate'],
            'group_id' => $group->id,
        ]);
        $board->save();
        
        $group->addUser($user);

        return response()->json([
            'message' => 'Group created', 
            'status' => 'redirect',        
        ]);
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();

        $group = Group::find($request->groupId);
        if ($group && $group->creator_id == $user->id) {
            $groupTitle = $group->title;
            $group->delete();
        } else {
            return response()->json(['error' => 'Group not found or not owned by user']);
        }
        return response()->json(['message' => 'Group "' . $groupTitle . '" has been deleted']);
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

    public function removeUser(Request $request)
    {
        $group = Group::find($request->group_id);
        $user = User::find($request->user_id);

        if (!$group || !$user) {
            return response()->json(['error' => 'Group or user not found']);
        }

        $group->removeUser($user);

        return response()->json(['message' => 'User removed from group']);
    }
}
