@extends('layouts.app')

@section('title', 'Sections - School Examination & Report Card Management System')
@section('page_title', 'Class Sections')

@section('content')
<!-- Filter Bar -->
<div class="filter-bar" style="margin-bottom: 1.25rem;">
    <form method="GET" action="{{ route('sections.index') }}" id="sections_filter_form" style="display: flex; gap: 0.85rem; align-items: center; flex-wrap: wrap;">
        <label for="filter_class_id" style="font-size: var(--font-size-sm); font-weight: 600;">Class:</label>
        <select name="class_id" id="filter_class_id" class="form-control" style="width: auto; min-width: 140px;" onchange="const sec = document.getElementById('filter_section_name'); if (sec) sec.value = ''; this.form.submit();">
            <option value="">All Classes</option>
            @foreach($classes as $c)
                <option value="{{ $c->id }}" {{ (string)$selectedClassId === (string)$c->id ? 'selected' : '' }}>
                    {{ $c->name }}
                </option>
            @endforeach
        </select>

        <label for="filter_section_name" style="font-size: var(--font-size-sm); font-weight: 600;">Section:</label>
        <select name="section_name" id="filter_section_name" class="form-control" style="width: auto; min-width: 140px;" onchange="this.form.submit();">
            <option value="">All Sections</option>
            @foreach($availableSectionNames as $secName)
                <option value="{{ $secName }}" {{ $selectedSectionName === $secName ? 'selected' : '' }}>
                    {{ $secName }}
                </option>
            @endforeach
        </select>

        <a href="{{ route('sections.index') }}" class="btn btn-secondary btn-sm" id="btn_reset_filters" style="padding: 0.45rem 0.85rem; text-decoration: none;">
            Reset
        </a>
    </form>
</div>

@if($activeYearError)
    <div class="alert alert-danger" style="margin-bottom: 1.25rem;">
        <strong>Academic Year Notice:</strong> {{ $activeYearError }}
    </div>
