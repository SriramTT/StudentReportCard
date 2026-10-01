@extends('layouts.app')

@section('title', 'Import Summary - School Examination & Report Card Management System')
@section('page_title', 'Student Import Results')

@section('content')
<div style="max-width: 900px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Summary Header Card -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="margin: 0;">Import Execution Summary</h3>
                <div style="font-size: var(--font-size-sm); color: var(--color-text-muted, #6b7280); margin-top: 0.25rem;">
                    Target: <strong>{{ $academicYear?->name }}</strong> —
                    Class <strong>{{ $schoolClass?->name }}</strong>
                    ({{ $section?->name }})
                </div>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="{{ route('students.import.form') }}" class="btn btn-secondary btn-sm">
                    Upload Another CSV
                </a>
                <a href="{{ route('students.index') }}" class="btn btn-primary btn-sm">
                    View Student Directory
                </a>
            </div>
        </div>

        <div style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 1rem; text-align: center;">
                <div style="padding: 1rem; background: var(--color-bg-subtle, #f3f4f6); border-radius: var(--radius-md, 0.375rem);">
                    <div style="font-size: 1.75rem; font-weight: 700; color: var(--color-text-main, #111827);">
                        {{ $results['total'] }}
                    </div>
                    <div style="font-size: var(--font-size-xs, 0.75rem); text-transform: uppercase; color: var(--color-text-muted, #6b7280); font-weight: 600;">
                        Total Rows
                    </div>
                </div>

                <div style="padding: 1rem; background: #ecfdf5; border-radius: var(--radius-md, 0.375rem); border: 1px solid #a7f3d0;">
                    <div style="font-size: 1.75rem; font-weight: 700; color: #065f46;">
                        {{ $results['created'] ?? 0 }}
                    </div>
                    <div style="font-size: var(--font-size-xs, 0.75rem); text-transform: uppercase; color: #047857; font-weight: 600;">
                        Created
                    </div>
                </div>

                <div style="padding: 1rem; background: #f0fdf4; border-radius: var(--radius-md, 0.375rem); border: 1px solid #bbf7d0;">
                    <div style="font-size: 1.75rem; font-weight: 700; color: #166534;">
                        {{ $results['placed'] ?? 0 }}
                    </div>
                    <div style="font-size: var(--font-size-xs, 0.75rem); text-transform: uppercase; color: #15803d; font-weight: 600;">
                        Placed
                    </div>
                </div>

                <div style="padding: 1rem; background: #eff6ff; border-radius: var(--radius-md, 0.375rem); border: 1px solid #bfdbfe;">
                    <div style="font-size: 1.75rem; font-weight: 700; color: #1e40af;">
                        {{ $results['unchanged'] ?? 0 }}
                    </div>
                    <div style="font-size: var(--font-size-xs, 0.75rem); text-transform: uppercase; color: #1d4ed8; font-weight: 600;">
                        Unchanged
                    </div>
                </div>

                <div style="padding: 1rem; background: #fef2f2; border-radius: var(--radius-md, 0.375rem); border: 1px solid #fecaca;">
                    <div style="font-size: 1.75rem; font-weight: 700; color: #991b1b;">
                        {{ $results['skipped'] }}
                    </div>
                    <div style="font-size: var(--font-size-xs, 0.75rem); text-transform: uppercase; color: #b91c1c; font-weight: 600;">
                        Rejected
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Errors / Rejections Table (if any) -->
    @if(!empty($results['errors']))
        <div class="card" style="border-left: 4px solid var(--color-danger, #dc2626);">
            <div class="card-header" style="background: #fff5f5;">
                <h4 style="margin: 0; color: var(--color-danger, #dc2626);">
                    Validation Rejections ({{ count($results['errors']) }})
                </h4>
            </div>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">CSV Row</th>
                            <th>Admission No</th>
                            <th>Student Name</th>
                            <th>Rejection Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results['errors'] as $err)
                            <tr>
                                <td><strong>#{{ $err['row'] }}</strong></td>
                                <td><code>{{ $err['admission_number'] ?? '—' }}</code></td>
                                <td>{{ $err['student_name'] ?? '—' }}</td>
                                <td style="color: var(--color-danger, #dc2626);">{{ $err['reason'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Successes Table (if any) -->
    @if(!empty($results['successes']))
        <div class="card">
            <div class="card-header">
                <h4 style="margin: 0; color: var(--color-success, #059669);">
                    Accepted & Processed Rows ({{ count($results['successes']) }})
                </h4>
            </div>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">CSV Row</th>
                            <th>Admission No</th>
                            <th>Student Name</th>
                            <th>Roll No</th>
                            <th>Action Taken</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results['successes'] as $succ)
                            <tr>
                                <td>#{{ $succ['row'] }}</td>
                                <td><strong>{{ $succ['admission_number'] }}</strong></td>
                                <td>{{ $succ['student_name'] }}</td>
                                <td><span class="badge badge-secondary">{{ $succ['roll_number'] }}</span></td>
                                <td>
                                    @if(str_contains(strtolower($succ['action']), 'unchanged'))
                                        <span class="badge badge-info">{{ $succ['action'] }}</span>
                                    @else
                                        <span class="badge badge-success">{{ $succ['action'] }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
