<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ManualFinancialLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Institution $school;
    private Campus $campus;
    private Campus $otherCampus;
    private User $cashier;
    private User $director;
    private StudentProfile $student;
    private int $fee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->school = Institution::create(['name' => 'Finance School', 'slug' => 'finance-school']);
        $this->campus = Campus::create([
            'institution_id' => $this->school->id, 'slug' => 'central', 'name' => 'Central',
        ]);
        $this->otherCampus = Campus::create([
            'institution_id' => $this->school->id, 'slug' => 'west', 'name' => 'West',
        ]);
        $this->cashier = $this->member('cashier', $this->campus);
        $this->director = $this->member('director');
        $this->student = StudentProfile::create([
            'institution_id' => $this->school->id, 'campus_id' => $this->campus->id,
            'student_number' => 'FIN-0001', 'display_name' => 'Synthetic Student',
            'created_by' => $this->director->id,
        ]);
        $this->fee = DB::table('fee_proposals')->insertGetId([
            'institution_id' => $this->school->id, 'campus_id' => $this->campus->id,
            'name' => 'Reviewed tuition fee', 'currency' => 'USD', 'amount_minor' => 2500,
            'status' => 'reviewed', 'created_by' => $this->cashier->id,
            'reviewed_by' => $this->director->id, 'reviewed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function member(string $role, ?Campus $campus = null): User
    {
        $user = User::factory()->create();
        InstitutionMembership::create([
            'institution_id' => $this->school->id,
            'user_id' => $user->id, 'status' => 'active',
        ]);
        InstitutionRoleAssignment::create([
            'institution_id' => $this->school->id,
            'user_id' => $user->id,
            'role_id' => Role::where('code', $role)->firstOrFail()->id,
            'campus_id' => $campus?->id,
        ]);
        if (in_array($role, ['cashier', 'director', 'administrator'], true)) {
            $user->forceFill([
                'mfa_enabled_at' => now(),
                'mfa_secret' => app(\App\Services\Totp::class)->newSecret(),
            ])->save();
        }
        return $user;
    }

    private function draftInvoice(): int
    {
        $this->actingAs($this->cashier)->post(route('finance.invoices.store', $this->school), [
            'campus_id' => $this->campus->id,
            'student_profile_id' => $this->student->id,
            'fee_proposal_id' => $this->fee,
            'invoice_number' => 'inv-2026-001',
        ])->assertRedirect();
        return (int) DB::table('student_invoices')->value('id');
    }

    public function test_reviewed_fee_invoices_require_independent_issue_and_manual_matches_are_not_payments(): void
    {
        $this->actingAs($this->cashier)->get(route('finance.index', [
            'institution' => $this->school, 'campus_id' => $this->campus->id,
        ]))->assertOk()->assertSee('Billing & manual reconciliation', false);
        $invoiceId = $this->draftInvoice();
        $invoice = DB::table('student_invoices')->find($invoiceId);
        $this->assertSame('INV-2026-001', $invoice->invoice_number);
        $this->assertSame(2500, (int)$invoice->amount_minor);
        $this->assertSame('draft', $invoice->status);

        $url = route('finance.invoices.review', [$this->school, $invoiceId]);
        $this->post($url, ['campus_id' => $this->campus->id, 'decision' => 'issue'])
            ->assertForbidden();
        $this->actingAs($this->director)->post($url, [
            'campus_id' => $this->campus->id, 'decision' => 'issue',
        ])->assertRedirect();
        $this->assertDatabaseHas('student_invoices', [
            'id' => $invoiceId, 'status' => 'issued', 'issued_by' => $this->director->id,
        ]);

        $this->actingAs($this->cashier)->post(
            route('finance.matches.store', [$this->school, $invoiceId]), [
                'campus_id' => $this->campus->id,
                'statement_reference' => 'BANK-00001',
                'amount' => '12.50',
            ]
        )->assertRedirect();
        $match = DB::table('manual_reconciliation_entries')->first();
        $this->assertSame(1250, (int)$match->amount_minor);
        $this->assertSame('pending', $match->status);

        $this->post(route('finance.matches.store', [$this->school, $invoiceId]), [
            'campus_id' => $this->campus->id,
            'statement_reference' => 'BANK-00002', 'amount' => '13.00',
        ])->assertSessionHasErrors('amount');

        $this->actingAs($this->director)->post(
            route('finance.matches.review', [$this->school, $match->id]), [
                'campus_id' => $this->campus->id, 'decision' => 'verify',
            ]
        )->assertRedirect();
        $this->assertDatabaseHas('manual_reconciliation_entries', [
            'id' => $match->id, 'status' => 'verified', 'reviewed_by' => $this->director->id,
        ]);
        $this->assertDatabaseHas('security_events', ['event_type' => 'billing.match.reviewed']);
        $this->assertSame('issued', DB::table('student_invoices')->find($invoiceId)->status);
    }

    public function test_cashier_cannot_approve_own_statement_and_no_other_campus_access(): void
    {
        $invoiceId = $this->draftInvoice();
        $this->actingAs($this->director)->post(
            route('finance.invoices.review', [$this->school, $invoiceId]), [
                'campus_id' => $this->campus->id, 'decision' => 'issue',
            ]
        )->assertRedirect();

        $this->actingAs($this->cashier)->post(
            route('finance.matches.store', [$this->school, $invoiceId]), [
                'campus_id' => $this->campus->id,
                'statement_reference' => 'BANK-ABCDE', 'amount' => '10.00',
            ]
        )->assertRedirect();
        $matchId = DB::table('manual_reconciliation_entries')->value('id');
        $this->post(route('finance.matches.review', [$this->school, $matchId]), [
            'campus_id' => $this->campus->id, 'decision' => 'verify',
        ])->assertForbidden();

        $this->get(route('finance.index', [
            'institution' => $this->school, 'campus_id' => $this->otherCampus->id,
        ]))->assertForbidden();
        $this->post(route('finance.invoices.store', $this->school), [
            'campus_id' => $this->otherCampus->id,
            'student_profile_id' => $this->student->id,
            'fee_proposal_id' => $this->fee,
            'invoice_number' => 'FORGED-CAMPUS',
        ])->assertForbidden();
        $this->assertDatabaseCount('student_invoices', 1);
    }

    public function test_students_and_other_institutions_have_no_access_to_private_finance_register(): void
    {
        $student = $this->member('student', $this->campus);
        $this->actingAs($student)->get(route('finance.index', [
            'institution' => $this->school, 'campus_id' => $this->campus->id,
        ]))->assertForbidden();

        $other = Institution::create(['name' => 'Other School', 'slug' => 'other-school']);
        $this->actingAs($this->director)->get(route('finance.index', [
            'institution' => $other, 'campus_id' => $this->campus->id,
        ]))->assertForbidden();
    }
}
