<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'translations' => function () {
                return [
                    'auth' => __('auth'),
                    'validation' => __('validation'),
                    'Login' => __('Login'),
                    'Email' => __('Email'),
                    'Password' => __('Password'),
                    'Remember me' => __('Remember me'),
                    'Forgot your password?' => __('Forgot your password?'),
                    'Register' => __('Register'),
                    'Name' => __('Name'),
                    'Confirm Password' => __('Confirm Password'),
                    'Already registered?' => __('Already registered?'),
                    'Dashboard' => __('Dashboard'),
                    'Profile' => __('Profile'),
                    'Log Out' => __('Log Out'),
                    'Save' => __('Save'),
                    'Cancel' => __('Cancel'),
                    'Delete' => __('Delete'),
                    'Edit' => __('Edit'),
                    'Create' => __('Create'),
                    'Update' => __('Update'),
                    
                    // Admin Dashboard specific translations
                    'You\'re logged in!' => __('You\'re logged in!'),
                    'Search Boards & Users' => __('Search Boards & Users'),
                    'total boards' => __('total boards'),
                    'Search by board title, user name or email' => __('Search by board title, user name or email'),
                    'Create Board' => __('Create Board'),
                    'No results found for' => __('No results found for'),
                    'Boards' => __('Boards'),
                    'Users' => __('Users'),
                    'View' => __('View'),
                    'View Boards' => __('View Boards'),
                    'Administration' => __('Administration'),
                    'Manage Vacations' => __('Manage Vacations'),
                    'Create vacations for the new school year' => __('Create vacations for the new school year'),
                    'Manage Categories' => __('Manage Categories'),
                    'Create Categories for Students' => __('Create Categories for Students'),
                    'Delete Board' => __('Delete Board'),
                    'Are you sure you want to delete this board? This action cannot be undone.' => __('Are you sure you want to delete this board? This action cannot be undone.'),
                    'Board deleted successfully' => __('Board deleted successfully'),
                    'Failed to perform search' => __('Failed to perform search'),
                    
                    // Dashboard specific translations
                    'Your Boards' => __('Your Boards'),
                    'View Board' => __('View Board'),
                    'Leave Board' => __('Leave Board'),
                    'Are you sure you want to leave this board? You will no longer have access to it.' => __('Are you sure you want to leave this board? You will no longer have access to it.'),
                    'Leave' => __('Leave'),
                    'You have been removed from the board' => __('You have been removed from the board'),
                    'Failed to leave board' => __('Failed to leave board'),
                ];
            },
        ];
    }
}
