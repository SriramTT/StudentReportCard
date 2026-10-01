@extends('layouts.app')

@section('title', 'Mark Entry & Evaluation — School Report Card System')
@section('page_title', 'Mark Entry & Evaluation')

@section('content')
<div class="mark-entry-container">
    <div id="mark-feedback-banner" style="display: none; margin-bottom: 1.25rem;"></div>

    <!-- 1. Context Selection Panel -->
    <div class="mark-context-panel">
        <form method="GET" action="{{ route('marks.index') }}" id="context-filter-form">
            <div class="mark-filter-row">
                <!-- Academic Year -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter-academic-year" style="font-size: var(--font-size-xs); font-weight: 600; color: var(--color-text-muted);">
                        Academic Year
                    </label>
                    <select name="academic_year_id" id="filter-academic-year" class="form-control form-control-sm mark-context-filter" onchange="window.handleContextChange('year', this)">
                        @foreach($academicYears as $year)
                            <option value="{{ $year->id }}" {{ $selectedYearId === $year->id ? 'selected' : '' }}>
                                {{ $year->name }} {{ $year->is_current ? '(Current)' : '' }} {{ $year->isClosed() ? '[CLOSED]' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Class -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter-class" style="font-size: var(--font-size-xs); font-weight: 600; color: var(--color-text-muted);">
                        Class
                    </label>
                    <select name="class_id" id="filter-class" class="form-control form-control-sm mark-context-filter" onchange="window.handleContextChange('class', this)">
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
                    <label for="filter-section" style="font-size: var(--font-size-xs); font-weight: 600; color: var(--color-text-muted);">
                        Section
                    </label>
                    <select name="section_id" id="filter-section" class="form-control form-control-sm mark-context-filter" {{ !$selectedClassId ? 'disabled' : '' }} onchange="window.handleContextChange('section', this)">
                        <option value="">{{ !$selectedClassId ? '-- Select Class First --' : ($availableSections->isEmpty() ? '-- No Sections Available --' : '-- Select Section --') }}</option>
                        @foreach($availableSections as $sec)
                            <option value="{{ $sec->id }}" {{ $selectedSectionId === $sec->id ? 'selected' : '' }}>
                                Section {{ $sec->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Subject -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter-subject" style="font-size: var(--font-size-xs); font-weight: 600; color: var(--color-text-muted);">
                        Subject
                    </label>
                    <select name="subject_id" id="filter-subject" class="form-control form-control-sm mark-context-filter" {{ !$selectedSectionId ? 'disabled' : '' }} onchange="window.handleContextChange('subject', this)">
                        <option value="">{{ !$selectedSectionId ? '-- Select Section First --' : ($availableSubjects->isEmpty() ? '-- No Subjects Available --' : '-- Select Subject --') }}</option>
                        @foreach($availableSubjects as $sub)
                            <option value="{{ $sub->id }}" {{ $selectedSubjectId === $sub->id ? 'selected' : '' }}>
                                {{ $sub->name }} ({{ $sub->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Assessment -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter-assessment" style="font-size: var(--font-size-xs); font-weight: 600; color: var(--color-text-muted);">
                        Assessment
                    </label>
                    <select name="assessment_id" id="filter-assessment" class="form-control form-control-sm mark-context-filter" {{ !$selectedSubjectId ? 'disabled' : '' }} onchange="window.handleContextChange('assessment', this)">
                        <option value="">{{ !$selectedSubjectId ? '-- Select Subject First --' : ($availableAssessments->isEmpty() ? '-- No Assessments Available --' : '-- Select Assessment --') }}</option>
                        @foreach($availableAssessments as $asmt)
                            <option value="{{ $asmt->id }}" {{ $selectedAssessmentId === $asmt->id ? 'selected' : '' }}>
                                {{ $asmt->name }} ({{ $asmt->assessmentType?->name }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>

        <!-- Context Meta Badges (Visible when assessment & applicability are loaded) -->
        @if($targetApplicability && $targetClassSubject)
            <div class="mark-context-header" style="margin-top: 1.25rem; margin-bottom: 0; padding-bottom: 0; border-bottom: none;">
                <div class="mark-context-badges">
                    <div class="mark-badge-item">
                        Year: <strong>{{ $selectedYear?->name }}</strong>
                    </div>
                    <div class="mark-badge-item">
                        Class: <strong>{{ $targetClassSubject->schoolClass?->name }} - {{ $targetClassSubject->section?->name ?? 'All' }}</strong>
                    </div>
                    <div class="mark-badge-item">
                        Subject: <strong>{{ $targetClassSubject->subject_name_snapshot }}</strong>
                    </div>
                    <div class="mark-badge-item">
                        Assessment: <strong>{{ $targetApplicability->assessment?->name }}</strong>
                    </div>
                    @if($targetApplicability->assessment?->term)
                        <div class="mark-badge-item">
                            Term: <strong>{{ $targetApplicability->assessment->term->name }}</strong>
                        </div>
                    @endif
                    @if($targetApplicability->assessment?->assessment_date)
                        <div class="mark-badge-item">
                            Date: <strong>{{ $targetApplicability->assessment->assessment_date->format('d M Y') }}</strong>
                        </div>
                    @endif
                    <div class="mark-badge-item mark-badge-max">
                        Maximum Marks: <strong>{{ $targetApplicability->maximum_marks }}</strong>
                    </div>
                </div>

                @if($isReadOnly)
                    <div>
                        @if($selectedYear?->isClosed())
                            <span class="badge badge-warning" style="font-size: 0.8rem; padding: 0.35rem 0.65rem;">
                                Academic Year Closed (Read-Only)
                            </span>
                        @else
                            <span class="badge badge-secondary" style="font-size: 0.8rem; padding: 0.35rem 0.65rem;">
                                View Only
                            </span>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>

    <!-- 2. Roster Grid Container -->
    @if(! $targetApplicability)
        <div class="card" style="text-align: center; padding: 3.5rem 1.5rem; margin-top: 1.5rem; background: var(--color-bg-card, #ffffff); border: 1px dashed var(--color-border, #d1d5db); border-radius: var(--border-radius-lg, 0.5rem);">
            <div style="font-size: 2.75rem; margin-bottom: 0.75rem;">📝</div>
            <h3 style="margin-bottom: 0.5rem; color: var(--color-text-main, #111827);">
                @if(!$selectedClassId)
                    Select a Class to Begin
                @elseif(!$selectedSectionId)
                    Select a Section for {{ $selectedClass?->name }}
                @elseif(!$selectedSubjectId)
                    Select a Subject
                @elseif(!$selectedAssessmentId)
                    Select an Assessment
                @else
                    Evaluation Context Incomplete
                @endif
            </h3>
            <p style="margin: 0; font-size: var(--font-size-sm, 0.875rem); color: var(--color-text-muted, #6b7280); max-width: 540px; margin-inline: auto;">
                @if(!$selectedClassId)
                    Choose an Academic Year and Class from the filter panel above.
                @elseif(!$selectedSectionId)
                    Choose an assigned Section to load mapped subjects.
                @elseif(!$selectedSubjectId)
                    Choose a Subject configured for this classroom.
                @elseif(!$selectedAssessmentId)
                    Choose an active Assessment to load maximum marks and the student roster.
                @else
                    Ensure active Assessment Applicability and Student Allocations exist for this subject.
                @endif
            </p>
        </div>
    @elseif($roster->isEmpty())
        <div class="card" style="padding: 3rem; text-align: center; color: var(--color-text-muted);">
            <div style="font-size: 2.5rem; margin-bottom: 0.75rem;">👥</div>
            <h3 style="color: var(--color-text-main); margin-bottom: 0.5rem;">No Allocated Students Found</h3>
            <p style="max-width: 32rem; margin: 0 auto; font-size: var(--font-size-sm);">
                No students are currently actively placed and allocated to <strong>{{ $targetClassSubject->subject_name_snapshot }}</strong> in this classroom.
            </p>
        </div>
    @else
        <form id="mark-batch-form" method="POST" action="{{ route('marks.batch_save') }}"
              data-academic-year-id="{{ $selectedYearId }}"
              data-year-id="{{ $selectedYearId }}"
              data-class-id="{{ $selectedClassId }}"
              data-section-id="{{ $selectedSectionId }}"
              data-subject-id="{{ $selectedSubjectId }}"
              data-assessment-id="{{ $selectedAssessmentId }}"
              data-max-marks="{{ $targetApplicability->maximum_marks }}"
              data-read-only="{{ $isReadOnly ? 'true' : 'false' }}">
            @csrf
            <input type="hidden" name="academic_year_id" value="{{ $selectedYearId }}">
            <input type="hidden" name="class_id" value="{{ $selectedClassId }}">
            <input type="hidden" name="section_id" value="{{ $selectedSectionId }}">
            <input type="hidden" name="subject_id" value="{{ $selectedSubjectId }}">
            <input type="hidden" name="assessment_id" value="{{ $selectedAssessmentId }}">

            <div class="mark-roster-wrapper">
                <!-- Action & Summary Bar -->
                <div class="mark-action-bar">
                    <!-- Left: Counters -->
                    <div class="mark-stats-summary">
                        <div class="mark-stat-chip">
                            Total: <span class="count" id="stat-total">{{ $roster->count() }}</span>
                        </div>
                        <div class="mark-stat-chip">
                            Entered: <span class="count" id="stat-entered">0</span>
                        </div>
                        <div class="mark-stat-chip">
                            Blank: <span class="count" id="stat-blank">0</span>
                        </div>
                        <div class="mark-stat-chip">
                            Absent: <span class="count" id="stat-absent">0</span>
                        </div>
                        <div class="mark-stat-chip">
                            Zero: <span class="count" id="stat-zero">0</span>
                        </div>
                    </div>

                    <!-- Right: Search & Save Controls -->
                    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                        <input type="text" id="student-search-input" placeholder="Search student or admission no..." class="form-control form-control-sm" style="width: 14rem;">

                        <select id="status-filter-select" class="form-control form-control-sm" style="width: 8rem;">
                            <option value="all">All Status</option>
                            <option value="entered">Entered</option>
                            <option value="blank">Blank</option>
                            <option value="absent">Absent</option>
                            <option value="zero">Zero (0)</option>
                        </select>

                        <button type="submit" id="mark-save-btn" class="btn btn-primary btn-sm" disabled>
                            Save Changes
                        </button>
                    </div>
                </div>

                <!-- Main Spreadsheet Table -->
                <div class="mark-table-container">
                    <table class="mark-table" id="mark-entry-table">
                        <thead>
                            <tr>
                                <th style="width: 5rem;">Roll No</th>
                                <th style="width: 9rem;">Admission No</th>
                                <th>Student Name</th>
                                <th style="width: 11rem; text-align: right;">
                                    Mark (Max: {{ $targetApplicability->maximum_marks }})
                                </th>
                                <th style="width: 8rem; text-align: center;">Status</th>
                                <th style="width: 7rem; text-align: center;">Save State</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($roster as $student)
                                <tr data-student-name="{{ $student->student_name }}"
                                    data-admission-number="{{ $student->admission_number }}"
                                    data-roll-number="{{ $student->roll_number }}">
                                    <td>
                                        <span style="font-weight: 600; color: var(--color-text-muted);">
                                            {{ $student->roll_number !== null ? $student->roll_number : '—' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-weight: 700; color: var(--color-primary);">
                                            {{ $student->admission_number }}
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-weight: 600; color: var(--color-text-main);">
                                            {{ $student->student_name }}
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="mark-cell-wrapper" style="justify-content: flex-end;">
                                            <input type="text"
                                                   class="mark-input {{ $student->result_status === 'absent' ? 'is-absent' : ($student->formatted_value === '0.00' ? 'is-zero' : '') }}"
                                                   data-sar-id="{{ $student->student_academic_record_id }}"
                                                   data-alloc-id="{{ $student->student_subject_allocation_id }}"
                                                   data-result-status="{{ $student->result_status }}"
                                                   value="{{ $student->formatted_value }}"
                                                   placeholder="—"
                                                   {{ $isReadOnly ? 'disabled' : '' }}
                                                   autocomplete="off">
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="mark-status-pill status-{{ $student->result_status }}">
                                            {{ ucfirst($student->result_status) }}
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="mark-save-state state-saved">
                                            Saved
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--color-text-muted); padding: 3rem 1.5rem;">
                                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">👥</div>
                                        <strong style="color: var(--color-text-main);">No students are currently allocated to this subject in the selected classroom.</strong>
                                        <p style="margin: 0.25rem 0 0; font-size: var(--font-size-xs); color: var(--color-text-muted);">Verify active student placements and subject allocations for this class and section.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Keyboard shortcuts guide -->
                <div class="mark-instruction-bar">
                    <div class="mark-instruction-shortcuts">
                        <strong>Keyboard Traversal:</strong>
                        <kbd>Tab</kbd> or <kbd>Enter</kbd> or <kbd>↓</kbd> next student &bull;
                        <kbd>Shift + Tab</kbd> or <kbd>↑</kbd> previous student &bull;
                        Type <kbd>A</kbd> for Absent &bull;
                        Numbers for Numeric marks &bull;
                        Clear to leave Blank
                    </div>
                    <div>
                        All entries transactionally audited with actor identity.
                    </div>
                </div>
            </div>
        </form>
    @endif
</div>
@endsection
