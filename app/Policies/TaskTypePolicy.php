<?php

namespace App\Policies;

use App\Models\TaskType;
use App\Models\User;

class TaskTypePolicy
{
    protected function isAdmin(User $user): bool
    {
        return $user->is_admin === true;
    }

    /**
     * O método before é executado antes de qualquer outra regra.
     * Se retornar true, o acesso é liberado imediatamente (ideal para Admins).
     */
    public function before(User $user): ?bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function show(User $user, TaskType $taskType): bool
    {
        return $user->id === $taskType->user_id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TaskType $taskType): bool
    {
        return $user->id === $taskType->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TaskType $taskType): bool
    {
        return $user->id === $taskType->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, TaskType $taskType): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, TaskType $taskType): bool
    {
        return false;
    }
}
