<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $table = 'students';

    protected $fillable = [
        'admission_number',
        'student_name',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function academicRecords(): HasMany
    {
        return $this->hasMany(StudentAcademicRecord::class, 'student_id');
    }

    public function latestAcademicRecord(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(StudentAcademicRecord::class, 'student_id')->latestOfMany();
    }

    /**
     * Resolve the student's applicable academic placement given an optional context.
     * Evaluates in-memory over eager-loaded academicRecords without triggering lazy queries.
     *
     * @param array<string, mixed> $context
     */
    public function getContextualAcademicRecord(array $context = []): ?StudentAcademicRecord
    {
        if (! $this->relationLoaded('academicRecords')) {
            return null;
        }

        $records = $this->academicRecords;

        // 1. Contextual filtering by academic year if specified
        if (!empty($context['academic_year_id'])) {
            $records = $records->where('academic_year_id', (int) $context['academic_year_id']);
        }

        // When resolving the student's authoritative current/contextual placement:
        // A historical placement closed by an internal transfer is not the current placement.
        // 1) Active placement in context
        // 2) Or the latest non-internal_transfer record in context (e.g. withdrawn, transferred_out)
        // 3) Or the latest record in context if all are historical
        $activeInContext = $records->first(fn($r) => $r->status?->value === 'active');
        $currentPlacement = $activeInContext
            ?? $records->first(fn($r) => $r->status?->value !== 'internal_transfer')
            ?? $records->first();

        if (! $currentPlacement) {
            // Fallback across all loaded records
            $currentPlacement = $this->academicRecords->first(fn($r) => $r->status?->value === 'active')
                ?? $this->academicRecords->first(fn($r) => $r->status?->value !== 'internal_transfer')
                ?? $this->academicRecords->first();
        }

        if (! $currentPlacement) {
            return null;
        }

        // 2. Classroom filtering: validate current placement against requested class/section
        if (!empty($context['class_id']) && (int) $currentPlacement->class_id !== (int) $context['class_id']) {
            return null;
        }
        if (!empty($context['section_id']) && (int) $currentPlacement->section_id !== (int) $context['section_id']) {
            return null;
        }

        // 3. Status handling
        if (!empty($context['status']) && $context['status'] !== 'all') {
            $statusVal = $context['status'] instanceof \BackedEnum ? $context['status']->value : (string) $context['status'];
            if ($currentPlacement->status?->value !== $statusVal) {
                return null;
            }
        }

        return $currentPlacement;
    }
}
