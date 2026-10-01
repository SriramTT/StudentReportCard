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

            $oldLogoPath = $settings->school_logo_path;
            $logoPath = $oldLogoPath;

            $isRemovingLogo = ! empty($data['remove_school_logo']);
            $hasNewLogoUpload = isset($data['school_logo']) && $data['school_logo'] instanceof \Illuminate\Http\UploadedFile;

            if ($hasNewLogoUpload) {
                // 1. Store new file first to ensure atomic replacement safety
                $newLogoPath = $data['school_logo']->store('logos', 'public');
                $logoPath = $newLogoPath;

                // 2. Safely delete old logo asset if different and exists
                if ($oldLogoPath && $oldLogoPath !== $newLogoPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($oldLogoPath)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($oldLogoPath);
                }
            } elseif ($isRemovingLogo) {
                if ($oldLogoPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($oldLogoPath)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($oldLogoPath);
                }
                $logoPath = null;
            } elseif (array_key_exists('school_logo_path', $data)) {
                $logoPath = $data['school_logo_path'] ? trim($data['school_logo_path']) : null;
            }

            $settings->update([
                'school_name' => trim($data['school_name']),
                'pass_mark' => $data['pass_mark'],
                'school_logo_path' => $logoPath,
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

    /**
     * Store a signature image file in private filesystem storage.
     * Allowed types: 'class-teacher', 'principal'
     */
    public function storeSignature(string $type, \Illuminate\Http\UploadedFile $file): string
    {
        $normalizedType = $this->normalizeSignatureType($type);

        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $ext = 'png';
        }

        $filename = "{$normalizedType}.{$ext}";
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $disk->makeDirectory('school-settings/signatures');

        // Store new file first to ensure failure safety
        $newPath = $disk->putFileAs(
            'school-settings/signatures',
            $file,
            $filename
        );

        // Remove stale extension variants for this signature type
        $files = $disk->files('school-settings/signatures');
        foreach ($files as $existingFile) {
            $basename = basename($existingFile);
            if ($basename !== $filename && preg_match("/^{$normalizedType}\.(png|jpg|jpeg|webp)$/i", $basename)) {
                $disk->delete($existingFile);
            }
        }

        return $newPath;
    }

    /**
     * Delete existing signature for a given type.
     */
    public function deleteSignature(string $type): void
    {
        $normalizedType = $this->normalizeSignatureType($type);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $files = $disk->files('school-settings/signatures');

        foreach ($files as $file) {
            $basename = basename($file);
            if (preg_match("/^{$normalizedType}\.(png|jpg|jpeg|webp)$/i", $basename)) {
                $disk->delete($file);
            }
        }
    }

    /**
     * Get the relative storage path of a signature if it exists.
     */
    public function getSignaturePath(string $type): ?string
    {
        $normalizedType = $this->normalizeSignatureType($type);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $files = $disk->files('school-settings/signatures');

        foreach ($files as $file) {
            $basename = basename($file);
            if (preg_match("/^{$normalizedType}\.(png|jpg|jpeg|webp)$/i", $basename)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Get the base64 data URI of a signature for PDF/web embedding.
     */
    public function getSignatureDataUri(string $type): ?string
    {
        $path = $this->getSignaturePath($type);
        if (! $path) {
            return null;
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        if (! $disk->exists($path)) {
            return null;
        }

        $bytes = $disk->get($path);
        if (empty($bytes)) {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    /**
     * Store an individual teacher's signature image file in private filesystem storage.
     * Stored at: school-settings/signatures/teachers/{userId}.{ext}
     */
    public function storeTeacherSignature(int $userId, \Illuminate\Http\UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $ext = 'png';
        }

        $filename = "{$userId}.{$ext}";
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $directory = 'school-settings/signatures/teachers';
        $disk->makeDirectory($directory);

        // Store new file first to ensure failure safety
        $newPath = $disk->putFileAs(
            $directory,
            $file,
            $filename
        );

        // Remove stale extension variants for this teacher user ID
        $files = $disk->files($directory);
        foreach ($files as $existingFile) {
            $basename = basename($existingFile);
            if ($basename !== $filename && preg_match("/^{$userId}\.(png|jpg|jpeg|webp)$/i", $basename)) {
                $disk->delete($existingFile);
            }
        }

        return $newPath;
    }

    /**
     * Delete an individual teacher's signature file.
     */
    public function deleteTeacherSignature(int $userId): void
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $directory = 'school-settings/signatures/teachers';
        $files = $disk->files($directory);

        foreach ($files as $file) {
            $basename = basename($file);
            if (preg_match("/^{$userId}\.(png|jpg|jpeg|webp)$/i", $basename)) {
                $disk->delete($file);
            }
        }
    }

    /**
     * Get the relative storage path of a teacher's signature if it exists.
     */
    public function getTeacherSignaturePath(int $userId): ?string
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $directory = 'school-settings/signatures/teachers';
        $files = $disk->files($directory);

        foreach ($files as $file) {
            $basename = basename($file);
            if (preg_match("/^{$userId}\.(png|jpg|jpeg|webp)$/i", $basename)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Get the base64 data URI of a teacher's signature for PDF/web embedding.
     */
    public function getTeacherSignatureDataUri(int $userId): ?string
    {
        $path = $this->getTeacherSignaturePath($userId);
        if (! $path) {
            return null;
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        if (! $disk->exists($path)) {
            return null;
        }

        $bytes = $disk->get($path);
        if (empty($bytes)) {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    /**
     * Normalize signature type to canonical hyphenated string ('class-teacher' or 'principal').
     */
    protected function normalizeSignatureType(string $type): string
    {
        $clean = strtolower(str_replace('_', '-', trim($type)));
        if (! in_array($clean, ['class-teacher', 'principal'], true)) {
            throw new \InvalidArgumentException("Invalid signature type: {$type}. Allowed: 'class-teacher', 'principal'.");
        }

        return $clean;
    }
}
