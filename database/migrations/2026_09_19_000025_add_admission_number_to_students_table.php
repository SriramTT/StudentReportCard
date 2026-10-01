<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE students
                ADD COLUMN admission_number VARCHAR(50) NOT NULL,
                ADD CONSTRAINT uk_students_admission_number UNIQUE (admission_number);
        ");

        DB::statement("CREATE INDEX idx_students_admission_number ON students (admission_number);");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS idx_students_admission_number;");
        DB::statement("ALTER TABLE students DROP CONSTRAINT IF EXISTS uk_students_admission_number;");
        DB::statement("ALTER TABLE students DROP COLUMN IF EXISTS admission_number;");
    }
};