@endif

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h3 style="margin: 0;">Registered Sections</h3>
        @can('create', App\Models\Section::class)
            <button type="button" class="btn btn-primary" id="btn-create-section" onclick="openModal('create-section-modal')" {{ $activeYearError ? 'disabled' : '' }}>
                + Add Class
            </button>
        @endcan
    </div>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Sections</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($groupedSections as $group)
                            <tr>
                                <td><strong>{{ $group->class_name }}</strong></td>
                                <td>
                                    <div class="section-chips-container">
                                        @foreach($group->sections as $sec)
                                            <span class="section-chip {{ $sec->is_active ? '' : 'section-chip--inactive' }}" id="section-chip-{{ $sec->id }}">
                                                {{ $sec->name }}
                                                @if(!$sec->is_active)
                                                    <span class="section-chip-status">(Inactive)</span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td>
                                    @if($group->status === 'active')
                                        <span class="badge badge-success">Active</span>
                                    @elseif($group->status === 'inactive')
                                        <span class="badge badge-secondary">Inactive</span>
                                    @else
                                        <span class="badge badge-warning" title="{{ $group->active_count }} Active, {{ $group->inactive_count }} Inactive">
                                            Mixed ({{ $group->active_count }} Active, {{ $group->inactive_count }} Inactive)
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="openModal('manage-sections-modal-{{ $group->class_id }}')" title="Edit sections in {{ $group->class_name }}">
                                            Edit
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="openModal('manage-sections-modal-{{ $group->class_id }}')" title="Toggle active status for sections in {{ $group->class_name }}">
                                            {{ $group->status === 'inactive' ? 'Activate' : 'Deactivate' }}
                                        </button>
                                        @can('delete', App\Models\Section::class)
                                            <button type="button" class="btn btn-danger btn-sm" onclick="openModal('manage-sections-modal-{{ $group->class_id }}')" title="Manage removal for sections in {{ $group->class_name }}">
                                                Remove
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                                    No sections found for the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Section-level Management Modals for each Class Group --}}
        @foreach($groupedSections as $group)
            <div id="manage-sections-modal-{{ $group->class_id }}" class="modal-backdrop" style="display: none;">
                <div class="modal-dialog" style="max-width: 680px; width: 95%;">
                    <div class="modal-header">
                        <h3 class="modal-title">Manage Sections — {{ $group->class_name }}</h3>
                        <button type="button" class="modal-close" onclick="closeModal('manage-sections-modal-{{ $group->class_id }}')">&times;</button>
                    </div>
                    <div class="modal-body" style="padding: 1.25rem;">
                        <p style="margin-bottom: 1rem; font-size: var(--font-size-sm); color: var(--color-text-muted);">
                            Each section is configured independently. You can rename sections, toggle active status, or remove unreferenced sections below.
                        </p>
                        <div class="data-table-wrapper">
                            <table class="data-table" style="margin-bottom: 0;">
                                <thead>
                                    <tr>
                                        <th>Section Name</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($group->sections as $section)
                                        @php
                                            $isReferenced = ($section->academic_records_count > 0 || $section->class_subjects_count > 0 || $section->teacher_assignments_count > 0);
                                        @endphp
                                        <tr>
                                            <td>
                                                @can('update', $section)
                                                    <form method="POST" action="{{ route('sections.update', $section) }}?class_id={{ $selectedClassId }}&section_name={{ $selectedSectionName }}" style="display: flex; gap: 0.5rem; align-items: center;">
                                                        @csrf
                                                        @method('PUT')
                                                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $section->name }}" required maxlength="50" style="max-width: 120px;" aria-label="Section name for {{ $section->name }}">
                                                        <input type="hidden" name="is_active" value="{{ $section->is_active ? '1' : '0' }}">
                                                        <button type="submit" class="btn btn-secondary btn-sm" title="Save section name">Save</button>
                                                    </form>
                                                @else
                                                    <strong>{{ $section->name }}</strong>
                                                @endcan
                                            </td>
                                            <td>
                                                <span class="badge {{ $section->is_active ? 'badge-success' : 'badge-secondary' }}">
                                                    {{ $section->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="row-actions">
                                                    @can('update', $section)
                                                        <form method="POST" action="{{ route('sections.update', $section) }}?class_id={{ $selectedClassId }}&section_name={{ $selectedSectionName }}" style="display: inline;">
                                                            @csrf
                                                            @method('PUT')
                                                            <input type="hidden" name="name" value="{{ $section->name }}">
                                                            <input type="hidden" name="is_active" value="{{ $section->is_active ? '0' : '1' }}">
                                                            <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Are you sure you want to {{ $section->is_active ? 'deactivate' : 'activate' }} section {{ $section->name }}?');">
                                                                {{ $section->is_active ? 'Deactivate' : 'Activate' }}
                                                            </button>
                                                        </form>
                                                    @endcan

                                                    @can('delete', $section)
                                                        @if($isReferenced)
                                                            <button type="button" class="btn btn-danger btn-sm" disabled style="opacity: 0.5; cursor: not-allowed;" title="Cannot remove: section has student records, class subjects, or teacher assignments. Deactivate instead.">
                                                                Protected
                                                            </button>
                                                        @else
                                                            <button type="button" class="btn btn-danger btn-sm" onclick="openModal('delete-section-modal-{{ $section->id }}')">
                                                                Remove
                                                            </button>
                                                        @endif
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('manage-sections-modal-{{ $group->class_id }}')">Close</button>
                    </div>
                </div>
            </div>

            {{-- Deletion Confirmation Modals for Unreferenced Sections in this Group --}}
            @foreach($group->sections as $section)
                @can('delete', $section)
                    @if(!$section->academic_records_count && !$section->class_subjects_count && !$section->teacher_assignments_count)
                        <div id="delete-section-modal-{{ $section->id }}" class="modal-backdrop" style="display: none; z-index: 1050;">
                            <div class="modal-dialog">
                                <div class="modal-header">
                                    <h3 class="modal-title">Remove Section</h3>
                                    <button type="button" class="modal-close" onclick="closeModal('delete-section-modal-{{ $section->id }}')">&times;</button>
                                </div>
                                <form method="POST" action="{{ route('sections.destroy', $section) }}?class_id={{ $selectedClassId }}&section_name={{ $selectedSectionName }}">
                                    @csrf
                                    @method('DELETE')
                                    <div class="modal-body">
                                        <p>Are you sure you want to permanently remove section <strong>{{ $section->name }}</strong> of <strong>{{ $group->class_name }}</strong>?</p>
                                        <div class="alert alert-warning" style="margin-top: 1rem; font-size: 0.875rem;">
                                            <strong>Warning:</strong> This operation cannot be undone. Sections with enrolled students, subject allocations, or teacher assignments cannot be removed and must be deactivated instead.
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" onclick="closeModal('delete-section-modal-{{ $section->id }}')">Cancel</button>
                                        <button type="submit" class="btn btn-danger">Confirm Remove</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif
                @endcan
            @endforeach
        @endforeach
    <!-- Create Class & Sections Modal -->
    @can('create', App\Models\Section::class)
    <div id="create-section-modal" class="modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="create-section-modal-title" onclick="if (event.target === this) closeModal('create-section-modal')">
        <div class="modal-dialog modal-dialog--md">
            <div class="modal-header">
                <h3 class="modal-title" id="create-section-modal-title">Add Class & Sections</h3>
                <button type="button" class="modal-close" aria-label="Close" onclick="closeModal('create-section-modal')">&times;</button>
            </div>
            @if($activeYearError)
                <div class="modal-body">
                    <div class="alert alert-warning" style="font-size: var(--font-size-sm); margin-bottom: 0;">
                        {{ $activeYearError }} Class and section creation is disabled.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('create-section-modal')">Close</button>
                </div>
            @else
                <form method="POST" action="{{ route('sections.store') }}" id="create-class-sections-form">
                    @csrf
                    <input type="hidden" name="_form_context" value="create_section">

                    <div class="modal-body">
                        <div class="form-group">
                            <label for="create_academic_year_id" class="form-label">Academic Year <span style="color: var(--color-danger);">*</span></label>
                            <select name="academic_year_id" id="create_academic_year_id" class="form-control" required>
                                @foreach($academicYears as $year)
                                    <option value="{{ $year->id }}" {{ ($activeYear && $activeYear->id == $year->id) ? 'selected' : '' }}>
                                        {{ $year->name }} {{ $year->is_current ? '(Current)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="create_class_name" class="form-label">Class <span style="color: var(--color-danger);">*</span></label>
                            <input type="text" name="class_name" id="create_class_name" class="form-control @error('class_name') is-invalid @enderror" list="existing_classes_list" placeholder="e.g. Class 8" value="{{ old('class_name') }}" required maxlength="50" autocomplete="off">
                            <datalist id="existing_classes_list">
                                @foreach($classes as $c)
                                    <option value="{{ $c->name }}"></option>
                                @endforeach
                            </datalist>
                            <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Select an existing class or type a new class name.</small>
                            @error('class_name')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Section Name(s) <span style="color: var(--color-danger);">*</span></label>
                            <div id="section_rows_container">
                                @php
                                    $oldSections = old('section_names', ['A']);
                                    if (!is_array($oldSections) || empty($oldSections)) {
                                        $oldSections = ['A'];
                                    }
                                @endphp
                                @foreach($oldSections as $index => $secVal)
                                    <div class="section-input-row" style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem;">
                                        <input type="text" name="section_names[]" class="form-control" placeholder="e.g. A" value="{{ $secVal }}" required maxlength="50">
                                        <button type="button" class="btn btn-secondary btn-sm remove-section-row-btn" onclick="removeSectionRow(this)" title="Delete section" style="color: var(--color-danger); padding: 0.35rem 0.65rem;">
                                            &times;
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" id="btn_add_section_row" onclick="addSectionRow()" style="margin-top: 0.25rem;">
                                + Add Another Section
                            </button>
                            @error('section_names')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem; margin-top: 1rem;">
                            <input type="checkbox" name="is_active" id="create_is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                            <label for="create_is_active" style="font-size: var(--font-size-sm); cursor: pointer; margin-bottom: 0;">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('create-section-modal')">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="btn-submit-create-class">Create</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <script>
    function addSectionRow() {
        const container = document.getElementById('section_rows_container');
        if (!container) return;
        const row = document.createElement('div');
        row.className = 'section-input-row';
        row.style.cssText = 'display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem;';
        row.innerHTML = `
            <input type="text" name="section_names[]" class="form-control" placeholder="e.g. B" required maxlength="50">
            <button type="button" class="btn btn-secondary btn-sm remove-section-row-btn" onclick="removeSectionRow(this)" title="Delete section" style="color: var(--color-danger); padding: 0.35rem 0.65rem;">
                &times;
            </button>
        `;
        container.appendChild(row);
        const input = row.querySelector('input');
        if (input) input.focus();
    }

    function removeSectionRow(btn) {
        const container = document.getElementById('section_rows_container');
        if (!container) return;
        const rows = container.querySelectorAll('.section-input-row');
        if (rows.length > 1) {
            btn.closest('.section-input-row').remove();
        } else {
            const input = rows[0].querySelector('input');
            if (input) input.value = '';
        }
    }
    </script>

    @if($errors->any() && old('_form_context') === 'create_section')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                openModal('create-section-modal');
            });
        </script>
    @endif
    @endcan
@endsection
