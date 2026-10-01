@extends('layouts.app')

@section('title', 'Edit Student - ' . $student->student_name)
@section('page_title', 'Edit Student')

@section('content')
<div style="max-width: 600px; margin: 0 auto;">
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;">Edit Student Name</h3>
            <a href="{{ $returnUrl ?? route('students.show', $student) }}" class="btn btn-secondary btn-sm">
                ← Back
            </a>
        </div>

        <form method="POST" action="{{ route('students.update', $student) }}" style="padding: 1.5rem;">
            @csrf
            @method('PUT')
            <input type="hidden" name="return_url" value="{{ $returnUrl ?? '' }}">

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="admission_number_display" class="form-label">
                    Admission Number (Immutable)
                </label>
                <input type="text" id="admission_number_display" class="form-input" value="{{ $student->admission_number }}" readonly disabled style="background-color: var(--color-bg-subtle, #f3f4f6); cursor: not-allowed; font-family: monospace; font-weight: 600; color: var(--color-primary-600, #2563eb);">
                <small style="color: var(--color-text-muted, #6b7280); font-size: var(--font-size-xs, 0.75rem); display: block; margin-top: 0.25rem;">
                    Admission number is the permanent student business identifier and cannot be modified.
                </small>
            </div>

            <div class="form-group" style="margin-bottom: 2rem;">
                <label for="student_name" class="form-label">
                    Student Full Name <span style="color: var(--color-danger, #dc2626);">*</span>
                </label>
                <input type="text" id="student_name" name="student_name" class="form-input @error('student_name') is-invalid @enderror" value="{{ old('student_name', $student->student_name) }}" required maxlength="200">
                @error('student_name')
                    <div class="form-error" style="color: var(--color-danger, #dc2626); font-size: var(--font-size-xs); margin-top: 0.25rem;">{{ $message }}</div>
                @enderror
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem; border-top: 1px solid var(--color-border, #e5e7eb); padding-top: 1.5rem;">
                <a href="{{ $returnUrl ?? route('students.show', $student) }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" id="btn-save-student">
                    Update Student
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
