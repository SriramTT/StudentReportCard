@extends('layouts.app')

@section('title', 'Student Directory - School Examination & Report Card Management System')
@section('page_title', 'Student Directory')

@section('content')
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h3 style="margin: 0;">Student Search & Directory</h3>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            @can('create', App\Models\Student::class)
                <a href="{{ route('students.create') }}" class="btn btn-primary" id="btn-create-student">
                    + Add New Student
                </a>
            @endcan
            @can('import', App\Models\Student::class)
                <a href="{{ route('students.import.form') }}" class="btn btn-secondary" id="btn-import-students">
                    Import CSV
                </a>
            @endcan
        </div>
    </div>

    <!-- Structured Filters and Search Card -->
    <div style="padding: 1.25rem; background: var(--color-bg-subtle, #f9fafb); border-bottom: 1px solid var(--color-border, #e5e7eb);">
        <form method="GET" action="{{ route('students.index') }}">
            <!-- Top: Full-Width Search -->
            <div class="form-group" style="margin-bottom: 1rem;">
                <label for="search" class="form-label" style="font-weight: 600;">Search Student</label>
                <input type="text" id="search" name="search" class="form-control" placeholder="Search by Admission No or Student Name..." value="{{ $filters['search'] ?? '' }}" style="width: 100%;">
            </div>

            <!-- Middle: Responsive 4-Column Filter Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="academic_year_id" class="form-label">Academic Year</label>
                    <select id="academic_year_id" name="academic_year_id" class="form-control">
                        <option value="">All Academic Years</option>
                        @foreach($academicYears as $year)
                            <option value="{{ $year->id }}" {{ (string)($filters['academic_year_id'] ?? '') === (string)$year->id ? 'selected' : '' }}>
                                {{ $year->name }} {{ $year->is_current ? '(Current)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="class_id" class="form-label">Class</label>
                    <select id="class_id" name="class_id" class="form-control">
                        @if($classes->isEmpty())
                            <option value="">No authorized classes</option>
                        @else
                            <option value="">All Classes</option>
                            @foreach($classes as $c)
                                <option value="{{ $c->id }}" {{ (string)($filters['class_id'] ?? '') === (string)$c->id ? 'selected' : '' }}>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="section_id" class="form-label">Section</label>
                    <select id="section_id" name="section_id" class="form-control">
                        @if($sections->isEmpty())
                            <option value="">No authorized sections</option>
                        @else
                            <option value="">All Sections</option>
                            @foreach($sections as $sec)
                                <option value="{{ $sec->id }}"
                                        data-class-id="{{ $sec->class_id }}"
                                        data-year-id="{{ $sec->academic_year_id }}"
                                        {{ (string)($filters['section_id'] ?? '') === (string)$sec->id ? 'selected' : '' }}>
                                    {{ $sec->name }} ({{ $sec->schoolClass?->name ?? 'Class ' . $sec->class_id }})
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="status" class="form-label">Placement Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="all" {{ in_array(($filters['status'] ?? ''), ['all', ''], true) ? 'selected' : '' }}>All Status</option>
                        <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="internal_transfer" {{ ($filters['status'] ?? '') === 'internal_transfer' ? 'selected' : '' }}>Internal Transfer</option>
                        <option value="transferred_out" {{ ($filters['status'] ?? '') === 'transferred_out' ? 'selected' : '' }}>Transferred Out</option>
                        <option value="withdrawn" {{ ($filters['status'] ?? '') === 'withdrawn' ? 'selected' : '' }}>Withdrawn</option>
                    </select>
                </div>
            </div>

            <!-- Bottom: Action Buttons -->
            <div style="display: flex; gap: 0.75rem; justify-content: flex-end; align-items: center;">
                <a href="{{ route('students.index') }}" class="btn btn-secondary">Reset</a>
                <button type="submit" class="btn btn-primary" id="btn-filter-students">Filter Students</button>
            </div>
        </form>
    </div>

    <!-- Data Table -->
    <div class="data-table-wrapper">
        <table class="data-table" id="students-table">
            <thead>
                <tr>
                    <th>Admission Number</th>
                    <th>Student Name</th>
                    <th>Current Placement</th>
                    <th>Roll No</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($students as $student)
                    @php
                        $latestRecord = $student->getContextualAcademicRecord($filters ?? []);
                    @endphp
                    <tr>
                        <td>
                            <strong style="color: var(--color-primary-600, #2563eb); font-family: monospace; font-size: 1.05em;">
                                {{ $student->admission_number }}
                            </strong>
                        </td>
                        <td>
                            <strong>{{ $student->student_name }}</strong>
                        </td>
                        <td>
                            @if($latestRecord)
                                {{ $latestRecord->schoolClass?->name ?? '—' }} ({{ $latestRecord->section?->name ?? '—' }})
                                <div style="font-size: var(--font-size-xs, 0.75rem); color: var(--color-text-muted, #6b7280);">
                                    {{ $latestRecord->academicYear?->name ?? '' }}
                                </div>
                            @else
                                <span style="color: var(--color-text-muted, #9ca3af);">No placement</span>
                            @endif
                        </td>
                        <td>
                            @if($latestRecord)
                                <span class="badge badge-secondary">{{ $latestRecord->roll_number }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if($latestRecord)
                                @php
                                    $statusClass = match($latestRecord->status->value) {
                                        'active' => 'badge-success',
                                        'internal_transfer' => 'badge-info',
                                        'transferred_out', 'withdrawn' => 'badge-secondary',
                                        default => 'badge-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $statusClass }}">
                                    {{ ucfirst(str_replace('_', ' ', $latestRecord->status->value)) }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <div class="row-actions" style="display: flex; gap: 0.25rem;">
                                @can('view', $student)
                                    <a href="{{ route('students.show', $student) }}" class="btn btn-secondary btn-sm" title="View Profile & History">
                                        View
                                    </a>
                                @endcan
                                @can('update', $student)
                                    <a href="{{ route('students.edit', [$student, 'return_url' => request()->fullUrl()]) }}" class="btn btn-secondary btn-sm" title="Edit Name">
                                        Edit
                                    </a>
                                @endcan
                                @can('transfer', $student)
                                    <a href="{{ route('students.transfer.form', [$student, 'return_url' => request()->fullUrl()]) }}" class="btn btn-secondary btn-sm" title="Internal Transfer">
                                        Transfer
                                    </a>
                                @endcan
                                @can('delete', $student)
                                    <button type="button" class="btn btn-danger btn-sm" onclick="openModal('delete-student-modal-{{ $student->id }}')" title="Remove Unused Student">
                                        Remove
                                    </button>
                                @endcan
                            </div>

                            @can('delete', $student)
                                <div id="delete-student-modal-{{ $student->id }}" class="modal-backdrop" style="display: none;">
                                    <div class="modal-dialog">
                                        <div class="modal-header">
                                            <h3 class="modal-title">Remove Student</h3>
                                            <button type="button" class="modal-close" onclick="closeModal('delete-student-modal-{{ $student->id }}')">&times;</button>
                                        </div>
                                        <form method="POST" action="{{ route('students.destroy', $student) }}">
                                            @csrf
                                            @method('DELETE')
                                            <div class="modal-body">
                                                <p>Are you sure you want to permanently remove student <strong>{{ $student->student_name }}</strong> (Admission No: <code>{{ $student->admission_number }}</code>)?</p>
                                                <div class="alert alert-warning" style="margin-top: 1rem; font-size: 0.875rem;">
                                                    <strong>Warning:</strong> This operation cannot be undone. A student can only be removed if no academic placement records exist. Historical student records must be preserved for reports and academic history.
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" onclick="closeModal('delete-student-modal-{{ $student->id }}')">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Confirm Remove</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2rem; color: var(--color-text-muted, #6b7280);">
                            No students found matching the selected criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($students->hasPages())
        <div style="padding: 1rem; border-top: 1px solid var(--color-border, #e5e7eb);">
            {{ $students->links() }}
        </div>
    @endif
</div>

<script>
/**
 * Filter the Section dropdown based on the currently selected Academic Year and Class.
 * This is a client-side usability aid only — server-side filtering remains authoritative.
 */
function handleDirectoryFilters() {
    const yearSelect  = document.getElementById('academic_year_id');
    const classSelect = document.getElementById('class_id');
    const secSelect   = document.getElementById('section_id');
    if (!yearSelect || !classSelect || !secSelect) return;

    const selectedYearId  = yearSelect.value;
    const selectedClassId = classSelect.value;
    const currentSecVal   = secSelect.value;
    let currentStillVisible = false;

    for (let opt of secSelect.options) {
        if (!opt.value) { continue; } // "All Sections" always visible
        const optClassId = opt.getAttribute('data-class-id');
        const optYearId  = opt.getAttribute('data-year-id');

        const classMatch = !selectedClassId || optClassId === String(selectedClassId);
        const yearMatch  = !selectedYearId  || optYearId  === String(selectedYearId);
        const visible    = classMatch && yearMatch;

        opt.style.display = visible ? '' : 'none';
        opt.disabled = !visible;

        if (visible && opt.value === currentSecVal) {
            currentStillVisible = true;
        }
    }

    // Clear section selection if the previously selected section is no longer visible
    if (!currentStillVisible && (selectedClassId || selectedYearId)) {
        secSelect.value = '';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Wire up Academic Year change
    const yearElem = document.getElementById('academic_year_id');
    if (yearElem) {
        yearElem.addEventListener('change', handleDirectoryFilters);
    }
    // Wire up Class change
    const classElem = document.getElementById('class_id');
    if (classElem) {
        classElem.addEventListener('change', handleDirectoryFilters);
    }
    // Apply on initial page load to restore correct visible state after a filter round-trip
    handleDirectoryFilters();
});
</script>
@endsection
