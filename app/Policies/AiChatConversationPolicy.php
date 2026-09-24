<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AiChatConversation;
use App\Models\User;

class AiChatConversationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AiChatConversation $aiChatConversation): bool
    {
        return $aiChatConversation->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AiChatConversation $aiChatConversation): bool
    {
        return $aiChatConversation->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AiChatConversation $aiChatConversation): bool
    {
        return $aiChatConversation->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AiChatConversation $aiChatConversation): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AiChatConversation $aiChatConversation): bool
    {
        //
    }
}
