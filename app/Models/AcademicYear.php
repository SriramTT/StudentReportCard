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

    public function isClosed(): bool
    {
        return $this->isDateExpired() || $this->status === AcademicYearStatus::CLOSED;
    }

    public function isOpen(): bool
    {
        return ! $this->isDateExpired() && $this->status === AcademicYearStatus::OPEN;
    }

    public function isDateCurrent(?\Carbon\CarbonInterface $date = null): bool
    {
        if (empty($this->start_date) || empty($this->end_date)) {
            return false;
        }
        $targetDate = ($date ?? now())->toDateString();
        return $this->start_date->toDateString() <= $targetDate && $targetDate <= $this->end_date->toDateString();
    }

    public function isDateExpired(?\Carbon\CarbonInterface $date = null): bool
    {
        if (empty($this->end_date)) {
            return false;
        }
        $targetDate = ($date ?? now())->toDateString();
        return $targetDate > $this->end_date->toDateString();
    }

    public function isDateUpcoming(?\Carbon\CarbonInterface $date = null): bool
    {
        if (empty($this->start_date)) {
            return false;
        }
        $targetDate = ($date ?? now())->toDateString();
        return $targetDate < $this->start_date->toDateString();
    }

    public function configurationWindowStart(): ?\Carbon\CarbonInterface
    {
        if (empty($this->start_date)) {
            return null;
        }
        return \Illuminate\Support\Carbon::parse($this->start_date)->startOfDay()->subMonthNoOverflow()->startOfDay();
    }

    public function configurationWindowEnd(): ?\Carbon\CarbonInterface
    {
        if (empty($this->end_date)) {
            return null;
        }
        return \Illuminate\Support\Carbon::parse($this->end_date)->startOfDay()->subMonthsNoOverflow(2)->startOfDay();
    }

    public function isConfigurationWindowOpen(?\Carbon\CarbonInterface $date = null): bool
    {
        $start = $this->configurationWindowStart();
        $end = $this->configurationWindowEnd();
        if (! $start || ! $end) {
            return false;
        }
        $target = ($date ?? now())->startOfDay();
        return $target->gte($start) && $target->lte($end);
    }

    public function isConfigurationWindowLocked(?\Carbon\CarbonInterface $date = null): bool
    {
        return ! $this->isConfigurationWindowOpen($date);
    }

    public function configurationWindowStatus(?\Carbon\CarbonInterface $date = null): string
    {
        $start = $this->configurationWindowStart();
        $end = $this->configurationWindowEnd();
        if (! $start || ! $end) {
            return 'Unknown';
        }
        $target = ($date ?? now())->startOfDay();
        if ($target->lt($start)) {
            return 'Locked (Opens ' . $start->toDateString() . ')';
        }
        if ($target->gt($end)) {
            return 'Locked (Closed ' . $end->toDateString() . ')';
        }
        return 'Open (Until ' . $end->toDateString() . ')';
    }

    public static function current(?\Carbon\CarbonInterface $date = null): ?AcademicYear
    {
        return app(\App\Services\AcademicYearService::class)->resolveCurrentAcademicYear($date);
    }
}
