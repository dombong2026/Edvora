<?php

namespace Tests\Feature;

use App\Models\AcademicSubject;
use App\Models\AcademicYear;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\Role;
use App\Models\User;
use App\Services\Totp;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function school(string $slug): Institution
    {
        return Institution::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => 'active']);
    }

    private function member(Institution $school, string $role): User
    {
        $user = User::factory()->create();
        InstitutionMembership::create([
            'institution_id' => $school->id, 'user_id' => $user->id, 'status' => 'active',
        ]);
        InstitutionRoleAssignment::create([
            'institution_id' => $school->id, 'user_id' => $user->id,
            'role_id' => Role::where('code', $role)->firstOrFail()->id,
        ]);
        if (in_array($role, ['director', 'administrator', 'dean', 'manager'], true)) {
            $user->forceFill([
                'mfa_secret' => app(Totp::class)->newSecret(), 'mfa_enabled_at' => now(),
            ])->save();
        }

        return $user;
    }

    public function test_director_creates_year_and_subject_scoped_to_own_school(): void
    {
        $school = $this->school('school-a');
        $director = $this->member($school, 'director');

        $this->actingAs($director)->post(route('academic.years.store', $school), [
            'code' => 'AY2027', 'name' => 'Academic Year 2027',
            'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31',
        ])->assertRedirect(route('academic.index', $school));
        $this->post(route('academic.subjects.store', $school), [
            'code' => 'MTH101', 'name' => 'Mathematics',
        ])->assertRedirect(route('academic.index', $school));

        $this->assertDatabaseHas('academic_years', ['institution_id' => $school->id, 'code' => 'AY2027', 'status' => 'draft']);
        $this->assertDatabaseHas('academic_subjects', ['institution_id' => $school->id, 'code' => 'MTH101']);
        $this->get(route('academic.index', $school))->assertOk()
            ->assertSee('Academic Year 2027')->assertSee('Mathematics');
    }

    public function test_duplicate_year_code_and_invalid_end_date_are_rejected(): void
    {
        $school = $this->school('school-a');
        $director = $this->member($school, 'director');
        AcademicYear::create([
            'institution_id' => $school->id, 'created_by' => $director->id,
            'code' => 'AY2027', 'name' => 'Year', 'starts_on' => '2027-01-01',
            'ends_on' => '2027-12-31',
        ]);

        $this->actingAs($director)->post(route('academic.years.store', $school), [
            'code' => 'AY2027', 'name' => 'Duplicate',
            'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31',
        ])->assertSessionHasErrors('code');

        $this->post(route('academic.years.store', $school), [
            'code' => 'AY2028', 'name' => 'Invalid',
            'starts_on' => '2028-12-31', 'ends_on' => '2028-01-01',
        ])->assertSessionHasErrors('ends_on');

        $this->assertDatabaseCount('academic_years', 1);
    }

    public function test_teacher_cannot_modify_catalog(): void
    {
        $school = $this->school('school-a');
        $teacher = $this->member($school, 'teacher');

        $this->actingAs($teacher)->get(route('academic.index', $school))->assertOk();
        $this->post(route('academic.subjects.store', $school), [
            'code' => 'SCI101', 'name' => 'Science',
        ])->assertForbidden();
        $this->assertDatabaseCount('academic_subjects', 0);
    }

    public function test_foreign_institution_data_is_not_visible(): void
    {
        $first = $this->school('first');
        $second = $this->school('second');
        $teacher = $this->member($first, 'teacher');
        $director = $this->member($second, 'director');
        AcademicSubject::create([
            'institution_id' => $second->id, 'created_by' => $director->id,
            'code' => 'SEC999', 'name' => 'Sensitive Institutional Subject',
        ]);

        $this->actingAs($teacher)->get(route('academic.index', $first))
            ->assertOk()->assertDontSee('Sensitive Institutional Subject');
        $this->get(route('academic.index', $second))->assertForbidden();
    }

    public function test_anonymous_user_is_redirected_before_catalog_access(): void
    {
        $school = $this->school('school-a');
        $this->get(route('academic.index', $school))->assertRedirect(route('login'));
    }
}
