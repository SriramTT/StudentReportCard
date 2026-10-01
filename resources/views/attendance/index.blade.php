@extends('layouts.app')

@section('title', 'Term Attendance Management — School Report Card System')
@section('page_title', 'Term Attendance Management')

@section('content')
<div class="attendance-container">

    @if($isClosedYear && !$isEditable)
        <div class="alert alert-warning" style="margin-bottom: 1.25rem;">
            <strong>Academic Year Closed:</strong> This academic year is closed. Teachers have read-only access. Only administrators and office staff may perform post-closure corrections.
        </div>
    @endif

    <!-- 1. Context Filter Panel -->
    <div class="card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem;">
        <form method="GET" action="{{ route('attendance.index') }}" id="attendance-filter-form">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end;">
                <!-- Academic Year -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter-academic-year" style="font-size: var(--font-size-xs); font-weight: 600; color: var(--color-text-muted); display: block; margin-bottom: 0.25rem;">
                        Academic Year
                    </label>
                    <select name="academic_year_id" id="filter-academic-year" class="form-control form-control-sm" onchange="this.form.submit()">
                        @foreach($academicYears as $year)
                            <option value="{{ $year->id }}" {{ $selectedYearId === $year->id ? 'selected' : '' }}>
                                {{ $year->name }} {{ $year->is_current ? '(Current)' : '' }} {{ $year->isClosed() ? '[CLOSED]' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Class -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter-class" style="font-size: var(--font-size-xs); font-weight: 600; color: var(--color-text-muted); display: block; margin-bottom: 0.25rem;">
                        Class
                    </label>
                    <select name="class_id" id="filter-class" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">-- Select Class --</option>
                        @foreach($availableClasses as $class)
                            <option value="{{ $class->id }}" {{ $selectedClassId === $class->id ? 'selected' : '' }}>
                                {{ $class->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Section -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter-section" style="font-size: var(--font-size-xs); font-weight: 600; color: var(--color-text-muted); display: block; margin-bottom: 0.25rem;">
                        Section
                    </label>
                    <select name="section_id" id="filter-section" class="form-control form-control-sm" {{ !$selectedClassId ? 'disabled' : '' }} onchange="this.form.submit()">
                        <option value="">{{ !$selectedClassId ? '-- Select Class First --' : ($availableSections->isEmpty() ? '-- No Sections Available --' : '-- Select Section --') }}</option>
                        @foreach($availableSections as $sec)
                            <option value="{{ $sec->id }}" {{ $selectedSectionId === $sec->id ? 'selected' : '' }}>
                                Section {{ $sec->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Term -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter-term" style="font-size: var(--font-size-xs); font-weight: 600; color: var(--color-text-muted); display: block; margin-bottom: 0.25rem;">
                        Term
                    </label>
                    <select name="term_id" id="filter-term" class="form-control form-control-sm" {{ !$selectedYearId ? 'disabled' : '' }} onchange="this.form.submit()">
                        <option value="">-- Select Term --</option>
                        @foreach($availableTerms as $term)
                            <option value="{{ $term->id }}" {{ $selectedTermId === $term->id ? 'selected' : '' }}>
                                {{ $term->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>
    </div>

    <!-- 2. Attendance Entry Roster -->
    @if($selectedYearId && $selectedClassId && $selectedSectionId && $selectedTermId)
        @if($roster->isEmpty())
            <div class="card" style="text-align: center; padding: 2.5rem; color: var(--color-text-muted);">
                <p style="font-size: var(--font-size-base); margin-bottom: 0.5rem;">No active student enrollments found in this classroom section.</p>
                <p style="font-size: var(--font-size-sm);">Ensure students have an active academic placement in this academic year, class, and section.</p>
            </div>
        @else
            <form method="POST" action="{{ route('attendance.batch_save') }}" id="attendance-batch-form">
                @csrf
                <input type="hidden" name="academic_year_id" value="{{ $selectedYearId }}">
                <input type="hidden" name="class_id" value="{{ $selectedClassId }}">
                <input type="hidden" name="section_id" value="{{ $selectedSectionId }}">
                <input type="hidden" name="term_id" value="{{ $selectedTermId }}">

                <!-- Batch Helper Toolbar -->
                @if($isEditable)
                    <div class="card" style="margin-bottom: 1rem; padding: 0.75rem 1.25rem; background: var(--color-bg-subtle, #f8fafc); border: 1px dashed var(--color-border);">
                        <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                            <label for="batch-total-working-days" style="font-size: var(--font-size-sm); font-weight: 600; color: var(--color-text);">
                                Set Section Working Days:
                            </label>
                            <div style="display: inline-flex; gap: 0.5rem; align-items: center;">
                                <input type="number" id="batch-total-working-days" min="0" class="form-control form-control-sm" style="width: 100px;" placeholder="e.g. 50">
                                <button type="button" id="btn-apply-working-days" class="btn btn-secondary btn-sm">Apply to All</button>
                            </div>
                            <span style="font-size: var(--font-size-xs); color: var(--color-text-muted);">
                                Pre-fills the "Total Working Days" for all students below. Individual student records will be saved upon submission.
                            </span>
                        </div>
                    </div>
                @endif

                <div class="card" style="overflow-x: auto;">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0; font-size: var(--font-size-base);">
                            Student Attendance Roster ({{ $roster->count() }} Students)
                        </h3>
                        @if($isEditable)
                            <button type="submit" class="btn btn-primary btn-sm" id="btn-save-attendance-top">Save Attendance</button>
                        @endif
                    </div>

                    <div class="data-table-wrapper">
                        <table class="data-table" id="attendance-table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="width: 70px; text-align: center;">Roll</th>
                                    <th style="width: 120px;">Admission No</th>
                                    <th>Student Name</th>
                                    <th style="width: 150px; text-align: center;">Days Attended</th>
                                    <th style="width: 160px; text-align: center;">Total Working Days</th>
                                    <th style="width: 130px; text-align: center;">Attendance %</th>
                                    <th style="width: 110px; text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($roster as $index => $item)
                                    <tr data-row-index="{{ $index }}">
                                        <td style="text-align: center; font-weight: 600;">
                                            {{ $item['roll_number'] ?? '—' }}
                                        </td>
                                        <td style="font-family: monospace; font-size: var(--font-size-xs);">
                                            {{ $item['admission_number'] }}
                                        </td>
                                        <td>
                                            <strong>{{ $item['student_name'] }}</strong>
                                            <input type="hidden" name="attendance_records[{{ $index }}][student_academic_record_id]" value="{{ $item['student_academic_record_id'] }}">
                                        </td>
                                        <td style="text-align: center;">
                                            @if($isEditable)
                                                <input type="number"
                                                    name="attendance_records[{{ $index }}][days_attended]"
                                                    value="{{ old("attendance_records.{$index}.days_attended", $item['days_attended']) }}"
                                                    min="0"
                                                    class="form-control form-control-sm att-days-attended"
                                                    style="text-align: center; max-width: 110px; margin: 0 auto;"
                                                    required
                                                    data-index="{{ $index }}">
                                            @else
                                                <span>{{ $item['days_attended'] }}</span>
                                                <input type="hidden" name="attendance_records[{{ $index }}][days_attended]" value="{{ $item['days_attended'] }}">
                                            @endif
                                        </td>
                                        <td style="text-align: center;">
                                            @if($isEditable)
                                                <input type="number"
                                                    name="attendance_records[{{ $index }}][total_working_days]"
                                                    value="{{ old("attendance_records.{$index}.total_working_days", $item['total_working_days']) }}"
                                                    min="0"
                                                    class="form-control form-control-sm att-total-working-days"
                                                    style="text-align: center; max-width: 110px; margin: 0 auto;"
                                                    required
                                                    data-index="{{ $index }}">
                                            @else
                                                <span>{{ $item['total_working_days'] }}</span>
                                                <input type="hidden" name="attendance_records[{{ $index }}][total_working_days]" value="{{ $item['total_working_days'] }}">
                                            @endif
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="badge {{ $item['calculation']->isAvailable ? 'badge-primary' : 'badge-secondary' }} att-percentage-badge" data-index="{{ $index }}">
                                                {{ $item['calculation']->formattedPercentage }}
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            @if($item['has_record'])
                                                <span class="badge badge-success" style="font-size: 0.75rem;">Recorded</span>
                                            @else
                                                <span class="badge badge-warning" style="font-size: 0.75rem;">Not Saved</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($isEditable)
                        <div class="card-footer" style="padding: 1rem 1.25rem; display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn btn-primary" id="btn-save-attendance-bottom">Save Attendance</button>
                        </div>
                    @endif
                </div>
            </form>
        @endif
    @else
        <div class="card" style="text-align: center; padding: 3rem 1.5rem; color: var(--color-text-muted);">
            <p style="font-size: var(--font-size-base); margin-bottom: 0.5rem;">Select an Academic Year, Class, Section, and Term above to load the student attendance roster.</p>
        </div>
    @endif
</div>
@endsection
