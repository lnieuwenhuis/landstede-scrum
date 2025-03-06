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
        $group = Group::findOrFail($request->groupId);
        $currentUser = Auth::user();
        
        // If not the creator, can only remove self
        if ($group->creator_id !== $currentUser->id) {
            if ($request->userId != $currentUser->id) {
                return response()->json(['error' => 'You are not authorized to remove this user']);
            }
            
            $group->users()->detach($currentUser->id);
            return response()->json(['success' => true, 'redirect' => '/dashboard']);
        }
        
        // Creator removing someone (including self)
        if ($request->userId == $currentUser->id) {
            // Creator removing self
            if ($request->userId == $currentUser->id) {
                $oldestMember = $group->users()
                    ->where('users.id', '!=', $currentUser->id)
                    ->orderBy('group_user.id')  // Using ID to determine the oldest member
                    ->first();

                if ($oldestMember) {
                    // Transfer ownership to oldest member
                    $group->creator_id = $oldestMember->id;
                    $group->save();
                    $group->users()->detach($currentUser->id);
                    return response()->json(['success' => true, 'redirect' => '/dashboard']);
                } else {
                    // No other members, delete the group
                    $group->delete();
                    return response()->json(['success' => true, 'redirect' => '/dashboard']);
                }
            }
        } else {
            // Creator removing another user
            $group->users()->detach($request->userId);
            return response()->json(['success' => true]);
        }
    }
}
