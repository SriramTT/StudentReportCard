<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', \App\Models\SchoolSetting::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'school_name' => ['required', 'string', 'max:150'],
            'pass_mark' => ['required', 'numeric', 'min:0'],
            'school_logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'school_logo_path' => ['nullable', 'string', 'max:255'],
            'remove_school_logo' => ['nullable', 'boolean'],
            'class_teacher_signature' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'principal_signature' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'remove_class_teacher_signature' => ['nullable', 'boolean'],
            'remove_principal_signature' => ['nullable', 'boolean'],
            'teacher_signatures' => ['nullable', 'array'],
            'teacher_signatures.*' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'remove_teacher_signatures' => ['nullable', 'array'],
            'remove_teacher_signatures.*' => ['integer', 'exists:users,id'],
        ];
    }

    /**
     * Configure the validator instance with business lifecycle rules for the school logo.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $settings = \App\Models\SchoolSetting::first();
            $hasExistingLogo = ! empty($settings?->school_logo_path);
            $isRemoving = $this->boolean('remove_school_logo');
            $hasNewUpload = $this->hasFile('school_logo');

            if ($isRemoving && ! $hasNewUpload) {
                $validator->errors()->add(
                    'school_logo',
                    'A school logo is required. Please upload a replacement logo before removing the existing logo.'
                );
            } elseif (! $hasExistingLogo && ! $hasNewUpload) {
                $validator->errors()->add(
                    'school_logo',
                    'A school logo is required. Please upload a school logo image.'
                );
            }
        });
    }
}
