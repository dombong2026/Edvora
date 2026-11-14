<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class StaffAndInAppNoticeTest extends TestCase
{
    use RefreshDatabase;

    private Institution $school;
    private Campus $campus;
    private User $director;
    private User $administrator;
    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->school = Institution::create(['name' => 'Personnel School', 'slug' => 'personnel']);
        $this->campus = Campus::create([
            'institution_id' => $this->school->id, 'slug' => 'primary', 'name' => 'Primary',
        ]);
        $this->director = $this->member('director');
        $this->administrator = $this->member('administrator');
        $this->teacher = $this->member('teacher', $this->campus);
    }

    private function member(string $role, ?Campus $campus = null): User
    {
        $user = User::factory()->create();
        InstitutionMembership::create([
            'institution_id' => $this->school->id, 'user_id' => $user->id, 'status' => 'active',
        ]);
        InstitutionRoleAssignment::create([
            'institution_id' => $this->school->id, 'user_id' => $user->id,
            'role_id' => Role::query()->where('code', $role)->firstOrFail()->id,
            'campus_id' => $campus?->id,
        ]);
        if (in_array($role, ['director', 'administrator'], true)) {
            $user->forceFill([
                'mfa_enabled_at' => now(),
                'mfa_secret' => app(\App\Services\Totp::class)->newSecret(),
            ])->save();
        }
        return $user;
    }

    public function test_teacher_leave_can_be_requested_reviewed_and_separated_from_own_review(): void
    {
        $this->actingAs($this->teacher)->get(route('staff.leave.index', $this->school))
            ->assertOk()->assertSee('Leave requests')->assertSee('Submit leave request');
        $this->post(route('staff.leave.store', $this->school), [
            'campus_id' => $this->campus->id, 'category' => 'annual',
            'starts_on' => now()->addDays(4)->toDateString(),
            'ends_on' => now()->addDays(6)->toDateString(),
        ])->assertRedirect();

        $leave = DB::table('staff_leave_requests')->first();
        $this->assertSame($this->teacher->id, (int) $leave->staff_user_id);
        $this->assertSame('pending', $leave->status);
        $this->actingAs($this->director)
            ->get(route('staff.leave.index', $this->school))->assertOk()->assertSee('Approve');
        $this->post(route('staff.leave.review', [$this->school, $leave->id]), [
            'decision' => 'approve',
        ])->assertRedirect();
        $this->assertDatabaseHas('staff_leave_requests', ['id' => $leave->id, 'status' => 'approved']);
        $this->assertDatabaseHas('security_events', ['event_type' => 'staff.leave.reviewed']);
    }

    public function test_leave_ownership_cancellation_and_forbidden_non_staff_access(): void
    {
        $student = $this->member('student', $this->campus);
        $this->actingAs($student)->get(route('staff.leave.index', $this->school))->assertForbidden();
        $this->post(route('staff.leave.store', $this->school), [
            'campus_id' => $this->campus->id, 'category' => 'annual',
            'starts_on' => now()->addDays(4)->toDateString(),
            'ends_on' => now()->addDays(6)->toDateString(),
        ])->assertForbidden();

        $this->actingAs($this->teacher)->post(route('staff.leave.store', $this->school), [
            'campus_id' => $this->campus->id, 'category' => 'personal',
            'starts_on' => now()->addDays(5)->toDateString(),
            'ends_on' => now()->addDays(8)->toDateString(),
        ])->assertRedirect();
        $id = (int) DB::table('staff_leave_requests')->value('id');
        $this->post(route('staff.leave.store', $this->school), [
            'campus_id' => $this->campus->id, 'category' => 'personal',
            'starts_on' => now()->addDays(6)->toDateString(),
            'ends_on' => now()->addDays(9)->toDateString(),
        ])->assertSessionHasErrors('starts_on');

        $this->actingAs($this->administrator)->post(route('staff.leave.cancel', [
            $this->school, $id,
        ]))->assertNotFound();
        $this->actingAs($this->teacher)->post(route('staff.leave.cancel', [
            $this->school, $id,
        ]))->assertRedirect();
        $this->assertDatabaseHas('staff_leave_requests', ['id' => $id, 'status' => 'cancelled']);
        $this->post(route('staff.leave.cancel', [$this->school, $id]))
            ->assertSessionHasErrors('leave');
    }

    public function test_private_payroll_planning_never_creates_a_payment_and_requires_independent_review(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('staff.payroll.index', $this->school))->assertForbidden();
        $this->actingAs($this->director)
            ->get(route('staff.payroll.index', $this->school))
            ->assertOk()->assertSee('Payroll planning');

        $payload = [
            'campus_id' => $this->campus->id,
            'staff_email' => $this->teacher->email,
            'period' => '2026-12',
            'currency' => 'USD',
            'gross' => '1020.50',
            'deductions' => '20.25',
        ];
        $this->post(route('staff.payroll.store', $this->school), $payload)->assertRedirect();
        $record = DB::table('payroll_plan_entries')->first();
        $this->assertSame(102050, (int) $record->gross_minor);
        $this->assertSame(2025, (int) $record->deductions_minor);
        $this->assertSame(100025, (int) $record->net_minor);
        $this->post(route('staff.payroll.store', $this->school), $payload)
            ->assertSessionHasErrors('period');
        $this->post(route('staff.payroll.review', [$this->school, $record->id]), [
            'decision' => 'approve',
        ])->assertForbidden();

        $this->actingAs($this->administrator)->post(
            route('staff.payroll.review', [$this->school, $record->id]),
            ['decision' => 'approve']
        )->assertRedirect();
        $this->assertDatabaseHas('payroll_plan_entries', [
            'id' => $record->id, 'status' => 'approved',
            'reviewed_by' => $this->administrator->id,
        ]);
        $this->post(route('staff.payroll.review', [$this->school, $record->id]), [
            'decision' => 'void',
        ])->assertSessionHasErrors('decision');
    }

    public function test_published_notices_have_private_and_idempotent_read_receipts(): void
    {
        $this->actingAs($this->director)->post(route('announcements.store', $this->school), [
            'title' => 'Latest school information',
            'body' => 'The campus schedule has been updated for December.',
        ])->assertRedirect();
        $notice = DB::table('institution_announcements')->first();

        $this->actingAs($this->teacher)->post(route('announcements.read', [
            $this->school, $notice->id,
        ]))->assertForbidden();
        $this->actingAs($this->administrator)->post(
            route('announcements.transition', [$this->school, $notice->id]),
            ['action' => 'publish']
        )->assertRedirect();

        $this->actingAs($this->teacher)
            ->get(route('announcements.index', $this->school))
            ->assertOk()->assertSee('Mark as read');

        $this->post(route('announcements.read', [$this->school, $notice->id]))
            ->assertRedirect();
        $this->post(route('announcements.read', [$this->school, $notice->id]))
            ->assertRedirect();
        $this->assertDatabaseCount('announcement_read_receipts', 1);
        $this->assertDatabaseHas('announcement_read_receipts', [
            'institution_id' => $this->school->id,
            'announcement_id' => $notice->id,
            'user_id' => $this->teacher->id,
        ]);
        $this->get(route('announcements.index', $this->school))
            ->assertOk()->assertSee('Read');
    }
}
