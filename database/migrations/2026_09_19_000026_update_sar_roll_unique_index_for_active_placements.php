<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Drop unconditional table-level UNIQUE constraint
        DB::statement("
            ALTER TABLE student_academic_records 
            DROP CONSTRAINT IF EXISTS uk_sar_year_class_section_roll;
        ");

        // 2. Create partial unique index enforcing uniqueness on ACTIVE placements only
        DB::statement("
            CREATE UNIQUE INDEX uk_sar_active_year_class_section_roll 
            ON student_academic_records (academic_year_id, class_id, section_id, roll_number) 
            WHERE status = 'active';
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Drop partial unique index
        DB::statement("
            DROP INDEX IF EXISTS uk_sar_active_year_class_section_roll;
        ");

        // 2. Restore unconditional table-level UNIQUE constraint
        DB::statement("
            ALTER TABLE student_academic_records 
            ADD CONSTRAINT uk_sar_year_class_section_roll 
            UNIQUE (academic_year_id, class_id, section_id, roll_number);
        ");
    }
};
