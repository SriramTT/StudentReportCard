@extends('layouts.app')

@section('title', 'Student Profile - ' . $student->student_name)
@section('page_title', 'Student Profile')

@section('content')
<div style="max-width: 1000px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Profile Card -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span style="font-size: var(--font-size-xs, 0.75rem); text-transform: uppercase; color: var(--color-text-muted, #6b7280); letter-spacing: 0.05em; font-weight: 600;">
                    Admission Number
                </span>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-primary-600, #2563eb); font-family: monospace;">
                    {{ $student->admission_number }}
                </div>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                @can('update', $student)
                    <a href="{{ route('students.edit', [$student, 'return_url' => route('students.show', $student)]) }}" class="btn btn-secondary" id="btn-edit-student">
                        Edit Student Name
                    </a>
                @endcan
                @can('transfer', $student)
                    <a href="{{ route('students.transfer.form', [$student, 'return_url' => route('students.show', $student)]) }}" class="btn btn-secondary" id="btn-transfer-student">
                        Internal Transfer
                    </a>
                @endcan
                @php
                    $currentActiveRecord = $student->academicRecords->first(fn($r) => $r->status?->value === 'active');
                @endphp
                @if($currentActiveRecord)
                    @can('updateAllocation', $student)
                        <a href="{{ route('student-subject-allocations.edit', $currentActiveRecord) }}" class="btn btn-secondary" id="btn-manage-subjects">
                            Subject Allocations
                        </a>
                    @endcan
                @endif
                @can('delete', $student)
                    <button type="button" class="btn btn-danger" onclick="openModal('delete-student-modal-{{ $student->id }}')" id="btn-delete-student">
                        Remove
                    </button>
                @endcan
                <a href="{{ route('students.index') }}" class="btn btn-secondary">
                    Back to Directory
                </a>
            </div>
        </div>

        @can('delete', $student)
            <div id="delete-student-modal-{{ $student->id }}" class="modal-backdrop" style="display: none;">
                <div class="modal-dialog">
                    <div class="modal-header">
                        <h3 class="modal-title">Remove Student</h3>
                        <button type="button" class="modal-close" onclick="closeModal('delete-student-modal-{{ $student->id }}')">&times;</button>
                    </div>
                    <form method="POST" action="{{ route('students.destroy', $student) }}">
                        @csrf
                        @method('DELETE')
                        <div class="modal-body">
                            <p>Are you sure you want to permanently remove student <strong>{{ $student->student_name }}</strong> (Admission No: <code>{{ $student->admission_number }}</code>)?</p>
                            <div class="alert alert-warning" style="margin-top: 1rem; font-size: 0.875rem;">
                                <strong>Warning:</strong> This operation cannot be undone. A student can only be removed if no academic placement records exist. Historical student records must be preserved for reports and academic history.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('delete-student-modal-{{ $student->id }}')">Cancel</button>
                            <button type="submit" class="btn btn-danger">Confirm Remove</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan

        <div style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                <div>
                    <label style="font-size: var(--font-size-xs, 0.75rem); color: var(--color-text-muted, #6b7280); display: block; margin-bottom: 0.25rem;">
                        Student Full Name
                    </label>
                    <div style="font-size: 1.25rem; font-weight: 600; color: var(--color-text-main, #111827);">
                        {{ $student->student_name }}
                    </div>
                </div>

                <div>
                    <label style="font-size: var(--font-size-xs, 0.75rem); color: var(--color-text-muted, #6b7280); display: block; margin-bottom: 0.25rem;">
                        Admission Number Status
                    </label>
                    <div>
                        <span class="badge badge-success">Permanent / Immutable</span>
                    </div>
                </div>

                <div>
                    <label style="font-size: var(--font-size-xs, 0.75rem); color: var(--color-text-muted, #6b7280); display: block; margin-bottom: 0.25rem;">
                        Created At
                    </label>
                    <div style="color: var(--color-text-main, #374151);">
                        {{ $student->created_at?->format('Y-m-d H:i') ?? '—' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Academic Placement History Card -->
    <div class="card">
        <div class="card-header">
            <h3 style="margin: 0;">Academic Placement History</h3>
        </div>

        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Academic Year</th>
                        <th>Class & Section</th>
                        <th>Roll Number</th>
                        <th>Status</th>
                        <th>Effective From</th>
                        <th>Effective To</th>
                        <th style="width: 130px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($student->academicRecords as $record)
                        <tr>
                            <td><strong>{{ $record->academicYear?->name ?? '—' }}</strong></td>
                            <td>
                                {{ $record->schoolClass?->name ?? '—' }}
                                ({{ $record->section?->name ?? '—' }})
                            </td>
                            <td>
                                <span class="badge badge-secondary">{{ $record->roll_number }}</span>
                            </td>
                            <td>
                                @php
                                    $statusBadge = match($record->status->value) {
                                        'active' => 'badge-success',
                                        'internal_transfer' => 'badge-info',
                                        'transferred_out', 'withdrawn' => 'badge-secondary',
                                        default => 'badge-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $statusBadge }}">
                                    {{ ucfirst(str_replace('_', ' ', $record->status->value)) }}
                                </span>
                            </td>
                            <td>{{ $record->effective_from?->format('Y-m-d') ?? '—' }}</td>
                            <td>{{ $record->effective_to?->format('Y-m-d') ?? 'Present' }}</td>
                            <td style="text-align: center;">
                                @if($record->status->value === 'active')
                                    @can('updateAllocation', $student)
                                        <a href="{{ route('student-subject-allocations.edit', $record) }}" class="btn btn-secondary btn-sm" id="btn-alloc-{{ $record->id }}" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                            Subjects
                                        </a>
                                    @endcan
                                @else
                                    <span style="color: var(--color-text-muted, #9ca3af); font-size: 0.75rem;">Archived</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem; color: var(--color-text-muted, #6b7280);">
                                No academic placement records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
