<?php

namespace App\Services\Attendance;

use App\Enums\AcademicYearStatus;
use App\Enums\StudentPlacementStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\StudentAcademicRecord;
use App\Models\Term;
use App\Models\User;
use App\Services\Attendance\DTOs\AttendanceResult;
use App\Services\AuditService;
use App\Services\TeacherAuthorizationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AttendanceService
{
    public function __construct(
        protected TeacherAuthorizationService $teacherAuth,
        protected AuditService $auditService
    ) {}

    /**
     * Compute attendance metrics with safe 0/0 handling.
     */
    public function calculateAttendance(int $daysAttended, int $totalWorkingDays): AttendanceResult
    {
        // Safe 0/0 Handling (BRD CL-010, DEC-028)
        if ($totalWorkingDays === 0) {
            return new AttendanceResult(
                daysAttended: $daysAttended,
                totalWorkingDays: $totalWorkingDays,
                percentage: null,
                formattedPercentage: 'N/A',
                isAvailable: false
            );
        }

        $percentage = round(($daysAttended / $totalWorkingDays) * 100, 2);

        return new AttendanceResult(
            daysAttended: $daysAttended,
            totalWorkingDays: $totalWorkingDays,
            percentage: $percentage,
            formattedPercentage: number_format($percentage, 2, '.', '') . '%',
            isAvailable: true
        );
    }

    /**
     * Load attendance context and student roster for the attendance UI.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function loadAttendanceContext(User $user, array $filters = []): array
    {
        $isAdminOrOffice = $user->isAdmin() || $user->isOfficeStaff();

        // 1. Academic Years
        $academicYears = AcademicYear::query()->orderBy('start_date', 'desc')->get();
        $selectedYearId = isset($filters['academic_year_id']) && $filters['academic_year_id'] !== ''
            ? (int) $filters['academic_year_id']
            : ($academicYears->firstWhere('is_current', true)?->id ?? $academicYears->first()?->id);

        $selectedYear = $academicYears->firstWhere('id', $selectedYearId);

        // Fetch active assignments for teacher in this year
        $activeAssignments = (! $isAdminOrOffice && $selectedYearId)
            ? $this->teacherAuth->getActiveAssignments($user, $selectedYearId)
            : collect();

        // 2. Classes
        if ($isAdminOrOffice) {
            $availableClasses = SchoolClass::getNaturallySorted(true);
        } else {
            // Subject teachers have zero attendance authority
            $classTeacherAssignments = $activeAssignments->filter(fn ($a) => $a->isClassTeacher());
            $classIds = $classTeacherAssignments->pluck('class_id')->unique()->filter()->values();
            $availableClasses = SchoolClass::getNaturallySorted(true, $classIds->all());
        }

        $selectedClassId = isset($filters['class_id']) && $filters['class_id'] !== ''
            ? (int) $filters['class_id']
            : null;

        if ($selectedClassId && ! $availableClasses->contains('id', $selectedClassId)) {
            $selectedClassId = null;
        }

        // 3. Sections
        $availableSections = collect();
        $selectedSectionId = isset($filters['section_id']) && $filters['section_id'] !== ''
            ? (int) $filters['section_id']
            : null;

        if ($selectedClassId) {
            if ($isAdminOrOffice) {
                $availableSections = Section::query()
                    ->where('academic_year_id', $selectedYearId)
                    ->where('class_id', $selectedClassId)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get();
            } else {
                $sectionIds = $activeAssignments->filter(fn ($a) => $a->isClassTeacher() && (int) $a->class_id === $selectedClassId)
                    ->pluck('section_id')
                    ->unique()
                    ->filter()
                    ->values();

                $availableSections = Section::query()
                    ->where('academic_year_id', $selectedYearId)
                    ->where('class_id', $selectedClassId)
                    ->whereIn('id', $sectionIds)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get();
            }

            if ($selectedSectionId && ! $availableSections->contains('id', $selectedSectionId)) {
                $selectedSectionId = null;
            }
        }

        // 4. Terms (show all terms for academic year, active or historical)
        $availableTerms = collect();
        $selectedTermId = isset($filters['term_id']) && $filters['term_id'] !== ''
            ? (int) $filters['term_id']
            : null;

        if ($selectedYearId) {
            $availableTerms = Term::query()
                ->where('academic_year_id', $selectedYearId)
                ->orderBy('sequence_no')
                ->get();

            if ($selectedTermId && ! $availableTerms->contains('id', $selectedTermId)) {
                $selectedTermId = null;
            }
            if (! $selectedTermId && $availableTerms->isNotEmpty()) {
                $selectedTermId = $availableTerms->first()->id;
            }
        }

        // 5. Determine authorization and roster
        $isEditable = false;
        $isClosedYear = $selectedYear?->status === AcademicYearStatus::CLOSED;
        $roster = collect();

        if ($selectedYearId && $selectedClassId && $selectedSectionId && $selectedTermId) {
            // Validate Teacher Authorization
            if (! $isAdminOrOffice) {
                $isClassTeacher = $this->teacherAuth->isClassTeacherFor(
                    $user,
                    $selectedYearId,
                    $selectedClassId,
                    $selectedSectionId
                );

                if (! $isClassTeacher) {
                    throw new HttpException(403, 'Unauthorized: You are not authorized to view or enter attendance for this section.');
                }

                // Closed year rule for teachers: read-only
                $isEditable = ! $isClosedYear;
            } else {
                // Admin / Office Staff: allowed to edit even in closed years
                $isEditable = true;
            }

            // Fetch enrolled students
            $sars = StudentAcademicRecord::query()
                ->where('academic_year_id', $selectedYearId)
                ->where('class_id', $selectedClassId)
                ->where('section_id', $selectedSectionId)
                ->where('status', StudentPlacementStatus::ACTIVE)
                ->with('student')
                ->orderBy('roll_number')
                ->get();

            // Load existing attendance
            $existingAttendance = Attendance::query()
                ->whereIn('student_academic_record_id', $sars->pluck('id'))
                ->where('term_id', $selectedTermId)
                ->get()
                ->keyBy('student_academic_record_id');

            $roster = $sars->map(function (StudentAcademicRecord $sar) use ($existingAttendance) {
                $att = $existingAttendance->get($sar->id);
                $daysAttended = $att?->days_attended ?? 0;
                $totalWorkingDays = $att?->total_working_days ?? 0;
                $calc = $this->calculateAttendance($daysAttended, $totalWorkingDays);

                return [
                    'student_academic_record_id' => $sar->id,
                    'student_id' => $sar->student_id,
                    'roll_number' => $sar->roll_number,
                    'admission_number' => $sar->student?->admission_number ?? '\u2014',
                    'student_name' => $sar->student?->student_name ?? '\u2014',
                    'days_attended' => $daysAttended,
                    'total_working_days' => $totalWorkingDays,
                    'has_record' => $att !== null,
                    'calculation' => $calc,
                ];
            });
        }

        return [
            'academicYears' => $academicYears,
            'selectedYearId' => $selectedYearId,
            'availableClasses' => $availableClasses,
            'selectedClassId' => $selectedClassId,
            'availableSections' => $availableSections,
            'selectedSectionId' => $selectedSectionId,
            'availableTerms' => $availableTerms,
            'selectedTermId' => $selectedTermId,
            'isEditable' => $isEditable,
            'isClosedYear' => $isClosedYear,
            'roster' => $roster,
        ];
    }

    /**
     * Batch save attendance records for a classroom in a target term.
     *
     * @param array<string, mixed> $data
     * @return int Number of records saved/updated
     * @throws HttpException|ValidationException
     */
    public function batchSaveAttendance(User $user, array $data): int
    {
        $academicYearId = (int) $data['academic_year_id'];
        $classId = (int) $data['class_id'];
        $sectionId = (int) $data['section_id'];
        $termId = (int) $data['term_id'];
        $records = $data['attendance_records'] ?? [];

        if (empty($records)) {
            throw ValidationException::withMessages(['attendance_records' => 'No attendance records provided.']);
        }

        $academicYear = AcademicYear::findOrFail($academicYearId);
        $term = Term::where('id', $termId)->where('academic_year_id', $academicYearId)->firstOrFail();

        $isAdminOrOffice = $user->isAdmin() || $user->isOfficeStaff();

        // 1. Authorization checks
        if (! $isAdminOrOffice) {
            if ($academicYear->status === AcademicYearStatus::CLOSED) {
                throw new HttpException(403, 'Forbidden: Academic year is closed. Teachers have read-only access.');
            }

            $isClassTeacher = $this->teacherAuth->isClassTeacherFor($user, $academicYearId, $classId, $sectionId);
            if (! $isClassTeacher) {
                throw new HttpException(403, 'Forbidden: You are not authorized to manage attendance for this classroom.');
            }
        }

        // 2. Anti-IDOR: Fetch valid SAR IDs belonging to this exact classroom
        $validSarIds = StudentAcademicRecord::query()
            ->where('academic_year_id', $academicYearId)
            ->where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $validSarLookup = array_flip($validSarIds);

        // 3. Validate rows before transaction
        foreach ($records as $index => $row) {
            $sarId = (int) ($row['student_academic_record_id'] ?? 0);
            if (! isset($validSarLookup[$sarId])) {
                throw ValidationException::withMessages([
                    "attendance_records.{$index}.student_academic_record_id" => "Student placement ID {$sarId} is invalid for the selected classroom.",
                ]);
            }

            $daysAttended = (int) ($row['days_attended'] ?? 0);
            $totalWorkingDays = (int) ($row['total_working_days'] ?? 0);

            if ($daysAttended < 0) {
                throw ValidationException::withMessages([
                    "attendance_records.{$index}.days_attended" => 'Days attended cannot be negative.',
                ]);
            }

            if ($totalWorkingDays < 0) {
                throw ValidationException::withMessages([
                    "attendance_records.{$index}.total_working_days" => 'Total working days cannot be negative.',
                ]);
            }

            if ($daysAttended > $totalWorkingDays) {
                throw ValidationException::withMessages([
                    "attendance_records.{$index}.days_attended" => "Days attended ({$daysAttended}) cannot exceed total working days ({$totalWorkingDays}).",
                ]);
            }
        }

        // 4. Execute transactional save and immutable audit
        return DB::transaction(function () use ($records, $termId, $user) {
            $savedCount = 0;

            foreach ($records as $row) {
                $sarId = (int) $row['student_academic_record_id'];
                $daysAttended = (int) $row['days_attended'];
                $totalWorkingDays = (int) $row['total_working_days'];

                $attendance = Attendance::where('student_academic_record_id', $sarId)
                    ->where('term_id', $termId)
                    ->first();

                if ($attendance) {
                    $beforeData = [
                        'days_attended' => $attendance->days_attended,
                        'total_working_days' => $attendance->total_working_days,
                    ];

                    // Check if actually changed to avoid spurious duplicate audit logs
                    if ($attendance->days_attended === $daysAttended && $attendance->total_working_days === $totalWorkingDays) {
                        continue;
                    }

                    $attendance->update([
                        'days_attended' => $daysAttended,
                        'total_working_days' => $totalWorkingDays,
                        'updated_by_user_id' => $user->id,
                    ]);

                    $afterData = [
                        'days_attended' => $attendance->days_attended,
                        'total_working_days' => $attendance->total_working_days,
                    ];

                    $this->auditService->logDomainAction(
                        userId: $user->id,
                        action: 'UPDATE_ATTENDANCE',
                        entityType: 'attendance',
                        entityId: $attendance->id,
                        beforeData: $beforeData,
                        afterData: $afterData,
                        description: "Updated term attendance for SAR ID {$sarId}: {$daysAttended}/{$totalWorkingDays}"
                    );
                } else {
                    $attendance = Attendance::create([
                        'student_academic_record_id' => $sarId,
                        'term_id' => $termId,
                        'days_attended' => $daysAttended,
                        'total_working_days' => $totalWorkingDays,
                        'entered_by_user_id' => $user->id,
                        'updated_by_user_id' => $user->id,
                    ]);

                    $this->auditService->logDomainAction(
                        userId: $user->id,
                        action: 'CREATE_ATTENDANCE',
                        entityType: 'attendance',
                        entityId: $attendance->id,
                        beforeData: null,
                        afterData: [
                            'student_academic_record_id' => $sarId,
                            'term_id' => $termId,
                            'days_attended' => $daysAttended,
                            'total_working_days' => $totalWorkingDays,
                        ],
                        description: "Created term attendance for SAR ID {$sarId}: {$daysAttended}/{$totalWorkingDays}"
                    );
                }

                $savedCount++;
            }

            return $savedCount;
        });
    }
}
