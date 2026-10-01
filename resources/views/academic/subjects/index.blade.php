@extends('layouts.app')

@section('title', 'Subjects - School Examination & Report Card Management System')
@section('page_title', 'Subject Master Catalog')

@section('content')
<!-- Listing Filter Bar -->
<div class="filter-bar" style="margin-bottom: 1.25rem;">
    <form method="GET" action="{{ route('subjects.index') }}" id="subject_filter_form" style="display: flex; gap: 0.85rem; align-items: center; flex-wrap: wrap;">
        <label for="filter_status" style="font-size: var(--font-size-sm); font-weight: 600;">Status:</label>
        <select name="status" id="filter_status" class="form-control" style="width: auto; min-width: 140px;" onchange="this.form.submit()">
            <option value="all" {{ ($selectedStatus ?? 'all') === 'all' ? 'selected' : '' }}>All Statuses</option>
            <option value="active" {{ ($selectedStatus ?? '') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ ($selectedStatus ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>

        <a href="{{ route('subjects.index') }}" class="btn btn-secondary btn-sm" id="btn_reset_filters" style="padding: 0.45rem 0.85rem; text-decoration: none;">
            Reset
        </a>
    </form>
</div>

<div class="card subject-page-sticky-card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h3 style="margin: 0;">Registered Subjects</h3>
        @can('create', App\Models\Subject::class)
            <button type="button" class="btn btn-primary" id="btn-create-subject" onclick="openModal('create-subject-modal')">
                + Add Subject
            </button>
        @endcan
    </div>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Subject Name</th>
                        <th>Code</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subjects as $subject)
                        <tr>
                            <td><strong>{{ $subject->name }}</strong></td>
                            <td><code>{{ $subject->code }}</code></td>
                            <td>
                                <span class="badge {{ $subject->category->value === 'main' ? 'badge-primary' : 'badge-warning' }}">
                                    {{ ucfirst($subject->category->value) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $subject->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $subject->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                @can('update', $subject)
                                    <div class="row-actions">
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="openModal('edit-subject-modal-{{ $subject->id }}')">
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('subjects.update', $subject) }}" style="display: inline;">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="name" value="{{ $subject->name }}">
                                            <input type="hidden" name="code" value="{{ $subject->code }}">
                                            <input type="hidden" name="category" value="{{ $subject->category->value }}">
                                            <input type="hidden" name="is_active" value="{{ $subject->is_active ? '0' : '1' }}">
                                            <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Are you sure you want to {{ $subject->is_active ? 'deactivate' : 'activate' }} this subject?');">
                                                {{ $subject->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>

                                        @can('delete', $subject)
                                            <button type="button" class="btn btn-danger btn-sm" onclick="openModal('delete-subject-modal-{{ $subject->id }}')">
                                                Remove
                                            </button>
                                        @endcan
                                    </div>

                                    @can('delete', $subject)
                                        <div id="delete-subject-modal-{{ $subject->id }}" class="modal-backdrop" style="display: none;">
                                            <div class="modal-dialog">
                                                <div class="modal-header">
                                                    <h3 class="modal-title">Remove Subject</h3>
                                                    <button type="button" class="modal-close" onclick="closeModal('delete-subject-modal-{{ $subject->id }}')">&times;</button>
                                                </div>
                                                <form method="POST" action="{{ route('subjects.destroy', $subject) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to permanently remove subject <strong>{{ $subject->name }} ({{ $subject->code }})</strong>?</p>
                                                        <div class="alert alert-warning" style="margin-top: 1rem; font-size: 0.875rem;">
                                                            <strong>Warning:</strong> This operation cannot be undone. Subjects mapped to classes or assigned to teachers cannot be removed and must be deactivated instead.
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" onclick="closeModal('delete-subject-modal-{{ $subject->id }}')">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Confirm Remove</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @endcan

                                    <div id="edit-subject-modal-{{ $subject->id }}" class="modal-backdrop">
                                        <div class="modal-dialog">
                                            <div class="modal-header">
                                                <h3>Edit Subject</h3>
                                                <button type="button" class="modal-close" onclick="closeModal('edit-subject-modal-{{ $subject->id }}')">&times;</button>
                                            </div>
                                            <form method="POST" action="{{ route('subjects.update', $subject) }}">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-body">
                                                    <div class="form-group">
                                                        <label class="form-label">Subject Name <span style="color: var(--color-danger);">*</span></label>
                                                        <input type="text" name="name" class="form-control" value="{{ old('name', $subject->name) }}" required maxlength="100">
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="form-label">Subject Code <span style="color: var(--color-danger);">*</span></label>
                                                        <input type="text" name="code" class="form-control" value="{{ old('code', $subject->code) }}" required maxlength="20">
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="form-label">Category <span style="color: var(--color-danger);">*</span></label>
                                                        <select name="category" class="form-control" required>
                                                            <option value="main" {{ $subject->category->value === 'main' ? 'selected' : '' }}>Main Subject</option>
                                                            <option value="elective" {{ $subject->category->value === 'elective' ? 'selected' : '' }}>Elective Subject</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                                                        <input type="checkbox" name="is_active" id="is_active_{{ $subject->id }}" value="1" {{ old('is_active', $subject->is_active) ? 'checked' : '' }}>
                                                        <label for="is_active_{{ $subject->id }}" style="font-size: var(--font-size-sm); cursor: pointer;">Active</label>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" onclick="closeModal('edit-subject-modal-{{ $subject->id }}')">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                                No subjects found. Create subjects using the form.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <!-- Create Subject Modal -->
    @can('create', App\Models\Subject::class)
    <div id="create-subject-modal" class="modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="create-subject-modal-title" onclick="if (event.target === this) closeModal('create-subject-modal')">
        <div class="modal-dialog modal-dialog--md">
            <div class="modal-header">
                <h3 class="modal-title" id="create-subject-modal-title">Add New Subject</h3>
                <button type="button" class="modal-close" aria-label="Close" onclick="closeModal('create-subject-modal')">&times;</button>
            </div>
            <form method="POST" action="{{ route('subjects.store') }}">
                @csrf
                <input type="hidden" name="_form_context" value="create_subject">

                <div class="modal-body">
                    <div class="form-group">
                        <label for="create_subject_name" class="form-label">Subject Name <span style="color: var(--color-danger);">*</span></label>
                        <input type="text" name="name" id="create_subject_name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Mathematics, English" value="{{ old('name') }}" required maxlength="100">
                        @error('name')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="create_subject_code" class="form-label">Subject Code <span style="color: var(--color-danger);">*</span></label>
                        <input type="text" name="code" id="create_subject_code" class="form-control @error('code') is-invalid @enderror" placeholder="e.g. MATH101, ENG01" value="{{ old('code') }}" required maxlength="20">
                        @error('code')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="create_subject_category" class="form-label">Category <span style="color: var(--color-danger);">*</span></label>
                        <select name="category" id="create_subject_category" class="form-control" required>
                            <option value="main" {{ old('category') == 'main' ? 'selected' : '' }}>Main Subject</option>
                            <option value="elective" {{ old('category') == 'elective' ? 'selected' : '' }}>Elective Subject</option>
                        </select>
                    </div>

                    <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="is_active" id="create_is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                        <label for="create_is_active" style="font-size: var(--font-size-sm); cursor: pointer;">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('create-subject-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Subject</button>
                </div>
            </form>
        </div>
    </div>

    @if($errors->any() && old('_form_context') === 'create_subject')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                openModal('create-subject-modal');
            });
        </script>
    @endif
    @endcan
@endsection
