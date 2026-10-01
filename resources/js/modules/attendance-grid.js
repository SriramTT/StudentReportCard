/**
 * Attendance Grid Vanilla JS Module
 * Handles immediate client-side preview calculations, batch working days pre-filling,
 * validation feedback, and keyboard spreadsheet navigation.
 *
 * NOTE: Server-side AttendanceService remains the sole authoritative calculation & validation source.
 */

export function initAttendanceGrid() {
    const table = document.getElementById('attendance-table');
    if (!table) return;

    const batchInput = document.getElementById('batch-total-working-days');
    const applyBtn = document.getElementById('btn-apply-working-days');
    const form = document.getElementById('attendance-batch-form');

    function updateRowPercentage(rowIndex) {
        const daysInput = table.querySelector(`.att-days-attended[data-index="${rowIndex}"]`);
        const totalInput = table.querySelector(`.att-total-working-days[data-index="${rowIndex}"]`);
        const badge = table.querySelector(`.att-percentage-badge[data-index="${rowIndex}"]`);

        if (!daysInput || !totalInput || !badge) return;

        const attended = parseInt(daysInput.value, 10);
        const total = parseInt(totalInput.value, 10);

        // Reset visual validation styling
        daysInput.style.borderColor = '';
        totalInput.style.borderColor = '';

        if (isNaN(attended) || isNaN(total)) {
            badge.textContent = '—';
            badge.className = 'badge badge-secondary att-percentage-badge';
            return;
        }

        if (attended < 0) {
            daysInput.style.borderColor = 'var(--color-danger, #ef4444)';
            badge.textContent = 'Invalid';
            badge.className = 'badge badge-danger att-percentage-badge';
            return;
        }

        if (total < 0) {
            totalInput.style.borderColor = 'var(--color-danger, #ef4444)';
            badge.textContent = 'Invalid';
            badge.className = 'badge badge-danger att-percentage-badge';
            return;
        }

        if (attended > total) {
            daysInput.style.borderColor = 'var(--color-danger, #ef4444)';
            badge.textContent = 'Exceeds Total';
            badge.className = 'badge badge-danger att-percentage-badge';
            return;
        }

        // Safe 0/0 Handling (CL-010, DEC-028)
        if (total === 0) {
            badge.textContent = 'N/A';
            badge.className = 'badge badge-secondary att-percentage-badge';
            return;
        }

        const pct = ((attended / total) * 100).toFixed(2);
        badge.textContent = pct + '%';
        badge.className = 'badge badge-primary att-percentage-badge';
    }

    // Attach input listeners
    table.addEventListener('input', (e) => {
        if (e.target.matches('.att-days-attended, .att-total-working-days')) {
            const rowIndex = e.target.getAttribute('data-index');
            updateRowPercentage(rowIndex);
        }
    });

    // Keyboard navigation (Arrow keys & Enter)
    table.addEventListener('keydown', (e) => {
        if (!e.target.matches('.att-days-attended, .att-total-working-days')) return;

        const isDays = e.target.classList.contains('att-days-attended');
        const currentIndex = parseInt(e.target.getAttribute('data-index'), 10);
        let targetElement = null;

        if (e.key === 'ArrowDown' || e.key === 'Enter') {
            e.preventDefault();
            const nextIndex = currentIndex + 1;
            const selector = isDays ? `.att-days-attended[data-index="${nextIndex}"]` : `.att-total-working-days[data-index="${nextIndex}"]`;
            targetElement = table.querySelector(selector);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const prevIndex = currentIndex - 1;
            const selector = isDays ? `.att-days-attended[data-index="${prevIndex}"]` : `.att-total-working-days[data-index="${prevIndex}"]`;
            targetElement = table.querySelector(selector);
        } else if (e.key === 'ArrowRight' && isDays) {
            targetElement = table.querySelector(`.att-total-working-days[data-index="${currentIndex}"]`);
        } else if (e.key === 'ArrowLeft' && !isDays) {
            targetElement = table.querySelector(`.att-days-attended[data-index="${currentIndex}"]`);
        }

        if (targetElement) {
            targetElement.focus();
            targetElement.select();
        }
    });

    // Apply to All button
    if (applyBtn && batchInput) {
        applyBtn.addEventListener('click', () => {
            const val = parseInt(batchInput.value, 10);
            if (isNaN(val) || val < 0) {
                alert('Please enter a valid non-negative number of working days.');
                batchInput.focus();
                return;
            }

            const totalInputs = table.querySelectorAll('.att-total-working-days');
            totalInputs.forEach((input) => {
                input.value = val;
                const rowIndex = input.getAttribute('data-index');
                updateRowPercentage(rowIndex);
            });
        });
    }

    // Client-side submit guard
    if (form) {
        form.addEventListener('submit', (e) => {
            const daysInputs = table.querySelectorAll('.att-days-attended');
            let hasError = false;

            daysInputs.forEach((input) => {
                const rowIndex = input.getAttribute('data-index');
                const totalInput = table.querySelector(`.att-total-working-days[data-index="${rowIndex}"]`);
                if (!totalInput) return;

                const attended = parseInt(input.value, 10);
                const total = parseInt(totalInput.value, 10);

                if (attended > total) {
                    hasError = true;
                    input.style.borderColor = 'var(--color-danger, #ef4444)';
                }
                if (attended < 0 || total < 0) {
                    hasError = true;
                }
            });

            if (hasError) {
                e.preventDefault();
                alert('Cannot submit: One or more rows have days attended exceeding total working days, or negative values.');
            }
        });
    }
}
