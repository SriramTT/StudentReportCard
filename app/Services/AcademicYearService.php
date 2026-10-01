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
     * Centralized authoritative resolver for the current academic year based on date.
     *
     * Rules:
     * - An academic year is current if: start_date <= target_date <= end_date.
     * - If target_date > end_date, it is closed / expired.
     * - If target_date < start_date, it is upcoming.
     * - If multiple academic years contain target_date, throw DomainException (multiple current year protection).
     * - If no academic year contains target_date, return null (graceful no-current-year condition).
     */
    public function resolveCurrentAcademicYear(?\Carbon\CarbonInterface $date = null): ?AcademicYear
    {
        $targetDate = ($date ?? now())->toDateString();

        $matchingYears = AcademicYear::query()
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->where('start_date', '<=', $targetDate)
            ->where('end_date', '>=', $targetDate)
            ->get();

        if ($matchingYears->count() > 1) {
            throw new \DomainException('Multiple overlapping academic years cover the current date. Ambiguous current academic year state detected.');
        }

        return $matchingYears->first();
    }

    /**
     * Create a new Academic Year.
     *
     * @param  array<string, mixed>  $data
     */
    public function createAcademicYear(array $data): AcademicYear
    {
        return DB::transaction(function () use ($data) {
            $startDate = \Illuminate\Support\Carbon::parse($data['start_date'])->toDateString();
            $endDate = \Illuminate\Support\Carbon::parse($data['end_date'])->toDateString();
            $today = now()->toDateString();

            // Date-derived current status and lifecycle status
            $isCurrent = ($startDate <= $today && $today <= $endDate);
            $status = ($today > $endDate) ? AcademicYearStatus::CLOSED : AcademicYearStatus::OPEN;

            if ($isCurrent) {
                AcademicYear::query()->update(['is_current' => false]);
            }

            $academicYear = AcademicYear::create([
                'name' => trim($data['name']),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
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

            $startDate = isset($data['start_date'])
                ? \Illuminate\Support\Carbon::parse($data['start_date'])->toDateString()
                : $academicYear->start_date?->toDateString();
            $endDate = isset($data['end_date'])
                ? \Illuminate\Support\Carbon::parse($data['end_date'])->toDateString()
                : $academicYear->end_date?->toDateString();
            $today = now()->toDateString();

            // Date-derived current status and lifecycle status
            $isCurrent = ($startDate && $endDate && $startDate <= $today && $today <= $endDate);
            $status = ($endDate && $today > $endDate) ? AcademicYearStatus::CLOSED : AcademicYearStatus::OPEN;

            if ($isCurrent) {
                AcademicYear::where('id', '!=', $academicYear->id)->update(['is_current' => false]);
            }

            $academicYear->update([
                'name' => isset($data['name']) ? trim($data['name']) : $academicYear->name,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
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

    /**
     * Delete an Academic Year if completely unused.
     *
     * @throws \DomainException
     */
    public function deleteAcademicYear(AcademicYear $academicYear): void
    {
        if ($academicYear->is_current) {
            throw new \DomainException('Cannot remove the current academic year. Please designate another academic year as current first.');
        }

        if (
            $academicYear->terms()->exists() ||
            $academicYear->sections()->exists() ||
            $academicYear->classSubjects()->exists() ||
            $academicYear->studentAcademicRecords()->exists() ||
            $academicYear->assessments()->exists() ||
            $academicYear->teacherAssignments()->exists() ||
            $academicYear->calculationSettings()->exists() ||
            $academicYear->reportConfigurations()->exists()
        ) {
            throw new \DomainException('This academic year cannot be removed because it contains dependent academic data (terms, classes, placements, or assessments). Close the academic year instead.');
        }

        DB::transaction(function () use ($academicYear) {
            $beforeData = [
                'id' => $academicYear->id,
                'name' => $academicYear->name,
                'start_date' => $academicYear->start_date?->toDateString(),
                'end_date' => $academicYear->end_date?->toDateString(),
                'status' => $academicYear->status->value,
                'is_current' => $academicYear->is_current,
            ];

            $academicYear->delete();

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'DELETE_ACADEMIC_YEAR',
                entityType: 'academic_years',
                entityId: $beforeData['id'],
                beforeData: $beforeData,
                afterData: null,
                description: 'Permanently removed academic year: ' . $beforeData['name']
            );
        });
    }
}
