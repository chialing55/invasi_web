<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\PlotFileAccess;
use PHPUnit\Framework\TestCase;

class PlotFileAccessTest extends TestCase
{
    public function test_admin_can_view_every_team(): void
    {
        $user = new User(['role' => 'admin', 'organization' => 'NTU']);

        $this->assertTrue(PlotFileAccess::allows($user, 'NCHU'));
    }

    public function test_member_can_only_view_own_team(): void
    {
        $user = new User(['role' => 'member', 'organization' => 'NTU']);

        $this->assertTrue(PlotFileAccess::allows($user, 'NTU'));
        $this->assertFalse(PlotFileAccess::allows($user, 'NCHU'));
        $this->assertFalse(PlotFileAccess::allows($user, null));
    }

    public function test_guest_and_deleted_user_cannot_view_files(): void
    {
        $deletedUser = new User;
        $deletedUser->setRawAttributes([
            'role' => 'admin',
            'organization' => 'NTU',
            'deleted_at' => '2026-09-25 00:00:00',
        ]);

        $this->assertFalse(PlotFileAccess::allows(null, 'NTU'));
        $this->assertFalse(PlotFileAccess::allows($deletedUser, 'NTU'));
    }
}
