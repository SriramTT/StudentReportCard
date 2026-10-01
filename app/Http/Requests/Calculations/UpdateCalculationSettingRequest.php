<?php

namespace App\Http\Requests\Calculations;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCalculationSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $setting = $this->route('calculationSetting');
        return $this->user()?->can('update', $setting) ?? false;
    }

    public function rules(): array
    {
        return [
            'calculation_method' => ['required', 'in:average_percentage,combined_marks'],
        ];
    }
}
