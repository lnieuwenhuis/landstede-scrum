<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class LoginController extends Controller
{
    public function redirectToProvider()
    {
        return Socialite::driver('azure')->redirect();
    }

    public function handleProviderCallback()
    {
        $azureUser = Socialite::driver('azure')->user();

        if ((explode('@', $azureUser->email)[1] != 'student.landstede.nl')) {
            $user = User::where('email', $azureUser->getEmail())->first();
            if (! $user) {
                $user = User::create([
                    'name' => $azureUser->name,
                    'email' => $azureUser->email,
                    'password' => bcrypt('password'),
                    'role' => 'admin',
                ]);
            }
        } else {
            $user = User::where('email', $azureUser->getEmail())->first();
            if (! $user) {
                $user = User::create([
                    'name' => $azureUser->name,
                    'email' => $azureUser->email,
                    'password' => bcrypt('password'),
                    'role' => 'user',
                ]);
            }
        }

        Auth::login($user, true);

        return redirect('/dashboard');
    }
}