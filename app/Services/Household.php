<?php

namespace App\Services;

use App\Models\Child;
use App\Models\HouseholdMember;

class Household
{
    /** Everyone who should hear about a child: the parent who owns them and each co-parent who has joined. */
    public static function userIdsFor(Child $child): array
    {
        return array_values(array_unique(array_merge(
            [$child->user_id],
            HouseholdMember::where('owner_id', $child->user_id)->where('status', 'active')->whereNotNull('user_id')->pluck('user_id')->all()
        )));
    }
}
