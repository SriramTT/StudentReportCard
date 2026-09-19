<?php

namespace App\Models;

use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportConfiguration extends Model
{
    protected $table = 'report_configurations';

    protected $fillable = [
        'academic_year_id',
        'name',
        'report_type',
        'configuration_data',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'report_type' => ReportType::class,
            'configuration_data' => 'array',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function assessmentSelections(): HasMany
    {
        return $this->hasMany(ReportAssessmentSelection::class, 'report_configuration_id');
    }
}
