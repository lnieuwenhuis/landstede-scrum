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
                    
                    // Categories specific translations
                    'Categories' => __('Categories'),
                    'New Category' => __('New Category'),
                    'Creating...' => __('Creating...'),
                    'No description provided' => __('No description provided'),
                    'No categories created yet' => __('No categories created yet'),
                    'Edit Category' => __('Edit Category'),
                    'Create Category' => __('Create Category'),
                    'Description' => __('Description'),
                    'Color' => __('Color'),
                    'Enter category name' => __('Enter category name'),
                    'Enter category description' => __('Enter category description'),
                    'Saving...' => __('Saving...'),
                    'Delete Category' => __('Delete Category'),
                    'Are you sure you want to delete this category? This action cannot be undone.' => __('Are you sure you want to delete this category? This action cannot be undone.'),
                    'Category created successfully' => __('Category created successfully'),
                    'Failed to create category' => __('Failed to create category'),
                    'Category updated successfully' => __('Category updated successfully'),
                    'Failed to update category' => __('Failed to update category'),
                    'Category deleted successfully' => __('Category deleted successfully'),
                    'Failed to delete category' => __('Failed to delete category'),
                    
                    // Vacations specific translations
                    'School Vacations' => __('School Vacations'),
                    'Active' => __('Active'),
                    'Activate' => __('Activate'),
                    'Delete Vacation' => __('Delete Vacation'),
                    'Add Vacation' => __('Add Vacation'),
                    'Save Vacation' => __('Save Vacation'),
                    'Create First Vacation' => __('Create First Vacation'),
                    'Create New Vacation' => __('Create New Vacation'),
                    'School Year' => __('School Year'),
                    'e.g. 2023-2024' => __('e.g. 2023-2024'),
                    'Select Vacation Dates' => __('Select Vacation Dates'),
                    'Select vacation dates' => __('Select vacation dates'),
                    'Selected Dates:' => __('Selected Dates:'),
                    'Mo' => __('Mo'),
                    'Tu' => __('Tu'),
                    'We' => __('We'),
                    'Th' => __('Th'),
                    'Fr' => __('Fr'),
                    'Sa' => __('Sa'),
                    'Su' => __('Su'),
                    'Create Vacation' => __('Create Vacation'),
                    'Vacation Dates for' => __('Vacation Dates for'),
                    'Edit Dates' => __('Edit Dates'),
                    'Edit Vacation Dates' => __('Edit Vacation Dates'),
                    'Save Changes' => __('Save Changes'),
                    'No dates have been added to this vacation.' => __('No dates have been added to this vacation.'),
                    'No vacations have been created yet.' => __('No vacations have been created yet.'),
                    'Are you sure you want to delete this vacation?' => __('Are you sure you want to delete this vacation?'),
                    'Vacation created successfully' => __('Vacation created successfully'),
                    'Failed to create vacation' => __('Failed to create vacation'),
                    'Failed to load vacation details' => __('Failed to load vacation details'),
                    'Vacation dates updated successfully' => __('Vacation dates updated successfully'),
                    'Failed to update vacation dates' => __('Failed to update vacation dates'),
                    'Failed to update vacation status' => __('Failed to update vacation status'),
                    
                    // Month names
                    'January' => __('January'),
                    'February' => __('February'),
                    'March' => __('March'),
                    'April' => __('April'),
                    'May' => __('May'),
                    'June' => __('June'),
                    'July' => __('July'),
                    'August' => __('August'),
                    'September' => __('September'),
                    'October' => __('October'),
                    'November' => __('November'),
                    'December' => __('December'),
                    
                    // UserBoards specific translations
                    'Boards for' => __('Boards for'),
                    'Go back' => __('Go back'),
                    'user(s)' => __('user(s)'),
                    'Owner' => __('Owner'),
                    'is not part of any boards yet.' => __('is not part of any boards yet.'),
                ];
            },
        ];
    }
}
