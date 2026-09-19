<?php

namespace App\Models;

use App\Casts\MarkValueCast;
use App\Enums\MarkResultStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mark extends Model
{
    protected $table = 'marks';

    protected $fillable = [
        'student_academic_record_id',
        'student_subject_allocation_id',
        'assessment_applicability_id',
        'mark_value',
        'result_status',
        'entered_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'mark_value' => MarkValueCast::class,
            'result_status' => MarkResultStatus::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function studentAcademicRecord(): BelongsTo
    {
        return $this->belongsTo(StudentAcademicRecord::class, 'student_academic_record_id');
    }

    public function studentSubjectAllocation(): BelongsTo
    {
        return $this->belongsTo(StudentSubjectAllocation::class, 'student_subject_allocation_id');
    }

    public function assessmentApplicability(): BelongsTo
    {
        return $this->belongsTo(AssessmentApplicability::class, 'assessment_applicability_id');
    }

    public function enteredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_user_id');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
