<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    public function checkUserLogin(): JsonResponse | User {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'Not Logged In!']);
        }

        return User::find($user->id)->first();
    }
}
