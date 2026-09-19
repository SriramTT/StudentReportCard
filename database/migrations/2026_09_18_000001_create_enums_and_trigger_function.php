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
        // 1. ENUM TYPE DEFINITIONS (matching schema.sql pre-drop pattern)
        DB::statement("DROP TYPE IF EXISTS report_type_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS calculation_method_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS mark_result_status_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS teacher_assignment_type_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS assessment_status_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS student_placement_status_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS subject_category_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS academic_year_status_enum CASCADE;");

        DB::statement("CREATE TYPE academic_year_status_enum AS ENUM ('open', 'closed');");
        DB::statement("CREATE TYPE subject_category_enum AS ENUM ('main', 'elective');");
        DB::statement("CREATE TYPE student_placement_status_enum AS ENUM ('active', 'internal_transfer', 'withdrawn', 'transferred_out');");
        DB::statement("CREATE TYPE assessment_status_enum AS ENUM ('active', 'inactive');");
        DB::statement("CREATE TYPE teacher_assignment_type_enum AS ENUM ('subject_teacher', 'class_teacher');");
        DB::statement("CREATE TYPE mark_result_status_enum AS ENUM ('blank', 'numeric', 'absent');");
        DB::statement("CREATE TYPE calculation_method_enum AS ENUM ('average_percentage', 'combined_marks');");
        DB::statement("CREATE TYPE report_type_enum AS ENUM ('exam', 'term', 'final');");

        // 2. REUSABLE TRIGGER FUNCTION FOR updated_at
        DB::statement("
            CREATE OR REPLACE FUNCTION trigger_set_updated_at()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = clock_timestamp();
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP FUNCTION IF EXISTS trigger_set_updated_at CASCADE;");
        DB::statement("DROP TYPE IF EXISTS report_type_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS calculation_method_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS mark_result_status_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS teacher_assignment_type_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS assessment_status_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS student_placement_status_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS subject_category_enum CASCADE;");
        DB::statement("DROP TYPE IF EXISTS academic_year_status_enum CASCADE;");
    }
};
