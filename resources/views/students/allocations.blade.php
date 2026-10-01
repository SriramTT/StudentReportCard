@extends('layouts.app')

@section('title', 'Manage Subject Allocations - ' . $student->student_name)
@section('page_title', 'Student Subject Allocations')

@section('content')
<div style="max-width: 900px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Placement Details Header Card -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h3 style="margin: 0;">{{ $student->student_name }}</h3>
                <div style="font-size: var(--font-size-sm, 0.875rem); color: var(--color-text-muted, #6b7280); font-family: monospace;">
                    {{ $student->admission_number }}
                </div>
            </div>
            <a href="{{ route('students.show', $student) }}" class="btn btn-secondary btn-sm">
                ← Back to Student Profile
            </a>
        </div>
        <div style="padding: 1.25rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; background: var(--color-bg-subtle, #f9fafb);">
            <div>
                <label style="font-size: var(--font-size-xs, 0.75rem); color: var(--color-text-muted, #6b7280);">Academic Year</label>
                <div style="font-weight: 600;">{{ $record->academicYear?->name }}</div>
            </div>
            <div>
                <label style="font-size: var(--font-size-xs, 0.75rem); color: var(--color-text-muted, #6b7280);">Class & Section</label>
                <div style="font-weight: 600;">{{ $record->schoolClass?->name }} — Section {{ $record->section?->name }}</div>
            </div>
            <div>
                <label style="font-size: var(--font-size-xs, 0.75rem); color: var(--color-text-muted, #6b7280);">Roll Number</label>
                <div style="font-weight: 600;">{{ $record->roll_number }}</div>
            </div>
            <div>
                <label style="font-size: var(--font-size-xs, 0.75rem); color: var(--color-text-muted, #6b7280);">Placement Status</label>
                <div><span class="badge badge-success">{{ ucfirst($record->status->value) }}</span></div>
            </div>
        </div>
    </div>

    <!-- Allocation Form Card -->
    <div class="card">
        <div class="card-header">
            <h4 style="margin: 0;">Classroom Subjects & Allocation Status</h4>
        </div>

        <form method="POST" action="{{ route('student-subject-allocations.update', $record) }}" style="padding: 1.5rem;">
            @csrf
            @method('PATCH')

            <p style="margin-top: 0; margin-bottom: 1.25rem; color: var(--color-text-muted, #6b7280); font-size: 0.875rem;">
                Select the subjects that apply to this student in this classroom. Core/main subjects are allocated by default. Electives can be allocated or deallocated prior to assessment mark recording (BR-021, DEC-017).
            </p>

            <style>
                #allocations-table th:first-child,
                #allocations-table td:first-child { width: 64px; text-align: center; }
                #allocations-table td { vertical-align: middle; }
            </style>
            <div class="data-table-wrapper" style="margin-bottom: 1.5rem;">
                <table class="data-table" id="allocations-table">
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;">Allocated</th>
                            <th>Subject Name</th>
                            <th>Code</th>
                            <th>Category</th>
                            <th>Scope</th>
                            <th>Status & Lockout</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($availableClassSubjects as $cs)
                            @php
                                $allocation = $allocations->get($cs->id);
                                $isAllocated = $allocation && $allocation->is_active;
                                $hasMarks = $allocation && $allocation->marks()->exists();
                            @endphp
                            <tr style="{{ $hasMarks ? 'background: #fdfdfd;' : '' }}">
                                <td style="text-align: center; vertical-align: middle;">
                                    @if($hasMarks)
                                        <input type="hidden" name="class_subject_ids[]" value="{{ $cs->id }}">
                                        <input type="checkbox" checked disabled title="Locked: Marks exist for this subject">
                                    @else
                                        <input type="checkbox" name="class_subject_ids[]" value="{{ $cs->id }}" id="cs_{{ $cs->id }}" {{ $isAllocated ? 'checked' : '' }}>
                                    @endif
                                </td>
                                <td>
                                    <label for="cs_{{ $cs->id }}" style="font-weight: 500; cursor: pointer; display: block; margin: 0;">
                                        {{ $cs->subject_name_snapshot }}
                                    </label>
                                </td>
                                <td><code>{{ $cs->subject?->code ?? '—' }}</code></td>
                                <td>
                                    <span class="badge {{ $cs->subject?->category?->value === 'elective' ? 'badge-info' : 'badge-primary' }}">
                                        {{ ucfirst($cs->subject?->category?->value ?? 'Main') }}
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 0.75rem; color: var(--color-text-muted, #6b7280);">
                                        {{ $cs->section_id ? 'Section-Specific' : 'Class-Wide' }}
                                    </span>
                                </td>
                                <td>
                                    @if($hasMarks)
                                        <span class="badge badge-warning" title="Cannot deallocate: Assessment marks recorded.">
                                            🔒 Marks Recorded (Locked)
                                        </span>
                                    @elseif($isAllocated)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-secondary">Not Allocated</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem; color: var(--color-text-muted, #6b7280);">
                                    No active class subjects configured for this classroom.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem; border-top: 1px solid var(--color-border, #e5e7eb); padding-top: 1.5rem;">
                <a href="{{ route('students.show', $student) }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" id="btn-save-allocations">
                    Save Allocations
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
