<?php

namespace App\Http\Controllers;

abstract class Controller
{
    public function checkUserLogin(): User | JsonResponse {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'Not Logged In!']);
        }

        return $user;
    }
}
