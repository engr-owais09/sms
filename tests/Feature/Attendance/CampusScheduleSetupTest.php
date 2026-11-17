<?php

namespace Tests\Feature\Attendance;

use App\Core\Tenancy\TenantContext;
use App\Modules\Attendance\Models\AcademicHoliday;
use App\Modules\Attendance\Models\CampusRoom;
use App\Modules\Attendance\Models\SchoolPeriod;
use App\Modules\Institute\Models\Branch;
use App\Modules\Institute\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampusScheduleSetupTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Beaconhouse Liberty',
            'slug' => 'bhs-att-1',
            'type' => 'school',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Liberty Campus',
            'code' => 'BHS-LIB',
            'city' => 'Lahore',
            'is_main' => true,
        ]);

        app(TenantContext::class)->setTenant($this->tenant);
        app(TenantContext::class)->setBranch($this->branch);
    }

    public function test_can_create_campus_rooms_with_unique_code_per_branch(): void
    {
        $room = CampusRoom::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'room_code' => 'R-101',
            'name' => 'Grade 9 Room A',
            'room_type' => 'classroom',
            'capacity' => 45,
        ]);

        $this->assertDatabaseHas('campus_rooms', [
            'id' => $room->id,
            'room_code' => 'R-101',
            'capacity' => 45,
        ]);

        $this->expectException(QueryException::class);
        CampusRoom::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'room_code' => 'R-101',
            'name' => 'Duplicate Room',
            'room_type' => 'classroom',
            'capacity' => 30,
        ]);
    }

    public function test_can_create_and_resolve_tenant_and_branch_holidays(): void
    {
        AcademicHoliday::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => null, // Tenant-wide
            'title' => 'Pakistan Day',
            'holiday_date' => '2026-03-23',
            'holiday_type' => 'gazetted',
        ]);

        AcademicHoliday::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'title' => 'Campus Sports Day',
            'holiday_date' => '2026-11-20',
            'holiday_type' => 'vacation',
        ]);

        $resolvedGlobal = AcademicHoliday::resolveForDate($this->tenant->id, $this->branch->id, '2026-03-23');
        $this->assertNotNull($resolvedGlobal);
        $this->assertEquals('Pakistan Day', $resolvedGlobal->title);

        $resolvedBranch = AcademicHoliday::resolveForDate($this->tenant->id, $this->branch->id, '2026-11-20');
        $this->assertNotNull($resolvedBranch);
        $this->assertEquals('Campus Sports Day', $resolvedBranch->title);

        $this->assertNull(AcademicHoliday::resolveForDate($this->tenant->id, $this->branch->id, '2026-11-21'));
    }

    public function test_school_periods_enforce_valid_time_order(): void
    {
        $period = SchoolPeriod::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'period_number' => 1,
            'label' => 'Period 1',
            'start_time' => '08:00:00',
            'end_time' => '08:45:00',
            'is_break' => false,
        ]);

        $this->assertDatabaseHas('school_periods', ['id' => $period->id, 'label' => 'Period 1']);

        $this->expectException(QueryException::class);
        SchoolPeriod::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'period_number' => 2,
            'label' => 'Invalid Period',
            'start_time' => '09:30:00',
            'end_time' => '09:00:00',
        ]);
    }
}
