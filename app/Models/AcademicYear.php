<?php

namespace App\Models;

use App\Enums\AcademicYearStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    protected $table = 'academic_years';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => AcademicYearStatus::class,
            'is_current' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class, 'academic_year_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'academic_year_id');
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class, 'academic_year_id');
    }

    public function studentAcademicRecords(): HasMany
    {
        return $this->hasMany(StudentAcademicRecord::class, 'academic_year_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'academic_year_id');
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'academic_year_id');
    }

    public function calculationSettings(): HasMany
    {
        return $this->hasMany(CalculationSetting::class, 'academic_year_id');
    }

    public function reportConfigurations(): HasMany
    {
        return $this->hasMany(ReportConfiguration::class, 'academic_year_id');
    }
}
