<?php

namespace Tests\Feature\Student;

use App\Core\Tenancy\TenantContext;
use App\Modules\Academic\Models\School\AcademicSession;
use App\Modules\Academic\Models\School\SchoolClass;
use App\Modules\Academic\Models\School\SchoolSection;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use App\Modules\Student\Models\Guardian;
use App\Modules\Student\Models\Student;
use App\Modules\Student\Services\StudentAdmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentAdmissionAndPiiTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected AcademicSession $session;
    protected SchoolClass $class;
    protected SchoolSection $section;
    protected StudentAdmissionService $admissionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admissionService = app(StudentAdmissionService::class);

        // Create Tenant
        $this->tenant = Tenant::create([
            'name' => 'Lahore Grammar School',
            'slug' => 'lgs-main',
            'type' => 'school',
            'status' => 'active',
        ]);

        // Create Main Branch
        $this->branch = Branch::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Gulberg Campus',
            'code' => 'GUL-01',
            'city' => 'Lahore',
            'is_main' => true,
        ]);

        // Establish tenant context
        app(TenantContext::class)->setTenant($this->tenant);
        app(TenantContext::class)->setBranch($this->branch);

        // Create Academic Session
        $this->session = AcademicSession::create([
            'tenant_id' => $this->tenant->id,
            'name' => '2026-2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
        ]);

        // Create Class & Section
        $this->class = SchoolClass::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Grade 9',
            'numeric_level' => 9,
            'display_order' => 9,
        ]);

        $this->section = SchoolSection::create([
            'tenant_id' => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'name' => 'Section A',
            'capacity' => 35,
        ]);
    }

    public function test_sequential_admission_number_generation(): void
    {
        $num1 = $this->admissionService->generateAdmissionNumber($this->tenant->id, 'LGS');
        $this->assertStringStartsWith('LGS-' . date('Y') . '-0001', $num1);

        // Admit first student
        $student1 = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => $num1,
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'gender' => 'male',
            'date_of_birth' => '2012-05-10',
            'admission_date' => now()->toDateString(),
        ]);

        // Generate second number
        $num2 = $this->admissionService->generateAdmissionNumber($this->tenant->id, 'LGS');
        $this->assertStringStartsWith('LGS-' . date('Y') . '-0002', $num2);

        // Another tenant has independent numbering
        $tenant2 = Tenant::create([
            'name' => 'Beaconhouse',
            'slug' => 'bss-main',
            'type' => 'school',
            'status' => 'active',
        ]);

        $numTenant2 = $this->admissionService->generateAdmissionNumber($tenant2->id, 'LGS');
        $this->assertStringStartsWith('LGS-' . date('Y') . '-0001', $numTenant2);
    }

    public function test_b_form_and_cnic_are_encrypted_at_rest_and_not_plaintext_in_db(): void
    {
        $rawBForm = '35201-1234567-1';
        $rawCnic = '35202-9876543-1';

        $student = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'LGS-2026-0001',
            'first_name' => 'Usman',
            'last_name' => 'Tariq',
            'gender' => 'male',
            'date_of_birth' => '2011-08-15',
            'admission_date' => now()->toDateString(),
        ]);
        $student->setBFormAttribute($rawBForm);
        $student->save();

        $guardian = Guardian::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Tariq',
            'last_name' => 'Mahmood',
            'phone' => '+923001234567',
        ]);
        $guardian->setCnicAttribute($rawCnic);
        $guardian->save();

        // 1. Inspect direct raw database records (bypassing Eloquent casts)
        $rawStudent = DB::table('students')->where('id', $student->id)->first();
        $this->assertNotEmpty($rawStudent->b_form_encrypted);
        $this->assertNotEquals($rawBForm, $rawStudent->b_form_encrypted);
        $this->assertStringNotContainsString($rawBForm, $rawStudent->b_form_encrypted, 'Plaintext B-Form must never exist in database column');

        $rawGuardian = DB::table('guardians')->where('id', $guardian->id)->first();
        $this->assertNotEmpty($rawGuardian->cnic_encrypted);
        $this->assertNotEquals($rawCnic, $rawGuardian->cnic_encrypted);
        $this->assertStringNotContainsString($rawCnic, $rawGuardian->cnic_encrypted, 'Plaintext CNIC must never exist in database column');

        // 2. Verify Eloquent transparently decrypts
        $freshStudent = Student::find($student->id);
        $this->assertEquals($rawBForm, $freshStudent->b_form);
        $this->assertEquals($rawBForm, $freshStudent->b_form_encrypted);

        $freshGuardian = Guardian::find($guardian->id);
        $this->assertEquals($rawCnic, $freshGuardian->cnic);
        $this->assertEquals($rawCnic, $freshGuardian->cnic_encrypted);
    }

    public function test_blind_index_enables_deterministic_search_across_formatting_variants(): void
    {
        $rawBForm = '35201-1234567-1';

        $student = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'LGS-2026-0001',
            'first_name' => 'Hamza',
            'last_name' => 'Bilal',
            'gender' => 'male',
            'date_of_birth' => '2010-03-22',
            'admission_date' => now()->toDateString(),
        ]);
        $student->setBFormAttribute($rawBForm);
        $student->save();

        // Check blind index column is populated
        $this->assertNotNull($student->b_form_blind_index);
        $this->assertEquals(64, strlen($student->b_form_blind_index)); // SHA-256 is 64 hex characters

        // Query using exact formatted string
        $found1 = Student::whereBForm('35201-1234567-1')->first();
        $this->assertNotNull($found1);
        $this->assertEquals($student->id, $found1->id);

        // Query using unhyphenated string (normalized lookup)
        $found2 = Student::whereBForm('3520112345671')->first();
        $this->assertNotNull($found2);
        $this->assertEquals($student->id, $found2->id);

        // Non-existent B-Form returns null
        $notFound = Student::whereBForm('35201-9999999-9')->first();
        $this->assertNull($notFound);
    }

    public function test_admit_to_school_service_creates_complete_records(): void
    {
        $result = $this->admissionService->admitToSchool([
            'first_name' => 'Zayd',
            'last_name' => 'Ahmed',
            'gender' => 'male',
            'date_of_birth' => '2013-02-14',
            'b_form' => '35202-5555555-3',
            'admission_date' => '2026-04-05',
            'guardian_name' => 'Ahmed Bilal',
            'guardian_phone' => '+923219876543',
            'guardian_email' => 'ahmed.bilal@example.com',
            'guardian_cnic' => '35202-1111111-1',
            'guardian_relation' => 'father',
            'academic_session_id' => $this->session->id,
            'school_section_id' => $this->section->id,
            'roll_number' => '01',
        ]);

        $student = $result['student'];
        $guardian = $result['guardian'];
        $enrollment = $result['enrollment'];

        $this->assertNotNull($student);
        $this->assertEquals('Zayd Ahmed', $student->full_name);
        $this->assertEquals('35202-5555555-3', $student->b_form);

        $this->assertNotNull($guardian);
        $this->assertEquals('+923219876543', $guardian->phone);
        $this->assertEquals('35202-1111111-1', $guardian->cnic);

        // Verify relationship attachment
        $this->assertTrue($student->guardians->contains($guardian));
        $this->assertEquals('father', $student->guardians->first()->pivot->relationship_type);
        $this->assertTrue($student->guardians->first()->pivot->is_primary);

        // Verify section enrollment
        $this->assertNotNull($enrollment);
        $this->assertEquals($this->session->id, $enrollment->academic_session_id);
        $this->assertEquals($this->section->id, $enrollment->school_section_id);
        $this->assertEquals('01', $enrollment->roll_number);
        $this->assertEquals('active', $enrollment->enrollment_status);
    }

    public function test_guardian_can_be_linked_to_multiple_students_as_wards(): void
    {
        $guardian = Guardian::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Muhammad',
            'last_name' => 'Rashid',
            'phone' => '+923005551234',
        ]);

        $child1 = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'LGS-2026-0001',
            'first_name' => 'Hassan',
            'last_name' => 'Rashid',
            'gender' => 'male',
            'date_of_birth' => '2012-01-01',
            'admission_date' => now()->toDateString(),
        ]);

        $child2 = Student::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'LGS-2026-0002',
            'first_name' => 'Fatima',
            'last_name' => 'Rashid',
            'gender' => 'female',
            'date_of_birth' => '2014-06-15',
            'admission_date' => now()->toDateString(),
        ]);

        $guardian->students()->attach($child1->id, [
            'tenant_id' => $this->tenant->id,
            'relationship_type' => 'father',
            'is_primary' => true,
        ]);

        $guardian->students()->attach($child2->id, [
            'tenant_id' => $this->tenant->id,
            'relationship_type' => 'father',
            'is_primary' => true,
        ]);

        $this->assertCount(2, $guardian->students);
        $this->assertTrue($guardian->students->contains($child1));
        $this->assertTrue($guardian->students->contains($child2));
    }
}

