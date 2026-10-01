@extends('layouts.app')

@section('title', 'Assessments - School Examination & Report Card Management System')
@section('page_title', 'Assessments')

@section('content')

{{-- Filter Bar: Assessment Type, Term, and Status --}}
{{-- Note: academic_year_id is preserved internally through the existing architecture
     and is not exposed as a user-facing filter per project requirements. --}}
<div class="card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem;">
    <form method="GET" action="{{ route('assessments.index') }}" style="display: flex; gap: 1.25rem; align-items: flex-end; flex-wrap: wrap;">

        {{-- Assessment Type Filter --}}
        <div style="min-width: 180px;">
            <label for="filter_assessment_type_id" style="font-size: var(--font-size-xs, 0.75rem); font-weight: 600; color: var(--color-text-muted, #64748b); display: block; margin-bottom: 0.25rem;">
                Assessment Type
            </label>
            <select name="assessment_type_id" id="filter_assessment_type_id" class="form-control" onchange="this.form.submit()">
                <option value="">All Types</option>
                @foreach($assessmentTypes as $type)
                    <option value="{{ $type->id }}" {{ $filterTypeId == $type->id ? 'selected' : '' }}>
                        {{ $type->name }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Term Filter --}}
        {{-- Terms are scoped to the resolved academic year.
             NULL term_id means the assessment is not assigned to any specific term
             (used for cross-term or final assessments). --}}
        <div style="min-width: 180px;">
            <label for="filter_term_id" style="font-size: var(--font-size-xs, 0.75rem); font-weight: 600; color: var(--color-text-muted, #64748b); display: block; margin-bottom: 0.25rem;">
                Term
            </label>
            <select name="term_id" id="filter_term_id" class="form-control" onchange="this.form.submit()">
                <option value="">All Terms</option>
                <option value="none" {{ $filterTermId === 'none' ? 'selected' : '' }}>None / Cross-Term / Final</option>
                @foreach($availableTerms as $t)
                    <option value="{{ $t->id }}" {{ $filterTermId == $t->id ? 'selected' : '' }}>
                        {{ $t->name }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Status Filter --}}
        <div style="min-width: 140px;">
            <label for="filter_status" style="font-size: var(--font-size-xs, 0.75rem); font-weight: 600; color: var(--color-text-muted, #64748b); display: block; margin-bottom: 0.25rem;">
                Status
            </label>
            <select name="status" id="filter_status" class="form-control" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="active" {{ $filterStatus === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ $filterStatus === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>

        {{-- Reset Filters --}}
        <div>
            <a href="{{ route('assessments.index') }}" class="btn btn-secondary btn-sm" style="padding: 0.45rem 0.85rem;">
                Reset Filters
            </a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h3 style="margin: 0;">Registered Assessments</h3>
        @can('create', App\Models\Assessment::class)
            <button type="button" class="btn btn-primary" id="btn-add-assessment" onclick="openModal('create-assessment-modal')">
                + Add Assessment
            </button>
        @endcan
    </div>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Assessment Name</th>
                            <th>Type</th>
                            <th>Term</th>
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
                                <td>
                                    <a href="{{ route('assessments.applicability.index', $assessment) }}" class="btn btn-secondary btn-sm">
                                        {{ $assessment->applicabilities_count }} Classes (Configure)
                                    </a>
                                </td>
                                <td>
                                    <span class="badge {{ $assessment->status->value === 'active' ? 'badge-success' : 'badge-secondary' }}">
                                        {{ ucfirst($assessment->status->value) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        @can('update', $assessment)
                                            <button type="button" class="btn btn-outline btn-sm" onclick="openModal('edit-assessment-modal-{{ $assessment->id }}')">
                                                Edit
                                            </button>
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

                                            @can('delete', $assessment)
                                                <button type="button" class="btn btn-danger btn-sm" onclick="openModal('delete-assessment-modal-{{ $assessment->id }}')">
                                                    Remove
                                                </button>
                                            @endcan

                                            @can('delete', $assessment)
                                                <div id="delete-assessment-modal-{{ $assessment->id }}" class="modal-backdrop" style="display: none;">
                                                    <div class="modal-dialog">
                                                        <div class="modal-header">
                                                            <h3 class="modal-title">Remove Assessment</h3>
                                                            <button type="button" class="modal-close" aria-label="Close" onclick="closeModal('delete-assessment-modal-{{ $assessment->id }}')">&times;</button>
                                                        </div>
                                                        <form method="POST" action="{{ route('assessments.destroy', $assessment) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to permanently remove assessment <strong>{{ $assessment->name }}</strong>?</p>
                                                                <div class="alert alert-warning" style="margin-top: 1rem; font-size: 0.875rem;">
                                                                    <strong>Warning:</strong> This operation cannot be undone. Assessments with subject applicabilities, report selections, or generated report cards cannot be removed and must be deactivated instead.
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" onclick="closeModal('delete-assessment-modal-{{ $assessment->id }}')">Cancel</button>
                                                                <button type="submit" class="btn btn-danger">Confirm Remove</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            @endcan

                                            {{-- Edit Assessment Modal --}}
                                            {{-- Assessment Date field is intentionally omitted.
                                                 The service preserves the existing stored date when
                                                 assessment_date is absent from the submitted payload. --}}
                                            <div id="edit-assessment-modal-{{ $assessment->id }}" class="modal-backdrop">
                                                <div class="modal-dialog">
                                                    <div class="modal-header">
                                                        <h3>Edit Assessment</h3>
                                                        <button type="button" class="modal-close" aria-label="Close" onclick="closeModal('edit-assessment-modal-{{ $assessment->id }}')">&times;</button>
                                                    </div>
                                                    <form method="POST" action="{{ route('assessments.update', $assessment) }}">
                                                        @csrf
                                                        @method('PUT')
                                                        <div class="modal-body">
                                                            <div class="form-group">
                                                                <label for="edit_assessment_name_{{ $assessment->id }}" class="form-label">Assessment Name <span style="color: var(--color-danger);">*</span></label>
                                                                <input type="text" name="name" id="edit_assessment_name_{{ $assessment->id }}" class="form-control" value="{{ $assessment->name }}" required maxlength="100">
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="edit_assessment_type_{{ $assessment->id }}" class="form-label">Assessment Type <span style="color: var(--color-danger);">*</span></label>
                                                                <select name="assessment_type_id" id="edit_assessment_type_{{ $assessment->id }}" class="form-control" required>
                                                                    @foreach($assessmentTypes as $type)
                                                                        <option value="{{ $type->id }}" {{ $assessment->assessment_type_id == $type->id ? 'selected' : '' }}>
                                                                            {{ $type->name }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="edit_assessment_term_{{ $assessment->id }}" class="form-label">Term (Optional)</label>
                                                                <select name="term_id" id="edit_assessment_term_{{ $assessment->id }}" class="form-control">
                                                                    <option value="">None / Cross-Term / Final</option>
                                                                    @foreach($availableTerms as $t)
                                                                        <option value="{{ $t->id }}" {{ $assessment->term_id == $t->id ? 'selected' : '' }}>
                                                                            {{ $t->name }} (Seq: {{ $t->sequence_no }})
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                                                                <input type="checkbox" name="status" id="edit_assessment_status_{{ $assessment->id }}" value="active" {{ $assessment->status->value === 'active' ? 'checked' : '' }}>
                                                                <label for="edit_assessment_status_{{ $assessment->id }}" style="cursor: pointer;">Active</label>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" onclick="closeModal('edit-assessment-modal-{{ $assessment->id }}')">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                                    No assessments found. Try adjusting your filters, or create an assessment using the form.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @can('create', App\Models\Assessment::class)
    <div id="create-assessment-modal" class="modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="create-assessment-modal-title" onclick="if (event.target === this) closeModal('create-assessment-modal')">
        <div class="modal-dialog modal-dialog--md">
            <div class="modal-header">
                <h3 id="create-assessment-modal-title">Add New Assessment</h3>
                <button type="button" class="modal-close" aria-label="Close" onclick="closeModal('create-assessment-modal')">&times;</button>
            </div>
            <form method="POST" action="{{ route('assessments.store') }}">
                @csrf
                <input type="hidden" name="_form_context" value="create_assessment">
                <input type="hidden" name="academic_year_id" value="{{ $selectedYearId }}">

                <div class="modal-body">
                    <div class="form-group">
                        <label for="create_assessment_name" class="form-label">Assessment Name <span style="color: var(--color-danger);">*</span></label>
                        <input type="text" name="name" id="create_assessment_name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Unit Test 1, Term Exam, Mid-Term" value="{{ old('name') }}" required maxlength="100">
                        <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Flexible naming; max marks are configured per subject.</small>
                        @error('name')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="create_assessment_type_id" class="form-label">Assessment Type <span style="color: var(--color-danger);">*</span></label>
                        <select name="assessment_type_id" id="create_assessment_type_id" class="form-control" required>
                            @foreach($assessmentTypes as $type)
                                <option value="{{ $type->id }}" {{ old('assessment_type_id') == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="create_term_id" class="form-label">Term (Optional)</label>
                        <select name="term_id" id="create_term_id" class="form-control">
                            <option value="">None / Cross-Term / Final</option>
                            @foreach($availableTerms as $t)
                                <option value="{{ $t->id }}" {{ old('term_id') == $t->id ? 'selected' : '' }}>{{ $t->name }} (Seq: {{ $t->sequence_no }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="status" id="create_status" value="active" {{ old('status', 'active') === 'active' ? 'checked' : '' }}>
                        <label for="create_status" style="font-size: var(--font-size-sm); cursor: pointer;">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('create-assessment-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Assessment</button>
                </div>
            </form>
        </div>
    </div>

    @if($errors->any() && old('_form_context') === 'create_assessment')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                openModal('create-assessment-modal');
            });
        </script>
    @endif
    @endcan
@endsection
