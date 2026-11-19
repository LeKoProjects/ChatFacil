<?php

namespace App\Policies;

use App\Models\User;

/** Gestão de atendentes da empresa. */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCompanyAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isCompanyAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isCompanyAdmin()
            && $target->company_id !== null
            && $user->belongsToCompany($target->company_id);
    }

    public function delete(User $user, User $target): bool
    {
        return $this->update($user, $target) && $user->id !== $target->id;
    }
}
