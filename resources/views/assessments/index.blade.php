@extends('layouts.app')

@section('title', 'Assessments - School Examination & Report Card Management System')
@section('page_title', 'Assessments')

@section('content')
<div class="filter-bar">
    <form method="GET" action="{{ route('assessments.index') }}" style="display: flex; gap: 0.75rem; align-items: center;">
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

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
    <div class="card">
        <div class="card-header">
            <h3>Registered Assessments</h3>
        </div>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Assessment Name</th>
                        <th>Type</th>
                        <th>Term</th>
                        <th>Date</th>
                        <th>Applicability</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assessments as $assessment)
                        <tr>
                            <td><strong>{{ $assessment->name }}</strong></td>
                            <td>{{ $assessment->assessmentType?->name }}</td>
                            <td>{{ $assessment->term?->name ?? 'All / Final' }}</td>
                            <td>{{ $assessment->assessment_date ? $assessment->assessment_date->format('Y-m-d') : 'N/A' }}</td>
                            <td>
                                <a href="{{ route('assessments.applicability.index', $assessment) }}" class="btn btn-secondary btn-sm">
                                    {{ $assessment->applicabilities_count }} Subjects (Configure)
                                </a>
                            </td>
                            <td>
                                <span class="badge {{ $assessment->status->value === 'active' ? 'badge-success' : 'badge-secondary' }}">
                                    {{ ucfirst($assessment->status->value) }}
                                </span>
                            </td>
                            <td>
                                @can('update', $assessment)
                                    <form method="POST" action="{{ route('assessments.update', $assessment) }}" style="display: inline;">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="name" value="{{ $assessment->name }}">
                                        <input type="hidden" name="assessment_type_id" value="{{ $assessment->assessment_type_id }}">
                                        <input type="hidden" name="term_id" value="{{ $assessment->term_id }}">
                                        <input type="hidden" name="status" value="{{ $assessment->status->value === 'active' ? 'inactive' : 'active' }}">
                                        <button type="submit" class="btn btn-secondary btn-sm">
                                            {{ $assessment->status->value === 'active' ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                                No assessments found for this academic year. Create an assessment using the form.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @can('create', App\Models\Assessment::class)
    <div class="card">
        <div class="card-header">
            <h3>Add New Assessment</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('assessments.store') }}">
                @csrf
                <input type="hidden" name="academic_year_id" value="{{ $selectedYearId }}">

                <div class="form-group">
                    <label for="name" class="form-label">Assessment Name <span style="color: var(--color-danger);">*</span></label>
                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Unit Test 1, Term Exam, Mid-Term" value="{{ old('name') }}" required maxlength="100">
                    <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Flexible naming; max marks are configured per subject.</small>
                    @error('name')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="assessment_type_id" class="form-label">Assessment Type <span style="color: var(--color-danger);">*</span></label>
                    <select name="assessment_type_id" id="assessment_type_id" class="form-control" required>
                        @foreach($assessmentTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="term_id" class="form-label">Term (Optional)</label>
                    <select name="term_id" id="term_id" class="form-control">
                        <option value="">None / Cross-Term / Final</option>
                        @foreach($availableTerms as $t)
                            <option value="{{ $t->id }}">{{ $t->name }} (Seq: {{ $t->sequence_no }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="assessment_date" class="form-label">Assessment Date (Optional)</label>
                    <input type="date" name="assessment_date" id="assessment_date" class="form-control @error('assessment_date') is-invalid @enderror" value="{{ old('assessment_date') }}">
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="status" id="status" value="active" checked>
                    <label for="status" style="font-size: var(--font-size-sm); cursor: pointer;">Active</label>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                    Create Assessment
                </button>
            </form>
        </div>
    </div>
    @endcan
</div>
@endsection
