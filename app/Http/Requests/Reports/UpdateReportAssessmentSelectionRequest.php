<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReportAssessmentSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isOfficeStaff();
    }

    public function rules(): array
    {
        return [
            'display_order' => ['sometimes', 'integer', 'min:1'],
            'is_displayed' => ['sometimes', 'boolean'],
        ];
    }
}
