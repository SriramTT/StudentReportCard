@extends('layouts.app')

@section('title', 'Report Configurations - School Examination & Report Card Management System')
@section('page_title', 'Report Configurations')

@section('content')

<style>
.is-dragging {
    opacity: 0.45;
    background-color: #f1f5f9 !important;
}
.drag-over-top {
    border-top: 2.5px solid var(--color-primary, #2563eb) !important;
}
.drag-over-bottom {
    border-bottom: 2.5px solid var(--color-primary, #2563eb) !important;
}
.drag-handle {
    cursor: grab;
    user-select: none;
    font-size: 1.15rem;
    color: var(--color-text-muted, #64748b);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.2rem 0.4rem;
    border-radius: 3px;
}
.drag-handle:hover {
    background-color: #e2e8f0;
    color: var(--color-text-dark, #0f172a);
}
.drag-handle:active {
    cursor: grabbing !important;
}
</style>

<div class="alert alert-info" style="margin-bottom: 1.25rem;">
    <strong>Display vs Calculation:</strong> Assessment selections configured below control display visibility and column order on student reports. They do NOT dictate marks calculation participation.
</div>

<!-- Filter Bar: Academic Year & Report Type Filter -->
<div class="card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem;">
    <form method="GET" action="{{ route('reports.configurations.index') }}" style="display: flex; gap: 1.25rem; align-items: flex-end; flex-wrap: wrap;">
        <!-- Report Type Filter -->
        <div style="min-width: 240px;">
            <label for="filter_report_type" style="font-size: var(--font-size-xs, 0.75rem); font-weight: 600; color: var(--color-text-muted, #64748b); display: block; margin-bottom: 0.25rem;">
                Report Type Filter
            </label>
            <select name="report_type" id="filter_report_type" class="form-control" onchange="this.form.submit()">
                <option value="all" {{ ($selectedReportType ?? 'all') === 'all' ? 'selected' : '' }}>All Report Types</option>
                <option value="term" {{ ($selectedReportType ?? '') === 'term' ? 'selected' : '' }}>Term report card</option>
                <option value="mid_term" {{ ($selectedReportType ?? '') === 'mid_term' ? 'selected' : '' }}>Mid term Assessments</option>
                <option value="final" {{ ($selectedReportType ?? '') === 'final' ? 'selected' : '' }}>Final Annual Consolidated Report</option>
                <option value="custom" {{ ($selectedReportType ?? '') === 'custom' ? 'selected' : '' }}>Custom Report</option>
            </select>
        </div>

        <div>
            <a href="{{ route('reports.configurations.index') }}" class="btn btn-secondary btn-sm" style="padding: 0.45rem 0.85rem;">
                Reset Filters
            </a>
        </div>

        @can('create', App\Models\ReportConfiguration::class)
            <div style="margin-left: auto;">
                <button type="button" class="btn btn-primary" id="btn-add-report-config" onclick="openModal('create-report-config-modal')">
                    + Add Configuration
                </button>
            </div>
        @endcan
    </form>
</div>

<!-- Configurations Listing with child selections -->
<div class="report-config-layout report-config-layout--single" style="width: 100%;">
    <div class="report-config-list-col" style="width: 100%;">
        <div class="report-config-list">
        @forelse($configurations as $config)
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <div>
                        <h3 style="display: inline-block; margin-right: 0.75rem;">{{ $config->name }}</h3>
                        <span class="badge badge-primary">{{ $config->user_facing_type_label }}</span>
                        @if($config->academicYear)
                            <span style="font-size: var(--font-size-xs); color: var(--color-text-muted); margin-left: 0.5rem;">{{ $config->academicYear->name }}</span>
                        @endif
                    </div>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <span class="badge {{ $config->is_active ? 'badge-success' : 'badge-secondary' }}">
                            {{ $config->is_active ? 'Active' : 'Inactive' }}
                        </span>
                        @can('update', $config)
                            <button type="button" class="btn btn-outline btn-sm" onclick="openModal('edit-rc-modal-{{ $config->id }}')">
                                Edit
                            </button>
                            <form method="POST" action="{{ route('reports.configurations.update', $config) }}" style="display: inline;">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="name" value="{{ $config->name }}">
                                <input type="hidden" name="report_type" value="{{ $config->isMidTerm() ? 'exam_midterm' : ($config->isCustom() ? 'exam_custom' : $config->report_type->value) }}">
                                <input type="hidden" name="is_active" value="{{ $config->is_active ? '0' : '1' }}">
                                <button type="submit" class="btn btn-secondary btn-sm">
                                    {{ $config->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>

                            @can('delete', $config)
                                <button type="button" class="btn btn-danger btn-sm" onclick="openModal('delete-rc-modal-{{ $config->id }}')">
                                    Remove
                                </button>
                            @endcan

                            @can('delete', $config)
                                <div id="delete-rc-modal-{{ $config->id }}" class="modal-backdrop" style="display: none;">
                                    <div class="modal-dialog">
                                        <div class="modal-header">
                                            <h3 class="modal-title">Remove Report Configuration</h3>
                                            <button type="button" class="modal-close" onclick="closeModal('delete-rc-modal-{{ $config->id }}')">&times;</button>
                                        </div>
                                        <form method="POST" action="{{ route('reports.configurations.destroy', $config) }}">
                                            @csrf
                                            @method('DELETE')
                                            <div class="modal-body">
                                                <p>Are you sure you want to permanently remove configuration <strong>{{ $config->name }}</strong>?</p>
                                                <div class="alert alert-warning" style="margin-top: 1rem; font-size: 0.875rem;">
                                                    <strong>Warning:</strong> This operation cannot be undone. Configurations with assessment selections must have their assessment selections removed first, or be deactivated.
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" onclick="closeModal('delete-rc-modal-{{ $config->id }}')">Cancel</button>
                                                <button type="submit" class="btn btn-danger">Confirm Remove</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endcan

                            <div id="edit-rc-modal-{{ $config->id }}" class="modal-backdrop">
                                <div class="modal-dialog">
                                    <div class="modal-header">
                                        <h3>Edit Report Configuration</h3>
                                        <button type="button" class="modal-close" onclick="closeModal('edit-rc-modal-{{ $config->id }}')">&times;</button>
                                    </div>
                                    <form method="POST" action="{{ route('reports.configurations.update', $config) }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-body">
                                            <div class="form-group">
                                                <label for="edit_rc_name_{{ $config->id }}" class="form-label">Configuration Name <span style="color: var(--color-danger);">*</span></label>
                                                <input type="text" name="name" id="edit_rc_name_{{ $config->id }}" class="form-control" value="{{ $config->name }}" required maxlength="100">
                                            </div>
                                            <div class="form-group">
                                                <label for="edit_rc_type_{{ $config->id }}" class="form-label">Report Type <span style="color: var(--color-danger);">*</span></label>
                                                <select name="report_type" id="edit_rc_type_{{ $config->id }}" class="form-control" required>
                                                    <option value="term" {{ $config->report_type->value === 'term' ? 'selected' : '' }}>Term report card</option>
                                                    <option value="exam_midterm" {{ $config->isMidTerm() ? 'selected' : '' }}>Mid term Assessments</option>
                                                    <option value="final" {{ $config->report_type->value === 'final' ? 'selected' : '' }}>Final Annual Consolidated Report</option>
                                                    <option value="exam_custom" {{ $config->isCustom() ? 'selected' : '' }}>Custom Report</option>
                                                </select>
                                                <small style="color: var(--color-text-muted); font-size: var(--font-size-xs); display: block; margin-top: 0.25rem;">
                                                    Mid term Assessments allows exactly one assessment. Custom Report allows multiple assessments.
                                                </small>
                                            </div>
                                            <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                                                <input type="checkbox" name="is_active" id="edit_rc_active_{{ $config->id }}" value="1" {{ $config->is_active ? 'checked' : '' }}>
                                                <label for="edit_rc_active_{{ $config->id }}" style="cursor: pointer;">Active</label>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" onclick="closeModal('edit-rc-modal-{{ $config->id }}')">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endcan
                    </div>
                </div>
                <div class="card-body" style="padding-bottom: 0.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <h4 style="font-size: var(--font-size-sm); color: var(--color-text-muted); margin-bottom: 0;">
                            Report Assessment Display Order & Selection
                        </h4>
                        <div id="reorder-status-{{ $config->id }}" style="display: none; font-size: var(--font-size-xs); font-weight: 600;"></div>
                    </div>

                    <div class="data-table-wrapper" style="margin-bottom: 1.25rem;">
                        <table class="data-table reorderable-table" data-config-id="{{ $config->id }}" data-reorder-url="{{ route('reports.selections.reorder', $config) }}">
                            <thead>
                                <tr>
                                    <th style="width: 38px; text-align: center;"></th>
                                    <th style="width: 70px;">Order</th>
                                    <th>Assessment</th>
                                    <th>Display on Report</th>
                                    <th style="width: 140px; text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($config->assessmentSelections as $sel)
                                    <tr class="assessment-reorder-row" data-id="{{ $sel->id }}">
                                        <td style="text-align: center; vertical-align: middle;">
                                            @can('update', $config)
                                                <span class="drag-handle" title="Drag to reorder" style="cursor: grab; color: var(--color-text-muted); font-size: 1.1rem; user-select: none;">
                                                    &#x2630;
                                                </span>
                                            @endcan
                                        </td>
                                        <td style="vertical-align: middle; font-weight: 600;">
                                            <span class="order-badge badge badge-secondary">
                                                {{ $sel->display_order }}
                                            </span>
                                        </td>
                                        <td>
                                            <strong>{{ $sel->assessment?->name }}</strong>
                                            <div style="font-size: var(--font-size-xs); color: var(--color-text-muted);">
                                                @if($sel->assessment?->assessmentType)
                                                    Type: {{ $sel->assessment->assessmentType->name }}
                                                @endif
                                            </div>
                                        </td>
                                        <td style="vertical-align: middle;">
                                            <span class="badge {{ $sel->is_displayed ? 'badge-success' : 'badge-secondary' }}">
                                                {{ $sel->is_displayed ? 'Displayed' : 'Hidden' }}
                                            </span>
                                        </td>
                                        <td style="text-align: right; vertical-align: middle;">
                                            <div class="row-actions" style="justify-content: flex-end;">
                                                @can('update', $config)
                                                    <form method="POST" action="{{ route('reports.selections.update', [$config, $sel]) }}" style="display: inline;">
                                                        @csrf
                                                        @method('PUT')
                                                        <input type="hidden" name="is_displayed" value="{{ $sel->is_displayed ? '0' : '1' }}">
                                                        <button type="submit" class="btn btn-secondary btn-sm" title="{{ $sel->is_displayed ? 'Hide from generated reports' : 'Show on generated reports' }}">
                                                            {{ $sel->is_displayed ? 'Hide' : 'Show' }}
                                                        </button>
                                                    </form>

                                                    <form method="POST" action="{{ route('reports.selections.destroy', [$config, $sel]) }}" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this assessment from the report display?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger btn-sm">
                                                            Remove
                                                        </button>
                                                    </form>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--color-text-muted); padding: 1.25rem;">
                                            No assessments selected for display yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Add Assessment Selection Form -->
                    @can('update', $config)
                        @if($config->isMidTerm() && $config->assessmentSelections->count() >= 1)
                            <div class="alert alert-info" style="margin-bottom: 1rem; font-size: var(--font-size-xs); display: flex; align-items: center; justify-content: space-between;">
                                <span>
                                    <strong>Single Assessment Configured:</strong> Mid term Assessments allow exactly one assessment. Remove the current assessment to change it, or edit this configuration into a Custom Report.
                                </span>
                                <span class="badge badge-primary">Mid term Limit (1/1)</span>
                            </div>
                        @else
                            <form method="POST" action="{{ route('reports.selections.store', $config) }}" style="display: flex; gap: 0.75rem; align-items: flex-end; background-color: #f8fafc; padding: 0.75rem 1rem; border-radius: var(--border-radius-md); border: 1px solid var(--color-border); margin-bottom: 1rem;">
                                @csrf
                                <div style="flex: 1;">
                                    <label style="font-size: var(--font-size-xs); font-weight: 600; display: block; margin-bottom: 0.25rem;">
                                        Add Assessment to Report {{ $config->isMidTerm() ? '(Exactly 1 allowed)' : '' }}:
                                    </label>
                                    <select name="assessment_id" class="form-control" style="font-size: var(--font-size-sm); padding: 0.4rem;" required>
                                        <option value="">-- Choose Assessment --</option>
                                        @php
                                            $selectedAssessmentIds = $config->assessmentSelections->pluck('assessment_id')->toArray();
                                            $selectableAssessments = $availableAssessments->whereNotIn('id', $selectedAssessmentIds);
                                            if ($config->academic_year_id) {
                                                $selectableAssessments = $selectableAssessments->where('academic_year_id', $config->academic_year_id);
                                            }
                                        @endphp
                                        @foreach($selectableAssessments as $a)
                                            <option value="{{ $a->id }}">
                                                {{ $a->name }} ({{ $a->term ? $a->term->name : 'No Term' }} &bull; {{ $a->academicYear?->name }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <input type="hidden" name="is_displayed" value="1">
                                <button type="submit" class="btn btn-primary btn-sm" style="padding: 0.5rem 1rem;">
                                    Add Assessment
                                </button>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>
        @empty
            <div class="card">
                <div class="card-body" style="text-align: center; color: var(--color-text-muted); padding: 3rem;">
                    No report configurations found matching your filter criteria.
                </div>
            </div>
        @endforelse
        </div>
    </div>
    <div class="report-config-side-col" style="display: none;">
        <div class="card report-config-sticky-card">
            <h3>New Report Configuration</h3>
        </div>
    </div>
</div>

    <!-- Create Report Configuration Modal -->
    @can('create', App\Models\ReportConfiguration::class)
    <div id="create-report-config-modal" class="modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="create-report-config-modal-title" onclick="if (event.target === this) closeModal('create-report-config-modal')">
        <div class="modal-dialog modal-dialog--lg">
            <div class="modal-header">
                <h3 id="create-report-config-modal-title">New Report Configuration</h3>
                <button type="button" class="modal-close" aria-label="Close" onclick="closeModal('create-report-config-modal')">&times;</button>
            </div>
            <form method="POST" action="{{ route('reports.configurations.store') }}">
                @csrf
                <input type="hidden" name="_form_context" value="create_report_config">

                <div class="modal-body">
                    <div class="form-group">
                        <label for="create_rc_name" class="form-label">Configuration Name <span style="color: var(--color-danger);">*</span></label>
                        <input type="text" name="name" id="create_rc_name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Term 1 Report Card Layout" value="{{ old('name') }}" required maxlength="100">
                        @error('name')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="create_rc_report_type" class="form-label">Report Type <span style="color: var(--color-danger);">*</span></label>
                        <select name="report_type" id="create_rc_report_type" class="form-control @error('report_type') is-invalid @enderror" required>
                            <option value="term" {{ old('report_type') == 'term' ? 'selected' : '' }}>Term report card</option>
                            <option value="exam_midterm" {{ old('report_type') == 'exam_midterm' ? 'selected' : '' }}>Mid term Assessments</option>
                            <option value="final" {{ old('report_type') == 'final' ? 'selected' : '' }}>Final Annual Consolidated Report</option>
                            <option value="exam_custom" {{ old('report_type') == 'exam_custom' ? 'selected' : '' }}>Custom Report</option>
                        </select>
                        <small style="color: var(--color-text-muted); font-size: var(--font-size-xs); display: block; margin-top: 0.25rem;">
                            Mid term Assessments allows exactly one assessment. Custom Report allows multiple assessments.
                        </small>
                        @error('report_type')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="is_active" id="create_rc_is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                        <label for="create_rc_is_active" style="font-size: var(--font-size-sm); cursor: pointer;">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('create-report-config-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Report Configuration</button>
                </div>
            </form>
        </div>
    </div>

    @if($errors->any() && old('_form_context') === 'create_report_config')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                openModal('create-report-config-modal');
            });
        </script>
    @endif
    @endcan

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.reorderable-table').forEach(function (table) {
        let draggedRow = null;
        let originalOrder = [];
        const configId = table.dataset.configId;
        const reorderUrl = table.dataset.reorderUrl;
        const statusEl = document.getElementById('reorder-status-' + configId);

        function getRows() {
            return Array.from(table.querySelectorAll('tbody tr.assessment-reorder-row'));
        }

        function updateBadges() {
            getRows().forEach(function (row, idx) {
                const badge = row.querySelector('.order-badge');
                if (badge) badge.textContent = '#' + (idx + 1);
            });
        }

        getRows().forEach(function (row) {
            const handle = row.querySelector('.drag-handle');
            if (!handle) return;

            handle.addEventListener('mousedown', function () {
                row.setAttribute('draggable', 'true');
            });

            handle.addEventListener('mouseup', function () {
                row.setAttribute('draggable', 'false');
            });

            row.addEventListener('dragstart', function (e) {
                draggedRow = row;
                originalOrder = getRows().map(r => r.dataset.id);
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', row.dataset.id);
                row.classList.add('is-dragging');
            });

            row.addEventListener('dragend', function () {
                row.classList.remove('is-dragging');
                row.setAttribute('draggable', 'false');
                draggedRow = null;
                table.querySelectorAll('tr.drag-over-top, tr.drag-over-bottom').forEach(function (el) {
                    el.classList.remove('drag-over-top', 'drag-over-bottom');
                });
            });

            row.addEventListener('dragover', function (e) {
                e.preventDefault();
                if (!draggedRow || draggedRow === row) return;
                e.dataTransfer.dropEffect = 'move';

                const rect = row.getBoundingClientRect();
                const midY = rect.top + rect.height / 2;
                if (e.clientY < midY) {
                    row.classList.add('drag-over-top');
                    row.classList.remove('drag-over-bottom');
                } else {
                    row.classList.add('drag-over-bottom');
                    row.classList.remove('drag-over-top');
                }
            });

            row.addEventListener('dragleave', function () {
                row.classList.remove('drag-over-top', 'drag-over-bottom');
            });

            row.addEventListener('drop', function (e) {
                e.preventDefault();
                row.classList.remove('drag-over-top', 'drag-over-bottom');
                if (!draggedRow || draggedRow === row) return;

                const rect = row.getBoundingClientRect();
                const midY = rect.top + rect.height / 2;
                if (e.clientY < midY) {
                    row.parentNode.insertBefore(draggedRow, row);
                } else {
                    row.parentNode.insertBefore(draggedRow, row.nextSibling);
                }

                updateBadges();

                const newOrder = getRows().map(r => r.dataset.id);
                if (JSON.stringify(newOrder) === JSON.stringify(originalOrder)) {
                    return;
                }

                if (statusEl) {
                    statusEl.textContent = 'Saving order...';
                    statusEl.style.color = 'var(--color-text-muted, #64748b)';
                    statusEl.style.display = 'block';
                }

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    || document.querySelector('input[name="_token"]')?.value;

                fetch(reorderUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ selection_ids: newOrder })
                })
                .then(function (response) {
                    if (!response.ok) {
                        return response.json().then(function (json) { throw new Error(json.message || 'Server error'); });
                    }
                    return response.json();
                })
                .then(function (data) {
                    if (statusEl) {
                        statusEl.textContent = '✓ Order saved';
                        statusEl.style.color = 'var(--color-success, #16a34a)';
                        setTimeout(function () { statusEl.style.display = 'none'; }, 2000);
                    }
                    originalOrder = newOrder;
                })
                .catch(function (err) {
                    if (statusEl) {
                        statusEl.textContent = '✗ Failed to save order. Restoring...';
                        statusEl.style.color = 'var(--color-danger, #dc2626)';
                    }
                    const tbody = table.querySelector('tbody');
                    originalOrder.forEach(function (id) {
                        const targetRow = tbody.querySelector('tr[data-id="' + id + '"]');
                        if (targetRow) tbody.appendChild(targetRow);
                    });
                    updateBadges();
                    alert('Could not save reordered assessments: ' + err.message);
                });
            });
        });
    });
});
</script>
@endsection
