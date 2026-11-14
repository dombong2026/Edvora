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

class OperationalWorkspacesTest extends TestCase
{
    use RefreshDatabase;

    private Institution $school;
    private Campus $north;
    private Campus $south;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->school = Institution::create(['name' => 'Alpha Academy', 'slug' => 'alpha-academy']);
        $this->north = Campus::create([
            'institution_id' => $this->school->id, 'slug' => 'north', 'name' => 'North Campus',
        ]);
        $this->south = Campus::create([
            'institution_id' => $this->school->id, 'slug' => 'south', 'name' => 'South Campus',
        ]);
    }

    private function member(string $role, ?Campus $campus = null, ?Institution $school = null, bool $mfa = true): User
    {
        $institution = $school ?? $this->school;
        $user = User::factory()->create();
        InstitutionMembership::create([
            'institution_id' => $institution->id, 'user_id' => $user->id, 'status' => 'active',
        ]);
        InstitutionRoleAssignment::create([
            'institution_id' => $institution->id, 'user_id' => $user->id,
            'role_id' => Role::query()->where('code', $role)->firstOrFail()->id,
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

    private function page(string $module, ?Campus $campus = null): string
    {
        return route('workspaces.index', [
            'institution' => $this->school, 'module' => $module,
            'campus_id' => ($campus ?? $this->north)->id,
        ]);
    }

    public function test_library_items_can_be_issued_once_and_returned_with_inventory_enforcement(): void
    {
        $librarian = $this->member('librarian', $this->north);
        $student = $this->member('student', $this->north);
        $this->actingAs($librarian)->get($this->page('library'))
            ->assertOk()->assertSee('Library circulation')->assertSee('edvora-sidebar');

        $this->post(route('workspaces.store', [$this->school, 'library']), [
            'campus_id' => $this->north->id, 'code' => 'BK-001',
            'title' => 'Modern Learning', 'author' => 'A. Writer', 'stock_total' => 1,
        ])->assertRedirect();
        $item = DB::table('library_items')->first();
        $this->assertSame('Modern Learning', $item->title);

        $loanInput = [
            'campus_id' => $this->north->id, 'borrower_email' => $student->email,
            'due_on' => now()->addDays(14)->toDateString(),
        ];
        $this->post(route('workspaces.loans.issue', [$this->school, $item->id]), $loanInput)
            ->assertRedirect();
        $loan = DB::table('library_loans')->first();
        $this->assertSame($student->id, (int) $loan->borrower_user_id);
        $this->get($this->page('library'))->assertOk()->assertSee('0 / 1');

        $this->post(route('workspaces.loans.issue', [$this->school, $item->id]), $loanInput)
            ->assertSessionHasErrors('item');

        $this->post(route('workspaces.loans.return', [$this->school, $loan->id]), [
            'campus_id' => $this->north->id,
        ])->assertRedirect();
        $this->assertNotNull(DB::table('library_loans')->find($loan->id)->returned_at);
        $this->post(route('workspaces.loans.return', [$this->school, $loan->id]), [
            'campus_id' => $this->north->id,
        ])->assertSessionHasErrors('loan');

        $this->assertDatabaseHas('security_events', ['event_type' => 'library.loan.returned', 'institution_id' => $this->school->id]);
    }

    public function test_fee_proposals_require_independent_director_review_and_store_money_as_minor_units(): void
    {
        $cashier = $this->member('cashier', $this->north);
        $director = $this->member('director');
        $this->actingAs($cashier)->get($this->page('cashier'))->assertOk()->assertSee('Fee proposals');

        $this->post(route('workspaces.store', [$this->school, 'cashier']), [
            'campus_id' => $this->north->id, 'name' => 'Registration proposal',
            'currency' => 'USD', 'amount' => '25.40',
        ])->assertRedirect();
        $record = DB::table('fee_proposals')->first();
        $this->assertSame(2540, (int) $record->amount_minor);
        $this->assertSame('draft', $record->status);

        $action = ['campus_id' => $this->north->id, 'action' => 'review'];
        $url = route('workspaces.transition', [$this->school, 'cashier', $record->id]);
        $this->post($url, $action)->assertForbidden();
        $this->actingAs($director)->post($url, $action)->assertRedirect();
        $this->assertSame('reviewed', DB::table('fee_proposals')->find($record->id)->status);
        $this->post($url, $action)->assertSessionHasErrors('action');

        $this->post(route('workspaces.store', [$this->school, 'cashier']), [
            'campus_id' => $this->north->id, 'name' => 'Whole riel proposal',
            'currency' => 'KHR', 'amount' => '12000',
        ])->assertRedirect();
        $this->assertDatabaseHas('fee_proposals', ['currency' => 'KHR', 'amount_minor' => 12000]);

        $this->post(route('workspaces.store', [$this->school, 'cashier']), [
            'campus_id' => $this->north->id, 'name' => 'Invalid fraction',
            'currency' => 'KHR', 'amount' => '1.23',
        ])->assertSessionHasErrors('amount');
    }

    public function test_reception_can_check_in_and_out_but_not_duplicate_or_cross_campus(): void
    {
        $receptionist = $this->member('receptionist', $this->north);
        $this->actingAs($receptionist)->get($this->page('reception'))
            ->assertOk()->assertSee('Reception desk');
        $this->post(route('workspaces.store', [$this->school, 'reception']), [
            'campus_id' => $this->north->id, 'visitor_name' => 'Guest Example',
            'purpose' => 'School inquiry',
        ])->assertRedirect();
        $record = DB::table('frontdesk_visits')->first();

        $url = route('workspaces.transition', [$this->school, 'reception', $record->id]);
        $this->post($url, ['campus_id' => $this->north->id, 'action' => 'checkout'])
            ->assertRedirect();
        $this->assertNotNull(DB::table('frontdesk_visits')->find($record->id)->checked_out_at);
        $this->post($url, ['campus_id' => $this->north->id, 'action' => 'checkout'])
            ->assertSessionHasErrors('action');
        $this->get($this->page('reception', $this->south))->assertForbidden();
        $this->post(route('workspaces.store', [$this->school, 'reception']), [
            'campus_id' => $this->south->id, 'visitor_name' => 'Forged Guest', 'purpose' => 'Check-in',
        ])->assertForbidden();
        $this->assertDatabaseMissing('frontdesk_visits', ['visitor_name' => 'Forged Guest']);
    }

    public function test_salesperson_can_progress_admissions_but_cannot_reopen_closed_enquiry(): void
    {
        $salesperson = $this->member('salesperson', $this->north);
        $this->actingAs($salesperson)->get($this->page('admissions'))
            ->assertOk()->assertSee('Admissions enquiries');
        $this->post(route('workspaces.store', [$this->school, 'admissions']), [
            'campus_id' => $this->north->id, 'contact_name' => 'Prospect Demo',
            'contact_email' => 'prospect@example.test', 'program' => 'General Studies',
        ])->assertRedirect();

        $record = DB::table('admission_enquiries')->first();
        $url = route('workspaces.transition', [$this->school, 'admissions', $record->id]);
        foreach (['contacted', 'qualified', 'closed'] as $stage) {
            $this->post($url, ['campus_id' => $this->north->id, 'action' => $stage])->assertRedirect();
        }
        $this->assertSame('closed', DB::table('admission_enquiries')->find($record->id)->stage);
        $this->post($url, ['campus_id' => $this->north->id, 'action' => 'contacted'])
            ->assertSessionHasErrors('action');
    }

    public function test_supervisor_operations_board_and_leadership_scope_are_real_and_audited(): void
    {
        $supervisor = $this->member('supervisor');
        $this->actingAs($supervisor)->get($this->page('operations'))->assertOk()
            ->assertSee('Operations task board')->assertSee('edvora-sidebar');
        $this->post(route('workspaces.store', [$this->school, 'operations']), [
            'campus_id' => $this->north->id, 'title' => 'Review campus doors',
            'priority' => 'high', 'due_on' => now()->addDays(2)->toDateString(),
        ])->assertRedirect();
        $record = DB::table('operations_tasks')->first();

        $url = route('workspaces.transition', [$this->school, 'operations', $record->id]);
        $this->post($url, ['campus_id' => $this->north->id, 'action' => 'in_progress'])
            ->assertRedirect();
        $this->post($url, ['campus_id' => $this->north->id, 'action' => 'resolved'])
            ->assertRedirect();
        $this->assertSame('resolved', DB::table('operations_tasks')->find($record->id)->status);
        $this->post($url, ['campus_id' => $this->north->id, 'action' => 'open'])
            ->assertSessionHasErrors('action');
        $this->assertDatabaseHas('security_events', ['event_type' => 'operations.task.updated']);
    }

    public function test_same_layout_for_every_new_specialist_and_role_specific_links(): void
    {
        foreach (['librarian' => 'library', 'cashier' => 'cashier', 'receptionist' => 'reception',
                  'salesperson' => 'admissions'] as $role => $module) {
            $user = $this->member($role, $this->north);
            $this->actingAs($user)
                ->get(route('dashboard.role', [$this->school, $role]))->assertOk()
                ->assertSee('Operational workspaces')
                ->assertSee(\App\Services\OperationalAccess::TITLES[$module]);
            $this->get($this->page($module))->assertOk()
                ->assertSee('data-theme-option="system"', false)
                ->assertSee('edvora-sidebar');
        }
    }

    public function test_shared_operations_role_access_is_limited_to_reviewed_leadership_codes(): void
    {
        foreach (['supervisor', 'manager', 'dean', 'subdirector'] as $role) {
            $account = $this->member($role);
            $this->actingAs($account)->get($this->page('operations'))->assertOk();
            $this->get($this->page('cashier'))->assertForbidden();
            $this->get($this->page('admissions'))->assertForbidden();
        }

        foreach (['director', 'administrator'] as $role) {
            $leader = $this->member($role);
            $this->actingAs($leader);
            foreach (array_keys(\App\Services\OperationalAccess::TITLES) as $module) {
                $this->get($this->page($module))->assertOk();
            }
        }
    }

    public function test_library_catalog_codes_normalize_before_unique_validation(): void
    {
        $librarian = $this->member('librarian', $this->north);
        $this->actingAs($librarian);
        $input = [
            'campus_id' => $this->north->id, 'code' => 'code-001',
            'title' => 'Practical Learning', 'stock_total' => 2,
        ];
        $this->post(route('workspaces.store', [$this->school, 'library']), $input)->assertRedirect();
        $this->assertDatabaseHas('library_items', ['code' => 'CODE-001']);
        $this->post(route('workspaces.store', [$this->school, 'library']), [
            ...$input, 'code' => ' CoDe-001 ',
        ])->assertSessionHasErrors('code');
        $this->assertDatabaseCount('library_items', 1);
    }

    public function test_fee_creator_cannot_approve_own_proposal_even_with_leadership_role(): void
    {
        $director = $this->member('director');
        $this->actingAs($director)->post(route('workspaces.store', [$this->school, 'cashier']), [
            'campus_id' => $this->north->id, 'name' => 'Self reviewed draft',
            'currency' => 'USD', 'amount' => '10.00',
        ])->assertRedirect();
        $id = DB::table('fee_proposals')->first()->id;
        $this->post(route('workspaces.transition', [$this->school, 'cashier', $id]), [
            'campus_id' => $this->north->id, 'action' => 'review',
        ])->assertForbidden();
        $this->assertDatabaseHas('fee_proposals', ['id' => $id, 'status' => 'draft']);
    }

    public function test_unassigned_roles_mfa_and_unauthorized_schools_cannot_read_or_mutate(): void
    {
        $unverified = $this->member('librarian', $this->north, null, false);
        $this->actingAs($unverified)->get($this->page('library'))->assertForbidden();
        $this->post(route('workspaces.store', [$this->school, 'library']), [
            'campus_id' => $this->north->id, 'code' => 'NO', 'title' => 'Blocked Book', 'stock_total' => 1,
        ])->assertForbidden();

        $librarian = $this->member('librarian', $this->north);
        $this->actingAs($librarian)->get($this->page('cashier'))->assertForbidden();
        $this->get($this->page('library', $this->south))->assertForbidden();

        $otherSchool = Institution::create(['name' => 'Secret College', 'slug' => 'secret-college']);
        $foreignCampus = Campus::create([
            'institution_id' => $otherSchool->id, 'slug' => 'central', 'name' => 'Central',
        ]);
        $foreignLibrarian = $this->member('librarian', $foreignCampus, $otherSchool);
        $this->actingAs($foreignLibrarian)->get($this->page('library'))->assertForbidden();

        $this->actingAs($librarian)->post(route('workspaces.store', [$this->school, 'library']), [
            'campus_id' => $foreignCampus->id, 'code' => 'FORGED', 'title' => 'Not allowed', 'stock_total' => 1,
        ])->assertNotFound();
        $this->assertDatabaseCount('library_items', 0);
    }
}
