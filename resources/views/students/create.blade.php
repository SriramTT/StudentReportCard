@extends('layouts.app')

@section('title', 'Add New Student - School Examination & Report Card Management System')
@section('page_title', 'Add New Student')

@section('content')
<div style="max-width: 800px; margin: 0 auto;">
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;">New Student Registration</h3>
            <a href="{{ route('students.index') }}" class="btn btn-secondary btn-sm">
                ← Back to Directory
            </a>
        </div>

        <form method="POST" action="{{ route('students.store') }}" style="padding: 1.5rem;">
            @csrf

            <h4 style="margin-top: 0; margin-bottom: 1rem; color: var(--color-primary); border-bottom: 2px solid var(--color-border, #e5e7eb); padding-bottom: 0.5rem;">
                1. Student Identity (Permanent Master)
            </h4>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                <div class="form-group">
                    <label for="admission_number" class="form-label">
                        Admission Number <span style="color: var(--color-danger, #dc2626);">*</span>
                    </label>
                    <input type="text" id="admission_number" name="admission_number" class="form-input @error('admission_number') is-invalid @enderror" value="{{ old('admission_number') }}" required placeholder="e.g. ADM-2026-001" maxlength="50">
                    <small style="color: var(--color-text-muted, #6b7280); font-size: var(--font-size-xs, 0.75rem); display: block; margin-top: 0.25rem;">
                        Unique, permanent business identifier. Immutable after creation.
                    </small>
                    @error('admission_number')
                        <div class="form-error" style="color: var(--color-danger, #dc2626); font-size: var(--font-size-xs); margin-top: 0.25rem;">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="student_name" class="form-label">
                        Student Full Name <span style="color: var(--color-danger, #dc2626);">*</span>
                    </label>
                    <input type="text" id="student_name" name="student_name" class="form-input @error('student_name') is-invalid @enderror" value="{{ old('student_name') }}" required placeholder="Full Name" maxlength="200">
                    @error('student_name')
                        <div class="form-error" style="color: var(--color-danger, #dc2626); font-size: var(--font-size-xs); margin-top: 0.25rem;">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <h4 style="margin-top: 0; margin-bottom: 1rem; color: var(--color-primary); border-bottom: 2px solid var(--color-border, #e5e7eb); padding-bottom: 0.5rem;">
                2. Initial Academic Placement
            </h4>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
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

                <div class="form-group">
                    <label for="roll_number" class="form-label">
                        Roll Number <span style="color: var(--color-danger, #dc2626);">*</span>
                    </label>
                    <input type="number" id="roll_number" name="roll_number" class="form-input @error('roll_number') is-invalid @enderror" value="{{ old('roll_number') }}" required min="1" placeholder="e.g. 1">
                    @error('roll_number')
                        <div class="form-error" style="color: var(--color-danger, #dc2626); font-size: var(--font-size-xs); margin-top: 0.25rem;">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="effective_from" class="form-label">
                        Effective Date
                    </label>
                    <input type="date" id="effective_from" name="effective_from" class="form-input @error('effective_from') is-invalid @enderror" value="{{ old('effective_from', date('Y-m-d')) }}">
                    @error('effective_from')
                        <div class="form-error" style="color: var(--color-danger, #dc2626); font-size: var(--font-size-xs); margin-top: 0.25rem;">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem; border-top: 1px solid var(--color-border, #e5e7eb); padding-top: 1.5rem;">
                <a href="{{ route('students.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" id="btn-submit-student">
                    Register Student
                </button>
            </div>
        </form>
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
