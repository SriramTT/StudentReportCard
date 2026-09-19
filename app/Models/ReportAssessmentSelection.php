<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportAssessmentSelection extends Model
{
    protected $table = 'report_assessment_selections';

    protected $fillable = [
        'report_configuration_id',
        'assessment_id',
        'display_order',
        'is_displayed',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_displayed' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function reportConfiguration(): BelongsTo
    {
        return $this->belongsTo(ReportConfiguration::class, 'report_configuration_id');
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }
}
