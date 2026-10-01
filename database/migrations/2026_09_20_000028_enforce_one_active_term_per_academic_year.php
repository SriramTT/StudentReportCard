<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Safety guard: Check for multiple active terms per academic year before applying constraint
        $conflicts = DB::table('terms')
            ->select('academic_year_id', DB::raw('count(*) as active_count'))
            ->where('is_active', true)
            ->groupBy('academic_year_id')
            ->havingRaw('count(*) > 1')
            ->get();

        if ($conflicts->isNotEmpty()) {
            $conflictDetails = [];
            foreach ($conflicts as $c) {
                $terms = DB::table('terms')
                    ->where('academic_year_id', $c->academic_year_id)
                    ->where('is_active', true)
                    ->pluck('name', 'id')
                    ->toArray();

                $termStrings = [];
                foreach ($terms as $id => $name) {
                    $termStrings[] = "Term '{$name}' (ID: {$id})";
                }

                $conflictDetails[] = "Academic Year ID {$c->academic_year_id} has {$c->active_count} active terms: [" . implode(', ', $termStrings) . "]";
            }

            throw new \RuntimeException(
                "MIGRATION BLOCKED [uk_terms_one_active_per_year]: Multiple active terms detected. " .
                implode('; ', $conflictDetails) . ". " .
                "Per project safety guidelines, existing active terms must not be arbitrarily deactivated automatically. " .
                "An authorized business decision is required to resolve which term remains active."
            );
        }

        DB::statement("ALTER TABLE terms ALTER COLUMN is_active SET DEFAULT FALSE;");

        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS uk_terms_one_active_per_year 
            ON terms (academic_year_id) 
            WHERE is_active = TRUE;
        ");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS uk_terms_one_active_per_year;");
        DB::statement("ALTER TABLE terms ALTER COLUMN is_active SET DEFAULT TRUE;");
    }
};
