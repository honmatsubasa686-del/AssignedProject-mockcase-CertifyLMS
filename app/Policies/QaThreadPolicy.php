<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\User;

class QaThreadPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array(
            $user->role,
            [UserRole::Admin, UserRole::Coach, UserRole::Student],
            true
        );
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, QaThread $qaThread): bool
    {
        return in_array(
            $user->role,
            [UserRole::Admin, UserRole::Coach, UserRole::Student],
            true
        );
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    public function show(QaThread $qaThread): View
    {
        $this->authorize('view', $qaThread);

        $qaThread->load([
            'user',
            'certification',
            'replies.user',
        ]);

        $qaThread->loadCount('replies');

        return view('qa-thread.show', [
            'thread' => $qaThread,
        ]);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, QaThread $qaThread): bool
    {
        return $qaThread->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, QaThread $qaThread): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $qaThread->user_id === $user->id
            && ! $qaThread->replies()->exists();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, QaThread $qaThread): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, QaThread $qaThread): bool
    {
        //
    }

    public function resolve(User $user, QaThread $qaThread): bool
    {
        return $qaThread->user_id === $user->id;
    }

    public function unresolve(User $user, QaThread $qaThread): bool
    {
        return $qaThread->user_id === $user->id;
    }
}
