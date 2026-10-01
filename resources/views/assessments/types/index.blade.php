@extends('layouts.app')

@section('title', 'Assessment Types - School Examination & Report Card Management System')
@section('page_title', 'Assessment Types')

@section('content')
<div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <h3 style="margin: 0;">Registered Assessment Types</h3>
                @can('create', App\Models\AssessmentType::class)
                    <button type="button" class="btn btn-primary" id="btn-add-assessment-type" onclick="openModal('create-type-modal')">
                        + Add Assessment Type
                    </button>
                @endcan
            </div>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Type Name</th>
                            <th>Assessments</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assessmentTypes as $type)
                            <tr>
                                <td><strong>{{ $type->name }}</strong></td>
                                <td>{{ $type->assessments_count }} created</td>
                                <td>
                                    <span class="badge {{ $type->is_active ? 'badge-success' : 'badge-secondary' }}">
                                        {{ $type->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        @can('update', $type)
                                            <button type="button" class="btn btn-outline btn-sm" onclick="openModal('edit-type-modal-{{ $type->id }}')">
                                                Edit
                                            </button>
                                            <form method="POST" action="{{ route('assessments.types.update', $type) }}" style="display: inline;">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="name" value="{{ $type->name }}">
                                                <input type="hidden" name="is_active" value="{{ $type->is_active ? '0' : '1' }}">
                                                <button type="submit" class="btn btn-secondary btn-sm">
                                                    {{ $type->is_active ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>

                                            @can('delete', $type)
                                                <button type="button" class="btn btn-danger btn-sm" onclick="openModal('delete-type-modal-{{ $type->id }}')">
                                                    Remove
                                                </button>
                                            @endcan

                                            @can('delete', $type)
                                                <div id="delete-type-modal-{{ $type->id }}" class="modal-backdrop" style="display: none;">
                                                    <div class="modal-dialog">
                                                        <div class="modal-header">
                                                            <h3 class="modal-title">Remove Assessment Type</h3>
                                                            <button type="button" class="modal-close" aria-label="Close" onclick="closeModal('delete-type-modal-{{ $type->id }}')">&times;</button>
                                                        </div>
                                                        <form method="POST" action="{{ route('assessments.types.destroy', $type) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to permanently remove assessment type <strong>{{ $type->name }}</strong>?</p>
                                                                <div class="alert alert-warning" style="margin-top: 1rem; font-size: 0.875rem;">
                                                                    <strong>Warning:</strong> This operation cannot be undone. Assessment types referenced by existing assessments cannot be removed and must be deactivated instead.
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" onclick="closeModal('delete-type-modal-{{ $type->id }}')">Cancel</button>
                                                                <button type="submit" class="btn btn-danger">Confirm Remove</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            @endcan

                                            <div id="edit-type-modal-{{ $type->id }}" class="modal-backdrop">
                                                <div class="modal-dialog">
                                                    <div class="modal-header">
                                                        <h3>Edit Assessment Type</h3>
                                                        <button type="button" class="modal-close" aria-label="Close" onclick="closeModal('edit-type-modal-{{ $type->id }}')">&times;</button>
                                                    </div>
                                                    <form method="POST" action="{{ route('assessments.types.update', $type) }}">
                                                        @csrf
                                                        @method('PUT')
                                                        <div class="modal-body">
                                                            <div class="form-group">
                                                                <label for="edit_type_name_{{ $type->id }}" class="form-label">Type Name <span style="color: var(--color-danger);">*</span></label>
                                                                <input type="text" name="name" id="edit_type_name_{{ $type->id }}" class="form-control" value="{{ $type->name }}" required maxlength="50">
                                                            </div>
                                                            <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                                                                <input type="checkbox" name="is_active" id="edit_type_active_{{ $type->id }}" value="1" {{ $type->is_active ? 'checked' : '' }}>
                                                                <label for="edit_type_active_{{ $type->id }}" style="cursor: pointer;">Active</label>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" onclick="closeModal('edit-type-modal-{{ $type->id }}')">Cancel</button>
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
                                <td colspan="4" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                                    No assessment types found. Create types using the form.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @can('create', App\Models\AssessmentType::class)
    {{-- Modal for Add Assessment Type (launched by the header button) --}}
    <div id="create-type-modal" class="modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="create-type-modal-title" onclick="if (event.target === this) closeModal('create-type-modal')">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 id="create-type-modal-title">Add Assessment Type</h3>
                <button type="button" class="modal-close" aria-label="Close" onclick="closeModal('create-type-modal')">&times;</button>
            </div>
            <form method="POST" action="{{ route('assessments.types.store') }}">
                @csrf
                <input type="hidden" name="_form_context" value="create_assessment_type">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="modal_type_name" class="form-label">Type Name <span style="color: var(--color-danger);">*</span></label>
                        <input type="text" name="name" id="modal_type_name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Unit Test, Term Exam, Class Test" value="{{ old('name') }}" required maxlength="50">
                        <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Database-driven category of evaluations.</small>
                        @error('name')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="is_active" id="modal_type_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                        <label for="modal_type_active" style="font-size: var(--font-size-sm); cursor: pointer;">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('create-type-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Assessment Type</button>
                </div>
            </form>
        </div>
    </div>

    @if($errors->any() && old('_form_context') === 'create_assessment_type')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                openModal('create-type-modal');
            });
        </script>
    @endif
    @endcan
@endsection
