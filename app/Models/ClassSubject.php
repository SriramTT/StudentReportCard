<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassSubject extends Model
{
    protected $table = 'class_subjects';

    protected $fillable = [
        'academic_year_id',
        'class_id',
        'section_id',
        'subject_id',
        'subject_name_snapshot',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function studentSubjectAllocations(): HasMany
    {
        return $this->hasMany(StudentSubjectAllocation::class, 'class_subject_id');
    }

    public function assessmentApplicabilities(): HasMany
    {
        return $this->hasMany(AssessmentApplicability::class, 'class_subject_id');
    }
}
