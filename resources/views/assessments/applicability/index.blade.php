@extends('layouts.app')

@section('title', 'Assessment Applicability - School Examination & Report Card Management System')
@section('page_title', 'Assessment Maximum Marks & Applicability')

@section('content')
<div style="margin-bottom: 1.25rem;">
    <a href="{{ route('assessments.index', ['academic_year_id' => $assessment->academic_year_id]) }}" class="btn btn-secondary btn-sm">
        &larr; Back to Assessments
    </a>
</div>

<div class="card" style="margin-bottom: 1.5rem; background-color: var(--color-primary-light); border-color: #bfdbfe;">
    <div class="card-body" style="padding: 1rem 1.5rem;">
        <h4 style="color: var(--color-primary); margin-bottom: 0.25rem;">
            {{ $assessment->name }}
        </h4>
        <p style="font-size: var(--font-size-sm); color: var(--color-secondary); margin: 0;">
            Academic Year: <strong>{{ $assessment->academicYear?->name }}</strong> |
            Type: <strong>{{ $assessment->assessmentType?->name }}</strong> |
            Term: <strong>{{ $assessment->term?->name ?? 'None' }}</strong>
        </p>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
    <!-- Configured Applicability Table -->
    <div class="card">
        <div class="card-header">
            <h3>Subject Maximum Marks for this Assessment</h3>
        </div>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>Section</th>
                        <th>Subject</th>
                        <th>Maximum Marks</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applicabilities as $app)
                        <tr>
                            <td><strong>{{ $app->classSubject?->schoolClass?->name }}</strong></td>
                            <td>{{ $app->classSubject?->section?->name ?? 'All Sections' }}</td>
                            <td>{{ $app->classSubject?->subject?->name }}</td>
                            <td>
                                <form method="POST" action="{{ route('assessments.applicability.update', [$assessment, $app]) }}" style="display: inline-flex; gap: 0.5rem; align-items: center;">
                                    @csrf
                                    @method('PUT')
                                    <input type="number" step="0.01" min="0.01" name="maximum_marks" value="{{ $app->maximum_marks }}" class="form-control" style="width: 100px; padding: 0.25rem 0.5rem; font-size: var(--font-size-sm);" required>
                                    <button type="submit" class="btn btn-secondary btn-sm">Update</button>
                                </form>
                            </td>
                            <td>
                                <span class="badge {{ $app->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $app->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                @can('update', $app)
                                    <form method="POST" action="{{ route('assessments.applicability.update', [$assessment, $app]) }}" style="display: inline;">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="is_active" value="{{ $app->is_active ? '0' : '1' }}">
                                        <button type="submit" class="btn btn-secondary btn-sm">
                                            {{ $app->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                                No class subjects mapped yet. Configure applicability using the form on the right.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Applicability Form -->
    @can('create', App\Models\AssessmentApplicability::class)
    <div class="card">
        <div class="card-header">
            <h3>Add Subject Applicability</h3>
        </div>
        <div class="card-body">
            @if($availableClassSubjects->isEmpty())
                <p style="color: var(--color-text-muted); font-size: var(--font-size-sm);">
                    All active class subjects for this academic year have already been configured for this assessment.
                </p>
            @else
                <form method="POST" action="{{ route('assessments.applicability.store', $assessment) }}">
                    @csrf

                    <div class="form-group">
                        <label for="class_subject_id" class="form-label">Select Class Subject <span style="color: var(--color-danger);">*</span></label>
                        <select name="class_subject_id" id="class_subject_id" class="form-control" required>
                            @foreach($availableClassSubjects as $cs)
                                <option value="{{ $cs->id }}">
                                    {{ $cs->schoolClass?->name }} ({{ $cs->section?->name ?? 'All' }}) - {{ $cs->subject?->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="maximum_marks" class="form-label">Maximum Marks <span style="color: var(--color-danger);">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="maximum_marks" id="maximum_marks" class="form-control @error('maximum_marks') is-invalid @enderror" placeholder="e.g. 20, 25.5, 50, 100" value="{{ old('maximum_marks', '100.00') }}" required>
                        <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Subject-specific max marks (supports decimals, not capped at 100).</small>
                        @error('maximum_marks')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                        <label for="is_active" style="font-size: var(--font-size-sm); cursor: pointer;">Active</label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                        Apply to Subject
                    </button>
                </form>
            @endif
        </div>
    </div>
    @endcan
</div>
@endsection
