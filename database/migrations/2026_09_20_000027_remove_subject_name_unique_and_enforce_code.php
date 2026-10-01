<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop unique index on lower(name)
        DB::statement("DROP INDEX IF EXISTS uk_subjects_name;");

        // Enforce unique index on subject code
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS uk_subjects_code ON subjects (code);");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS uk_subjects_code;");
        DB::statement("CREATE UNIQUE INDEX uk_subjects_name ON subjects (LOWER(name));");
    }
};
