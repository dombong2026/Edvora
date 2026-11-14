<?php

namespace Tests\Unit;

use App\Enums\AccountRole;
use PHPUnit\Framework\TestCase;

class AccountRoleTest extends TestCase
{
    public function test_all_role_codes_are_unique_and_stable(): void
    {
        $codes = array_map(fn (AccountRole $role): string => $role->value, AccountRole::cases());

        $this->assertCount(15, $codes);
        $this->assertCount(15, array_unique($codes));
        $this->assertContains('teacher', $codes);
        $this->assertContains('subdirector', $codes);
    }

    public function test_platform_admin_is_not_an_institution_wide_role(): void
    {
        $this->assertNotContains('admin', AccountRole::institutionWideCodes());
        $this->assertNotContains('student', AccountRole::institutionWideCodes());
    }
}
