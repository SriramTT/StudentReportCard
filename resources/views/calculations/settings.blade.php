@extends('layouts.app')

@section('title', 'Calculation Settings - School Examination & Report Card Management System')
@section('page_title', 'Calculation Settings')

@section('content')
<div class="filter-bar">
    <form method="GET" action="{{ route('calculations.settings.index') }}" style="display: flex; gap: 0.75rem; align-items: center;">
        <label for="filter_academic_year_id" style="font-size: var(--font-size-sm); font-weight: 600;">Academic Year:</label>
        <select name="academic_year_id" id="filter_academic_year_id" class="form-control" onchange="this.form.submit()">
            @foreach($academicYears as $year)
                <option value="{{ $year->id }}" {{ $selectedYearId == $year->id ? 'selected' : '' }}>
                    {{ $year->name }} {{ $year->is_current ? '(Current)' : '' }}
                </option>
            @endforeach
        </select>
    </form>
</div>

<div class="alert alert-info">
    <strong>Calculation Invariant:</strong> The approved business rule specifies that only the designated Term Exam contributes to the term percentage calculation. The selected method governs how assessments are aggregated when multiple evaluations participate.
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
    <div class="card">
        <div class="card-header">
            <h3>Class Calculation Methods</h3>
        </div>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>Academic Year</th>
                        <th>Calculation Method</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($settings as $setting)
                        <tr>
                            <td><strong>{{ $setting->schoolClass?->name }}</strong></td>
                            <td>{{ $setting->academicYear?->name }}</td>
                            <td>
                                <span class="badge badge-primary">
                                    {{ $setting->calculation_method->value === 'average_percentage' ? 'Average Percentage' : 'Combined Marks' }}
                                </span>
                            </td>
                            <td>
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
    <div class="card">
        <div class="card-header">
            <h3>Configure Class Calculation</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('calculations.settings.store') }}">
                @csrf
                <input type="hidden" name="academic_year_id" value="{{ $selectedYearId }}">

                <div class="form-group">
                    <label for="class_id" class="form-label">Class <span style="color: var(--color-danger);">*</span></label>
                    <select name="class_id" id="class_id" class="form-control" required>
                        @foreach($classes as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="calculation_method" class="form-label">Calculation Method <span style="color: var(--color-danger);">*</span></label>
                    <select name="calculation_method" id="calculation_method" class="form-control" required>
                        <option value="average_percentage">Average Percentage (Equal weighting across components)</option>
                        <option value="combined_marks">Combined Marks (Total obtained / Total maximum)</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                    Save Calculation Setting
                </button>
            </form>
        </div>
    </div>
    @endcan
</div>
@endsection
