<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function searchUsers(Request $request)
    {
        $searchTerm = $request->input('searchTerm');
        $users = User::where('name', 'like', '%' . $searchTerm . '%')->get();
        return response()->json($users);
    }

    public function getUserFromInput(Request $request)
    {
        $input = $request->input('userString');
        $user = null;
        
        // Check if input is an email address
        if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $input)->first();
        } else {
            // If not an email, search by name
            $user = User::where('name', $input)->first();
        }
        
        return response()->json($user);
    }

    public function addUsersToBoard(Request $request)
    {
        $boardId = $request->input('board_id');
        $userIds = $request->input('user_ids');
        $board = Board::find($boardId);
        
        // Attach each user to the board instead of using sync
        foreach ($userIds as $userId) {
            // Check if the user is already attached to avoid duplicates
            if (!$board->users()->where('user_id', $userId)->exists()) {
                $board->users()->attach($userId);
            }
        }
        
        return response()->json(['message' => 'Users added to board successfully']);
    }

    public function removeUser(Request $request)
    {
        $board = Board::findOrFail($request->board_id);
        $currentUser = Auth::user();
        
        // If not the creator, can only remove self
        if ($board->creator_id !== $currentUser->id) {
            if ($request->user_id != $currentUser->id) {
                return response()->json(['error' => 'You are not authorized to remove this user']);
            }
            
            $board->users()->detach($currentUser->id);
            return response()->json(['message' => 'Succesfully removed user', 'redirect' => '/dashboard']);
        }
        
        // Creator removing someone (including self)
        if ($request->user_id == $currentUser->id) {
            // Creator removing self
            if ($request->user_id == $currentUser->id) {
                $oldestMember = $board->users()
                    ->where('users.id', '!=', $currentUser->id)
                    ->orderBy('group_user.id')  // Using ID to determine the oldest member
                    ->first();

                if ($oldestMember) {
                    // Transfer ownership to oldest member
                    $board->creator_id = $oldestMember->id;
                    $board->save();
                    $board->users()->detach($currentUser->id);
                    return response()->json(['message' => 'Succesfully removed user', 'redirect' => '/dashboard']);
                } else {
                    // No other members, delete the group
                    $board->delete();
                    return response()->json(['message' => 'Succesfully removed user', 'redirect' => '/dashboard']);
                }
            }
        } else {
            // Creator removing another user
            $board->users()->detach($request->user_id);
            return response()->json(['message' => 'Succesfully removed user']);
        }
    }

    public function changeUserColor(Request $request)
    {
        $user = parent::checkUserLogin();

        $user->color = $request->color;
        $user->save();

        return response()->json(['message' => 'Color changed']);
    }
}
