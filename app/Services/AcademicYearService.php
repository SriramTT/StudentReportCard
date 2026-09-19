<?php

namespace App\Services;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AcademicYearService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Create a new Academic Year.
     *
     * @param  array<string, mixed>  $data
     */
    public function createAcademicYear(array $data): AcademicYear
    {
        return DB::transaction(function () use ($data) {
            $isCurrent = ! empty($data['is_current']);

            if ($isCurrent) {
                AcademicYear::query()->update(['is_current' => false]);
            }

            $academicYear = AcademicYear::create([
                'name' => trim($data['name']),
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'status' => AcademicYearStatus::OPEN,
                'is_current' => $isCurrent,
            ]);

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'CREATE_ACADEMIC_YEAR',
                entityType: 'academic_years',
                entityId: $academicYear->id,
                beforeData: null,
                afterData: $academicYear->only(['name', 'start_date', 'end_date', 'status', 'is_current']),
                description: 'Created academic year: ' . $academicYear->name
            );

            return $academicYear;
        });
    }

    /**
     * Update an Academic Year.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateAcademicYear(AcademicYear $academicYear, array $data): AcademicYear
    {
        return DB::transaction(function () use ($academicYear, $data) {
            $beforeData = $academicYear->only(['name', 'start_date', 'end_date', 'status', 'is_current']);

            $isCurrent = isset($data['is_current']) ? (bool) $data['is_current'] : $academicYear->is_current;

            if ($isCurrent && ! $academicYear->is_current) {
                AcademicYear::where('id', '!=', $academicYear->id)->update(['is_current' => false]);
            }

            $academicYear->update([
                'name' => isset($data['name']) ? trim($data['name']) : $academicYear->name,
                'start_date' => $data['start_date'] ?? $academicYear->start_date,
                'end_date' => $data['end_date'] ?? $academicYear->end_date,
                'is_current' => $isCurrent,
            ]);

            $afterData = $academicYear->fresh()->only(['name', 'start_date', 'end_date', 'status', 'is_current']);

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'UPDATE_ACADEMIC_YEAR',
                entityType: 'academic_years',
                entityId: $academicYear->id,
                beforeData: $beforeData,
                afterData: $afterData,
                description: 'Updated academic year: ' . $academicYear->name
            );

            return $academicYear;
        });
    }

    /**
     * Mark an Academic Year as current.
     */
    public function setCurrentYear(AcademicYear $academicYear): AcademicYear
    {
        return DB::transaction(function () use ($academicYear) {
            $beforeData = $academicYear->only(['is_current']);

            AcademicYear::where('id', '!=', $academicYear->id)->update(['is_current' => false]);
            $academicYear->update(['is_current' => true]);

            $afterData = $academicYear->fresh()->only(['is_current']);

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'SET_CURRENT_ACADEMIC_YEAR',
                entityType: 'academic_years',
                entityId: $academicYear->id,
                beforeData: $beforeData,
                afterData: $afterData,
                description: 'Set academic year as current: ' . $academicYear->name
            );

            return $academicYear;
        });
    }

    /**
     * Close an Academic Year.
     */
    public function closeYear(AcademicYear $academicYear): AcademicYear
    {
        return DB::transaction(function () use ($academicYear) {
            $beforeData = ['status' => $academicYear->status->value];

            $academicYear->update(['status' => AcademicYearStatus::CLOSED]);

            $afterData = ['status' => $academicYear->status->value];

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'CLOSE_ACADEMIC_YEAR',
                entityType: 'academic_years',
                entityId: $academicYear->id,
                beforeData: $beforeData,
                afterData: $afterData,
                description: 'Closed academic year: ' . $academicYear->name
            );

            return $academicYear;
        });
    }

    /**
     * Reopen an Academic Year.
     */
    public function reopenYear(AcademicYear $academicYear): AcademicYear
    {
        return DB::transaction(function () use ($academicYear) {
            $beforeData = ['status' => $academicYear->status->value];

            $academicYear->update(['status' => AcademicYearStatus::OPEN]);

            $afterData = ['status' => $academicYear->status->value];

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'REOPEN_ACADEMIC_YEAR',
                entityType: 'academic_years',
                entityId: $academicYear->id,
                beforeData: $beforeData,
                afterData: $afterData,
                description: 'Reopened academic year: ' . $academicYear->name
            );

            return $academicYear;
        });
    }
}
