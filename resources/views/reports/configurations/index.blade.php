@extends('layouts.app')

@section('title', 'Report Configurations - School Examination & Report Card Management System')
@section('page_title', 'Report Configurations')

@section('content')
<div class="filter-bar">
    <form method="GET" action="{{ route('reports.configurations.index') }}" style="display: flex; gap: 0.75rem; align-items: center;">
        <label for="filter_academic_year_id" style="font-size: var(--font-size-sm); font-weight: 600;">Filter Year:</label>
        <select name="academic_year_id" id="filter_academic_year_id" class="form-control" onchange="this.form.submit()">
            <option value="">All Configurations (Including Global)</option>
            @foreach($academicYears as $year)
                <option value="{{ $year->id }}" {{ $selectedYearId == $year->id ? 'selected' : '' }}>
                    {{ $year->name }} {{ $year->is_current ? '(Current)' : '' }}
                </option>
            @endforeach
        </select>
    </form>
</div>

<div class="alert alert-info">
    <strong>Display vs Calculation:</strong> Assessment selections configured below control display visibility and column order on student reports. They do NOT dictate marks calculation participation.
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
    <!-- Configurations Listing with child selections -->
    <div>
        @forelse($configurations as $config)
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 style="display: inline-block; margin-right: 0.75rem;">{{ $config->name }}</h3>
                        <span class="badge badge-primary">{{ ucfirst($config->report_type->value) }} Report</span>
                        @if($config->academicYear)
                            <span class="badge badge-secondary">{{ $config->academicYear->name }}</span>
                        @else
                            <span class="badge badge-warning">Universal / Template</span>
                        @endif
                    </div>
                    <div>
                        <span class="badge {{ $config->is_active ? 'badge-success' : 'badge-secondary' }}">
                            {{ $config->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>
                <div class="card-body" style="padding-bottom: 0.5rem;">
                    <h4 style="font-size: var(--font-size-sm); color: var(--color-text-muted); margin-bottom: 0.75rem;">
                        Report Assessment Display Order & Selection
                    </h4>

                    <div class="data-table-wrapper" style="margin-bottom: 1.25rem;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Assessment</th>
                                    <th>Display on Report</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($config->assessmentSelections as $sel)
                                    <tr>
                                        <td><strong>#{{ $sel->display_order }}</strong></td>
                                        <td>{{ $sel->assessment?->name }}</td>
                                        <td>
                                            <span class="badge {{ $sel->is_displayed ? 'badge-success' : 'badge-secondary' }}">
                                                {{ $sel->is_displayed ? 'Displayed' : 'Hidden' }}
                                            </span>
                                        </td>
                                        <td>
                                            @can('update', $config)
                                                <form method="POST" action="{{ route('reports.selections.update', [$config, $sel]) }}" style="display: inline-flex; gap: 0.5rem; align-items: center;">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="number" min="1" name="display_order" value="{{ $sel->display_order }}" class="form-control" style="width: 70px; padding: 0.2rem 0.4rem; font-size: var(--font-size-xs);" title="Change Order">
                                                    <input type="hidden" name="is_displayed" value="{{ $sel->is_displayed ? '0' : '1' }}">
                                                    <button type="submit" class="btn btn-secondary btn-sm">
                                                        {{ $sel->is_displayed ? 'Hide' : 'Show' }} / Reorder
                                                    </button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: var(--color-text-muted); padding: 1rem;">
                                            No assessments selected for display yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Add Assessment Selection Form -->
                    @can('update', $config)
                        <form method="POST" action="{{ route('reports.selections.store', $config) }}" style="display: flex; gap: 0.75rem; align-items: flex-end; background-color: #f8fafc; padding: 0.75rem 1rem; border-radius: var(--border-radius-md); border: 1px solid var(--color-border); margin-bottom: 1rem;">
                            @csrf
                            <div style="flex: 2;">
                                <label style="font-size: var(--font-size-xs); font-weight: 600; display: block; margin-bottom: 0.25rem;">Add Assessment to Report:</label>
                                <select name="assessment_id" class="form-control" style="font-size: var(--font-size-sm); padding: 0.4rem;" required>
                                    @php
                                        $selectedAssessmentIds = $config->assessmentSelections->pluck('assessment_id')->toArray();
                                        $selectableAssessments = $availableAssessments->whereNotIn('id', $selectedAssessmentIds);
                                        if ($config->academic_year_id) {
                                            $selectableAssessments = $selectableAssessments->where('academic_year_id', $config->academic_year_id);
                                        }
                                    @endphp
                                    @foreach($selectableAssessments as $a)
                                        <option value="{{ $a->id }}">{{ $a->name }} ({{ $a->academicYear?->name }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div style="flex: 1;">
                                <label style="font-size: var(--font-size-xs); font-weight: 600; display: block; margin-bottom: 0.25rem;">Order:</label>
                                <input type="number" min="1" name="display_order" value="{{ $config->assessmentSelections->count() + 1 }}" class="form-control" style="font-size: var(--font-size-sm); padding: 0.4rem;" required>
                            </div>
                            <input type="hidden" name="is_displayed" value="1">
                            <button type="submit" class="btn btn-primary btn-sm" style="padding: 0.5rem 1rem;">
                                Add
                            </button>
                        </form>
                    @endcan
                </div>
            </div>
        @empty
            <div class="card">
                <div class="card-body" style="text-align: center; color: var(--color-text-muted); padding: 3rem;">
                    No report configurations found. Create one using the form.
                </div>
            </div>
        @endforelse
    </div>

    <!-- Create Report Configuration Form -->
    @can('create', App\Models\ReportConfiguration::class)
    <div class="card">
        <div class="card-header">
            <h3>New Report Configuration</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('reports.configurations.store') }}">
                @csrf

                <div class="form-group">
                    <label for="name" class="form-label">Configuration Name <span style="color: var(--color-danger);">*</span></label>
                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Term 1 Report Card Layout" value="{{ old('name') }}" required maxlength="100">
                    @error('name')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="report_type" class="form-label">Report Type <span style="color: var(--color-danger);">*</span></label>
                    <select name="report_type" id="report_type" class="form-control" required>
                        <option value="term" {{ old('report_type') == 'term' ? 'selected' : '' }}>Term Report Card</option>
                        <option value="exam" {{ old('report_type') == 'exam' ? 'selected' : '' }}>Individual Exam / Assessment Marksheet</option>
                        <option value="final" {{ old('report_type') == 'final' ? 'selected' : '' }}>Final Annual Consolidated Report</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="academic_year_id" class="form-label">Academic Year (Optional)</label>
                    <select name="academic_year_id" id="academic_year_id" class="form-control">
                        <option value="">Universal / Non-Year Specific</option>
                        @foreach($academicYears as $year)
                            <option value="{{ $year->id }}" {{ $selectedYearId == $year->id ? 'selected' : '' }}>
                                {{ $year->name }} {{ $year->is_current ? '(Current)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Leave blank to create a global report layout template.</small>
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                    <label for="is_active" style="font-size: var(--font-size-sm); cursor: pointer;">Active</label>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                    Create Report Configuration
                </button>
            </form>
        </div>
    </div>
    @endcan
</div>
@endsection
