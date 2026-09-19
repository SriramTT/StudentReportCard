<?php

namespace App\Models;

use App\Enums\CalculationMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalculationSetting extends Model
{
    protected $table = 'calculation_settings';

    protected $fillable = [
        'academic_year_id',
        'class_id',
        'calculation_method',
    ];

    protected function casts(): array
    {
        return [
            'calculation_method' => CalculationMethod::class,
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
}
