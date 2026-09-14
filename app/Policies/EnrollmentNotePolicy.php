<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;

class EnrollmentNotePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Enrollment $enrollment): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Coach => $this->isAssignedCoach($enrollment, $user),
            default => false,
        };
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EnrollmentNote $enrollmentNote): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role !== UserRole::Coach) {
            return false;
        }

        return $this->isAssignedCoach($enrollmentNote->enrollment, $user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Enrollment $enrollment): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Coach => $this->isAssignedCoach($enrollment, $user),
            default => false,
        };
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EnrollmentNote $enrollmentNote): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role !== UserRole::Coach) {
            return false;
        }

        return $enrollmentNote->author_user_id === $user->id
            && $this->isAssignedCoach($enrollmentNote->enrollment, $user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EnrollmentNote $enrollmentNote): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->role !== UserRole::Coach) {
            return false;
        }

        return $enrollmentNote->author_user_id === $user->id
            && $this->isAssignedCoach($enrollmentNote->enrollment, $user);
    }

    private function isAssignedCoach(Enrollment $enrollment, User $coach): bool
    {
        $enrollment->loadMissing('certification.coaches');

        return $enrollment->certification?->coaches->contains('id', $coach->id) ?? false;
    }
}
