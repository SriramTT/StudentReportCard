@extends('layouts.app')

@section('title', 'School Settings - School Examination & Report Card Management System')
@section('page_title', 'School Settings')

@section('content')
<div class="card" style="max-width: 42rem;">
    <div class="card-header">
        <h3>School Global Configuration</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.school_settings.update') }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="school_name" class="form-label">School Name <span style="color: var(--color-danger);">*</span></label>
                <input type="text" name="school_name" id="school_name" class="form-control @error('school_name') is-invalid @enderror" value="{{ old('school_name', $settings->school_name) }}" required maxlength="150">
                @error('school_name')
                    <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="pass_mark" class="form-label">Standard Pass Mark (%) <span style="color: var(--color-danger);">*</span></label>
                <input type="number" step="0.01" min="0" name="pass_mark" id="pass_mark" class="form-control @error('pass_mark') is-invalid @enderror" value="{{ old('pass_mark', $settings->pass_mark) }}" required>
                <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Default score percentage required to pass a subject or term.</small>
                @error('pass_mark')
                    <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="school_logo_path" class="form-label">School Logo Path / Identifier</label>
                <input type="text" name="school_logo_path" id="school_logo_path" class="form-control @error('school_logo_path') is-invalid @enderror" value="{{ old('school_logo_path', $settings->school_logo_path) }}" maxlength="255">
                <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Relative logo storage path for reports and marksheets.</small>
                @error('school_logo_path')
                    <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary" id="save-school-settings-btn">
                    Save School Settings
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
