<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendanceRecordPolicy
{
    public function update(User $user, Attendance $attendance): bool
    {
        return $user->isAdmin()
            || $user->id === $attendance->user_id;
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->isAdmin()
            || $user->id === $attendance->user_id;
    }
}
