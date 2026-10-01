@extends('layouts.app')

@section('title', 'Academic Terms - School Examination & Report Card Management System')
@section('page_title', 'Academic Terms')

@section('content')
<div class="filter-bar" style="display: flex; justify-content: right; align-items: center; flex-wrap: wrap; gap: 1rem;">

    @if($terms->count() > 1)
        @can('reorder', App\Models\Term::class)
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span id="reorder-status-msg" style="font-size: var(--font-size-xs); color: var(--color-text-muted); display: none;">Order modified</span>
                <button type="button" id="save-order-btn" class="btn btn-primary btn-sm" style="display: none;" onclick="saveTermsOrder()">
                    Save Order
                </button>
            </div>
        @endcan
    @endif
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h3 style="margin: 0;">Configured Terms</h3>
        @can('create', App\Models\Term::class)
            <button type="button" class="btn btn-primary" id="btn-create-term" onclick="openModal('create-term-modal')">
                + Add Term
            </button>
        @endcan
    </div>
            <div class="data-table-wrapper">
                <table class="data-table" id="terms-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Order</th>
                            <th>Term Name</th>
                            <th>Academic Year</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="terms-table-body">
                        @forelse($terms as $index => $term)
                            <tr class="term-row" draggable="true" data-term-id="{{ $term->id }}" style="cursor: grab;">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span class="drag-handle" style="cursor: grab; font-size: 1.1rem; color: var(--color-text-muted);" title="Drag to reorder">&#9776;</span>
                                        <span class="term-order-badge" style="font-weight: 700;">{{ $index + 1 }}</span>
                                    </div>
                                </td>
                                <td><strong>{{ $term->name }}</strong></td>
                                <td>{{ $term->academicYear?->name }}</td>
                                <td>
                                    @can('update', $term)
                                        <div class="row-actions">
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="openModal('edit-term-modal-{{ $term->id }}')">
                                                Edit
                                            </button>

                                            @can('delete', $term)
                                                <button type="button" class="btn btn-danger btn-sm" onclick="openModal('delete-term-modal-{{ $term->id }}')">
                                                    Remove
                                                </button>
                                            @endcan
                                        </div>

                                        @can('delete', $term)
                                            <div id="delete-term-modal-{{ $term->id }}" class="modal-backdrop" style="display: none;">
                                                <div class="modal-dialog">
                                                    <div class="modal-header">
                                                        <h3 class="modal-title">Remove Term</h3>
                                                        <button type="button" class="modal-close" onclick="closeModal('delete-term-modal-{{ $term->id }}')">&times;</button>
                                                    </div>
                                                    <form method="POST" action="{{ route('terms.destroy', $term) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to permanently remove term <strong>{{ $term->name }}</strong>?</p>
                                                            <div class="alert alert-warning" style="margin-top: 1rem; font-size: 0.875rem;">
                                                                <strong>Warning:</strong> This operation cannot be undone. Terms referenced by assessments, attendance records, or generated report cards cannot be removed.
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" onclick="closeModal('delete-term-modal-{{ $term->id }}')">Cancel</button>
                                                            <button type="submit" class="btn btn-danger">Confirm Remove</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        @endcan

                                        <div id="edit-term-modal-{{ $term->id }}" class="modal-backdrop">
                                            <div class="modal-dialog">
                                                <div class="modal-header">
                                                    <h3>Edit Term</h3>
                                                    <button type="button" class="modal-close" onclick="closeModal('edit-term-modal-{{ $term->id }}')">&times;</button>
                                                </div>
                                                <form method="POST" action="{{ route('terms.update', $term) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="modal-body">
                                                        <div class="form-group">
                                                            <label class="form-label">Term Name <span style="color: var(--color-danger);">*</span></label>
                                                            <input type="text" name="name" class="form-control" value="{{ old('name', $term->name) }}" required maxlength="50">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" onclick="closeModal('edit-term-modal-{{ $term->id }}')">Cancel</button>
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
                                    No terms configured for this academic year. Add terms using the form.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create Term Modal -->
    @can('create', App\Models\Term::class)
    <div id="create-term-modal" class="modal-backdrop" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="create-term-modal-title" onclick="if (event.target === this) closeModal('create-term-modal')">
        <div class="modal-dialog modal-dialog--md">
            <div class="modal-header">
                <h3 class="modal-title" id="create-term-modal-title">Add New Term</h3>
                <button type="button" class="modal-close" aria-label="Close" onclick="closeModal('create-term-modal')">&times;</button>
            </div>
            <form method="POST" action="{{ route('terms.store') }}">
                @csrf
                <input type="hidden" name="_form_context" value="create_term">
                <input type="hidden" name="academic_year_id" id="academic_year_id" value="{{ $selectedYearId }}">

                <div class="modal-body">
                    <div class="form-group">
                        <label for="create_term_name" class="form-label">Term Name <span style="color: var(--color-danger);">*</span></label>
                        <input type="text" name="name" id="create_term_name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Term 1, Semester 1, Pre-Final" value="{{ old('name') }}" required maxlength="50">
                        <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Flexible naming (e.g. Term 1..5, Semester 1..2).</small>
                        @error('name')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('create-term-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Term</button>
                </div>
            </form>
        </div>
    </div>

    @if($errors->any() && old('_form_context') === 'create_term')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                openModal('create-term-modal');
            });
        </script>
    @endif
    @endcan

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tableBody = document.getElementById('terms-table-body');
    const saveBtn = document.getElementById('save-order-btn');
    const statusMsg = document.getElementById('reorder-status-msg');
    let draggedRow = null;

    if (!tableBody) return;

    const rows = tableBody.querySelectorAll('.term-row');
    rows.forEach(row => {
        row.addEventListener('dragstart', function (e) {
            draggedRow = this;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', this.innerHTML);
            this.style.opacity = '0.5';
        });

        row.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            const targetRow = this;
            if (targetRow && targetRow !== draggedRow) {
                const bounding = targetRow.getBoundingClientRect();
                const offset = bounding.y + (bounding.height / 2);
                if (e.clientY - offset > 0) {
                    targetRow.after(draggedRow);
                } else {
                    targetRow.before(draggedRow);
                }
                updateRowOrderNumbers();
                showSaveButton();
            }
        });

        row.addEventListener('dragend', function () {
            this.style.opacity = '1';
            draggedRow = null;
        });
    });

    function updateRowOrderNumbers() {
        const currentRows = tableBody.querySelectorAll('.term-row');
        currentRows.forEach((r, idx) => {
            const badge = r.querySelector('.term-order-badge');
            if (badge) {
                badge.textContent = (idx + 1);
            }
        });
    }

    function showSaveButton() {
        if (saveBtn) {
            saveBtn.style.display = 'inline-block';
            if (statusMsg) {
                statusMsg.style.display = 'inline';
                statusMsg.textContent = 'Unsaved order changes';
                statusMsg.style.color = 'var(--color-warning, #d97706)';
            }
        }
    }

    window.saveTermsOrder = function () {
        const termIds = [];
        tableBody.querySelectorAll('.term-row').forEach(r => {
            const id = r.getAttribute('data-term-id');
            if (id) termIds.push(parseInt(id, 10));
        });

        const academicYearId = document.getElementById('academic_year_id').value;

        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
        }

        fetch('{{ route("terms.reorder") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                academic_year_id: parseInt(academicYearId, 10),
                term_ids: termIds
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Server error while saving order');
            }
            return response.json();
        })
        .then(data => {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.style.display = 'none';
            }
            if (statusMsg) {
                statusMsg.style.display = 'inline';
                statusMsg.textContent = 'Order saved successfully!';
                statusMsg.style.color = 'var(--color-success, #16a34a)';
                setTimeout(() => {
                    statusMsg.style.display = 'none';
                }, 3000);
            }
        })
        .catch(err => {
            alert('Failed to save order: ' + err.message);
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Order';
            }
        });
    };
});
</script>
@endsection
