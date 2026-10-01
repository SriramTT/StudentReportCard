<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class ReorderReportAssessmentSelectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $config = $this->route('reportConfiguration');
        return $this->user()?->can('update', $config) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'selection_ids' => ['required', 'array', 'min:1'],
            'selection_ids.*' => ['required', 'integer'],
        ];
    }
}
