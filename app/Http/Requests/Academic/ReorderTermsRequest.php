<?php

namespace App\Http\Requests\Academic;

use App\Models\Term;
use Illuminate\Foundation\Http\FormRequest;

class ReorderTermsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reorder', Term::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'term_ids' => ['required', 'array', 'min:1'],
            'term_ids.*' => ['required', 'integer', 'distinct', 'exists:terms,id'],
        ];
    }
}
