@extends('layouts.app')

@section('title', 'Internal Transfer - ' . $student->student_name)
@section('page_title', 'Internal Student Transfer')

@section('content')
<div style="max-width: 700px; margin: 0 auto;">
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="margin: 0;">Transfer Student</h3>
                <div style="font-size: var(--font-size-sm); color: var(--color-text-muted, #6b7280); margin-top: 0.25rem;">
                    {{ $student->student_name }} (<span style="font-family: monospace; font-weight: 600;">{{ $student->admission_number }}</span>)
                </div>
            </div>
            <a href="{{ $returnUrl ?? route('students.show', $student) }}" class="btn btn-secondary btn-sm">
                ← Back
            </a>
        </div>

        <form method="POST" action="{{ route('students.transfer', $student) }}" style="padding: 1.5rem;">
            @csrf
            <input type="hidden" name="return_url" value="{{ $returnUrl ?? '' }}">

            <div style="background: var(--color-bg-subtle, #f9fafb); padding: 1rem; border-radius: var(--radius-md, 0.375rem); margin-bottom: 1.5rem; border: 1px solid var(--color-border, #e5e7eb);">
                <div style="font-weight: 600; font-size: var(--font-size-sm); margin-bottom: 0.5rem; color: var(--color-text-main, #111827);">
                    Current Placement
                </div>
                @php
                    $activeRecord = $student->academicRecords->where('status.value', 'active')->first();
                @endphp
                @if($activeRecord)
                    <div style="font-size: var(--font-size-sm); color: var(--color-text-muted, #4b5563);">
                        <strong>{{ $activeRecord->academicYear?->name }}</strong> —
                        Class: <strong>{{ $activeRecord->schoolClass?->name }}</strong>,
                        Section: <strong>{{ $activeRecord->section?->name }}</strong>,
                        Roll No: <strong>{{ $activeRecord->roll_number }}</strong>
                        (Active since {{ $activeRecord->effective_from?->format('Y-m-d') }})
                    </div>
                @else
                    <div style="font-size: var(--font-size-sm); color: var(--color-text-muted, #9ca3af);">
                        No active placement currently found.
                    </div>
                @endif
            </div>

            <h4 style="margin-top: 0; margin-bottom: 1rem; color: var(--color-primary-700);">
                Destination Placement
            </h4>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label for="academic_year_id" class="form-label">
                        Target Academic Year <span style="color: var(--color-danger, #dc2626);">*</span>
                    </label>
                    <select id="academic_year_id" name="academic_year_id" class="form-select @error('academic_year_id') is-invalid @enderror" required>
                        <option value="">Select Academic Year</option>
                        @foreach($academicYears as $year)
                            <option value="{{ $year->id }}" {{ old('academic_year_id', $activeRecord?->academic_year_id) == $year->id ? 'selected' : '' }}>
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
                        Target Class <span style="color: var(--color-danger, #dc2626);">*</span>
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
                        Target Section <span style="color: var(--color-danger, #dc2626);">*</span>
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
                        Target Roll Number <span style="color: var(--color-danger, #dc2626);">*</span>
                    </label>
                    <input type="number" id="roll_number" name="roll_number" class="form-input @error('roll_number') is-invalid @enderror" value="{{ old('roll_number') }}" required min="1" placeholder="e.g. 15">
                    @error('roll_number')
                        <div class="form-error" style="color: var(--color-danger, #dc2626); font-size: var(--font-size-xs); margin-top: 0.25rem;">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group" style="grid-column: span 2;">
                    <label for="effective_date" class="form-label">
                        Transfer Effective Date <span style="color: var(--color-danger, #dc2626);">*</span>
                    </label>
                    <input type="date" id="effective_date" name="effective_date" class="form-input @error('effective_date') is-invalid @enderror" value="{{ old('effective_date', date('Y-m-d')) }}" required>
                    <small style="color: var(--color-text-muted, #6b7280); font-size: var(--font-size-xs, 0.75rem); display: block; margin-top: 0.25rem;">
                        The student's previous placement will be closed with internal transfer status on this date. Historical marks and placements remain fully preserved.
                    </small>
                    @error('effective_date')
                        <div class="form-error" style="color: var(--color-danger, #dc2626); font-size: var(--font-size-xs); margin-top: 0.25rem;">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem; border-top: 1px solid var(--color-border, #e5e7eb); padding-top: 1.5rem;">
                <a href="{{ $returnUrl ?? route('students.show', $student) }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" id="btn-submit-transfer">
                    Execute Transfer
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
