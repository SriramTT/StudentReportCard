@extends('layouts.app')

@section('title', 'Calculation Settings - School Examination & Report Card Management System')
@section('page_title', 'Calculation Settings')

@section('content')

<div class="alert alert-info">
    <strong>Calculation Invariant:</strong> The approved business rule specifies that only the designated Term Exam contributes to the term percentage calculation. The selected method governs how assessments are aggregated when multiple evaluations participate.
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h3 style="margin: 0;">Class Calculation Methods</h3>
        @can('create', App\Models\CalculationSetting::class)
            <button type="button" class="btn btn-primary" id="btn-configure-calc" onclick="openModal('create-calc-setting-modal')">
                + Configure Class
            </button>
        @endcan
    </div>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>Calculation Method</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($settings as $setting)
                        <tr>
                            <td><strong>{{ $setting->schoolClass?->name }}</strong></td>
                            
                            <td>
                                <span class="badge badge-primary">
                                    {{ $setting->calculation_method->value === 'average_percentage' ? 'Average Percentage' : 'Combined Marks' }}
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    @can('update', $setting)
                                        <form method="POST" action="{{ route('calculations.settings.update', $setting) }}" style="display: inline-flex; gap: 0.5rem; align-items: center;">
                                            @csrf
                                            @method('PUT')
                                            <select name="calculation_method" class="form-control" style="width: auto; padding: 0.25rem 0.5rem; font-size: var(--font-size-sm);">
                                                <option value="average_percentage" {{ $setting->calculation_method->value === 'average_percentage' ? 'selected' : '' }}>Average Percentage</option>
                                                <option value="combined_marks" {{ $setting->calculation_method->value === 'combined_marks' ? 'selected' : '' }}>Combined Marks</option>
                                            </select>
                                            <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                                        </form>
                                    @endcan

                                    @can('delete', $setting)
                                        <form method="POST" action="{{ route('calculations.settings.destroy', $setting) }}" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this calculation setting?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                                No calculation settings configured for this academic year yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @can('create', App\Models\CalculationSetting::class)
    <div id="create-calc-setting-modal" class="modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="create-calc-setting-modal-title" onclick="if (event.target === this) closeModal('create-calc-setting-modal')">
        <div class="modal-dialog modal-dialog--md">
            <div class="modal-header">
                <h3 id="create-calc-setting-modal-title">Configure Class Calculation</h3>
                <button type="button" class="modal-close" aria-label="Close" onclick="closeModal('create-calc-setting-modal')">&times;</button>
            </div>
            <form method="POST" action="{{ route('calculations.settings.store') }}">
                @csrf
                <input type="hidden" name="_form_context" value="create_calculation_setting">
                <input type="hidden" name="academic_year_id" value="{{ $selectedYearId }}">

                <div class="modal-body">
                    <div class="form-group">
                        <label for="create_calc_class_id" class="form-label">Class <span style="color: var(--color-danger);">*</span></label>
                        <select name="class_id" id="create_calc_class_id" class="form-control @error('class_id') is-invalid @enderror" required>
                            @foreach($classes as $c)
                                <option value="{{ $c->id }}" {{ old('class_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                        @error('class_id')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="create_calculation_method" class="form-label">Calculation Method <span style="color: var(--color-danger);">*</span></label>
                        <select name="calculation_method" id="create_calculation_method" class="form-control @error('calculation_method') is-invalid @enderror" required>
                            <option value="average_percentage" {{ old('calculation_method') == 'average_percentage' ? 'selected' : '' }}>Average Percentage (Equal weighting across components)</option>
                            <option value="combined_marks" {{ old('calculation_method') == 'combined_marks' ? 'selected' : '' }}>Combined Marks (Total obtained / Total maximum)</option>
                        </select>
                        @error('calculation_method')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('create-calc-setting-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Calculation Setting</button>
                </div>
            </form>
        </div>
    </div>

    @if($errors->any() && old('_form_context') === 'create_calculation_setting')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                openModal('create-calc-setting-modal');
            });
        </script>
    @endif
    @endcan
@endsection
