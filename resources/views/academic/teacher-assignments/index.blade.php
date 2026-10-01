@extends('layouts.app')

@section('title', 'Staff Assignments - School Examination & Report Card Management System')
@section('page_title', 'Staff Assignments')

@section('content')
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h3 style="margin: 0;">Staff Classroom & Subject Assignments</h3>
        @can('create', App\Models\TeacherAssignment::class)
            <button type="button" class="btn btn-primary" id="btn-create-assignment" onclick="openCreateAssignmentModal()">
                + Assign Teacher
            </button>
        @endcan
    </div>

    <!-- Filters Card -->
    <div style="padding: 1.25rem; background: var(--color-bg-subtle, #f9fafb); border-bottom: 1px solid var(--color-border, #e5e7eb);">
        <form method="GET" action="{{ route('teacher_assignments.index') }}">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter_user_id" class="form-label">Staff</label>
                    <select id="filter_user_id" name="user_id" class="form-control">
                        <option value="">All Staffs</option>
                        @foreach($teachers as $t)
                            <option value="{{ $t->id }}" {{ (string) request('user_id') === (string) $t->id ? 'selected' : '' }}>
                                {{ $t->display_name }} ({{ $t->username }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter_assignment_type" class="form-label">Assignment Type</label>
                    <select id="filter_assignment_type" name="assignment_type" class="form-control">
                        <option value="">All Assignment Types</option>
                        <option value="class_teacher" {{ request('assignment_type') === 'class_teacher' ? 'selected' : '' }}>Class Teacher</option>
                        <option value="subject_teacher" {{ request('assignment_type') === 'subject_teacher' ? 'selected' : '' }}>Subject Teacher</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter_class_id" class="form-label">Class</label>
                    <select id="filter_class_id" name="class_id" class="form-control">
                        <option value="">All Classes</option>
                        @foreach($classes as $c)
                            <option value="{{ $c->id }}" {{ (string) request('class_id') === (string) $c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter_section_id" class="form-label">Section</label>
                    <select id="filter_section_id" name="section_id" class="form-control" {{ ($selectedClassId && $filterSections->isNotEmpty()) ? '' : 'disabled' }}>
                        @if(!$selectedClassId)
                            <option value="">All Sections</option>
                        @elseif($filterSections->isEmpty())
                            <option value="">No sections available</option>
                        @else
                            <option value="">All Sections</option>
                            @foreach($filterSections as $s)
                                <option value="{{ $s->id }}" {{ (string) $selectedSectionId === (string) $s->id ? 'selected' : '' }}>
                                    {{ $s->name }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter_status" class="form-label">Status</label>
                    <select id="filter_status" name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary" id="btn-filter-assignments">Filter</button>
                <a href="{{ route('teacher_assignments.index') }}" class="btn btn-secondary" id="btn-reset-assignments">Reset</a>
            </div>
        </form>
    </div>

    <!-- Assignments Table -->
    <div class="data-table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Staff</th>
                    <th>Account Role</th>
                    <th>Class</th>
                    <th>Section</th>
                    <th>Role</th>
                    <th>Subject Scope</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($assignments as $assignment)
                    <tr>
                        <td>
                            <strong>{{ $assignment->user?->display_name }}</strong>
                            <div style="font-size: var(--font-size-xs); color: var(--color-text-muted);">
                                {{ $assignment->user?->username }}
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-secondary">
                                {{ $assignment->user?->role?->name ?? 'Staff' }}
                            </span>
                        </td>
                        <td>{{ $assignment->schoolClass?->name }}</td>
                        <td>{{ $assignment->section?->name }}</td>
                        <td>
                            @if($assignment->assignment_type->value === 'class_teacher')
                                <span class="badge badge-info">Class Teacher</span>
                            @else
                                <span class="badge badge-primary">Subject Teacher</span>
                            @endif
                        </td>
                        <td>
                            @if($assignment->assignment_type->value === 'class_teacher' || $assignment->subject_id === null)
                                <span style="font-style: italic; color: var(--color-text-main);">
                                    All Applicable Subjects (Class Teacher)
                                </span>
                            @else
                                <strong>{{ $assignment->subject?->name }}</strong>
                                <span style="font-size: var(--font-size-xs); color: var(--color-text-muted);">({{ $assignment->subject?->code }})</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $assignment->is_active ? 'badge-success' : 'badge-danger' }}">
                                {{ $assignment->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <div class="row-actions">
                                @can('update', $assignment)
                                    <button type="button" class="btn btn-secondary btn-sm"
                                        onclick="openEditAssignmentModal({{ json_encode([
                                            'id' => $assignment->id,
                                            'user_id' => $assignment->user_id,
                                            'academic_year_id' => $assignment->academic_year_id,
                                            'class_id' => $assignment->class_id,
                                            'section_id' => $assignment->section_id,
                                            'assignment_type' => $assignment->assignment_type->value,
                                            'subject_id' => $assignment->subject_id,
                                            'effective_from' => $assignment->effective_from?->toDateString(),
                                            'effective_to' => $assignment->effective_to?->toDateString(),
                                            'is_active' => $assignment->is_active,
                                        ]) }})">
                                        Edit
                                    </button>
                                @endcan

                                @if($assignment->is_active)
                                    @can('deactivate', $assignment)
                                        <form method="POST" action="{{ route('teacher_assignments.deactivate', $assignment) }}" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-secondary btn-sm"
                                                onclick="return confirm('Are you sure you want to deactivate this assignment?');">
                                                Deactivate
                                            </button>
                                        </form>
                                    @endcan
                                @else
                                    @can('activate', $assignment)
                                        <form method="POST" action="{{ route('teacher_assignments.activate', $assignment) }}" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-secondary btn-sm"
                                                onclick="return confirm('Are you sure you want to activate this assignment?');">
                                                Activate
                                            </button>
                                        </form>
                                    @endcan
                                @endif

                                @can('delete', $assignment)
                                    <button type="button" class="btn btn-danger btn-sm" onclick="openModal('delete-ta-modal-{{ $assignment->id }}')">
                                        Remove
                                    </button>
                                @endcan
                            </div>

                            @can('delete', $assignment)
                                <div id="delete-ta-modal-{{ $assignment->id }}" class="modal-backdrop" style="display: none;">
                                    <div class="modal-dialog">
                                        <div class="modal-header">
                                            <h3 class="modal-title">Remove Teacher Assignment</h3>
                                            <button type="button" class="modal-close" onclick="closeModal('delete-ta-modal-{{ $assignment->id }}')">&times;</button>
                                        </div>
                                        <form method="POST" action="{{ route('teacher_assignments.destroy', $assignment) }}">
                                            @csrf
                                            @method('DELETE')
                                            <div class="modal-body">
                                                <p>Are you sure you want to permanently remove this assignment for <strong>{{ $assignment->user?->display_name }}</strong> ({{ $assignment->schoolClass?->name }}{{ $assignment->section ? ' - ' . $assignment->section->name : '' }})?</p>
                                                <div class="alert alert-warning" style="margin-top: 1rem; font-size: 0.875rem;">
                                                    <strong>Warning:</strong> This operation cannot be undone. Assignments where the teacher has already recorded marks or attendance cannot be removed and must be deactivated instead to maintain historical accountability.
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" onclick="closeModal('delete-ta-modal-{{ $assignment->id }}')">Cancel</button>
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
                        <td colspan="10" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                            No teacher assignments found matching criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($assignments->hasPages())
        <div style="padding: 1rem; border-top: 1px solid var(--color-border, #e5e7eb);">
            {{ $assignments->links() }}
        </div>
    @endif
