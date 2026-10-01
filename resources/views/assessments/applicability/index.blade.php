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

<!-- Dynamic Filter Bar -->
<div class="card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem;">
    <form method="GET" action="{{ route('assessments.applicability.index', $assessment) }}" id="applicability_filter_form" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
        <label for="filter_class_id" style="font-size: var(--font-size-sm); font-weight: 600;">Class:</label>
        <select name="class_id" id="filter_class_id" class="form-control" style="width: auto; min-width: 140px;" onchange="this.form.submit()">
            <option value="">All Classes</option>
            @foreach($filterClasses as $c)
                <option value="{{ $c->id }}" {{ (string)$selectedClassId === (string)$c->id ? 'selected' : '' }}>
                    {{ $c->name }}
                </option>
            @endforeach
        </select>

        <label for="filter_section_id" style="font-size: var(--font-size-sm); font-weight: 600;">Section:</label>
        <select name="section_id" id="filter_section_id" class="form-control" style="width: auto; min-width: 140px;" onchange="this.form.submit()">
            <option value="">All Sections</option>
            <option value="all_sections" {{ $selectedSectionId === 'all_sections' ? 'selected' : '' }}>Class-Wide Only</option>
            @foreach($filterSections as $sec)
                <option value="{{ $sec->id }}" {{ (string)$selectedSectionId === (string)$sec->id ? 'selected' : '' }}>
                    {{ $sec->name }}@if(!$selectedClassId && $sec->schoolClass) ({{ $sec->schoolClass->name }})@endif
                </option>
            @endforeach
        </select>

        <label for="filter_subject_id" style="font-size: var(--font-size-sm); font-weight: 600;">Subject:</label>
        <select name="subject_id" id="filter_subject_id" class="form-control" style="width: auto; min-width: 140px;" onchange="this.form.submit()">
            <option value="">All Subjects</option>
            @foreach($filterSubjects as $sub)
                <option value="{{ $sub->id }}" {{ (string)$selectedSubjectId === (string)$sub->id ? 'selected' : '' }}>
                    {{ $sub->name }} ({{ $sub->code }})
                </option>
            @endforeach
        </select>

        <label for="filter_status" style="font-size: var(--font-size-sm); font-weight: 600;">Status:</label>
        <select name="status" id="filter_status" class="form-control" style="width: auto; min-width: 130px;" onchange="this.form.submit()">
            <option value="all" {{ ($selectedStatus ?? 'all') === 'all' ? 'selected' : '' }}>All Status</option>
            <option value="active" {{ ($selectedStatus ?? '') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ ($selectedStatus ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>

        <a href="{{ route('assessments.applicability.index', $assessment) }}" class="btn btn-secondary btn-sm" id="btn_reset_filters" style="padding: 0.45rem 0.85rem; text-decoration: none;">
            Reset
        </a>
    </form>
</div>

<div class="assessment-applicability-layout">
    <!-- Configured Applicability Table -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <h3 style="margin: 0;">Subject Maximum Marks for this Assessment</h3>
            @can('create', App\Models\AssessmentApplicability::class)
                <button type="button" class="btn btn-primary" id="btn-add-applicability" onclick="openModal('create-applicability-modal')">
                    + Add Class Applicability
                </button>
            @endcan
        </div>
        <div class="data-table-wrapper">
            <table class="data-table" id="applicability_table">
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>Section</th>
                        <th>Subject</th>
                        <th>Maximum Marks</th>
                        <th>Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applicabilities as $app)
                        <tr>
                            <td><strong>{{ $app->classSubject?->schoolClass?->name }}</strong></td>
                            <td>{{ $app->classSubject?->section?->name ?? 'All Sections' }}</td>
                            <td>
                                {{ $app->classSubject?->subject?->name }}@if($app->classSubject?->subject?->code) <span style="font-weight: 500; opacity: 0.85; margin-left: 0.25rem;">({{ $app->classSubject->subject->code }})</span>@endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('assessments.applicability.update', [$assessment, $app]) }}" style="display: inline-flex; gap: 0.5rem; align-items: center;">
                                    @csrf
                                    @method('PUT')
                                    <input type="number" step="0.01" min="0.01" max="9999.99" name="maximum_marks" value="{{ $app->maximum_marks }}" class="form-control" style="width: 100px; padding: 0.25rem 0.5rem; font-size: var(--font-size-sm);" required>
                                    <button type="submit" class="btn btn-secondary btn-sm">Update</button>
                                </form>
                            </td>
                            <td>
                                <span class="badge {{ $app->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $app->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <div class="row-actions" style="justify-content: center;">
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

                                    @can('delete', $app)
                                        <button type="button" class="btn btn-danger btn-sm" onclick="openModal('remove-app-modal-{{ $app->id }}')">
                                            Remove
                                        </button>
                                    @endcan
                                </div>

                                <!-- Modals for Remove Action -->
                                @can('delete', $app)
                                    @if(($app->marks_count ?? 0) === 0)
                                        <!-- Unreferenced Mapping Deletion Confirmation Modal -->
                                        <div id="remove-app-modal-{{ $app->id }}" class="modal-backdrop" style="display: none; text-align: left;">
                                            <div class="modal-dialog">
                                                <div class="modal-header">
                                                    <h3 class="modal-title">Remove Subject Applicability</h3>
                                                    <button type="button" class="modal-close" onclick="closeModal('remove-app-modal-{{ $app->id }}')">&times;</button>
                                                </div>
                                                <form method="POST" action="{{ route('assessments.applicability.destroy', [$assessment, $app]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to permanently remove assessment applicability for <strong>{{ $app->classSubject?->schoolClass?->name }} ({{ $app->classSubject?->section?->name ?? 'All Sections' }}) - {{ $app->classSubject?->subject?->name }}@if($app->classSubject?->subject?->code) ({{ $app->classSubject->subject->code }})@endif</strong>?</p>
                                                        <div class="alert alert-warning" style="margin-top: 1rem; font-size: 0.875rem;">
                                                            <strong>Notice:</strong> This mapping has no recorded marks. Permanent removal cannot be undone.
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" onclick="closeModal('remove-app-modal-{{ $app->id }}')">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Confirm Permanent Remove</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @else
                                        <!-- Protected Mapping Modal (Marks Recorded) -->
                                        <div id="remove-app-modal-{{ $app->id }}" class="modal-backdrop" style="display: none; text-align: left;">
                                            <div class="modal-dialog">
                                                <div class="modal-header">
                                                    <h3 class="modal-title">Cannot Remove Subject Applicability</h3>
                                                    <button type="button" class="modal-close" onclick="closeModal('remove-app-modal-{{ $app->id }}')">&times;</button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>The applicability mapping for <strong>{{ $app->classSubject?->schoolClass?->name }} ({{ $app->classSubject?->section?->name ?? 'All Sections' }}) - {{ $app->classSubject?->subject?->name }}@if($app->classSubject?->subject?->code) ({{ $app->classSubject->subject->code }})@endif</strong> cannot be removed because <strong>{{ $app->marks_count }}</strong> student mark(s) have already been recorded against it.</p>
                                                    <div class="alert alert-warning" style="margin-top: 1rem; font-size: 0.875rem;">
                                                        <strong>Protection Rule:</strong> To preserve historical marks and student report integrity, this mapping cannot be deleted. If you wish to discontinue this subject for future evaluations, please use the <strong>Deactivate</strong> action instead.
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" onclick="closeModal('remove-app-modal-{{ $app->id }}')">Close</button>
                                                    @can('update', $app)
                                                        @if($app->is_active)
                                                            <form method="POST" action="{{ route('assessments.applicability.update', [$assessment, $app]) }}" style="display: inline;">
                                                                @csrf
                                                                @method('PUT')
                                                                <input type="hidden" name="is_active" value="0">
                                                                <button type="submit" class="btn btn-secondary">Deactivate Instead</button>
                                                            </form>
                                                        @endif
                                                    @endcan
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                                No subject applicability mappings match the current filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Class-Level Applicability Modal -->
@can('create', App\Models\AssessmentApplicability::class)
<div id="create-applicability-modal" class="modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="create-applicability-modal-title" onclick="if (event.target === this) closeModal('create-applicability-modal')">
    <div class="modal-dialog modal-dialog--md">
        <div class="modal-header">
            <h3 id="create-applicability-modal-title">Add Class Assessment Applicability</h3>
            <button type="button" class="modal-close" aria-label="Close" onclick="closeModal('create-applicability-modal')">&times;</button>
        </div>
        @if($availableClassesWithSubjects->isEmpty())
            <div class="modal-body">
                <p style="color: var(--color-text-muted); font-size: var(--font-size-sm); margin: 0;">
                    No active classes found for this academic year.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('create-applicability-modal')">Close</button>
            </div>
        @else
            <form method="POST" action="{{ route('assessments.applicability.store', $assessment) }}" id="applicability_create_form">
                @csrf
                <input type="hidden" name="_form_context" value="create_applicability">

                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="applicability_class_id">Select Class <span style="color: var(--color-danger);">*</span></label>
                        <select name="class_id" id="applicability_class_id" class="form-control" required>
                            <option value="">-- Select Class --</option>
                            @foreach($availableClassesWithSubjects as $cls)
                                <option value="{{ $cls->id }}"
                                    data-sections='@json($cls->sections)'
                                    data-subjects='@json($cls->subjects)'
                                    {{ (string) old('class_id') === (string) $cls->id ? 'selected' : '' }}>
                                    {{ $cls->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('class_id')
                            <span class="invalid-feedback" style="display: block; color: var(--color-danger); font-size: var(--font-size-xs); margin-top: 0.25rem;">{{ $message }}</span>
                        @enderror
                        @error('class_subject_ids')
                            <span class="invalid-feedback" style="display: block; color: var(--color-danger); font-size: var(--font-size-xs); margin-top: 0.25rem;">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Scope / Section Information Banner -->
                    <div id="class_sections_banner" style="display: none; margin-top: 0.75rem; padding: 0.75rem 1rem; border-radius: 6px; background-color: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; font-size: var(--font-size-sm);">
                        <div style="font-weight: 600; margin-bottom: 0.25rem;">Class-Wide Scope:</div>
                        <div>
                            Selecting this class applies the assessment to <strong>all current sections</strong>:
                            <span id="class_sections_list" style="font-weight: 600;">None</span>.
                        </div>
                        <div style="font-size: var(--font-size-xs); margin-top: 0.35rem; color: #3b82f6;">
                            Note: Newly created sections under this class will automatically inherit this applicability until student marks are entered.
                        </div>
                    </div>

                    <!-- Subject Maximum Marks Configuration -->
                    <div class="form-group" id="class_subjects_container" style="display: none; margin-top: 1.25rem;">
                        <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
                            <span>Subject Maximum Marks <span style="color: var(--color-danger);">*</span></span>
                            <span id="subjects_count_badge" style="font-size: 0.8rem; font-weight: normal; color: var(--color-text-muted);">0 subjects</span>
                        </label>
                        <p style="font-size: var(--font-size-xs); color: var(--color-text-muted); margin-top: -0.25rem; margin-bottom: 0.75rem;">
                            Specify maximum marks for each subject in this class. These marks apply across all sections of this class.
                        </p>

                        <div id="no_subjects_warning" style="display: none; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px; padding: 0.875rem; color: #92400e; font-size: var(--font-size-sm);">
                            No subjects have been mapped to this class for this academic year yet. Please map subjects to this class first.
                        </div>

                        <div id="subjects_list_table_wrapper" style="display: none; border: 1px solid var(--color-border); border-radius: 6px; overflow: hidden;">
                            <table class="data-table" style="margin: 0; width: 100%;">
                                <thead>
                                    <tr style="background: var(--color-background-soft, #f8fafc);">
                                        <th style="width: 40px; text-align: center;">
                                            <input type="checkbox" id="check_all_subjects" checked title="Select/Deselect all">
                                        </th>
                                        <th>Subject</th>
                                        <th style="width: 160px;">Maximum Marks</th>
                                    </tr>
                                </thead>
                                <tbody id="subjects_table_body">
                                    <!-- Populated via JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem; margin-top: 1rem; margin-bottom: 0;">
                        <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                        <label for="is_active" style="font-size: var(--font-size-sm); cursor: pointer; margin-bottom: 0;">Active</label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('create-applicability-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btn_submit_applicability" disabled>
                        Apply Assessment to Class
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
@endcan

<script>
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'flex';
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function () {
    const classSelect = document.getElementById('applicability_class_id');
    const sectionsBanner = document.getElementById('class_sections_banner');
    const sectionsList = document.getElementById('class_sections_list');
    const subjectsContainer = document.getElementById('class_subjects_container');
    const subjectsBadge = document.getElementById('subjects_count_badge');
    const noSubjectsWarning = document.getElementById('no_subjects_warning');
    const tableWrapper = document.getElementById('subjects_list_table_wrapper');
    const tbody = document.getElementById('subjects_table_body');
    const checkAll = document.getElementById('check_all_subjects');
    const submitBtn = document.getElementById('btn_submit_applicability');

    const enteredMarks = {};
    @php
        $oldSubMax = (array) old('subject_maximum_marks', []);
    @endphp
    @foreach($oldSubMax as $sId => $val)
        enteredMarks[{{ (int) $sId }}] = "{{ $val }}";
    @endforeach

    function updateSubmitState() {
        if (!submitBtn) return;
        const checkedCheckboxes = tbody ? tbody.querySelectorAll('.subject-include-checkbox:checked') : [];
        submitBtn.disabled = (!classSelect || !classSelect.value || checkedCheckboxes.length === 0);
    }

    function onClassChanged() {
        if (!classSelect || !classSelect.value) {
            if (sectionsBanner) sectionsBanner.style.display = 'none';
            if (subjectsContainer) subjectsContainer.style.display = 'none';
            if (submitBtn) submitBtn.disabled = true;
            return;
        }

        const selectedOption = classSelect.options[classSelect.selectedIndex];
        let sections = [];
        let subjects = [];

        try {
            sections = JSON.parse(selectedOption.getAttribute('data-sections') || '[]');
        } catch (e) {
            sections = [];
        }

        try {
            subjects = JSON.parse(selectedOption.getAttribute('data-subjects') || '[]');
        } catch (e) {
            subjects = [];
        }

        // Show sections banner
        if (sectionsBanner && sectionsList) {
            sectionsBanner.style.display = 'block';
            if (sections.length > 0) {
                sectionsList.textContent = sections.map(s => 'Section ' + s).join(', ');
            } else {
                sectionsList.textContent = 'None currently (will automatically propagate to new sections)';
            }
        }

        // Show subjects container
        if (subjectsContainer && tbody) {
            subjectsContainer.style.display = 'block';
            tbody.innerHTML = '';

            if (subjectsBadge) {
                subjectsBadge.textContent = `${subjects.length} subject${subjects.length === 1 ? '' : 's'}`;
            }

            if (subjects.length === 0) {
                if (noSubjectsWarning) noSubjectsWarning.style.display = 'block';
                if (tableWrapper) tableWrapper.style.display = 'none';
                if (submitBtn) submitBtn.disabled = true;
                return;
            }

            if (noSubjectsWarning) noSubjectsWarning.style.display = 'none';
            if (tableWrapper) tableWrapper.style.display = 'block';

            subjects.forEach(sub => {
                const tr = document.createElement('tr');
                const defaultMark = enteredMarks[sub.id] !== undefined ? enteredMarks[sub.id] : '100.00';

                tr.innerHTML = `
                    <td style="text-align: center; vertical-align: middle;">
                        <input type="checkbox" class="subject-include-checkbox" data-subject-id="${sub.id}" checked>
                    </td>
                    <td style="vertical-align: middle;">
                        <strong>${escapeHtml(sub.name)}</strong>
                        ${sub.code ? `<span style="font-size: var(--font-size-xs); color: var(--color-text-muted); margin-left: 0.25rem;">(${escapeHtml(sub.code)})</span>` : ''}
                    </td>
                    <td style="vertical-align: middle;">
                        <input type="number" step="0.01" min="0.01" max="9999.99"
                            name="subject_maximum_marks[${sub.id}]"
                            data-subject-id="${sub.id}"
                            class="form-control subject-mark-input"
                            value="${escapeHtml(defaultMark)}"
                            placeholder="e.g. 100"
                            style="padding: 0.35rem 0.5rem; font-size: var(--font-size-sm); width: 140px;"
                            required>
                    </td>
                `;

                const cb = tr.querySelector('.subject-include-checkbox');
                const markInput = tr.querySelector('.subject-mark-input');

                cb.addEventListener('change', function () {
                    markInput.disabled = !this.checked;
                    updateSubmitState();
                });

                markInput.addEventListener('input', function () {
                    enteredMarks[sub.id] = this.value;
                });

                tbody.appendChild(tr);
            });

            if (checkAll) {
                checkAll.checked = true;
            }

            updateSubmitState();
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    if (classSelect) {
        classSelect.addEventListener('change', onClassChanged);
        if (classSelect.value) {
            onClassChanged();
        }
    }

    if (checkAll && tbody) {
        checkAll.addEventListener('change', function () {
            const isChecked = this.checked;
            tbody.querySelectorAll('.subject-include-checkbox').forEach(cb => {
                cb.checked = isChecked;
                const row = cb.closest('tr');
                if (row) {
                    const input = row.querySelector('.subject-mark-input');
                    if (input) input.disabled = !isChecked;
                }
            });
            updateSubmitState();
        });
    }
});
</script>

@if($errors->any() && (old('class_id') || old('_form_context') === 'create_applicability' || $errors->has('class_id') || $errors->has('class_subject_ids') || $errors->has('maximum_marks')))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        openModal('create-applicability-modal');
    });
</script>
@endif
@endsection
