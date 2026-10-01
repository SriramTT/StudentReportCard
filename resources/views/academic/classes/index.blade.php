@extends('layouts.app')

@section('title', 'Classes - School Examination & Report Card Management System')
@section('page_title', 'Class Catalog')

@section('content')
<!-- Filter Bar -->
<div class="filter-bar" style="margin-bottom: 1.25rem;">
    <form method="GET" action="{{ route('classes.index') }}" id="classes_filter_form" style="display: flex; gap: 0.85rem; align-items: center; flex-wrap: wrap;">
        <label for="filter_status" style="font-size: var(--font-size-sm); font-weight: 600;">Status:</label>
        <select name="status" id="filter_status" class="form-control" style="width: auto; min-width: 140px;" onchange="this.form.submit();">
            <option value="all" {{ ($selectedStatus ?? 'all') === 'all' ? 'selected' : '' }}>All Classes</option>
            <option value="active" {{ ($selectedStatus ?? '') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ ($selectedStatus ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>

        <a href="{{ route('classes.index') }}" class="btn btn-secondary btn-sm" id="btn_reset_filters" style="padding: 0.45rem 0.85rem; text-decoration: none;">
            Reset
        </a>
    </form>
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h3 style="margin: 0;">Registered Classes</h3>
        @can('create', App\Models\SchoolClass::class)
            <button type="button" class="btn btn-primary" id="btn-create-class" onclick="openModal('create-class-modal')">
                + Add Class
            </button>
        @endcan
    </div>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Class Name</th>
                            <th>Sections</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($classes as $class)
                            <tr>
                                <td><strong>{{ $class->name }}</strong></td>
                                <td>{{ $class->sections_count }} configured</td>
                                <td>
                                    <span class="badge {{ $class->is_active ? 'badge-success' : 'badge-secondary' }}">
                                        {{ $class->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    @can('update', $class)
                                        <div class="row-actions">
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="openModal('edit-class-modal-{{ $class->id }}')">
                                                Edit
                                            </button>
                                            <form method="POST" action="{{ route('classes.update', $class) }}" style="display: inline;">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="name" value="{{ $class->name }}">
                                                <input type="hidden" name="is_active" value="{{ $class->is_active ? '0' : '1' }}">
                                                <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Are you sure you want to {{ $class->is_active ? 'deactivate' : 'activate' }} this class?');">
                                                    {{ $class->is_active ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>

                                            @can('delete', $class)
                                                <button type="button" class="btn btn-danger btn-sm" onclick="openModal('delete-class-modal-{{ $class->id }}')">
                                                    Remove
                                                </button>
                                            @endcan
                                        </div>

                                        @can('delete', $class)
                                            <div id="delete-class-modal-{{ $class->id }}" class="modal-backdrop" style="display: none;">
                                                <div class="modal-dialog">
                                                    <div class="modal-header">
                                                        <h3 class="modal-title">Remove Class</h3>
                                                        <button type="button" class="modal-close" onclick="closeModal('delete-class-modal-{{ $class->id }}')">&times;</button>
                                                    </div>
                                                    <form method="POST" action="{{ route('classes.destroy', $class) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to permanently remove class <strong>{{ $class->name }}</strong>?</p>
                                                            <div class="alert alert-warning" style="margin-top: 1rem; font-size: 0.875rem;">
                                                                <strong>Warning:</strong> This operation cannot be undone. Classes with associated sections, student academic records, subjects, or calculation settings cannot be removed and must be deactivated instead.
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" onclick="closeModal('delete-class-modal-{{ $class->id }}')">Cancel</button>
                                                            <button type="submit" class="btn btn-danger">Confirm Remove</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        @endcan

                                        <div id="edit-class-modal-{{ $class->id }}" class="modal-backdrop">
                                            <div class="modal-dialog">
                                                <div class="modal-header">
                                                    <h3>Edit Class</h3>
                                                    <button type="button" class="modal-close" onclick="closeModal('edit-class-modal-{{ $class->id }}')">&times;</button>
                                                </div>
                                                <form method="POST" action="{{ route('classes.update', $class) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="modal-body">
                                                        <div class="form-group">
                                                            <label class="form-label">Class Name <span style="color: var(--color-danger);">*</span></label>
                                                            <input type="text" name="name" class="form-control" value="{{ old('name', $class->name) }}" required maxlength="50">
                                                        </div>
                                                        <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                                                            <input type="checkbox" name="is_active" id="is_active_{{ $class->id }}" value="1" {{ old('is_active', $class->is_active) ? 'checked' : '' }}>
                                                            <label for="is_active_{{ $class->id }}" style="font-size: var(--font-size-sm); cursor: pointer;">Active</label>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" onclick="closeModal('edit-class-modal-{{ $class->id }}')">Cancel</button>
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
                                <td colspan="4" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                                    No classes found. Create classes using the form.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create Class Modal -->
    @can('create', App\Models\SchoolClass::class)
    <div id="create-class-modal" class="modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="create-class-modal-title" onclick="if (event.target === this) closeModal('create-class-modal')">
        <div class="modal-dialog modal-dialog--md">
            <div class="modal-header">
                <h3 class="modal-title" id="create-class-modal-title">Add New Class</h3>
                <button type="button" class="modal-close" aria-label="Close" onclick="closeModal('create-class-modal')">&times;</button>
            </div>
            <form method="POST" action="{{ route('classes.store') }}">
                @csrf
                <input type="hidden" name="_form_context" value="create_class">

                <div class="modal-body">
                    <div class="form-group">
                        <label for="create_class_name" class="form-label">Class Name <span style="color: var(--color-danger);">*</span></label>
                        <input type="text" name="name" id="create_class_name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Class 8, Grade 9" value="{{ old('name') }}" required maxlength="50">
                        @error('name')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="is_active" id="create_is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                        <label for="create_is_active" style="font-size: var(--font-size-sm); cursor: pointer;">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('create-class-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Class</button>
                </div>
            </form>
        </div>
    </div>

    @if($errors->any() && old('_form_context') === 'create_class')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                openModal('create-class-modal');
            });
        </script>
    @endif
    @endcan
@endsection
