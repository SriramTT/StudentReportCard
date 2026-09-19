<?php

namespace App\Models;

use App\Enums\AssessmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    protected $table = 'assessments';

    protected $fillable = [
        'academic_year_id',
        'term_id',
        'assessment_type_id',
        'name',
        'assessment_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'assessment_date' => 'date',
            'status' => AssessmentStatus::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id');
    }

    public function assessmentType(): BelongsTo
    {
        return $this->belongsTo(AssessmentType::class, 'assessment_type_id');
    }

    public function applicabilities(): HasMany
    {
        return $this->hasMany(AssessmentApplicability::class, 'assessment_id');
    }

    public function reportSelections(): HasMany
    {
        return $this->hasMany(ReportAssessmentSelection::class, 'assessment_id');
    }

    public function generatedReports(): HasMany
    {
        return $this->hasMany(GeneratedReport::class, 'assessment_id');
    }
}
