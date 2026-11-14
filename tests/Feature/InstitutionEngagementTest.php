<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\PlatformAdministrator;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstitutionEngagementTest extends TestCase
{
    use RefreshDatabase;

    private Institution $school;
    private Institution $foreignSchool;
    private Campus $north;
    private Campus $south;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->school = Institution::create(['name' => 'Alpha Academy', 'slug' => 'alpha-academy']);
        $this->foreignSchool = Institution::create(['name' => 'Secret School', 'slug' => 'secret-school']);
        $this->north = Campus::create([
            'institution_id' => $this->school->id, 'slug' => 'north', 'name' => 'North',
        ]);
        $this->south = Campus::create([
            'institution_id' => $this->school->id, 'slug' => 'south', 'name' => 'South',
        ]);
    }

    private function member(string $role, ?Campus $campus = null, bool $mfa = false): User
    {
        $user = User::factory()->create();
        InstitutionMembership::create([
            'institution_id' => $this->school->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        InstitutionRoleAssignment::create([
            'institution_id' => $this->school->id,
            'user_id' => $user->id,
            'role_id' => Role::where('code', $role)->firstOrFail()->id,
            'campus_id' => $campus?->id,
        ]);
        if ($mfa) {
            $user->forceFill([
                'mfa_enabled_at' => now(),
                'mfa_secret' => app(\App\Services\Totp::class)->newSecret(),
            ])->save();
        }
        return $user;
    }

    private function openTicket(User $owner): int
    {
        $this->actingAs($owner)->post(route('helpdesk.store', $this->school), [
            'campus_id' => $this->north->id,
            'category' => 'academic',
            'title' => 'Help with my class',
            'body' => 'Could you help me understand my schedule?',
        ])->assertRedirect();
        return DB::table('institution_help_tickets')->orderByDesc('id')->first()->id;
    }

    public function test_all_institution_roles_share_real_support_and_announcement_navigation(): void
    {
        $student = $this->member('student', $this->north);
        $this->actingAs($student)->get(route('dashboard.institution', $this->school))
            ->assertOk()->assertSee('School updates')
            ->assertSee(route('helpdesk.index', $this->school), false)
            ->assertSee(route('announcements.index', $this->school), false);

        $this->get(route('helpdesk.index', $this->school))
            ->assertOk()->assertSee('Private support requests')
            ->assertSee('Submit support request');
        $this->get(route('announcements.index', $this->school))
            ->assertOk()->assertSee('School announcements')
            ->assertDontSee('Draft a school announcement');
    }

    public function test_support_request_has_actual_replies_status_history_and_private_access(): void
    {
        $student = $this->member('student', $this->north);
        $stranger = $this->member('student', $this->north);
        $director = $this->member('director', null, true);
        $ticketId = $this->openTicket($student);

        $this->assertDatabaseHas('institution_help_tickets', [
            'id' => $ticketId, 'institution_id' => $this->school->id,
            'created_by' => $student->id, 'status' => 'open',
        ]);
        $this->assertDatabaseCount('institution_help_replies', 1);
        $this->actingAs($stranger)
            ->get(route('helpdesk.show', [$this->school, $ticketId]))->assertForbidden();
        $this->post(route('helpdesk.reply', [$this->school, $ticketId]), [
            'body' => 'Unauthorized message',
        ])->assertForbidden();

        $this->actingAs($director)->get(route('helpdesk.show', [$this->school, $ticketId]))
            ->assertOk()->assertSee('Help with my class');
        $this->post(route('helpdesk.reply', [$this->school, $ticketId]), [
            'body' => 'Your timetable is available in My classrooms.',
        ])->assertRedirect();
        $this->assertDatabaseHas('institution_help_tickets', ['id' => $ticketId, 'status' => 'answered']);

        $this->actingAs($student)->get(route('helpdesk.show', [$this->school, $ticketId]))
            ->assertOk()->assertSee('Your timetable is available');
        $this->post(route('helpdesk.reply', [$this->school, $ticketId]), [
            'body' => 'Thank you for clarifying.',
        ])->assertRedirect();
        $this->assertDatabaseHas('institution_help_tickets', ['id' => $ticketId, 'status' => 'open']);
        $this->post(route('helpdesk.status', [$this->school, $ticketId]), [
            'action' => 'close',
        ])->assertRedirect();
        $this->assertDatabaseHas('institution_help_tickets', ['id' => $ticketId, 'status' => 'closed']);

        $this->post(route('helpdesk.reply', [$this->school, $ticketId]), [
            'body' => 'This needs reopening',
        ])->assertSessionHasErrors('body');

        $this->post(route('helpdesk.status', [$this->school, $ticketId]), [
            'action' => 'reopen',
        ])->assertRedirect();
        $this->assertDatabaseHas('institution_help_tickets', ['id' => $ticketId, 'status' => 'open']);
        $this->assertDatabaseCount('institution_help_replies', 3);
        $this->assertDatabaseHas('security_events', ['event_type' => 'help.ticket.reopened']);
    }

    public function test_helpdesk_rejects_cross_campus_and_suspended_members(): void
    {
        $student = $this->member('student', $this->north);
        $this->actingAs($student)->post(route('helpdesk.store', $this->school), [
            'campus_id' => $this->south->id, 'category' => 'account',
            'title' => 'A forged campus ticket',
            'body' => 'This must not be stored outside the assigned campus.',
        ])->assertForbidden();
        $this->assertDatabaseCount('institution_help_tickets', 0);

        $ticketId = $this->openTicket($student);
        InstitutionMembership::query()->where('user_id', $student->id)
            ->where('institution_id', $this->school->id)->update(['status' => 'suspended']);

        $this->get(route('helpdesk.show', [$this->school, $ticketId]))->assertForbidden();
        $this->get(route('announcements.index', $this->school))->assertForbidden();
        $this->get(route('dashboard.institution', $this->foreignSchool))->assertForbidden();
    }

    public function test_notices_require_independent_review_and_campus_audience(): void
    {
        $writer = $this->member('director', null, true);
        $reviewer = $this->member('administrator', null, true);
        $northStudent = $this->member('student', $this->north);
        $southStudent = $this->member('student', $this->south);

        $this->actingAs($writer)->post(route('announcements.store', $this->school), [
            'campus_id' => $this->north->id,
            'title' => 'North campus timetable update',
            'body' => 'Classes will follow the new schedule next Monday.',
        ])->assertRedirect();

        $notice = DB::table('institution_announcements')->first();
        $this->assertSame('draft', $notice->status);
        $this->post(route('announcements.transition', [$this->school, $notice->id]), [
            'action' => 'publish',
        ])->assertForbidden();

        $this->actingAs($northStudent)
            ->get(route('announcements.show', [$this->school, $notice->id]))->assertForbidden();
        $this->get(route('announcements.index', $this->school))
            ->assertDontSee('North campus timetable update');

        $this->actingAs($reviewer)
            ->post(route('announcements.transition', [$this->school, $notice->id]), [
                'action' => 'publish',
            ])->assertRedirect();
        $this->assertDatabaseHas('institution_announcements', [
            'id' => $notice->id, 'status' => 'published', 'published_by' => $reviewer->id,
        ]);

        $this->actingAs($northStudent)
            ->get(route('announcements.index', $this->school))
            ->assertOk()->assertSee('North campus timetable update');
        $this->get(route('announcements.show', [$this->school, $notice->id]))
            ->assertOk()->assertSee('Classes will follow');

        $this->actingAs($southStudent)
            ->get(route('announcements.index', $this->school))
            ->assertOk()->assertDontSee('North campus timetable update');
        $this->get(route('announcements.show', [$this->school, $notice->id]))->assertForbidden();

        $this->actingAs($reviewer)
            ->post(route('announcements.transition', [$this->school, $notice->id]), [
                'action' => 'archive',
            ])->assertRedirect();
        $this->actingAs($northStudent)
            ->get(route('announcements.show', [$this->school, $notice->id]))->assertForbidden();
        $this->assertDatabaseHas('security_events', ['event_type' => 'notice.archived']);
    }

    public function test_institution_wide_notice_is_visible_only_to_its_active_members(): void
    {
        $writer = $this->member('administrator', null, true);
        $reviewer = $this->member('director', null, true);
        $student = $this->member('student', $this->south);
        $outside = User::factory()->create();

        $this->actingAs($writer)->post(route('announcements.store', $this->school), [
            'title' => 'Everyone welcome to the event',
            'body' => 'Please attend the school orientation event in December.',
        ])->assertRedirect();
        $id = DB::table('institution_announcements')->first()->id;
        $this->actingAs($reviewer)->post(route('announcements.transition', [$this->school, $id]), [
            'action' => 'publish',
        ])->assertRedirect();
        $this->actingAs($student)->get(route('announcements.index', $this->school))
            ->assertOk()->assertSee('Everyone welcome');
        $this->actingAs($outside)->get(route('announcements.index', $this->school))->assertForbidden();
    }

    public function test_platform_administrator_has_no_tenant_support_access_without_membership(): void
    {
        $platform = User::factory()->create();
        PlatformAdministrator::create(['user_id' => $platform->id]);
        $platform->forceFill([
            'mfa_enabled_at' => now(),
            'mfa_secret' => app(\App\Services\Totp::class)->newSecret(),
        ])->save();

        $this->actingAs($platform)->get(route('dashboard.platform'))->assertOk();
        $this->get(route('helpdesk.index', $this->school))->assertForbidden();
        $this->get(route('announcements.index', $this->school))->assertForbidden();
    }

    public function test_public_feature_and_help_pages_are_navigable_for_guests(): void
    {
        $pages = [
            'public.features' => 'Purpose-built school workspaces',
            'public.roles' => 'One premium experience for every account',
            'public.security' => 'Built for accountable school operations',
            'public.help' => 'Find your way around Edvora',
        ];
        foreach ($pages as $route => $heading) {
            $this->get(route($route))->assertOk()
                ->assertSee($heading)
                ->assertSee('Sign in')
                ->assertSee(route('public.help'), false)
                ->assertSee('edvora');
        }
    }
}
