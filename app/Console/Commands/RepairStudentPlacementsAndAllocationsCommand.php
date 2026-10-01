<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAcademicRecord;
use App\Models\User;
use App\Services\AuditService;
use App\Services\StudentSubjectAllocationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairStudentPlacementsAndAllocationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'school:repair-placements-and-allocations {--dry-run : Simulate the repair without committing changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Forensically repair mismatched section_id on student academic records and allocate default subjects.';

    /**
     * Execute the console command.
     */
    public function handle(
        StudentSubjectAllocationService $allocationService,
        AuditService $auditService
    ): int {
        $isDryRun = (bool) $this->option('dry-run');

        $this->info($isDryRun ? '--- DRY RUN MODE (No database changes will be committed) ---' : '--- EXECUTING PRODUCTION REPAIR ---');

        // Resolve system actor (Administrator 'sriram' ID 44 or first admin)
        $adminUser = User::where('username', 'sriram')->first() ?? User::first();
        $actorId = $adminUser?->id ?? 1;

        $dbName = DB::connection()->getDatabaseName();
        $this->info("Connected Database: {$dbName}");

        DB::beginTransaction();

        try {
            // Find all Class 1 sections to map section name -> section_id for class 35
            $class1 = SchoolClass::where('id', 35)->orWhere('name', 'Class 1')->first();
            if (! $class1) {
                $this->error('Class 1 not found in database.');
                DB::rollBack();
                return 1;
            }

            $validSections = Section::where('class_id', $class1->id)->get()->keyBy('name');
            $this->info("Found Class 1 (ID {$class1->id}) sections: " . $validSections->keys()->implode(', '));

            // Section mapping table:
            // Section 62 ('A', parent Class 8) -> Section 46 ('A', parent Class 1)
            // Section 45 ('B', parent Class 7) -> Section 47 ('B', parent Class 1)
            // Section 54 ('C', parent Class 3) -> Section 48 ('C', parent Class 1)
            $sectionCorrectionMap = [
                62 => 46, // Class 8 'A' -> Class 1 'A'
                45 => 47, // Class 7 'B' -> Class 1 'B'
                54 => 48, // Class 3 'C' -> Class 1 'C'
            ];

            // Verify the targets exist and actually belong to Class 1
            foreach ($sectionCorrectionMap as $invalidSecId => $validSecId) {
                $validSec = Section::find($validSecId);
                if (! $validSec || $validSec->class_id !== $class1->id) {
                    throw new \RuntimeException("Target section {$validSecId} does not exist or does not belong to Class 1 (ID {$class1->id}).");
                }
            }

            // Find inconsistent records in StudentAcademicRecord
            $inconsistentRecords = StudentAcademicRecord::query()
                ->where('class_id', $class1->id)
                ->whereIn('section_id', array_keys($sectionCorrectionMap))
                ->with(['student', 'section'])
                ->get();

            $this->info("Found {$inconsistentRecords->count()} student academic records with cross-class section mismatches.");

            $repairedCount = 0;
            foreach ($inconsistentRecords as $record) {
                $oldSectionId = $record->section_id;
                $newSectionId = $sectionCorrectionMap[$oldSectionId];
                $oldSection = Section::find($oldSectionId);
                $newSection = Section::find($newSectionId);

                $studentAdm = $record->student?->admission_number ?? 'UNKNOWN';

                $this->line(" - SAR #{$record->id} ({$studentAdm}): section {$oldSectionId} ('{$oldSection?->name}', Class {$oldSection?->class_id}) -> {$newSectionId} ('{$newSection?->name}', Class {$newSection?->class_id})");

                if (! $isDryRun) {
                    $record->update([
                        'section_id' => $newSectionId,
                    ]);

                    $auditService->logDomainAction(
                        $actorId,
                        'repair_placement_section',
                        'student_academic_records',
                        $record->id,
                        ['section_id' => $oldSectionId],
                        ['section_id' => $newSectionId],
                        "Forensically repaired placement section from {$oldSectionId} to {$newSectionId} for student {$studentAdm}."
                    );
                }

                $repairedCount++;
            }

            // Next: Allocate default classroom subjects to all active placements in Class 1
            $this->info("\nProcessing default subject allocations for active Class 1 placements...");

            $activePlacements = StudentAcademicRecord::query()
                ->where('class_id', $class1->id)
                ->where('status', 'active')
                ->get();

            $this->info("Found {$activePlacements->count()} active student placements in Class 1.");

            $totalAllocationsCreated = 0;
            foreach ($activePlacements as $placement) {
                // If in dry-run, we inspect what subjects would be allocated
                if ($isDryRun) {
                    $available = $allocationService->getAvailableClassSubjects($placement);
                    $totalAllocationsCreated += $available->count();
                } else {
                    $allocatedCount = $allocationService->allocateDefaultSubjectsForPlacement($placement, $actorId);
                    $totalAllocationsCreated += $allocatedCount;
                }
            }

            $this->info("Default subject allocations: {$totalAllocationsCreated} allocations " . ($isDryRun ? "identified to create/verify." : "created/verified."));

            if ($isDryRun) {
                DB::rollBack();
                $this->info("\n[DRY RUN COMPLETE] Rolled back all changes. No database rows were modified.");
            } else {
                DB::commit();
                $this->info("\n[PRODUCTION REPAIR SUCCESSFUL] Transaction committed. Repaired {$repairedCount} SAR records, processed {$totalAllocationsCreated} subject allocations.");
            }

            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Error occurred during repair: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}
