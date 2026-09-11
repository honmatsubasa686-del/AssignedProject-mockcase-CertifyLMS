<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;

class EnrollmentGoalPolicy
{
    public function create(User $user, Enrollment $enrollment): bool
    {
        return $user->role === UserRole::Student
            && $enrollment->user_id === $user->id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EnrollmentGoal $goal): bool
    {
        return $this->isOwnerStudent($user, $goal);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EnrollmentGoal $goal): bool
    {
        return $this->isOwnerStudent($user, $goal);
    }

    public function markAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->isOwnerStudent($user, $goal);
    }

    public function unmarkAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->isOwnerStudent($user, $goal);
    }

    private function isOwnerStudent(User $user, EnrollmentGoal $goal): bool
    {
        $goal->loadMissing('enrollment');

        return $user->role === UserRole::Student
            && $goal->enrollment?->user_id === $user->id;
    }
}
