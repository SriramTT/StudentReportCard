<?php

namespace App\Models;

use App\Enums\TeacherAssignmentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAssignment extends Model
{
    protected $table = 'teacher_assignments';

    protected $fillable = [
        'user_id',
        'academic_year_id',
        'class_id',
        'section_id',
        'subject_id',
        'assignment_type',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'assignment_type' => TeacherAssignmentType::class,
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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

    /**
     * Determine if this assignment row represents a Class Teacher assignment.
     */
    public function isClassTeacher(): bool
    {
        return $this->assignment_type === TeacherAssignmentType::CLASS_TEACHER;
    }

    /**
     * Determine if this assignment row represents a Subject Teacher assignment.
     */
    public function isSubjectTeacher(): bool
    {
        return $this->assignment_type === TeacherAssignmentType::SUBJECT_TEACHER;
    }
}
