/**
 * Phase 11: Mark Entry Grid Vanilla JS Engine
 * Features:
 * - High-speed spreadsheet keyboard traversal (Enter, Tab, Shift+Tab, Arrows)
 * - Tri-state/Absence typing handling ('A'/'a' -> Absent, Backspace/Empty -> Blank, Numbers -> Numeric/Zero)
 * - In-memory dirty state tracking
 * - Transactional batch save AJAX dispatch (submits changed rows, updates commit status)
 * - In-memory student filtering (Search by name/admission no, filter by status)
 * - Navigation protection with unsaved changes warning
 * - Robust cascading context filter handling (Year -> Class -> Section -> Subject -> Assessment)
 */

window._markGridIsDirty = false;

// Global Context Change Handler with Dirty State Verification & Child Resets
window.handleContextChange = function (level, element) {
    if (!element) return;
    const form = element.closest('form');
    if (!form) return;

    if (window._markGridIsDirty) {
        const confirmed = confirm('You have unsaved mark entries. Changing the evaluation context will discard unsaved changes. Proceed?');
        if (!confirmed) {
            if (element.dataset.prevVal !== undefined) {
                element.value = element.dataset.prevVal;
            }
            return;
        }
        window._markGridIsDirty = false;
    }

    const classSelect = form.querySelector('#filter-class');
    const secSelect = form.querySelector('#filter-section');
    const subSelect = form.querySelector('#filter-subject');
    const asmtSelect = form.querySelector('#filter-assessment');

    if (level === 'year') {
        if (classSelect) classSelect.value = '';
        if (secSelect) secSelect.value = '';
        if (subSelect) subSelect.value = '';
        if (asmtSelect) asmtSelect.value = '';
    } else if (level === 'class') {
        if (secSelect) secSelect.value = '';
        if (subSelect) subSelect.value = '';
        if (asmtSelect) asmtSelect.value = '';
    } else if (level === 'section') {
        if (subSelect) subSelect.value = '';
        if (asmtSelect) asmtSelect.value = '';
    } else if (level === 'subject') {
        if (asmtSelect) asmtSelect.value = '';
    }

    form.submit();
};

export function initContextFilters() {
    const contextSelects = document.querySelectorAll('.mark-context-filter');
    contextSelects.forEach(select => {
        select.dataset.prevVal = select.value;
        select.addEventListener('focus', () => {
            select.dataset.prevVal = select.value;
        });
    });

    window.addEventListener('beforeunload', (e) => {
        if (window._markGridIsDirty) {
            e.preventDefault();
            e.returnValue = 'You have unsaved marks. Are you sure you want to leave?';
            return e.returnValue;
        }
    });
}

