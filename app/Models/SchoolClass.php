<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'name',
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

    /**
     * Return classes ordered using human natural sorting (e.g. Class 1, Class 2, ... Class 10).
     *
     * @param bool $activeOnly
     * @param array<int>|null $classIds
     * @return \Illuminate\Support\Collection<int, SchoolClass>
     */
    public static function getNaturallySorted(bool $activeOnly = true, ?array $classIds = null): \Illuminate\Support\Collection
    {
        $query = static::query();
        if ($activeOnly) {
            $query->where('is_active', true);
        }
        if ($classIds !== null) {
            $query->whereIn('id', $classIds);
        }
        return $query->get()->sortBy('name', SORT_NATURAL)->values();
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'class_id');
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class, 'class_id');
    }

    public function studentAcademicRecords(): HasMany
    {
        return $this->hasMany(StudentAcademicRecord::class, 'class_id');
    }

    public function academicRecords(): HasMany
    {
        return $this->hasMany(StudentAcademicRecord::class, 'class_id');
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'class_id');
    }

    public function calculationSettings(): HasMany
    {
        return $this->hasMany(CalculationSetting::class, 'class_id');
    }
}
