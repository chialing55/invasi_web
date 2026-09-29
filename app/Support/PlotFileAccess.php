<?php

namespace App\Support;

use App\Models\User;

final class PlotFileAccess
{
    public static function allows(?User $user, ?string $plotTeam): bool
    {
        if ($user === null || ($user->getAttributes()['deleted_at'] ?? null) !== null) {
            return false;
        }

        return $user->role === 'admin'
            || ($plotTeam !== null && (string) $user->organization === $plotTeam);
    }
}
