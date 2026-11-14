<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\Role;
use App\Models\User;
use App\Services\InternalInbox;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class InternalInboxTest extends TestCase
{
    use RefreshDatabase;

    private Institution $school;
    private Campus $campus;
    private User $teacher;
    private User $director;
    private User $otherTeacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->school = Institution::create(['name' => 'Private School', 'slug' => 'private-school']);
        $this->school->refresh(); // Materialize the database's default active state.
        $this->campus = Campus::create([
            'institution_id' => $this->school->id, 'slug' => 'north', 'name' => 'North',
        ]);
        $this->teacher = $this->member('teacher', $this->campus);
        $this->otherTeacher = $this->member('teacher', $this->campus);
        $this->director = $this->member('director');
    }

    private function member(string $role, ?Campus $campus = null): User
    {
        $user = User::factory()->create();
        InstitutionMembership::create([
            'institution_id' => $this->school->id, 'user_id' => $user->id, 'status' => 'active',
        ]);
        InstitutionRoleAssignment::create([
            'institution_id' => $this->school->id, 'user_id' => $user->id,
            'role_id' => Role::where('code', $role)->firstOrFail()->id,
            'campus_id' => $campus?->id,
        ]);
        if ($role === 'director') {
            $user->forceFill([
                'mfa_enabled_at' => now(), 'mfa_secret' => app(\App\Services\Totp::class)->newSecret(),
            ])->save();
        }
        return $user;
    }

    public function test_leave_approval_triggers_private_in_app_alert_that_can_be_read_and_archived(): void
    {
        $this->actingAs($this->teacher)->post(route('staff.leave.store', $this->school), [
            'campus_id' => $this->campus->id,
            'category' => 'annual',
            'starts_on' => now()->addDays(5)->toDateString(),
            'ends_on' => now()->addDays(7)->toDateString(),
        ])->assertRedirect();
        $leave = DB::table('staff_leave_requests')->firstOrFail();

        $this->actingAs($this->director)->post(route('staff.leave.review', [
            $this->school, $leave->id,
        ]), ['decision' => 'approve'])->assertRedirect();

        $notice = DB::table('internal_inbox_messages')->firstOrFail();
        $this->assertSame($this->teacher->id, (int) $notice->recipient_user_id);
        $this->assertSame('staff_leave_approved', $notice->event_key);
        $this->assertSame('leave', $notice->source_type);

        $this->actingAs($this->otherTeacher)->get(route('inbox.index', $this->school))
            ->assertOk()->assertDontSee('Your leave request was approved');
        $this->post(route('inbox.mark', [$this->school, $notice->id]), [
            'action' => 'archive',
        ])->assertNotFound();

        $this->actingAs($this->teacher)->get(route('inbox.index', $this->school))
            ->assertOk()->assertSee('Your leave request was approved')
            ->assertSee('Mark as read');
        $this->post(route('inbox.mark', [$this->school, $notice->id]), [
            'action' => 'read',
        ])->assertRedirect();
        $this->assertNotNull(DB::table('internal_inbox_messages')->find($notice->id)->read_at);
        $this->get(route('inbox.index', [
            'institution' => $this->school, 'filter' => 'unread',
        ]))->assertOk()->assertDontSee('Your leave request was approved');

        $this->post(route('inbox.mark', [$this->school, $notice->id]), [
            'action' => 'archive',
        ])->assertRedirect();
        $this->assertNotNull(DB::table('internal_inbox_messages')->find($notice->id)->archived_at);
        $this->get(route('inbox.index', $this->school))
            ->assertOk()->assertDontSee('Your leave request was approved');
    }

    public function test_inbox_events_are_idempotent_and_never_leave_the_institution(): void
    {
        $inbox = app(InternalInbox::class);
        $url = route('staff.leave.index', $this->school, false);
        $inbox->send($this->school, $this->teacher, 'staff_leave_approved', 'leave', 100, $url);
        $inbox->send($this->school, $this->teacher, 'staff_leave_approved', 'leave', 100, $url);
        $this->assertDatabaseCount('internal_inbox_messages', 1);

        $foreign = Institution::create(['name' => 'Foreign School', 'slug' => 'foreign-school']);
        $foreign->refresh();
        $inbox->send($foreign, $this->teacher, 'staff_leave_approved', 'leave', 100,
            route('staff.leave.index', $foreign, false));
        $this->assertDatabaseCount('internal_inbox_messages', 1);

        InstitutionMembership::where('institution_id', $this->school->id)
            ->where('user_id', $this->teacher->id)->update(['status' => 'suspended']);
        $this->actingAs($this->teacher)->get(route('inbox.index', $this->school))
            ->assertForbidden();
        $inbox->send($this->school, $this->teacher, 'staff_leave_rejected', 'leave', 101, $url);
        $this->assertDatabaseCount('internal_inbox_messages', 1);
    }
}
