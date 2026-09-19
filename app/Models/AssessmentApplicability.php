<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentApplicability extends Model
{
    protected $table = 'assessment_applicability';

    protected $fillable = [
        'assessment_id',
        'class_subject_id',
        'maximum_marks',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'maximum_marks' => 'decimal:2',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id');
    }

    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class, 'class_subject_id');
    }

    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class, 'assessment_applicability_id');
    }
}
