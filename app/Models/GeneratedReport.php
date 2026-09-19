<?php

namespace App\Models;

use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedReport extends Model
{
    protected $table = 'generated_reports';

    public $timestamps = false;

    protected $fillable = [
        'student_academic_record_id',
        'report_type',
        'term_id',
        'assessment_id',
        'revision_number',
        'file_path',
        'generated_by_user_id',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'report_type' => ReportType::class,
            'revision_number' => 'integer',
            'generated_at' => 'immutable_datetime',
        ];
    }

    public function studentAcademicRecord(): BelongsTo
    {
        return $this->belongsTo(StudentAcademicRecord::class, 'student_academic_record_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id');
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }

    public function generatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }
}
