@extends('layouts.app')

@section('title', 'Class Subjects - School Examination & Report Card Management System')
@section('page_title', 'Curriculum Mapping (Class Subjects)')

@section('content')
<!-- Listing Filter Bar -->
<div class="filter-bar" style="margin-bottom: 1.25rem;">
    <form method="GET" action="{{ route('class_subjects.index') }}" id="listing_filter_form" style="display: flex; gap: 0.85rem; align-items: center; flex-wrap: wrap;">
        @if(request()->filled('academic_year_id'))
            <input type="hidden" name="academic_year_id" value="{{ request('academic_year_id') }}">
        @endif

        <label for="filter_class_id" style="font-size: var(--font-size-sm); font-weight: 600;">Class:</label>
        <select name="class_id" id="filter_class_id" class="form-control" style="width: auto; min-width: 140px;" onchange="document.getElementById('filter_section_id').value=''; this.form.submit()">
            <option value="">All Classes</option>
            @foreach($classes as $c)
                <option value="{{ $c->id }}" {{ (string)$selectedClassId === (string)$c->id ? 'selected' : '' }}>
                    {{ $c->name }}
                </option>
            @endforeach
        </select>

        <label for="filter_section_id" style="font-size: var(--font-size-sm); font-weight: 600;">Section:</label>
        <select name="section_id" id="filter_section_id" class="form-control" style="width: auto; min-width: 140px;" onchange="this.form.submit()">
            <option value="">All Sections</option>
            <option value="all_sections" {{ $selectedSectionId === 'all_sections' ? 'selected' : '' }}>Class-Wide Only</option>
            @foreach($listingSections as $sec)
                <option value="{{ $sec->id }}" {{ (string)$selectedSectionId === (string)$sec->id ? 'selected' : '' }}>
                    {{ $sec->name }} ({{ $sec->schoolClass?->name }})
                </option>
            @endforeach
        </select>

        <label for="filter_status" style="font-size: var(--font-size-sm); font-weight: 600;">Status:</label>
        <select name="status" id="filter_status" class="form-control" style="width: auto; min-width: 130px;" onchange="this.form.submit()">
            <option value="all" {{ ($selectedStatus ?? 'all') === 'all' ? 'selected' : '' }}>All Status</option>
            <option value="active" {{ ($selectedStatus ?? '') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ ($selectedStatus ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>

        <a href="{{ route('class_subjects.index') }}" class="btn btn-secondary btn-sm" id="btn_reset_filters" style="padding: 0.45rem 0.85rem; text-decoration: none;">
            Reset
        </a>
    </form>
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h3 style="margin: 0;">Class Subject Mappings</h3>
        @can('create', App\Models\ClassSubject::class)
            <button type="button" class="btn btn-primary" id="btn-map-subject" onclick="openModal('map-subject-modal')">
                + Map Subject to Class
            </button>
        @endcan
    </div>
            <div class="data-table-wrapper">
                <table class="data-table" id="class_subjects_table">
                    <thead>
                        <tr>
                            <th style="width: 44px; text-align: center;"></th>
                            <th style="width: 20%;">Class</th>
                            <th style="width: 20%;">Section</th>
                            <th style="width: 22%;">Status</th>
                            <th style="text-align: right; padding-right: 1.25rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($groupedMappings as $group)
                            <tr class="group-parent-row" onclick="handleGroupRowClick(event, '{{ $group->group_key }}')">
                                <td style="width: 44px; text-align: center; vertical-align: middle;">
                                    <button type="button" class="group-toggle-btn" id="btn-toggle-{{ $group->group_key }}" aria-expanded="false" aria-controls="drawer-{{ $group->group_key }}" aria-label="Toggle subjects for {{ $group->schoolClass?->name }}" onclick="toggleGroupDrawer('{{ $group->group_key }}', event)">
                                        <svg class="group-toggle-icon" id="icon-{{ $group->group_key }}" width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </td>
                                <td>
                                    <strong>{{ $group->schoolClass?->name }}</strong>
                                </td>
                                <td>
                                    @if($group->is_legacy_null)
                                        <span style="color: var(--color-text-muted); font-style: italic;">All Sections (Legacy)</span>
                                    @else
                                        <span>{{ $group->section?->name }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($group->status === 'active')
                                        <span class="badge badge-success">&#8226; Active</span>
                                    @elseif($group->status === 'inactive')
                                        <span class="badge badge-secondary">&#8226; Inactive</span>
                                    @else
                                        <span class="badge badge-warning">&#8226; Mixed ({{ $group->active_count }} active, {{ $group->inactive_count }} inactive)</span>
                                    @endif
                                </td>
                                <td style="text-align: right; vertical-align: middle; padding-right: 1.25rem;">
                                    <div class="row-actions" style="display: inline-flex; justify-content: flex-end; align-items: center; gap: 0.5rem; white-space: nowrap;" onclick="event.stopPropagation()">
                                        @can('update', App\Models\ClassSubject::class)
                                            <button type="button" class="btn btn-outline btn-sm" style="min-width: 52px; text-align: center;" onclick="openModal('edit-group-modal-{{ $group->group_key }}')">
                                                Edit
                                            </button>
                                            <button type="button" class="btn btn-secondary btn-sm" style="min-width: 86px; text-align: center;" onclick="openModal('toggle-status-modal-{{ $group->group_key }}')">
                                                {{ $group->status === 'inactive' ? 'Activate' : 'Deactivate' }}
                                            </button>
                                        @endcan

                                        @can('delete', new App\Models\ClassSubject())
                                            <button type="button" class="btn btn-danger btn-sm" style="min-width: 68px; text-align: center;" onclick="openModal('delete-group-modal-{{ $group->group_key }}')">
                                                Remove
                                            </button>
                                        @endcan
                                    </div>

                                    <!-- Modals for Group -->
                                    @can('update', App\Models\ClassSubject::class)
                                        <!-- Edit Modal -->
                                        <div id="edit-group-modal-{{ $group->group_key }}" class="modal-backdrop" style="display: none; text-align: left;" onclick="event.stopPropagation()">
                                            <div class="modal-dialog">
                                                <div class="modal-header">
                                                    <h3 class="modal-title">Edit Subjects: {{ $group->schoolClass?->name }} ({{ $group->is_legacy_null ? 'All Sections (Legacy)' : $group->section?->name }})</h3>
                                                    <button type="button" class="modal-close" onclick="closeEditModal('edit-group-modal-{{ $group->group_key }}')">&times;</button>
                                                </div>
                                                <form method="POST" action="{{ route('class_subjects.group_sync') }}" class="edit-group-form">
                                                    @csrf
                                                    <input type="hidden" name="class_id" value="{{ $group->class_id }}">
                                                    <input type="hidden" name="section_id" value="{{ $group->section_id ?? 'null' }}">
                                                    <input type="hidden" name="academic_year_id" value="{{ $group->academic_year_id }}">

                                                    <div class="modal-body">
                                                        <p style="font-size: var(--font-size-sm); color: var(--color-text-muted); margin-bottom: 0.75rem;">
                                                            Select the subjects that should be mapped to this section. Unchecking an unreferenced subject will remove it. Linked subjects with allocations will be safely deactivated to preserve student records.
                                                        </p>
                                                        <div class="subject-checkbox-list" style="max-height: 280px; overflow-y: auto;">
                                                            @php
                                                                $activeMappedSubjectIds = $group->mappings->where('is_active', true)->pluck('subject_id')->toArray();
                                                            @endphp
                                                            @foreach($subjects as $sub)
                                                                @php
                                                                    $mappedCs = $group->mappings->firstWhere('subject_id', $sub->id);
                                                                    $isCurrentlyActive = in_array($sub->id, $activeMappedSubjectIds);
                                                                    $isCurrentlyInactive = $mappedCs && ! $mappedCs->is_active;
                                                                    $hasRecordedMarks = $mappedCs && (($mappedCs->marks_count ?? 0) > 0 || $mappedCs->marks()->exists());
                                                                @endphp
                                                                <div class="subject-checkbox-item" style="padding: 0.35rem 0; display: flex; align-items: center; gap: 0.5rem;">
                                                                    @if($hasRecordedMarks)
                                                                        <input type="checkbox" checked disabled id="modal_sub_{{ $group->group_key }}_{{ $sub->id }}">
                                                                        <input type="hidden" name="subject_ids[]" value="{{ $sub->id }}">
                                                                        <label for="modal_sub_{{ $group->group_key }}_{{ $sub->id }}" style="margin-bottom: 0; cursor: default;">
                                                                            <strong>{{ $sub->name }}</strong> ({{ $sub->code }})
                                                                            <span class="badge badge-secondary" style="font-size: 0.65rem; margin-left: 0.35rem;">Locked (Recorded Marks)</span>
                                                                        </label>
                                                                    @else
                                                                        <input type="checkbox" name="subject_ids[]" value="{{ $sub->id }}" id="modal_sub_{{ $group->group_key }}_{{ $sub->id }}" {{ $isCurrentlyActive ? 'checked' : '' }} style="cursor: pointer;">
                                                                        <label for="modal_sub_{{ $group->group_key }}_{{ $sub->id }}" style="margin-bottom: 0; cursor: pointer;">
                                                                            <strong>{{ $sub->name }}</strong> ({{ $sub->code }})
                                                                            @if($isCurrentlyInactive)
                                                                                <span class="badge badge-secondary" style="font-size: 0.65rem; margin-left: 0.35rem;">(Inactive)</span>
                                                                            @endif
                                                                        </label>
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" onclick="closeEditModal('edit-group-modal-{{ $group->group_key }}')">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                        <!-- Status Toggle Modal -->
                                        <div id="toggle-status-modal-{{ $group->group_key }}" class="modal-backdrop" style="display: none; text-align: left;" onclick="event.stopPropagation()">
                                            <div class="modal-dialog">
                                                <div class="modal-header">
                                                    <h3 class="modal-title">{{ $group->status === 'inactive' ? 'Activate' : 'Deactivate' }} Section Mappings</h3>
                                                    <button type="button" class="modal-close" onclick="closeModal('toggle-status-modal-{{ $group->group_key }}')">&times;</button>
                                                </div>
                                                <form method="POST" action="{{ route('class_subjects.group_status') }}">
                                                    @csrf
                                                    <input type="hidden" name="class_id" value="{{ $group->class_id }}">
                                                    <input type="hidden" name="section_id" value="{{ $group->section_id ?? 'null' }}">
                                                    <input type="hidden" name="academic_year_id" value="{{ $group->academic_year_id }}">
                                                    <input type="hidden" name="is_active" value="{{ $group->status === 'inactive' ? '1' : '0' }}">

                                                    <div class="modal-body">
                                                        <p>
                                                            Are you sure you want to <strong>{{ $group->status === 'inactive' ? 'activate' : 'deactivate' }}</strong> all <strong>{{ $group->total_count }}</strong> subject mapping(s) for <strong>{{ $group->schoolClass?->name }} ({{ $group->is_legacy_null ? 'All Sections (Legacy)' : $group->section?->name }})</strong>?
                                                        </p>
                                                        @if($group->status !== 'inactive')
                                                            <div class="alert alert-warning" style="margin-top: 1rem; font-size: 0.85rem;">
                                                                Deactivated subjects will remain visible in historical records and student reports, but won't be offered for new mark entries.
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" onclick="closeModal('toggle-status-modal-{{ $group->group_key }}')">Cancel</button>
                                                        <button type="submit" class="btn {{ $group->status === 'inactive' ? 'btn-primary' : 'btn-secondary' }}">
                                                            Confirm {{ $group->status === 'inactive' ? 'Activate' : 'Deactivate' }}
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @endcan

                                    @can('delete', new App\Models\ClassSubject())
                                        <!-- Delete Modal -->
                                        <div id="delete-group-modal-{{ $group->group_key }}" class="modal-backdrop" style="display: none; text-align: left;" onclick="event.stopPropagation()">
                                            <div class="modal-dialog">
                                                <div class="modal-header">
                                                    <h3 class="modal-title">Remove Mappings: {{ $group->schoolClass?->name }} ({{ $group->is_legacy_null ? 'All Sections (Legacy)' : $group->section?->name }})</h3>
                                                    <button type="button" class="modal-close" onclick="closeModal('delete-group-modal-{{ $group->group_key }}')">&times;</button>
                                                </div>
                                                <form method="POST" action="{{ route('class_subjects.group_destroy') }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="class_id" value="{{ $group->class_id }}">
                                                    <input type="hidden" name="section_id" value="{{ $group->section_id ?? 'null' }}">
                                                    <input type="hidden" name="academic_year_id" value="{{ $group->academic_year_id }}">

                                                    <div class="modal-body">
                                                        <p>
                                                            Are you sure you want to permanently remove all <strong>{{ $group->total_count }}</strong> subject mapping(s) for <strong>{{ $group->schoolClass?->name }} ({{ $group->is_legacy_null ? 'All Sections (Legacy)' : $group->section?->name }})</strong>?
                                                        </p>
                                                        <div class="alert alert-warning" style="margin-top: 1rem; font-size: 0.85rem;">
                                                            <strong>Protection Notice:</strong> Permanent removal is only allowed if NO students, marks, or assessment rules reference any of these subjects. If any subject is referenced, removal will be blocked and you must deactivate instead.
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" onclick="closeModal('delete-group-modal-{{ $group->group_key }}')">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Confirm Permanent Remove</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                            <!-- Expanded Subject Badges Drawer -->
                            <tr id="drawer-{{ $group->group_key }}" class="group-drawer-row" style="display: none;">
                                <td colspan="5" style="padding: 0.75rem 1rem 1.25rem 1rem;">
                                    <div class="group-drawer-card">
                                        <div class="group-drawer-header">Mapped Subjects ({{ $group->total_count }})</div>
                                        <div class="subject-pills-container">
                                            @foreach($group->mappings as $cs)
                                                <span class="subject-pill {{ $cs->is_active ? '' : 'subject-pill--inactive' }}">
                                                    {{ $cs->subject_name_snapshot ?: ($cs->subject?->name ?? 'Subject') }}@if($cs->subject?->code) <span class="subject-pill-code">({{ $cs->subject->code }})</span>@endif
                                                    @if(!$cs->is_active)
                                                        <small style="margin-left: 0.25rem; opacity: 0.75;">(Inactive)</small>
                                                    @endif
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                                    No class subjects mapped for the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Map Subject to Class Modal -->
    @can('create', App\Models\ClassSubject::class)
    <div id="map-subject-modal" class="modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="map-subject-modal-title" onclick="if (event.target === this) closeModal('map-subject-modal')">
        <div class="modal-dialog modal-dialog--md">
            <div class="modal-header">
                <h3 class="modal-title" id="map-subject-modal-title">Map Subject to Class</h3>
                <button type="button" class="modal-close" aria-label="Close" onclick="closeModal('map-subject-modal')">&times;</button>
            </div>
            <form method="POST" action="{{ route('class_subjects.store') }}" id="mapping_form">
                @csrf
                <input type="hidden" name="_form_context" value="create_class_subject">

                <div class="modal-body">
                    <div class="form-group">
                        <label for="mapping_class_id" class="form-label">Class <span style="color: var(--color-danger);">*</span></label>
                        <select name="class_id" id="mapping_class_id" class="form-control" required>
                            <option value="">Select Class</option>
                            @foreach($classes as $c)
                                <option value="{{ $c->id }}" {{ (string)old('class_id', $selectedClassId) === (string)$c->id ? 'selected' : '' }}>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('class_id')
                            <span class="invalid-feedback" style="display: block; color: var(--color-danger); font-size: var(--font-size-xs); margin-top: 0.25rem;">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="mapping_section_id" class="form-label">Section <span style="color: var(--color-danger);">*</span></label>
                        <select name="section_id" id="mapping_section_id" class="form-control" required>
                            <option value="">Select Section</option>
                            <option value="all_sections" {{ old('section_id') === 'all_sections' ? 'selected' : '' }}>All Sections (Class-Wide)</option>
                        </select>
                        <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Select a specific section or map across all sections.</small>
                        @error('section_id')
                            <span class="invalid-feedback" style="display: block; color: var(--color-danger); font-size: var(--font-size-xs); margin-top: 0.25rem;">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="subject_multiselect_trigger">Subject <span style="color: var(--color-danger);">*</span></label>
                        
                        <div class="custom-multiselect" id="subject_multiselect">
                            <button type="button" class="custom-multiselect-trigger form-control" id="subject_multiselect_trigger" aria-haspopup="listbox" aria-expanded="false" aria-controls="subject_multiselect_menu">
                                <span class="custom-multiselect-label" id="subject_multiselect_label">Select Subjects</span>
                                <svg class="custom-multiselect-arrow" width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 010 1.414l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>

                            <div class="custom-multiselect-menu" id="subject_multiselect_menu" role="listbox" aria-multiselectable="true" style="display: none;">
                                <!-- Top Search Input Inside Opened Dropdown -->
                                <div class="custom-multiselect-search-box">
                                    <input type="text" id="subject_search_input" class="custom-multiselect-search-input" placeholder="Search subjects..." autocomplete="off" aria-label="Search subjects">
                                </div>

                                <!-- Quick Actions: Select All / Clear All -->
                                <div class="custom-multiselect-actions">
                                    <button type="button" class="multiselect-action-btn" id="btn_ms_select_all">Select All</button>
                                    <button type="button" class="multiselect-action-btn" id="btn_ms_clear_all">Clear All</button>
                                </div>

                                <!-- Options List -->
                                <div class="custom-multiselect-options" id="subject_multiselect_options">
                                    @php $oldSubjectIds = (array) old('subject_ids', []); @endphp
                                    @foreach($subjects as $s)
                                        <label class="custom-multiselect-item" data-search="{{ strtolower($s->name . ' ' . $s->code) }}" for="sub_cb_{{ $s->id }}">
                                            <input type="checkbox" name="subject_ids[]" value="{{ $s->id }}" id="sub_cb_{{ $s->id }}" class="subject-multiselect-checkbox" form="mapping_form" {{ in_array($s->id, $oldSubjectIds) ? 'checked' : '' }}>
                                            <span class="multiselect-item-name">{{ $s->name }}</span>
                                            <span class="multiselect-item-code">({{ $s->code }})</span>
                                        </label>
                                    @endforeach
                                    <div class="custom-multiselect-empty" id="subject_multiselect_empty" style="display: none;">
                                        No matching subjects
                                    </div>
                                </div>
                            </div>
                        </div>
                        <small style="color: var(--color-text-muted); font-size: var(--font-size-xs); display: block; margin-top: 0.25rem;">
                            A permanent snapshot of the subject name will be stored.
                        </small>
                        @error('subject_ids')
                            <span class="invalid-feedback" style="display: block; color: var(--color-danger); font-size: var(--font-size-xs); margin-top: 0.25rem;">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                        <input type="checkbox" name="is_active" id="mapping_is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                        <label for="mapping_is_active" style="font-size: var(--font-size-sm); cursor: pointer; margin-bottom: 0;">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('map-subject-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Map Subject</button>
                </div>
            </form>
        </div>
    </div>

    @if($errors->any() && old('_form_context') === 'create_class_subject')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                openModal('map-subject-modal');
            });
        </script>
    @endif
    @endcan

<script>
document.addEventListener('DOMContentLoaded', function () {
    const allMappingSections = @json($mappingSections);
    const mappingClassSelect = document.getElementById('mapping_class_id');
    const mappingSectionSelect = document.getElementById('mapping_section_id');
    const mappingForm = document.getElementById('mapping_form');
    const oldSectionId = @json(old('section_id'));

    // Dynamic Class -> Section update in form
    function updateMappingSections() {
        if (!mappingClassSelect || !mappingSectionSelect) return;
        const selectedClassId = mappingClassSelect.value;
        const previousValue = mappingSectionSelect.value || oldSectionId;

        mappingSectionSelect.innerHTML = '';

        const placeholderOpt = document.createElement('option');
        placeholderOpt.value = '';
        placeholderOpt.textContent = 'Select Section';
        mappingSectionSelect.appendChild(placeholderOpt);

        const classWideOpt = document.createElement('option');
        classWideOpt.value = 'all_sections';
        classWideOpt.textContent = 'All Sections (Class-Wide)';
        if (previousValue === 'all_sections') {
            classWideOpt.selected = true;
        }
        mappingSectionSelect.appendChild(classWideOpt);

        if (!selectedClassId) return;

        const matching = allMappingSections.filter(s => String(s.class_id) === String(selectedClassId));

        matching.forEach(sec => {
            const opt = document.createElement('option');
            opt.value = sec.id;
            opt.textContent = sec.name;
            if (String(sec.id) === String(previousValue)) {
                opt.selected = true;
            }
            mappingSectionSelect.appendChild(opt);
        });
    }

    if (mappingClassSelect) {
        mappingClassSelect.addEventListener('change', updateMappingSections);
        updateMappingSections();
    }

    // Searchable Multi-Select Dropdown logic
    const msContainer = document.getElementById('subject_multiselect');
    const msTrigger = document.getElementById('subject_multiselect_trigger');
    const msLabel = document.getElementById('subject_multiselect_label');
    const msMenu = document.getElementById('subject_multiselect_menu');
    const msSearch = document.getElementById('subject_search_input');
    const msOptions = document.querySelectorAll('.custom-multiselect-item');
    const msCheckboxes = document.querySelectorAll('.subject-multiselect-checkbox');
    const msEmpty = document.getElementById('subject_multiselect_empty');
    const msSelectAllBtn = document.getElementById('btn_ms_select_all');
    const msClearAllBtn = document.getElementById('btn_ms_clear_all');

    function updateMsSummary() {
        if (!msLabel) return;
        const checked = Array.from(msCheckboxes).filter(cb => cb.checked);
        if (checked.length === 0) {
            msLabel.textContent = 'Select Subjects';
            msLabel.style.color = 'var(--color-text-muted, #64748b)';
        } else if (checked.length === 1) {
            const item = checked[0].closest('.custom-multiselect-item');
            const name = item ? item.querySelector('.multiselect-item-name')?.textContent : '1 Subject';
            const code = item ? item.querySelector('.multiselect-item-code')?.textContent : '';
            msLabel.textContent = `${name} ${code}`.trim();
            msLabel.style.color = 'var(--color-text-main, #0f172a)';
        } else if (checked.length === 2) {
            const names = checked.map(cb => {
                const item = cb.closest('.custom-multiselect-item');
                return item ? item.querySelector('.multiselect-item-name')?.textContent.trim() : '';
            }).filter(Boolean);
            msLabel.textContent = names.join(', ');
            msLabel.style.color = 'var(--color-text-main, #0f172a)';
        } else {
            msLabel.textContent = `${checked.length} subjects selected`;
            msLabel.style.color = 'var(--color-text-main, #0f172a)';
        }
    }

    function positionMenu() {
        if (!msMenu || msMenu.style.display !== 'flex' || !msTrigger) return;

        const rect = msTrigger.getBoundingClientRect();
        const viewportHeight = window.innerHeight;
        const viewportWidth = window.innerWidth;

        // If trigger is scrolled out of viewport, close menu
        if (rect.bottom < 0 || rect.top > viewportHeight) {
            closeMsMenu();
            return;
        }

        // Horizontal positioning: align with trigger width and left
        const menuWidth = Math.max(rect.width, 240);
        let left = rect.left;
        if (left + menuWidth > viewportWidth - 12) {
            left = Math.max(12, viewportWidth - menuWidth - 12);
        }
        msMenu.style.width = menuWidth + 'px';
        msMenu.style.left = left + 'px';

        // Vertical positioning: check space below vs space above
        const spaceBelow = viewportHeight - rect.bottom - 8;
        const spaceAbove = rect.top - 8;

        const optionsContainer = msMenu.querySelector('.custom-multiselect-options');

        if (spaceBelow < 220 && spaceAbove > spaceBelow) {
            // Open upward
            msMenu.style.top = 'auto';
            msMenu.style.bottom = (viewportHeight - rect.top + 4) + 'px';
            const maxMenuH = Math.max(140, Math.min(spaceAbove, 320));
            if (optionsContainer) {
                optionsContainer.style.maxHeight = (maxMenuH - 95) + 'px';
            }
        } else {
            // Open downward
            msMenu.style.bottom = 'auto';
            msMenu.style.top = (rect.bottom + 4) + 'px';
            const maxMenuH = Math.max(140, Math.min(spaceBelow, 320));
            if (optionsContainer) {
                optionsContainer.style.maxHeight = (maxMenuH - 95) + 'px';
            }
        }
    }

    function openMsMenu() {
        if (!msMenu) return;
        // Portal to body to escape any card overflow-y: auto / overflow: hidden boundaries
        if (msMenu.parentNode !== document.body) {
            document.body.appendChild(msMenu);
        }
        msMenu.style.display = 'flex';
        msTrigger.setAttribute('aria-expanded', 'true');
        positionMenu();
        if (msSearch) {
            msSearch.focus();
        }
    }

    function closeMsMenu() {
        if (!msMenu) return;
        msMenu.style.display = 'none';
        msTrigger.setAttribute('aria-expanded', 'false');
        if (msSearch) {
            msSearch.value = '';
            filterMsOptions('');
        }
    }

    function filterMsOptions(query) {
        const q = (query || '').toLowerCase().trim();
        let visibleCount = 0;
        msOptions.forEach(opt => {
            const text = opt.getAttribute('data-search') || '';
            if (!q || text.includes(q)) {
                opt.style.display = 'flex';
                visibleCount++;
            } else {
                opt.style.display = 'none';
            }
        });
        if (msEmpty) {
            msEmpty.style.display = (visibleCount === 0) ? 'block' : 'none';
        }
    }

    if (msTrigger) {
        msTrigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const isOpen = msMenu.style.display === 'flex';
            if (isOpen) {
                closeMsMenu();
            } else {
                openMsMenu();
            }
        });
    }

    if (msSearch) {
        msSearch.addEventListener('click', function (e) {
            e.stopPropagation();
        });
        msSearch.addEventListener('input', function () {
            filterMsOptions(this.value);
        });
        msSearch.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeMsMenu();
                msTrigger.focus();
            }
        });
    }

    msCheckboxes.forEach(cb => {
        cb.addEventListener('change', function () {
            updateMsSummary();
        });
    });

    if (msSelectAllBtn) {
        msSelectAllBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            msOptions.forEach(opt => {
                if (opt.style.display !== 'none') {
                    const cb = opt.querySelector('.subject-multiselect-checkbox');
                    if (cb) cb.checked = true;
                }
            });
            updateMsSummary();
        });
    }

    if (msClearAllBtn) {
        msClearAllBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            msOptions.forEach(opt => {
                if (opt.style.display !== 'none') {
                    const cb = opt.querySelector('.subject-multiselect-checkbox');
                    if (cb) cb.checked = false;
                }
            });
            updateMsSummary();
        });
    }

    // Close when clicking outside both trigger and menu
    document.addEventListener('click', function (e) {
        if (msMenu && msMenu.style.display === 'flex') {
            if (!msTrigger.contains(e.target) && !msMenu.contains(e.target)) {
                closeMsMenu();
            }
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && msMenu && msMenu.style.display === 'flex') {
            closeMsMenu();
            msTrigger.focus();
        }
    });

    // Reposition dynamically when window is resized or any container is scrolled
    window.addEventListener('resize', positionMenu, { passive: true });
    document.addEventListener('scroll', function () {
        if (msMenu && msMenu.style.display === 'flex') {
            positionMenu();
        }
    }, { capture: true, passive: true });

    // Before form submit, ensure msMenu is inside the form so that native submission succeeds everywhere
    if (mappingForm) {
        mappingForm.addEventListener('submit', function () {
            if (msMenu && msMenu.parentNode === document.body) {
                mappingForm.appendChild(msMenu);
            }
        });
    }

    // Initial summary on page load
    updateMsSummary();

    // Prevent duplicate submissions on edit group forms
    document.querySelectorAll('.edit-group-form').forEach(function (form) {
        form.addEventListener('submit', function () {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Saving...';
            }
        });
    });
});

function closeEditModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
        }
    }
    closeModal(modalId);
}

function toggleGroupDrawer(groupKey, event) {
    if (event) {
        event.stopPropagation();
    }
    const drawer = document.getElementById('drawer-' + groupKey);
    const btn = document.getElementById('btn-toggle-' + groupKey);
    const icon = document.getElementById('icon-' + groupKey);
    if (!drawer) return;

    const isExpanded = drawer.style.display !== 'none';
    if (isExpanded) {
        drawer.style.display = 'none';
        if (btn) btn.setAttribute('aria-expanded', 'false');
        if (icon) icon.style.transform = 'rotate(0deg)';
    } else {
        drawer.style.display = 'table-row';
        if (btn) btn.setAttribute('aria-expanded', 'true');
        if (icon) icon.style.transform = 'rotate(90deg)';
    }
}

function handleGroupRowClick(event, groupKey) {
    if (event.target.closest('button') || event.target.closest('a') || event.target.closest('input') || event.target.closest('.modal-backdrop')) {
        return;
    }
    toggleGroupDrawer(groupKey, event);
}
</script>
@endsection

