<?php

namespace App\Services;

use App\Enums\StudentPlacementStatus;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class StudentImportService
{
    public function __construct(
        protected AuditService $auditService,
        protected StudentSubjectAllocationService $allocationService
    ) {}

    /**
     * Generate CSV template for student import.
     */
    public function generateTemplate(): string
    {
        $csv = "admission_number,student_name,roll_number\n";
        $csv .= "ADM-2026-001,John Doe,1\n";
        $csv .= "ADM-2026-002,Jane Smith,2\n";

        return $csv;
    }

    /**
     * Parse and import CSV rows within the specified academic context.
     *
     * @param  UploadedFile  $file
     * @param  int  $academicYearId
     * @param  int  $classId
     * @param  int  $sectionId
     * @param  int  $actorId
     * @return array{
     *     total: int,
     *     imported: int,
     *     skipped: int,
     *     errors: array<int, array{row: int, admission_number: string|null, student_name: string|null, reason: string}>,
     *     successes: array<int, array{row: int, admission_number: string, student_name: string, roll_number: int, action: string}>
     * }
     */
    public function import(
        UploadedFile $file,
        int $academicYearId,
        int $classId,
        int $sectionId,
        int $actorId
    ): array {
        $section = \App\Models\Section::find($sectionId);
        if (! $section || (int) $section->class_id !== (int) $classId) {
            throw new \InvalidArgumentException('The selected section does not belong to the selected class.');
        }

        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return [
                'total' => 0,
                'imported' => 0,
                'skipped' => 0,
                'errors' => [['row' => 0, 'admission_number' => null, 'student_name' => null, 'reason' => 'Could not read CSV file.']],
                'successes' => [],
            ];
        }

        // Read header
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return [
                'total' => 0,
                'imported' => 0,
                'skipped' => 0,
                'errors' => [['row' => 1, 'admission_number' => null, 'student_name' => null, 'reason' => 'CSV file is empty.']],
                'successes' => [],
            ];
        }

        // Clean UTF-8 BOM if present
        $header[0] = preg_replace('/[\x{EF}\x{BB}\x{BF}]/u', '', $header[0]);
        $normalizedHeader = array_map(fn($col) => strtolower(trim((string) $col)), $header);

        $admIdx = array_search('admission_number', $normalizedHeader, true);
        $nameIdx = array_search('student_name', $normalizedHeader, true);
        $rollIdx = array_search('roll_number', $normalizedHeader, true);

        if ($admIdx === false || $nameIdx === false || $rollIdx === false) {
            fclose($handle);
            return [
                'total' => 0,
                'imported' => 0,
                'skipped' => 0,
                'errors' => [[
                    'row' => 1,
                    'admission_number' => null,
                    'student_name' => null,
                    'reason' => 'Invalid CSV header. Expected columns: admission_number, student_name, roll_number',
                ]],
                'successes' => [],
            ];
        }

        // Pre-scan rows to detect in-file duplicate admission numbers (Case 4) and duplicate roll numbers
        $rawRows = [];
        $admissionCounts = [];
        $rollCounts = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (empty(array_filter($row, fn($val) => trim((string) $val) !== ''))) {
                continue; // Skip completely empty rows
            }

            $rawAdmission = isset($row[$admIdx]) ? trim((string) $row[$admIdx]) : '';
            $rawName = isset($row[$nameIdx]) ? trim((string) $row[$nameIdx]) : '';
            $rawRoll = isset($row[$rollIdx]) ? trim((string) $row[$rollIdx]) : '';

            $rawRows[] = [
                'row_number' => $rowNum,
                'admission_number' => $rawAdmission,
                'student_name' => $rawName,
                'roll_number' => $rawRoll,
            ];

            if ($rawAdmission !== '') {
                $admissionCounts[$rawAdmission][] = $rowNum;
            }
            if ($rawRoll !== '' && is_numeric($rawRoll) && (int) $rawRoll >= 1) {
                $rollCounts[(int) $rawRoll][] = $rowNum;
            }
        }
        fclose($handle);

        $results = [
            'total' => count($rawRows),
            'imported' => 0,
            'created' => 0,
            'placed' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'errors' => [],
            'successes' => [],
        ];

        foreach ($rawRows as $item) {
            $row = $item['row_number'];
            $adm = $item['admission_number'];
            $name = $item['student_name'];
            $roll = $item['roll_number'];

            // CASE 5: Admission number is blank/missing
            if ($adm === '') {
                $results['skipped']++;
                $results['errors'][] = [
                    'row' => $row,
                    'admission_number' => null,
                    'student_name' => $name,
                    'reason' => 'Admission number is required and cannot be empty.',
                ];
                continue;
            }

            // Length validation
            if (mb_strlen($adm) > 50) {
                $results['skipped']++;
                $results['errors'][] = [
                    'row' => $row,
                    'admission_number' => $adm,
                    'student_name' => $name,
                    'reason' => 'Admission number exceeds maximum length of 50 characters.',
                ];
                continue;
            }

            // CASE 4: The same admission number appears multiple times in the same CSV
            if (isset($admissionCounts[$adm]) && count($admissionCounts[$adm]) > 1) {
                $results['skipped']++;
                $conflictingRows = implode(', ', $admissionCounts[$adm]);
                $results['errors'][] = [
                    'row' => $row,
                    'admission_number' => $adm,
                    'student_name' => $name,
                    'reason' => "Duplicate admission number '{$adm}' detected within the same CSV (conflicting rows: {$conflictingRows}).",
                ];
                continue;
            }

            // Validate student name
            if ($name === '') {
                $results['skipped']++;
                $results['errors'][] = [
                    'row' => $row,
                    'admission_number' => $adm,
                    'student_name' => null,
                    'reason' => 'Student name is required.',
                ];
                continue;
            }

            // Validate roll number
            if (!is_numeric($roll) || (int) $roll < 1) {
                $results['skipped']++;
                $results['errors'][] = [
                    'row' => $row,
                    'admission_number' => $adm,
                    'student_name' => $name,
                    'reason' => 'Roll number must be a positive integer.',
                ];
                continue;
            }

            $rollNumber = (int) $roll;

            // In-file duplicate roll number validation
            if (isset($rollCounts[$rollNumber]) && count($rollCounts[$rollNumber]) > 1) {
                $results['skipped']++;
                $conflictingRows = implode(', ', $rollCounts[$rollNumber]);
                $results['errors'][] = [
                    'row' => $row,
                    'admission_number' => $adm,
                    'student_name' => $name,
                    'reason' => "Duplicate roll number '{$rollNumber}' detected within the same CSV (conflicting rows: {$conflictingRows}).",
                ];
                continue;
            }

            // Process row within an isolated transaction boundary
            try {
                DB::beginTransaction();

                // Pre-check target active roll number occupant
                $activeRollOccupant = StudentAcademicRecord::where('academic_year_id', $academicYearId)
                    ->where('class_id', $classId)
                    ->where('section_id', $sectionId)
                    ->where('roll_number', $rollNumber)
                    ->where('status', StudentPlacementStatus::ACTIVE)
                    ->with('student')
                    ->first();

                $existingStudent = Student::where('admission_number', $adm)->first();

                if (!$existingStudent) {
                    // Check if roll is already taken by another active student
                    if ($activeRollOccupant) {
                        DB::rollBack();
                        $results['skipped']++;
                        $occupantName = $activeRollOccupant->student?->student_name ?? "Student ID {$activeRollOccupant->student_id}";
                        $results['errors'][] = [
                            'row' => $row,
                            'admission_number' => $adm,
                            'student_name' => $name,
                            'reason' => "Roll number {$rollNumber} is already assigned to active student '{$occupantName}' in this class and section.",
                        ];
                        continue;
                    }

                    // CASE 1: Admission number does not exist -> Create new student & placement
                    $student = Student::create([
                        'admission_number' => $adm,
                        'student_name' => $name,
                    ]);

                    $record = StudentAcademicRecord::create([
                        'student_id' => $student->id,
                        'academic_year_id' => $academicYearId,
                        'class_id' => $classId,
                        'section_id' => $sectionId,
                        'roll_number' => $rollNumber,
                        'status' => StudentPlacementStatus::ACTIVE,
                        'effective_from' => now()->toDateString(),
                    ]);

                    $this->allocationService->allocateDefaultSubjectsForPlacement($record, $actorId);

                    $this->auditService->logDomainAction(
                        $actorId,
                        'import_create',
                        'students',
                        $student->id,
                        null,
                        [
                            'admission_number' => $student->admission_number,
                            'student_name' => $student->student_name,
                            'record_id' => $record->id,
                        ],
                        "Imported new student {$student->student_name} ({$student->admission_number}) via CSV."
                    );

                    DB::commit();

                    $results['created']++;
                    $results['imported']++;
                    $results['successes'][] = [
                        'row' => $row,
                        'admission_number' => $adm,
                        'student_name' => $name,
                        'roll_number' => $rollNumber,
                        'action' => 'Created student and initial placement',
                    ];
                } else {
                    // CASE 3: Admission number already exists but CSV student_name differs
                    if (strcasecmp(trim($existingStudent->student_name), $name) !== 0) {
                        DB::rollBack();
                        $results['skipped']++;
                        $results['errors'][] = [
                            'row' => $row,
                            'admission_number' => $adm,
                            'student_name' => $name,
                            'reason' => "Admission number '{$adm}' already belongs to student '{$existingStudent->student_name}'. CSV student name '{$name}' does not match.",
                        ];
                        continue;
                    }

                    // Check student's current active placements in this academic year
                    $activePlacementInYear = StudentAcademicRecord::where('student_id', $existingStudent->id)
                        ->where('academic_year_id', $academicYearId)
                        ->where('status', StudentPlacementStatus::ACTIVE)
                        ->with(['schoolClass', 'section'])
                        ->first();

                    if ($activePlacementInYear) {
                        if ($activePlacementInYear->class_id == $classId && $activePlacementInYear->section_id == $sectionId) {
                            // Student is already placed in this exact classroom
                            if ($activePlacementInYear->roll_number === $rollNumber) {
                                // CASE: UNCHANGED PLACEMENT
                                DB::commit();
                                $results['unchanged']++;
                                $results['successes'][] = [
                                    'row' => $row,
                                    'admission_number' => $adm,
                                    'student_name' => $existingStudent->student_name,
                                    'roll_number' => $rollNumber,
                                    'action' => 'Existing student confirmed in classroom (unchanged)',
                                ];
                            } else {
                                // Roll number update for active student
                                if ($activeRollOccupant && $activeRollOccupant->student_id !== $existingStudent->id) {
                                    DB::rollBack();
                                    $results['skipped']++;
                                    $occupantName = $activeRollOccupant->student?->student_name ?? "Student ID {$activeRollOccupant->student_id}";
                                    $results['errors'][] = [
                                        'row' => $row,
                                        'admission_number' => $adm,
                                        'student_name' => $name,
                                        'reason' => "Roll number {$rollNumber} is already assigned to active student '{$occupantName}' in this class and section.",
                                    ];
                                    continue;
                                }

                                $activePlacementInYear->update(['roll_number' => $rollNumber]);
                                DB::commit();
                                $results['placed']++;
                                $results['imported']++;
                                $results['successes'][] = [
                                    'row' => $row,
                                    'admission_number' => $adm,
                                    'student_name' => $existingStudent->student_name,
                                    'roll_number' => $rollNumber,
                                    'action' => "Updated roll number to {$rollNumber}",
                                ];
                            }
                        } else {
                            // Section 13: Student has active placement in another classroom in this year
                            DB::rollBack();
                            $className = $activePlacementInYear->schoolClass?->name ?? "Class ID {$activePlacementInYear->class_id}";
                            $sectionName = $activePlacementInYear->section?->name ?? "Section ID {$activePlacementInYear->section_id}";
                            $results['skipped']++;
                            $results['errors'][] = [
                                'row' => $row,
                                'admission_number' => $adm,
                                'student_name' => $name,
                                'reason' => "Student currently has an active placement in {$className} ({$sectionName}). Use the Student Transfer workflow instead of CSV placement.",
                            ];
                            continue;
                        }
                    } else {
                        // Student exists in master but has NO active placement in this academic year
                        if ($activeRollOccupant) {
                            DB::rollBack();
                            $results['skipped']++;
                            $occupantName = $activeRollOccupant->student?->student_name ?? "Student ID {$activeRollOccupant->student_id}";
                            $results['errors'][] = [
                                'row' => $row,
                                'admission_number' => $adm,
                                'student_name' => $name,
                                'reason' => "Roll number {$rollNumber} is already assigned to active student '{$occupantName}' in this class and section.",
                            ];
                            continue;
                        }

                        $newRecord = StudentAcademicRecord::create([
                            'student_id' => $existingStudent->id,
                            'academic_year_id' => $academicYearId,
                            'class_id' => $classId,
                            'section_id' => $sectionId,
                            'roll_number' => $rollNumber,
                            'status' => StudentPlacementStatus::ACTIVE,
                            'effective_from' => now()->toDateString(),
                        ]);

                        $this->allocationService->allocateDefaultSubjectsForPlacement($newRecord, $actorId);

                        $this->auditService->logDomainAction(
                            $actorId,
                            'import_placement',
                            'student_academic_records',
                            $newRecord->id,
                            null,
                            [
                                'student_id' => $existingStudent->id,
                                'admission_number' => $existingStudent->admission_number,
                                'record_id' => $newRecord->id,
                            ],
                            "Assigned placement to existing student {$existingStudent->admission_number} via CSV import."
                        );

                        DB::commit();

                        $results['placed']++;
                        $results['imported']++;
                        $results['successes'][] = [
                            'row' => $row,
                            'admission_number' => $adm,
                            'student_name' => $existingStudent->student_name,
                            'roll_number' => $rollNumber,
                            'action' => 'Assigned existing student to classroom placement',
                        ];
                    }
                }
            } catch (\Illuminate\Database\QueryException $e) {
                DB::rollBack();
                $results['skipped']++;
                if ($e->getCode() === '23505' && str_contains($e->getMessage(), 'uk_sar_active_year_class_section_roll')) {
                    $reason = "Roll number {$rollNumber} is already assigned to an active student in this class and section.";
                } elseif ($e->getCode() === '23505' && str_contains($e->getMessage(), 'uk_students_admission_number')) {
                    $reason = "Admission number '{$adm}' already exists in the database.";
                } else {
                    $reason = 'Database integrity error encountered during row processing.';
                }
                $results['errors'][] = [
                    'row' => $row,
                    'admission_number' => $adm,
                    'student_name' => $name,
                    'reason' => $reason,
                ];
            } catch (Throwable $e) {
                DB::rollBack();
                $results['skipped']++;
                $results['errors'][] = [
                    'row' => $row,
                    'admission_number' => $adm,
                    'student_name' => $name,
                    'reason' => 'An unexpected error occurred while processing row.',
                ];
            }
        }

        return $results;
    }
}
