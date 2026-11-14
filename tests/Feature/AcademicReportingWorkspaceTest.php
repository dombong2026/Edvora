<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\AcademicReporting;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicReportingWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Institution $school;
    private Campus $north;
    private Campus $south;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->school = Institution::create(['name' => 'Reporting School', 'slug' => 'reporting-school']);
        $this->north = Campus::create(['institution_id' => $this->school->id, 'slug' => 'north', 'name' => 'North']);
        $this->south = Campus::create(['institution_id' => $this->school->id, 'slug' => 'south', 'name' => 'South']);
    }

    private function member(string $role, ?Campus $campus = null, bool $mfa = true): User
    {
        $user = User::factory()->create();
        InstitutionMembership::create([
            'user_id' => $user->id, 'institution_id' => $this->school->id, 'status' => 'active',
        ]);
        InstitutionRoleAssignment::create([
            'institution_id' => $this->school->id, 'user_id' => $user->id,
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

    public function test_reports_are_aggregated_scoped_and_exportable(): void
    {
        $director = $this->member('director');
        foreach ([$this->north, $this->south] as $campus) {
            StudentProfile::create([
                'institution_id' => $this->school->id,
                'campus_id' => $campus->id,
                'student_number' => 'N-'.$campus->id,
                'display_name' => 'Synthetic Student '.$campus->id,
                'created_by' => $director->id,
            ]);
        }
        $reporting = app(AcademicReporting::class);
        $this->assertSame(2, $reporting->metrics($this->school)['students']);
        $this->assertSame(1, $reporting->metrics($this->school, $this->north)['students']);

        $this->actingAs($director)->get(route('reports.index', [
            'institution' => $this->school, 'campus_id' => $this->north->id,
        ]))->assertOk()->assertSee('Registered student profiles')
            ->assertDontSee('Synthetic Student')->assertSee('Download summary CSV');

        $export = $this->get(route('reports.export', [
            'institution' => $this->school, 'campus_id' => $this->north->id,
        ]))->assertOk();
        $this->assertStringContainsString('.csv', $export->headers->get('content-disposition'));
        $this->assertStringContainsString('text/csv', $export->headers->get('content-type'));
    }

    public function test_only_authorized_mfa_academic_leadership_can_read_reports(): void
    {
        $student = $this->member('student', $this->north, false);
        $this->actingAs($student)->get(route('reports.index', $this->school))->assertForbidden();
        $this->get(route('reports.export', $this->school))->assertForbidden();

        $director = $this->member('director');
        $this->actingAs($director)->get(route('reports.index', [
            'institution' => $this->school, 'campus_id' => 999999,
        ]))->assertForbidden();
        $this->get(route('reports.export', [
            'institution' => $this->school, 'campus_id' => 999999,
        ]))->assertForbidden();

        $foreign = Institution::create(['name' => 'Foreign', 'slug' => 'foreign']);
        $this->get(route('reports.index', $foreign))->assertForbidden();

        foreach (['manager', 'dean', 'administrator'] as $role) {
            $authorized = $this->member($role);
            $this->actingAs($authorized)->get(route('reports.index', $this->school))
                ->assertOk()->assertSee('Academic reports');
        }
    }
}
