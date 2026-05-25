<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /*
    |--------------------------------------------------------------------------
    | ALLOWED ADMIN ROLES
    |--------------------------------------------------------------------------
    */
    private array $adminRoles = [
        'adminsuper',
        'adminhr',
        'adminaccounting',
        'admintesting',
        'adminmarketing',
        'admininventory',
    ];

    /*
    |--------------------------------------------------------------------------
    | VIEW ANY USERS
    |--------------------------------------------------------------------------
    */
    public function viewAny(User $authUser): bool
    {
        return in_array(
            $authUser->role,
            $this->adminRoles
        );
    }

    /*
    |--------------------------------------------------------------------------
    | VIEW USER
    |--------------------------------------------------------------------------
    */
    public function view(User $authUser, User $user): bool
    {
        return in_array(
            $authUser->role,
            $this->adminRoles
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE USER
    |--------------------------------------------------------------------------
    */
    public function create(User $authUser): bool
    {
        return $authUser->role === 'adminsuper';
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE USER
    |--------------------------------------------------------------------------
    */
    public function update(User $authUser, User $user): bool
    {
        /*
        |--------------------------------------------------------------------------
        | ADMINSUPER CAN UPDATE ANYONE
        |--------------------------------------------------------------------------
        */
        if ($authUser->role === 'adminsuper') {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | OTHER ADMINS CANNOT UPDATE ADMINSUPER
        |--------------------------------------------------------------------------
        */
        if (
            in_array($authUser->role, $this->adminRoles) &&
            $user->role === 'adminsuper'
        ) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | OTHER ADMINS CAN UPDATE NON-ADMINSUPER
        |--------------------------------------------------------------------------
        */
        return in_array(
            $authUser->role,
            $this->adminRoles
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE USER
    |--------------------------------------------------------------------------
    */
    public function delete(User $authUser, User $user): bool
    {
        /*
        |--------------------------------------------------------------------------
        | ONLY ADMINSUPER CAN DELETE
        |--------------------------------------------------------------------------
        */
        if ($authUser->role !== 'adminsuper') {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | PREVENT SELF DELETE
        |--------------------------------------------------------------------------
        */
        if ($authUser->id === $user->id) {
            return false;
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | RESET PASSWORD
    |--------------------------------------------------------------------------
    */
    public function resetPassword(User $authUser, User $user): bool
    {
        return in_array(
            $authUser->role,
            $this->adminRoles
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TOGGLE STATUS
    |--------------------------------------------------------------------------
    */
    public function toggleStatus(User $authUser, User $user): bool
    {
        /*
        |--------------------------------------------------------------------------
        | ONLY ADMINSUPER
        |--------------------------------------------------------------------------
        */
        if ($authUser->role !== 'adminsuper') {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | CANNOT DISABLE SELF
        |--------------------------------------------------------------------------
        */
        if ($authUser->id === $user->id) {
            return false;
        }

        return true;
    }
}