<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Website;

class WebsitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCompanyAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isCompanyAdmin();
    }

    public function update(User $user, Website $website): bool
    {
        return $user->isCompanyAdmin() && $user->belongsToCompany($website->company_id);
    }

    public function delete(User $user, Website $website): bool
    {
        return $this->update($user, $website);
    }
}
