@extends('layouts.app')

@section('title', 'Import Students - School Examination & Report Card Management System')
@section('page_title', 'Import Students via CSV')

@section('content')
<div style="max-width: 800px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Import Form Card -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;">Upload Student Roster</h3>
            <a href="{{ route('students.index') }}" class="btn btn-secondary btn-sm">
                ← Back to Directory
            </a>
        </div>

        <form method="POST" action="{{ route('students.import') }}" enctype="multipart/form-data" style="padding: 1.5rem;">
            @csrf

            <h4 style="margin-top: 0; margin-bottom: 1rem; color: var(--color-primary); border-bottom: 2px solid var(--color-border, #e5e7eb); padding-bottom: 0.5rem;">
                1. Target Academic Context
            </h4>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                <div class="form-group">
                    <label for="academic_year_id" class="form-label">
                        Academic Year <span style="color: var(--color-danger, #dc2626);">*</span>
                    </label>
                    <select id="academic_year_id" name="academic_year_id" class="form-select @error('academic_year_id') is-invalid @enderror" required>
                        <option value="">Select Academic Year</option>
                        @foreach($academicYears as $year)
                            <option value="{{ $year->id }}" {{ old('academic_year_id') == $year->id ? 'selected' : '' }}>
                                {{ $year->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('academic_year_id')
                        <div class="form-error" style="color: var(--color-danger, #dc2626); font-size: var(--font-size-xs); margin-top: 0.25rem;">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="class_id" class="form-label">
                        Class <span style="color: var(--color-danger, #dc2626);">*</span>
                    </label>
                    <select id="class_id" name="class_id" class="form-select @error('class_id') is-invalid @enderror" required onchange="handleClassChange(this.value)">
                        <option value="">Select Class</option>
                        @foreach($classes as $c)
                            <option value="{{ $c->id }}" {{ old('class_id') == $c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('class_id')
                        <div class="form-error" style="color: var(--color-danger, #dc2626); font-size: var(--font-size-xs); margin-top: 0.25rem;">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="section_id" class="form-label">
                        Section <span style="color: var(--color-danger, #dc2626);">*</span>
                    </label>
                    <select id="section_id" name="section_id" class="form-select @error('section_id') is-invalid @enderror" required>
                        <option value="">Select Section</option>
                        @foreach($sections as $sec)
                            <option value="{{ $sec->id }}" data-class-id="{{ $sec->class_id }}" {{ old('section_id') == $sec->id ? 'selected' : '' }}>
                                {{ $sec->name }} ({{ $sec->schoolClass?->name ?? 'Class ' . $sec->class_id }})
                            </option>
                        @endforeach
                    </select>
                    @error('section_id')
                        <div class="form-error" style="color: var(--color-danger, #dc2626); font-size: var(--font-size-xs); margin-top: 0.25rem;">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <h4 style="margin-top: 0; margin-bottom: 1rem; color: var(--color-primary); border-bottom: 2px solid var(--color-border, #e5e7eb); padding-bottom: 0.5rem;">
                2. CSV File Upload
            </h4>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="file" class="form-label">
                    Select CSV File <span style="color: var(--color-danger, #dc2626);">*</span>
                </label>
                <input type="file" id="file" name="file" accept=".csv,text/csv" class="form-input @error('file') is-invalid @enderror" required>
                <small style="color: var(--color-text-muted, #6b7280); font-size: var(--font-size-xs, 0.75rem); display: block; margin-top: 0.25rem;">
                    Format: comma-separated (.csv) with headers: <code>admission_number,student_name,roll_number</code>. Maximum size: 2MB.
                </small>
                @error('file')
                    <div class="form-error" style="color: var(--color-danger, #dc2626); font-size: var(--font-size-xs); margin-top: 0.25rem;">{{ $message }}</div>
                @enderror
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--color-border, #e5e7eb); padding-top: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <a href="{{ route('students.template') }}" class="btn btn-secondary btn-sm" id="btn-download-template">
                    ⬇ Download CSV Template
                </a>
                <div style="display: flex; gap: 0.75rem;">
                    <a href="{{ route('students.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="btn-submit-import">
                        Upload & Import
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Instructions & Rules Card -->
    <div class="card" style="background: var(--color-bg-subtle, #f9fafb);">
        <div class="card-header">
            <h4 style="margin: 0; font-size: var(--font-size-base, 1rem);">Approved Identity & Matching Rules (DEC-072)</h4>
        </div>
        <div style="padding: 1.25rem; font-size: var(--font-size-sm, 0.875rem); line-height: 1.6; color: var(--color-text-muted, #4b5563);">
            <ul style="margin: 0; padding-left: 1.25rem; display: flex; flex-direction: column; gap: 0.5rem;">
                <li><strong>Case 1 (New Admission Number):</strong> Creates a new master student and registers their placement in the selected classroom.</li>
                <li><strong>Case 2 (Existing Admission Number & Matching Name):</strong> Reuses the student master record without creating duplicate students. Associates the student with the selected classroom.</li>
                <li><strong>Case 3 (Existing Admission Number & Mismatched Name):</strong> Rejected with validation error. Stored student name is protected and never overwritten.</li>
                <li><strong>Case 4 (Duplicate Admission Number in CSV):</strong> Conflicting rows inside the file are rejected with line numbers reported.</li>
                <li><strong>Case 5 (Blank Admission Number):</strong> Rejected with row validation error.</li>
                <li><strong>Case 6 (Historical Placement Preservation):</strong> Prior academic placements and marks remain preserved and are never deleted or rewritten.</li>
            </ul>
        </div>
    </div>
</div>

<script>
function handleClassChange(classId) {
    const secSelect = document.getElementById('section_id');
    const currentVal = secSelect.value;
    let hasSelected = false;
    for (let opt of secSelect.options) {
        if (!opt.value) continue;
        const belongs = (!classId || opt.getAttribute('data-class-id') === String(classId));
        opt.style.display = belongs ? '' : 'none';
        opt.disabled = !belongs;
        if (belongs && opt.value === currentVal) {
            hasSelected = true;
        }
    }
    if (!hasSelected && classId) {
        secSelect.value = '';
    }
}
document.addEventListener('DOMContentLoaded', () => {
    const classElem = document.getElementById('class_id');
    if (classElem && classElem.value) {
        handleClassChange(classElem.value);
    }
});
</script>
@endsection
