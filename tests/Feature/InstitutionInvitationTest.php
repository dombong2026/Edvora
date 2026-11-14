<?php

namespace Tests\Feature;

use App\Enums\AccountRole;
use App\Models\Campus;
use App\Models\Institution;
use App\Models\InstitutionInvitation;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\Role;
use App\Models\User;
use App\Services\InstitutionInvitationService;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InstitutionInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Mail::fake();
    }

    private function setupInstitution(): array
    {
        $institution = Institution::create(['name' => 'Test School', 'slug' => 'test-school', 'status' => 'active']);
        $campus = Campus::create(['institution_id' => $institution->id, 'name' => 'Main', 'slug' => 'main', 'status' => 'active']);
        $director = User::factory()->create();
        InstitutionMembership::create(['institution_id' => $institution->id, 'user_id' => $director->id, 'status' => 'active']);
        InstitutionRoleAssignment::create([
            'institution_id' => $institution->id, 'user_id' => $director->id,
            'role_id' => Role::where('code', 'director')->first()->id,
        ]);
        $director->forceFill([
            'mfa_enabled_at' => now(),
            'mfa_secret' => app(\App\Services\Totp::class)->newSecret(),
        ])->save();

        return [$institution, $campus, $director];
    }

    public function test_director_can_invite_student_then_redeem_token_only_once(): void
    {
        [$institution, $campus, $director] = $this->setupInstitution();

        $this->actingAs($director)->post(route('invitations.store', $institution), [
            'email' => 'student@example.test', 'role' => 'student', 'campus_id' => $campus->id,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('institution_invitations', 1);

        $invitation = InstitutionInvitation::firstOrFail();
        $this->assertNull($invitation->accepted_at);
        $this->assertTrue($invitation->expires_at->isFuture());

        // Token is never stored as clear text.
        $this->assertSame(64, strlen($invitation->token_hash));
        $this->assertDatabaseCount('users', 1);

        $this->assertSame('student', $invitation->role->code);

        // Domain service tested with a second identity as if the email link were received.
        $student = User::factory()->create(['email' => 'student@example.test']);
        $token = 'known-example-token';
        $invitation->update(['token_hash' => hash('sha256', $token)]);

        app(InstitutionInvitationService::class)->accept($token, $student);
        $this->assertSame('active', $student->memberships()->first()->status);
        $this->assertSame($campus->id, $student->roleAssignments()->first()->campus_id);
        $this->assertNotNull($invitation->fresh()->accepted_at);

        $this->expectException(AuthorizationException::class);
        app(InstitutionInvitationService::class)->accept($token, $student);
    }

    public function test_unprivileged_or_foreign_actor_cannot_invite(): void
    {
        [$institution, $campus, $director] = $this->setupInstitution();
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post(route('invitations.store', $institution), [
            'email' => 'anyone@example.test', 'role' => 'teacher', 'campus_id' => $campus->id,
        ])->assertForbidden();
        $this->assertDatabaseCount('institution_invitations', 0);
    }

    public function test_invitation_to_another_institutions_campus_is_rejected(): void
    {
        [$institution, $campus, $director] = $this->setupInstitution();
        $other = Institution::create(['name' => 'Foreign', 'slug' => 'foreign']);
        $foreignCampus = Campus::create(['institution_id' => $other->id, 'name' => 'Other', 'slug' => 'other']);

        $this->actingAs($director)->post(route('invitations.store', $institution), [
            'email' => 'test@example.test', 'role' => 'student', 'campus_id' => $foreignCampus->id,
        ])->assertNotFound();
        $this->assertDatabaseCount('institution_invitations', 0);
    }

    public function test_expired_invitation_cannot_be_redeemed(): void
    {
        [$institution, $campus, $director] = $this->setupInstitution();
        $service = app(InstitutionInvitationService::class);
        $invitation = $service->invite($director, $institution, 'new@example.test', AccountRole::Student, $campus);
        $invitation->forceFill(['expires_at' => now()->subMinute(), 'token_hash' => hash('sha256', 'expired')])->save();

        $this->get(route('invitations.show', ['token' => 'expired']))->assertNotFound();

        $person = User::factory()->create(['email' => 'new@example.test']);
        $this->expectException(AuthorizationException::class);
        $service->accept('expired', $person);
    }

    public function test_suspended_member_is_not_reactivated_by_invite(): void
    {
        [$institution, $campus, $director] = $this->setupInstitution();
        $student = User::factory()->create(['email' => 'blocked@example.test']);
        InstitutionMembership::create(['user_id' => $student->id, 'institution_id' => $institution->id, 'status' => 'suspended']);
        $service = app(InstitutionInvitationService::class);
        $invitation = $service->invite($director, $institution, $student->email, AccountRole::Student, $campus);
        $invitation->update(['token_hash' => hash('sha256', 'suspended')]);

        $this->expectException(AuthorizationException::class);
        $service->accept('suspended', $student);
    }

    public function test_non_leadership_role_needs_campus(): void
    {
        [$institution, $campus, $director] = $this->setupInstitution();
        $this->expectException(ValidationException::class);
        app(InstitutionInvitationService::class)->invite(
            $director, $institution, 'nobody@example.test', AccountRole::Teacher, null
        );
    }

    public function test_new_invitee_can_create_account_only_using_valid_one_time_token(): void
    {
        [$institution, $campus, $director] = $this->setupInstitution();
        $service = app(InstitutionInvitationService::class);
        $invitation = $service->invite($director, $institution, 'newuser@example.test', AccountRole::Teacher, $campus);
        $invitation->forceFill(['token_hash' => hash('sha256', 'secure-token')])->save();

        $this->get(route('invitations.show', ['token' => 'secure-token']))
            ->assertOk()->assertSee('Join Test School');
        $this->post(route('invitations.accept', ['token' => 'secure-token']), [
            'name' => 'Invited Teacher',
            'password' => 'LongCorrectPassword123!',
            'password_confirmation' => 'LongCorrectPassword123!',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'newuser@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('teacher', $user->roleAssignments()->with('role')->first()->role->code);
        $this->assertDatabaseHas('security_events', ['event_type' => 'invitation.accepted', 'subject_user_id' => $user->id]);

        $this->post(route('invitations.accept', ['token' => 'secure-token']))->assertNotFound();
        $this->assertDatabaseCount('users', 2);
    }

    public function test_existing_user_must_sign_in_before_accepting(): void
    {
        [$institution, $campus, $director] = $this->setupInstitution();
        $invitee = User::factory()->create(['email' => 'existing@example.test']);
        $invitation = app(InstitutionInvitationService::class)
            ->invite($director, $institution, $invitee->email, AccountRole::Teacher, $campus);
        $invitation->forceFill(['token_hash' => hash('sha256', 'login-first')])->save();

        $this->get(route('invitations.show', ['token' => 'login-first']))
            ->assertRedirect(route('login'));
        $this->assertDatabaseMissing('institution_memberships', [
            'user_id' => $invitee->id, 'institution_id' => $institution->id,
        ]);

        $this->actingAs($invitee)->get(route('invitations.show', ['token' => 'login-first']))
            ->assertOk();
        $this->post(route('invitations.accept', ['token' => 'login-first']))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('institution_memberships', [
            'user_id' => $invitee->id, 'institution_id' => $institution->id, 'status' => 'active',
        ]);
    }

    public function test_authorized_officer_can_revoke_pending_invitation(): void
    {
        [$institution, $campus, $director] = $this->setupInstitution();
        $invitation = app(InstitutionInvitationService::class)
            ->invite($director, $institution, 'revoked@example.test', AccountRole::Student, $campus);
        $invitation->forceFill(['token_hash' => hash('sha256', 'revoke-token')])->save();

        $this->actingAs($director)->delete(route('invitations.revoke', [$institution, $invitation]))
            ->assertRedirect();

        $this->assertNotNull($invitation->fresh()->revoked_at);
        $this->assertDatabaseHas('security_events', [
            'event_type' => 'invitation.revoked', 'actor_user_id' => $director->id,
        ]);
        $this->get(route('invitations.show', ['token' => 'revoke-token']))->assertNotFound();
    }

}
