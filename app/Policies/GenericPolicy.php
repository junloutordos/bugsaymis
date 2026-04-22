<?php

namespace App\Policies;

use App\Models\User;

/**
 * A simple permission-driven base policy.
 *
 * Usage (in any child policy):
 *
 *   public function view(User $user): bool
 *   {
 *       return $this->can($user, 'documents.view');
 *   }
 *
 * Or inline without extending:
 *   Gate::define('reports.export', fn (User $u) => $u->hasPermission('reports.export'));
 */
abstract class GenericPolicy
{
    /**
     * Convenience wrapper so child policies read cleanly.
     */
    protected function can(User $user, string $permission): bool
    {
        return $user->hasPermission($permission);
    }

    /**
     * True if the user owns the given model record (by user_id column).
     */
    protected function owns(User $user, object $model, string $column = 'user_id'): bool
    {
        return $user->id === $model->{$column};
    }

    /**
     * Owner OR has the given permission.
     */
    protected function ownsOrCan(User $user, object $model, string $permission, string $column = 'user_id'): bool
    {
        return $this->owns($user, $model, $column) || $this->can($user, $permission);
    }
}
