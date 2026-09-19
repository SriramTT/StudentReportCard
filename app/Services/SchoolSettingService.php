<?php

namespace App\Services;

use App\Models\SchoolSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SchoolSettingService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Get or initialize the singleton SchoolSetting record.
     */
    public function getSettings(): SchoolSetting
    {
        $settings = SchoolSetting::first();

        if (! $settings) {
            $settings = SchoolSetting::create([
                'school_name' => 'Demo International Academy',
                'school_logo_path' => null,
                'pass_mark' => 35.00,
            ]);
        }

        return $settings;
    }

    /**
     * Update the singleton SchoolSetting record with audit logging.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSettings(array $data): SchoolSetting
    {
        return DB::transaction(function () use ($data) {
            $settings = $this->getSettings();
            $beforeData = $settings->only(['school_name', 'school_logo_path', 'pass_mark']);

            $settings->update([
                'school_name' => trim($data['school_name']),
                'pass_mark' => $data['pass_mark'],
                'school_logo_path' => array_key_exists('school_logo_path', $data)
                    ? ($data['school_logo_path'] ? trim($data['school_logo_path']) : null)
                    : $settings->school_logo_path,
            ]);

            $afterData = $settings->only(['school_name', 'school_logo_path', 'pass_mark']);

            $this->auditService->logDomainAction(
                userId: Auth::id(),
                action: 'UPDATE_SCHOOL_SETTINGS',
                entityType: 'school_settings',
                entityId: $settings->id,
                beforeData: $beforeData,
                afterData: $afterData,
                description: 'Updated school settings configuration'
            );

            return $settings;
        });
    }
}
