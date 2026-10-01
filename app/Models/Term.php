<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Term extends Model
{
    protected $table = 'terms';

    protected $fillable = [
        'academic_year_id',
        'name',
        'sequence_no',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sequence_no' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'term_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(Attendance::class, 'term_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class, 'term_id');
    }

    public function generatedReports(): HasMany
    {
        return $this->hasMany(GeneratedReport::class, 'term_id');
    }
}
