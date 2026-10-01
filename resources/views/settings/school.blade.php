@extends('layouts.app')

@section('title', 'School Settings - School Examination & Report Card Management System')
@section('page_title', 'School Settings')

@section('content')
<form method="POST" action="{{ route('admin.school_settings.update') }}" enctype="multipart/form-data" id="school-settings-form">
    @csrf
    @method('PUT')

    <!-- Top Row: General Settings & School Logo -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
        <!-- Card 1: School Information -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <h3>School Global Configuration</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="school_name" class="form-label">School Name <span style="color: var(--color-danger);">*</span></label>
                    <input type="text" name="school_name" id="school_name" class="form-control @error('school_name') is-invalid @enderror" value="{{ old('school_name', $settings->school_name) }}" required maxlength="150">
                    <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Institutional name printed on report cards and official transcripts.</small>
                    @error('school_name')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="pass_mark" class="form-label">Standard Pass Mark (%) <span style="color: var(--color-danger);">*</span></label>
                    <input type="number" step="0.01" min="0" name="pass_mark" id="pass_mark" class="form-control @error('pass_mark') is-invalid @enderror" value="{{ old('pass_mark', $settings->pass_mark) }}" required>
                    <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Default score percentage required to pass a subject or term.</small>
                    @error('pass_mark')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Card 2: School Branding & Logo -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <h3>School Branding</h3>
            </div>
            <div class="card-body">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="school_logo">School Logo <span style="color: var(--color-danger);">*</span></label>
                    
                    @if($settings->school_logo_path)
                        <div style="margin-bottom: 0.85rem; display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap;">
                            <img src="{{ asset('storage/' . $settings->school_logo_path) }}" 
                                 alt="Current School Logo" 
                                 style="max-height: 64px; max-width: 180px; object-fit: contain; border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 4px; background: #fff;"
                                 onerror="this.style.display='none'; document.getElementById('logo-fallback-badge').style.display='inline-block';">
                            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--font-size-xs); color: var(--color-danger); cursor: pointer; font-weight: 500;">
                                <input type="checkbox" name="remove_school_logo" id="remove_school_logo" value="1">
                                Remove Logo
                            </label>
                            <span id="logo-fallback-badge" style="display: none; font-size: var(--font-size-xs); background: var(--color-bg-subtle, #f3f4f6); padding: 4px 8px; border-radius: 4px; border: 1px solid var(--color-border);">
                                Current: <code>{{ $settings->school_logo_path }}</code>
                            </span>
                        </div>
                    @endif

                    <input type="file" name="school_logo" id="school_logo" class="form-control @error('school_logo') is-invalid @enderror" accept="image/png,image/jpeg,image/webp">
                    <small style="color: var(--color-text-muted); font-size: var(--font-size-xs); display: block; margin-top: 0.35rem;">
                        Upload PNG, JPEG, or WEBP image (max 2MB). Required for sidebar header and formal report cards.
                    </small>
                    @error('school_logo')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Row: Signatures Management (Full Width) -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <h3>Authoritative Signatures</h3>
        </div>
        <div class="card-body">
            <!-- Principal Signature -->
            <div class="form-group" style="margin-bottom: 2rem;">
                <label class="form-label" for="principal_signature" style="font-size: var(--font-size-sm); font-weight: 700;">Principal Signature</label>
                
                @if(!empty($principalSignatureUri))
                    <div style="margin-bottom: 0.75rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <img src="{{ $principalSignatureUri }}" 
                             alt="Principal Signature Preview" 
                             style="max-height: 50px; max-width: 150px; object-fit: contain; border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 4px; background: #fff;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--font-size-xs); color: var(--color-danger); cursor: pointer;">
                            <input type="checkbox" name="remove_principal_signature" value="1">
                            Remove signature
                        </label>
                    </div>
                @endif

                <input type="file" name="principal_signature" id="principal_signature" class="form-control @error('principal_signature') is-invalid @enderror" accept="image/png,image/jpeg,image/webp">
                <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Upload PNG, JPEG, or WEBP signature image (max 2MB) to appear as the global Principal signature on report cards.</small>
                @error('principal_signature')
                    <div class="form-error">{{ $message }}</div>
                @enderror
            </div>

            <!-- Class Teacher Signatures -->
            <div class="form-group" style="margin-bottom: 0; border-top: 1px solid var(--color-border); padding-top: 1.5rem;">
                <label class="form-label" style="font-size: var(--font-size-sm); font-weight: 700; margin-bottom: 0.25rem;">Class Teacher Signatures</label>
                <small style="display: block; color: var(--color-text-muted); font-size: var(--font-size-xs); margin-bottom: 1rem;">
                    Signatures are keyed to individual teachers. Each student's report card automatically renders the signature of their assigned Class Teacher.
                </small>

                @if($classTeachers->isEmpty())
                    <div style="padding: 1rem 1.25rem; background: var(--color-bg-subtle, #f8fafc); border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: var(--font-size-sm); color: var(--color-text-muted);">
                        No teachers with active Class Teacher assignments found. Assign Class Teachers in <a href="{{ route('teacher_assignments.index') }}" style="color: var(--color-primary); font-weight: 600;">Teacher Assignments</a>.
                    </div>
                @else
                    <div class="table-responsive" style="border: 1px solid var(--color-border); border-radius: var(--radius-sm); overflow: hidden;">
                        <table class="data-table" style="width: 100%; border-collapse: collapse; font-size: var(--font-size-sm);">
                            <thead style="background: var(--color-bg-subtle, #f8fafc); border-bottom: 1px solid var(--color-border);">
                                <tr>
                                    <th style="padding: 10px 14px; text-align: left;">Teacher</th>
                                    <th style="padding: 10px 14px; text-align: left;">Assigned Classroom(s)</th>
                                    <th style="padding: 10px 14px; text-align: center; width: 160px;">Current Signature</th>
                                    <th style="padding: 10px 14px; text-align: left; width: 280px;">Upload / Replace</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($classTeachers as $teacher)
                                    <tr style="border-bottom: 1px solid var(--color-border);">
                                        <td style="padding: 10px 14px; vertical-align: middle;">
                                            <strong>{{ $teacher->display_name }}</strong>
                                            <div style="font-size: var(--font-size-xs); color: var(--color-text-muted);">{{ $teacher->username }}</div>
                                        </td>
                                        <td style="padding: 10px 14px; vertical-align: middle; color: var(--color-text-main);">
                                            {{ $teacher->assignedClassroomsDisplay }}
                                        </td>
                                        <td style="padding: 10px 14px; text-align: center; vertical-align: middle;">
                                            @if(!empty($teacher->signatureUri))
                                                <div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                                                    <img src="{{ $teacher->signatureUri }}" 
                                                         alt="{{ $teacher->display_name }} Signature" 
                                                         style="max-height: 40px; max-width: 120px; object-fit: contain; border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 2px; background: #fff;">
                                                    <label style="display: flex; align-items: center; gap: 0.25rem; font-size: 11px; color: var(--color-danger); cursor: pointer;">
                                                        <input type="checkbox" name="remove_teacher_signatures[]" value="{{ $teacher->id }}">
                                                        Remove
                                                    </label>
                                                </div>
                                            @else
                                                <span style="font-size: var(--font-size-xs); color: var(--color-text-muted); font-style: italic;">Not configured</span>
                                            @endif
                                        </td>
                                        <td style="padding: 10px 14px; vertical-align: middle;">
                                            <input type="file" 
                                                   name="teacher_signatures[{{ $teacher->id }}]" 
                                                   id="teacher_signature_{{ $teacher->id }}" 
                                                   class="form-control" 
                                                   style="font-size: var(--font-size-xs); padding: 5px 8px;"
                                                   accept="image/png,image/jpeg,image/webp">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Submit Bar -->
    <div style="display: flex; justify-content: flex-end; margin-top: 1rem; margin-bottom: 2rem;">
        <button type="submit" class="btn btn-primary" id="save-school-settings-btn" style="padding: 0.6rem 1.75rem; font-size: var(--font-size-sm); font-weight: 600;">
            Save School Settings
        </button>
    </div>
</form>
@endsection