</div>

<!-- Modal: Create/Edit Teacher Assignment -->
<div id="assignment-modal" class="modal-backdrop" style="display: none;">
    <div class="modal-dialog" style="max-width: 600px;">
        <div class="modal-header">
            <h3 id="assignment-modal-title">Create Teacher Assignment</h3>
            <button type="button" class="modal-close" onclick="closeModal('assignment-modal')">&times;</button>
        </div>
        <form id="assignment-form" method="POST" action="{{ route('teacher_assignments.store') }}">
            @csrf
            <input type="hidden" name="_method" id="assignment_form_method" value="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Teacher <span style="color: var(--color-danger);">*</span></label>
                    <select id="modal_user_id" name="user_id" class="form-control" required onchange="handleTeacherSelect(this)">
                        <option value="">Select Teacher</option>
                        @foreach($teachers as $t)
                            <option value="{{ $t->id }}" data-role="{{ $t->role?->name }}">
                                {{ $t->display_name }} ({{ $t->username }}) — [{{ $t->role?->name }}]
                            </option>
                        @endforeach
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Academic Year</label>
                        <div id="modal_academic_year_display" style="padding: 0.5rem 0.75rem; background: var(--color-bg-subtle, #f9fafb); border: 1px solid var(--color-border, #e5e7eb); border-radius: var(--radius-sm, 6px); font-size: var(--font-size-sm, 0.875rem); min-height: 38px; display: flex; align-items: center;">
                            @if($currentAcademicYear)
                                <span id="modal_year_text">{{ $currentAcademicYear->name }}</span>
                                <span class="badge badge-info" id="modal_year_badge" style="margin-left: 0.5rem;">Active</span>
                            @else
                                <span style="color: var(--color-danger, #ef4444);" id="modal_year_text">No active year configured</span>
                            @endif
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Assignment Type <span style="color: var(--color-danger);">*</span></label>
                        <select id="modal_assignment_type" name="assignment_type" class="form-control" required onchange="handleAssignmentTypeChange(this.value)">
                            <option value="subject_teacher">Subject Teacher</option>
                            <option value="class_teacher">Class Teacher</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Class <span style="color: var(--color-danger);">*</span></label>
                        <select id="modal_class_id" name="class_id" class="form-control" required onchange="handleModalClassChange(this.value)">
                            <option value="">Select Class</option>
                            @foreach($classes as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Section <span style="color: var(--color-danger);">*</span></label>
                        <select id="modal_section_id" name="section_id" class="form-control" required disabled onchange="filterModalSubjects()">
                            <option value="">Select Class first</option>
                        </select>
                    </div>
                </div>

                <!-- Subject Field: dynamically toggled based on Assignment Type -->
                <div class="form-group" id="subject_select_container">
                    <label class="form-label" id="subject_label">Subject <span style="color: var(--color-danger);">*</span></label>
                    <select id="modal_subject_id" name="subject_id" class="form-control">
                        <option value="">Select Subject</option>
                        @foreach($subjects as $sub)
                            <option value="{{ $sub->id }}">{{ $sub->name }} ({{ $sub->code }})</option>
                        @endforeach
                    </select>
                    <div id="class_teacher_subject_notice" style="display: none; padding: 0.5rem 0.75rem; background: var(--color-bg-subtle, #f3f4f6); border: 1px solid var(--color-border, #e5e7eb); border-radius: var(--radius-sm); font-size: var(--font-size-sm); color: var(--color-text-main);">
                        ✓ <strong>All Applicable Subjects (Class Teacher)</strong> — Covers all subjects offered to this class and section.
                    </div>
                </div>

                <!-- Dual Assignment Option for Class Teacher -->
                <div id="dual_assignment_container" style="display: none; margin-top: 1rem; padding: 1rem; background: var(--color-bg-subtle, #f9fafb); border: 1px solid var(--color-border, #e5e7eb); border-radius: var(--radius-sm, 6px);">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                        <input type="checkbox" name="also_assign_subject" id="modal_also_assign_subject" value="1" onchange="handleDualAssignmentToggle(this.checked)">
                        <label for="modal_also_assign_subject" style="font-size: var(--font-size-sm); font-weight: 600; cursor: pointer; margin-bottom: 0;">
                            Also assign as Subject Teacher
                        </label>
                    </div>
                    <div id="dual_subject_select_wrapper" style="display: none;">
                        <label class="form-label" style="font-size: var(--font-size-sm);">Subject <span style="color: var(--color-danger);">*</span></label>
                        <select id="modal_also_subject_id" name="also_subject_id" class="form-control">
                            <option value="">Select Subject</option>
                            @foreach($subjects as $sub)
                                <option value="{{ $sub->id }}">{{ $sub->name }} ({{ $sub->code }})</option>
                            @endforeach
                        </select>
                        <small style="color: var(--color-text-muted); font-size: var(--font-size-xs); display: block; margin-top: 0.25rem;">
                            Atomically creates both a Class Teacher assignment and a Subject Teacher assignment for this class/section.
                        </small>
                    </div>
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                    <input type="checkbox" name="is_active" id="modal_is_active" value="1" checked>
                    <label for="modal_is_active" style="font-size: var(--font-size-sm); cursor: pointer; margin-bottom: 0;">Active Assignment</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('assignment-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btn-save-assignment">Save Assignment</button>
            </div>
        </form>
    </div>
</div>

<script>
const allSections = @json($sections);
const allClassSubjects = @json($classSubjects);
const currentAcademicYear = @json($currentAcademicYear);
const academicYears = @json($academicYears);
const filterAcademicYearId = @json($targetYearId);
let currentModalYearId = currentAcademicYear ? currentAcademicYear.id : null;

function handleTeacherSelect(selectElem) {
    const selectedOpt = selectElem.options[selectElem.selectedIndex];
    const role = selectedOpt ? selectedOpt.getAttribute('data-role') : '';
    const assignmentTypeSelect = document.getElementById('modal_assignment_type');

    const classOpt = assignmentTypeSelect.querySelector('option[value="class_teacher"]');
    const subjectOpt = assignmentTypeSelect.querySelector('option[value="subject_teacher"]');

    if (role === 'Class Teacher') {
        classOpt.disabled = false;
        classOpt.style.display = '';
        subjectOpt.disabled = false;
        subjectOpt.style.display = '';
        if (assignmentTypeSelect.value !== 'class_teacher' && assignmentTypeSelect.value !== 'subject_teacher') {
            assignmentTypeSelect.value = 'class_teacher';
        }
    } else if (role === 'Subject Teacher') {
        subjectOpt.disabled = false;
        subjectOpt.style.display = '';
        classOpt.disabled = true;
        classOpt.style.display = 'none';
        assignmentTypeSelect.value = 'subject_teacher';
    } else {
        classOpt.disabled = false;
        classOpt.style.display = '';
        subjectOpt.disabled = false;
        subjectOpt.style.display = '';
    }

    handleAssignmentTypeChange(assignmentTypeSelect.value);
}

function handleAssignmentTypeChange(type) {
    const subjectSelect = document.getElementById('modal_subject_id');
    const subjectLabel = document.getElementById('subject_label');
    const classTeacherNotice = document.getElementById('class_teacher_subject_notice');
    const dualContainer = document.getElementById('dual_assignment_container');
    const isCreate = document.getElementById('assignment_form_method').value === 'POST';

    if (type === 'class_teacher') {
        subjectSelect.style.display = 'none';
        subjectSelect.required = false;
        subjectSelect.value = '';
        subjectLabel.style.display = 'none';
        classTeacherNotice.style.display = 'block';
        if (dualContainer && isCreate) {
            dualContainer.style.display = 'block';
            filterDualSubjects();
        }
    } else {
        subjectSelect.style.display = 'block';
        subjectSelect.required = true;
        subjectLabel.style.display = 'block';
        classTeacherNotice.style.display = 'none';
        if (dualContainer) {
            dualContainer.style.display = 'none';
            const dualCheck = document.getElementById('modal_also_assign_subject');
            if (dualCheck) {
                dualCheck.checked = false;
                handleDualAssignmentToggle(false);
            }
        }
        filterModalSubjects();
    }
}

function handleDualAssignmentToggle(isChecked) {
    const wrapper = document.getElementById('dual_subject_select_wrapper');
    const select = document.getElementById('modal_also_subject_id');
    if (wrapper) wrapper.style.display = isChecked ? 'block' : 'none';
    if (select) {
        select.required = isChecked;
        if (isChecked) {
            filterDualSubjects();
        } else {
            select.value = '';
        }
    }
}

function filterDualSubjects() {
    const academicYearId = currentModalYearId;
    const classId = document.getElementById('modal_class_id').value;
    const sectionId = document.getElementById('modal_section_id').value;
    const subjectSelect = document.getElementById('modal_also_subject_id');

    if (!subjectSelect) return;
    const previousVal = subjectSelect.value;
    subjectSelect.innerHTML = '<option value="">Select Subject</option>';

    if (academicYearId && classId) {
        const mappedSubjects = [];
        const seenSubjectIds = new Set();

        allClassSubjects.forEach(cs => {
            const matchesYear = String(cs.academic_year_id) === String(academicYearId);
            const matchesClass = String(cs.class_id) === String(classId);
            const matchesSection = !cs.section_id || (sectionId && String(cs.section_id) === String(sectionId));

            if (matchesYear && matchesClass && matchesSection && cs.subject) {
                if (!seenSubjectIds.has(cs.subject.id)) {
                    seenSubjectIds.add(cs.subject.id);
                    mappedSubjects.push(cs.subject);
                }
            }
        });

        mappedSubjects.forEach(sub => {
            const opt = document.createElement('option');
            opt.value = sub.id;
            opt.textContent = sub.name + ' (' + sub.code + ')';
            if (previousVal && String(sub.id) === String(previousVal)) {
                opt.selected = true;
            }
            subjectSelect.appendChild(opt);
        });
    }
}

function handleModalClassChange(classId, preserveSectionId = null) {
    const sectionSelect = document.getElementById('modal_section_id');
    sectionSelect.innerHTML = '';

    if (!classId) {
        sectionSelect.innerHTML = '<option value="">Select Class first</option>';
        sectionSelect.disabled = true;
        filterModalSubjects();
        filterDualSubjects();
        return;
    }

    const matchingSections = allSections.filter(sec => {
        const matchClass = String(sec.class_id) === String(classId);
        const matchYear = !currentModalYearId || String(sec.academic_year_id) === String(currentModalYearId);
        return matchClass && matchYear;
    });

    if (matchingSections.length === 0) {
        sectionSelect.innerHTML = '<option value="">No sections available</option>';
        sectionSelect.disabled = true;
    } else {
        sectionSelect.disabled = false;
        const defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = 'Select Section';
        sectionSelect.appendChild(defaultOpt);

        let preservedFound = false;
        matchingSections.forEach(sec => {
            const opt = document.createElement('option');
            opt.value = sec.id;
            opt.textContent = sec.name;
            if (preserveSectionId && String(sec.id) === String(preserveSectionId)) {
                opt.selected = true;
                preservedFound = true;
            }
            sectionSelect.appendChild(opt);
        });

        if (preserveSectionId && !preservedFound) {
            sectionSelect.value = '';
        }
    }

    filterModalSubjects();
    filterDualSubjects();
}

function filterModalSubjects(preserveSubjectId = null) {
    filterDualSubjects();
    const assignmentType = document.getElementById('modal_assignment_type').value;
    if (assignmentType === 'class_teacher') {
        return;
    }

    const academicYearId = currentModalYearId;
    const classId = document.getElementById('modal_class_id').value;
    const sectionId = document.getElementById('modal_section_id').value;
    const subjectSelect = document.getElementById('modal_subject_id');

    subjectSelect.innerHTML = '<option value="">Select Subject</option>';

    if (academicYearId && classId) {
        const mappedSubjects = [];
        const seenSubjectIds = new Set();

        allClassSubjects.forEach(cs => {
            const matchesYear = String(cs.academic_year_id) === String(academicYearId);
            const matchesClass = String(cs.class_id) === String(classId);
            const matchesSection = !cs.section_id || (sectionId && String(cs.section_id) === String(sectionId));

            if (matchesYear && matchesClass && matchesSection && cs.subject) {
                if (!seenSubjectIds.has(cs.subject.id)) {
                    seenSubjectIds.add(cs.subject.id);
                    mappedSubjects.push(cs.subject);
                }
            }
        });

        mappedSubjects.forEach(sub => {
            const opt = document.createElement('option');
            opt.value = sub.id;
            opt.textContent = sub.name + ' (' + sub.code + ')';
            if (preserveSubjectId && String(sub.id) === String(preserveSubjectId)) {
                opt.selected = true;
            }
            subjectSelect.appendChild(opt);
        });
    }
}

function openCreateAssignmentModal() {
    document.getElementById('assignment-modal-title').textContent = 'Create Teacher Assignment';
    const form = document.getElementById('assignment-form');
    form.action = "{{ route('teacher_assignments.store') }}";
    document.getElementById('assignment_form_method').value = 'POST';

    // Reset dual assignment controls
    const dualContainer = document.getElementById('dual_assignment_container');
    const dualCheck = document.getElementById('modal_also_assign_subject');
    const dualSelect = document.getElementById('modal_also_subject_id');
    if (dualCheck) dualCheck.checked = false;
    if (dualSelect) {
        dualSelect.value = '';
        dualSelect.required = false;
    }
    const dualWrapper = document.getElementById('dual_subject_select_wrapper');
    if (dualWrapper) dualWrapper.style.display = 'none';
    if (dualContainer) dualContainer.style.display = 'none';

    currentModalYearId = currentAcademicYear ? currentAcademicYear.id : null;
    const yearDisplay = document.getElementById('modal_academic_year_display');
    if (yearDisplay) {
        if (currentAcademicYear) {
            yearDisplay.innerHTML = `<span>${currentAcademicYear.name}</span> <span class="badge badge-info" style="margin-left: 0.5rem;">Active</span>`;
        } else {
            yearDisplay.innerHTML = `<span style="color: var(--color-danger, #ef4444);">No active year configured</span>`;
        }
    }

    const teacherSelect = document.getElementById('modal_user_id');
    teacherSelect.value = '';
    handleTeacherSelect(teacherSelect);

    document.getElementById('modal_class_id').value = '';
    
    const sectionSelect = document.getElementById('modal_section_id');
    sectionSelect.innerHTML = '<option value="">Select Class first</option>';
    sectionSelect.disabled = true;

    filterModalSubjects();

    document.getElementById('modal_is_active').checked = true;

    openModal('assignment-modal');
}

function openEditAssignmentModal(assignment) {
    document.getElementById('assignment-modal-title').textContent = 'Edit Teacher Assignment';
    const form = document.getElementById('assignment-form');
    form.action = '/teacher-assignments/' + assignment.id;
    document.getElementById('assignment_form_method').value = 'PUT';

    // Hide dual assignment in edit mode
    const dualContainer = document.getElementById('dual_assignment_container');
    const dualCheck = document.getElementById('modal_also_assign_subject');
    const dualSelect = document.getElementById('modal_also_subject_id');
    if (dualCheck) dualCheck.checked = false;
    if (dualSelect) {
        dualSelect.value = '';
        dualSelect.required = false;
    }
    const dualWrapper = document.getElementById('dual_subject_select_wrapper');
    if (dualWrapper) dualWrapper.style.display = 'none';
    if (dualContainer) dualContainer.style.display = 'none';

    currentModalYearId = assignment.academic_year_id;
    const yearDisplay = document.getElementById('modal_academic_year_display');
    if (yearDisplay) {
        const foundYear = academicYears.find(y => String(y.id) === String(assignment.academic_year_id));
        const yearName = foundYear ? foundYear.name : ('Year #' + assignment.academic_year_id);
        const isCurrent = (currentAcademicYear && String(currentAcademicYear.id) === String(assignment.academic_year_id));
        yearDisplay.innerHTML = `<span>${yearName}</span> <span class="badge ${isCurrent ? 'badge-info' : 'badge-secondary'}" style="margin-left: 0.5rem;">${isCurrent ? 'Active' : 'Historical'}</span>`;
    }

    const teacherSelect = document.getElementById('modal_user_id');
    teacherSelect.value = assignment.user_id;
    handleTeacherSelect(teacherSelect);

    document.getElementById('modal_class_id').value = assignment.class_id;
    handleModalClassChange(assignment.class_id, assignment.section_id);

    document.getElementById('modal_assignment_type').value = assignment.assignment_type;
    handleAssignmentTypeChange(assignment.assignment_type);

    if (assignment.assignment_type === 'subject_teacher') {
        filterModalSubjects(assignment.subject_id);
    }

    document.getElementById('modal_is_active').checked = Boolean(assignment.is_active);

    openModal('assignment-modal');
}

function handleFilterClassChange(classId, preserveSectionId = null) {
    const sectionSelect = document.getElementById('filter_section_id');
    if (!sectionSelect) return;

    sectionSelect.innerHTML = '';

    if (!classId) {
        const defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = 'All Sections';
        sectionSelect.appendChild(defaultOpt);
        sectionSelect.disabled = true;
        sectionSelect.value = '';
        return;
    }

    const targetYear = filterAcademicYearId;
    if (!targetYear) {
        const noYearOpt = document.createElement('option');
        noYearOpt.value = '';
        noYearOpt.textContent = 'No active academic year';
        sectionSelect.appendChild(noYearOpt);
        sectionSelect.disabled = true;
        sectionSelect.value = '';
        return;
    }

    const matchingSections = allSections.filter(sec => {
        const matchClass = String(sec.class_id) === String(classId);
        const matchYear = String(sec.academic_year_id) === String(targetYear);
        return matchClass && matchYear;
    });

    if (matchingSections.length === 0) {
        const noSecOpt = document.createElement('option');
        noSecOpt.value = '';
        noSecOpt.textContent = 'No sections available';
        sectionSelect.appendChild(noSecOpt);
        sectionSelect.disabled = true;
        sectionSelect.value = '';
        return;
    }

    sectionSelect.disabled = false;
    const allOpt = document.createElement('option');
    allOpt.value = '';
    allOpt.textContent = 'All Sections';
    sectionSelect.appendChild(allOpt);

    let preservedFound = false;
    matchingSections.forEach(sec => {
        const opt = document.createElement('option');
        opt.value = sec.id;
        opt.textContent = sec.name;
        if (preserveSectionId && String(sec.id) === String(preserveSectionId)) {
            opt.selected = true;
            preservedFound = true;
        }
        sectionSelect.appendChild(opt);
    });

    if (preserveSectionId && !preservedFound) {
        sectionSelect.value = '';
    }
}

const filterClassSelect = document.getElementById('filter_class_id');
if (filterClassSelect) {
    filterClassSelect.addEventListener('change', function() {
        handleFilterClassChange(this.value);
    });
}
</script>
@endsection
