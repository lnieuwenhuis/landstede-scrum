<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Board extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 
        'description',
        'non_working_days',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'board_user');
    }

    public function columns(): HasMany
    {
        return $this->hasMany(Column::class);
    }

    public function doneColumns(): HasMany
    {
        return $this->hasMany(Column::class)->where('is_done_column', true);
    }

    public function addUser(User $user)
    {
        $this->users()->attach($user);
    }

    public function removeUser(User $user)
    {
        $this->users()->detach($user);
    }

    public function getCreator(): User
    {
        return User::find($this->creator_id)->first();
    }

    public function generateSprints()
    {
        $totalDays = Carbon::parse($this->end_date)->diffInDays($this->start_date);
            $sprintLength = ceil($totalDays / 4);

            $sprints = [];
            $currentDate = Carbon::parse($this->start_date);

            for ($i = 0; $i < 4; $i++) {
                $sprintStart = $currentDate->copy();
                $sprintEnd = $currentDate->copy()->addDays($sprintLength);
                
                // Ensure the last sprint doesn't exceed end_date
                if ($i == 3) {
                    $sprintEnd = Carbon::parse($this->end_date);
                }
                
                $sprints[] = [
                    'start_date' => $sprintStart->format('Y-m-d'),
                    'end_date' => $sprintEnd->format('Y-m-d'),
                    'name' => 'Sprint ' . ($i + 1),
                ];
                
                $currentDate->addDays($sprintLength);
            }

            // Save the calculated sprints to the database
            $this->sprints = json_encode($sprints);
            $this->save();
    }

    public function sprints()
    {
        if (! $this->sprints) {
            $this->generateSprints();
        }

        // Return the sprints as an array
        return json_decode($this->sprints, true);
    }

    public function nonWorkingDays()
    {
        $vacation = Vacation::activeVacation();
        $non_working_days = $this->non_working_days;
        $vacationDates = $vacation->vacation_dates ?? [];

        $freeDates = array_unique(array_merge(json_decode($non_working_days), json_decode($vacationDates)));
        sort($freeDates);
        return $freeDates;
    }

    public function checkSprints()
    {
        $currentDate = Carbon::now();
        $sprints = $this->sprints();

        foreach ($sprints as $sprint) {
            $startDate = Carbon::parse($sprint['start_date']);
            $endDate = Carbon::parse($sprint['end_date']);
            
            if ($currentDate->between($startDate, $endDate) || $sprint['status'] == 'checked') {
                return $sprint;
            } else {
                $sprint['status'] = 'locked';
            }
        }
    }
}
