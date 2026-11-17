<?php

namespace App\Modules\Attendance\Services;

use App\Core\Tenancy\TenantTransactionManager;
use App\Modules\Academic\Models\Academy\AcademyBatchSchedule;
use App\Modules\Attendance\Exceptions\TimetableConflictException;
use App\Modules\Attendance\Models\CampusRoom;
use App\Modules\Attendance\Models\SchoolPeriod;
use App\Modules\Attendance\Models\SchoolTimetableEntry;
use InvalidArgumentException;

class TimetableConflictService
{
    /**
     * Validate and assign a school timetable slot, preventing section, teacher,
     * and room clashes within the School wing and across the Academy wing.
     */
    public function assignSchoolSlot(array $data): SchoolTimetableEntry
    {
        return TenantTransactionManager::run(function () use ($data) {
            $period = SchoolPeriod::findOrFail($data['school_period_id']);

            if ($period->is_break) {
                throw new InvalidArgumentException("Cannot assign a subject to break period '{$period->label}'.");
            }

            $tenantId = $data['tenant_id'];
            $sessionId = $data['academic_session_id'];
            $day = (int) $data['day_of_week'];
            $ignoreId = $data['id'] ?? null;

            $this->assertNoSectionConflict($tenantId, $sessionId, $data['school_section_id'], $day, $period->id, $ignoreId);

            if (!empty($data['teacher_id'])) {
                $this->assertNoTeacherConflict($tenantId, $sessionId, (int) $data['teacher_id'], $day, $period, $ignoreId);
            }

            if (!empty($data['campus_room_id'])) {
                $this->assertNoRoomConflict($tenantId, $sessionId, $data['campus_room_id'], $day, $period, $ignoreId);
            }

            if ($ignoreId) {
                $entry = SchoolTimetableEntry::findOrFail($ignoreId);
                $entry->update($data);
                return $entry->fresh();
            }

            return SchoolTimetableEntry::create($data);
        });
    }

    protected function assertNoSectionConflict(
        string $tenantId,
        string $sessionId,
        string $sectionId,
        int $day,
        string $periodId,
        ?string $ignoreId
    ): void {
        $conflict = SchoolTimetableEntry::where('tenant_id', $tenantId)
            ->where('academic_session_id', $sessionId)
            ->where('school_section_id', $sectionId)
            ->where('day_of_week', $day)
            ->where('school_period_id', $periodId)
            ->where('is_active', true)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->first();

        if ($conflict) {
            throw new TimetableConflictException("Section already has a class assigned in this period.");
        }
    }

    protected function assertNoTeacherConflict(
        string $tenantId,
        string $sessionId,
        int $teacherId,
        int $day,
        SchoolPeriod $period,
        ?string $ignoreId
    ): void {
        $schoolClash = SchoolTimetableEntry::with('section')
            ->where('tenant_id', $tenantId)
            ->where('academic_session_id', $sessionId)
            ->where('teacher_id', $teacherId)
            ->where('day_of_week', $day)
            ->where('school_period_id', $period->id)
            ->where('is_active', true)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->first();

        if ($schoolClash) {
            $sectionName = $schoolClash->section->name ?? 'another section';
            throw new TimetableConflictException("Teacher clash detected: already assigned to {$sectionName} during {$period->label}.");
        }

        // Cross-domain check against Academy rolling batches
        $academyClash = AcademyBatchSchedule::join('academy_batches', 'academy_batch_schedules.academy_batch_id', '=', 'academy_batches.id')
            ->where('academy_batch_schedules.tenant_id', $tenantId)
            ->where('academy_batches.instructor_id', $teacherId)
            ->whereIn('academy_batches.status', ['upcoming', 'in_progress'])
            ->where('academy_batch_schedules.day_of_week', $day)
            ->where('academy_batch_schedules.start_time', '<', $period->end_time)
            ->where('academy_batch_schedules.end_time', '>', $period->start_time)
            ->select('academy_batches.name as batch_name')
            ->first();

        if ($academyClash) {
            throw new TimetableConflictException("Hybrid teacher clash detected: assigned to Academy batch '{$academyClash->batch_name}' at overlapping time.");
        }
    }

    protected function assertNoRoomConflict(
        string $tenantId,
        string $sessionId,
        string $roomId,
        int $day,
        SchoolPeriod $period,
        ?string $ignoreId
    ): void {
        $roomClash = SchoolTimetableEntry::where('tenant_id', $tenantId)
            ->where('academic_session_id', $sessionId)
            ->where('campus_room_id', $roomId)
            ->where('day_of_week', $day)
            ->where('school_period_id', $period->id)
            ->where('is_active', true)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->first();

        if ($roomClash) {
            throw new TimetableConflictException("Room clash detected: room is already occupied during {$period->label}.");
        }

        $room = CampusRoom::find($roomId);
        if ($room) {
            $academyRoomClash = AcademyBatchSchedule::join('academy_batches', 'academy_batch_schedules.academy_batch_id', '=', 'academy_batches.id')
                ->where('academy_batch_schedules.tenant_id', $tenantId)
                ->where('academy_batches.branch_id', $room->branch_id)
                ->whereIn('academy_batches.status', ['upcoming', 'in_progress'])
                ->where('academy_batch_schedules.day_of_week', $day)
                ->where('academy_batch_schedules.room_number', $room->room_code)
                ->where('academy_batch_schedules.start_time', '<', $period->end_time)
                ->where('academy_batch_schedules.end_time', '>', $period->start_time)
                ->select('academy_batches.name as batch_name')
                ->first();

            if ($academyRoomClash) {
                throw new TimetableConflictException("Hybrid room clash detected: Room {$room->room_code} is booked for Academy batch '{$academyRoomClash->batch_name}'.");
            }
        }
    }
}
