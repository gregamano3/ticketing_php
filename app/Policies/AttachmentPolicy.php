<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\TicketReply;
use App\Models\User;

class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        $ticket = $attachment->ticket();

        if (! $ticket || ! $user->can('view', $ticket)) {
            return false;
        }

        // Files on internal notes are staff-only.
        return ! ($attachment->attachable instanceof TicketReply && $attachment->attachable->is_internal) || $user->isStaff();
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $attachment->user_id === $user->id && $this->view($user, $attachment);
    }
}
