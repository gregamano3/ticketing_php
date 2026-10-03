<?php

namespace App\Policies;

use App\Models\CannedResponse;
use App\Models\User;

class CannedResponsePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    /** Personal responses belong to their owner; shared ones need the dedicated permission. */
    public function update(User $user, CannedResponse $response): bool
    {
        return $response->isShared()
            ? $user->can('canned.manage-shared')
            : $response->user_id === $user->id;
    }

    public function delete(User $user, CannedResponse $response): bool
    {
        return $this->update($user, $response);
    }
}