export function initMarkGrid() {
    const gridForm = document.getElementById('mark-batch-form');
    if (!gridForm) return;

    const saveBtn = document.getElementById('mark-save-btn');
    const table = document.getElementById('mark-entry-table');
    const maxMarks = parseFloat(gridForm.dataset.maxMarks || '100');
    const isReadOnly = gridForm.dataset.readOnly === 'true';

    const countTotal = document.getElementById('stat-total');
    const countEntered = document.getElementById('stat-entered');
    const countBlank = document.getElementById('stat-blank');
    const countAbsent = document.getElementById('stat-absent');
    const countZero = document.getElementById('stat-zero');

    const searchInput = document.getElementById('student-search-input');
    const statusFilter = document.getElementById('status-filter-select');
    const feedbackBanner = document.getElementById('mark-feedback-banner');

    if (!table) return;

    const inputs = Array.from(table.querySelectorAll('.mark-input'));
    let isDirty = false;

    // 1. Initial Summary Calculation
    updateSummaryStats();

    // 2. Keyboard Navigation & Cell Manipulation
    inputs.forEach((input, index) => {
        // Track original state
        input.dataset.originalValue = input.value.trim();
        input.dataset.originalStatus = input.dataset.resultStatus;

        input.addEventListener('keydown', (e) => {
            if (isReadOnly) return;

            // Enter or Down Arrow -> Move to next row
            if (e.key === 'Enter' || e.key === 'ArrowDown') {
                e.preventDefault();
                moveToInput(index + 1);
            }
            // Up Arrow -> Move to previous row
            else if (e.key === 'ArrowUp') {
                e.preventDefault();
                moveToInput(index - 1);
            }
        });

        input.addEventListener('input', () => {
            if (isReadOnly) return;
            handleCellInput(input);
            updateSummaryStats();
            checkDirtyState();
        });
    });

    function moveToInput(targetIndex) {
        let curr = targetIndex;
        while (curr >= 0 && curr < inputs.length) {
            const row = inputs[curr].closest('tr');
            if (row && row.style.display !== 'none') {
                inputs[curr].focus();
                inputs[curr].select();
                return;
            }
            curr += (targetIndex > inputs.indexOf(document.activeElement) ? 1 : -1);
        }
    }

    function handleCellInput(input) {
        const raw = input.value.trim();
        const row = input.closest('tr');
        const statusBadge = row.querySelector('.mark-status-pill');
        const saveState = row.querySelector('.mark-save-state');

        // Reset state classes
        input.classList.remove('is-absent', 'is-zero', 'is-invalid');

        // ABSENT handling ('A' or 'a')
        if (raw.toUpperCase() === 'A') {
            input.value = 'A';
            input.dataset.resultStatus = 'absent';
            input.classList.add('is-absent');
            updatePill(statusBadge, 'absent', 'Absent');
        }
        // BLANK handling (empty)
        else if (raw === '') {
            input.dataset.resultStatus = 'blank';
            updatePill(statusBadge, 'blank', 'Blank');
        }
        // NUMERIC handling
        else {
            const num = parseFloat(raw);
            if (isNaN(num) || num < 0 || num > maxMarks) {
                input.classList.add('is-invalid');
                input.dataset.resultStatus = 'numeric';
                updatePill(statusBadge, 'invalid', 'Invalid');
            } else {
                input.dataset.resultStatus = 'numeric';
                if (num === 0) {
                    input.classList.add('is-zero');
                    updatePill(statusBadge, 'numeric', 'Zero (0.00)');
                } else {
                    updatePill(statusBadge, 'numeric', 'Entered');
                }
            }
        }

        // Check if row is modified compared to original
        const isCellChanged = input.value.trim() !== input.dataset.originalValue ||
                              input.dataset.resultStatus !== input.dataset.originalStatus;

        if (isCellChanged) {
            input.classList.add('is-dirty');
            saveState.textContent = 'Unsaved';
            saveState.className = 'mark-save-state state-unsaved';
        } else {
            input.classList.remove('is-dirty');
            saveState.textContent = 'Saved';
            saveState.className = 'mark-save-state state-saved';
        }
    }

    function updatePill(badge, status, text) {
        if (!badge) return;
        badge.className = `mark-status-pill status-${status}`;
        badge.textContent = text;
    }

    function checkDirtyState() {
        const hasDirtyCells = inputs.some(i => i.classList.contains('is-dirty'));
        const hasInvalidCells = inputs.some(i => i.classList.contains('is-invalid'));

        isDirty = hasDirtyCells;
        window._markGridIsDirty = isDirty;
        saveBtn.disabled = !isDirty || hasInvalidCells || isReadOnly;

        if (hasInvalidCells) {
            saveBtn.title = 'Cannot save: some entered marks exceed maximum marks or are negative.';
        } else if (isDirty) {
            saveBtn.title = 'Click to save modified marks';
        } else {
            saveBtn.title = 'No unsaved modifications';
        }
    }

    function updateSummaryStats() {
        let total = inputs.length;
        let entered = 0;
        let blank = 0;
        let absent = 0;
        let zero = 0;

        inputs.forEach(input => {
            const status = input.dataset.resultStatus;
            const val = parseFloat(input.value.trim());

            if (status === 'absent') {
                absent++;
            } else if (status === 'blank') {
                blank++;
            } else if (status === 'numeric') {
                if (val === 0) {
                    zero++;
                } else {
                    entered++;
                }
            }
        });

        if (countTotal) countTotal.textContent = total;
        if (countEntered) countEntered.textContent = entered;
        if (countBlank) countBlank.textContent = blank;
        if (countAbsent) countAbsent.textContent = absent;
        if (countZero) countZero.textContent = zero;
    }

    // 3. In-Memory Search & Status Filtering
    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }
    if (statusFilter) {
        statusFilter.addEventListener('change', applyFilters);
    }

    function applyFilters() {
        const term = (searchInput?.value || '').toLowerCase().trim();
        const selectedStatus = statusFilter?.value || 'all';

        table.querySelectorAll('tbody tr').forEach(row => {
            const input = row.querySelector('.mark-input');
            if (!input) return;

            const studentName = (row.dataset.studentName || '').toLowerCase();
            const admissionNo = (row.dataset.admissionNumber || '').toLowerCase();
            const rollNo = (row.dataset.rollNumber || '').toLowerCase();
            const status = input.dataset.resultStatus;
            const val = parseFloat(input.value.trim());

            const matchesSearch = !term || studentName.includes(term) || admissionNo.includes(term) || rollNo.includes(term);

            let matchesStatus = true;
            if (selectedStatus === 'entered') {
                matchesStatus = status === 'numeric' && val > 0;
            } else if (selectedStatus === 'blank') {
                matchesStatus = status === 'blank';
            } else if (selectedStatus === 'absent') {
                matchesStatus = status === 'absent';
            } else if (selectedStatus === 'zero') {
                matchesStatus = status === 'numeric' && val === 0;
            }

            row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
        });
    }

    // 4. Batch Save AJAX Submission
    gridForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (!isDirty || isReadOnly) return;

        // Gather only modified (dirty) rows
        const changedInputs = inputs.filter(i => i.classList.contains('is-dirty'));
        if (changedInputs.length === 0) return;

        // Check for client validation errors
        if (changedInputs.some(i => i.classList.contains('is-invalid'))) {
            showFeedback('error', 'Please resolve highlighted errors before saving.');
            return;
        }

        const payloadMarks = changedInputs.map(input => {
            const status = input.dataset.resultStatus;
            let val = null;
            if (status === 'numeric') {
                val = input.value.trim();
            }
            return {
                student_academic_record_id: parseInt(input.dataset.sarId),
                student_subject_allocation_id: parseInt(input.dataset.allocId),
                result_status: status,
                mark_value: val,
            };
        });

        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || gridForm.querySelector('input[name="_token"]')?.value;

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';
        showFeedback('info', `Saving ${payloadMarks.length} modified mark(s)...`);

        const academicYearId = parseInt(
            gridForm.dataset.academicYearId ||
            gridForm.dataset.yearId ||
            gridForm.querySelector('input[name="academic_year_id"]')?.value
        );
        const classId = parseInt(
            gridForm.dataset.classId ||
            gridForm.querySelector('input[name="class_id"]')?.value
        );
        const sectionId = parseInt(
            gridForm.dataset.sectionId ||
            gridForm.querySelector('input[name="section_id"]')?.value
        );
        const subjectId = parseInt(
            gridForm.dataset.subjectId ||
            gridForm.querySelector('input[name="subject_id"]')?.value
        );
        const assessmentId = parseInt(
            gridForm.dataset.assessmentId ||
            gridForm.querySelector('input[name="assessment_id"]')?.value
        );

        try {
            const response = await fetch(gridForm.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({
                    academic_year_id: academicYearId,
                    class_id: classId,
                    section_id: sectionId,
                    subject_id: subjectId,
                    assessment_id: assessmentId,
                    marks: payloadMarks,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                const errMsg = data.message || 'Failed to save marks. Please check your inputs.';
                showFeedback('error', errMsg);
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Mark Changes';
                return;
            }

            // Success: Update state
            changedInputs.forEach(input => {
                input.dataset.originalValue = input.value.trim();
                input.dataset.originalStatus = input.dataset.resultStatus;
                input.classList.remove('is-dirty');

                const row = input.closest('tr');
                const saveState = row.querySelector('.mark-save-state');
                if (saveState) {
                    saveState.textContent = 'Saved';
                    saveState.className = 'mark-save-state state-saved';
                }
            });

            isDirty = false;
            window._markGridIsDirty = false;
            saveBtn.disabled = true;
            saveBtn.textContent = 'Save Mark Changes';
            showFeedback('success', data.message || `Successfully saved ${data.saved} mark(s).`);
        } catch (err) {
            showFeedback('error', 'Network error occurred while saving marks. Please try again.');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Mark Changes';
        }
    });

    function showFeedback(type, message) {
        if (!feedbackBanner) return;

        feedbackBanner.style.display = 'block';
        feedbackBanner.className = `alert alert-${type === 'error' ? 'danger' : (type === 'success' ? 'success' : 'info')}`;
        feedbackBanner.textContent = message;

        if (type === 'success') {
            setTimeout(() => {
                feedbackBanner.style.display = 'none';
            }, 5000);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initContextFilters();
    initMarkGrid();
});
