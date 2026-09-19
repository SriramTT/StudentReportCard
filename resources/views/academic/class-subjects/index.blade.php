@extends('layouts.app')

@section('title', 'Class Subjects - School Examination & Report Card Management System')
@section('page_title', 'Curriculum Mapping (Class Subjects)')

@section('content')
<div class="filter-bar">
    <form method="GET" action="{{ route('class_subjects.index') }}" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <label for="filter_academic_year_id" style="font-size: var(--font-size-sm); font-weight: 600;">Year:</label>
        <select name="academic_year_id" id="filter_academic_year_id" class="form-control" onchange="this.form.submit()">
            @foreach($academicYears as $year)
                <option value="{{ $year->id }}" {{ $selectedYearId == $year->id ? 'selected' : '' }}>
                    {{ $year->name }} {{ $year->is_current ? '(Current)' : '' }}
                </option>
            @endforeach
        </select>

        <label for="filter_class_id" style="font-size: var(--font-size-sm); font-weight: 600;">Class:</label>
        <select name="class_id" id="filter_class_id" class="form-control" onchange="this.form.submit()">
            <option value="">All Classes</option>
            @foreach($classes as $c)
                <option value="{{ $c->id }}" {{ $selectedClassId == $c->id ? 'selected' : '' }}>
                    {{ $c->name }}
                </option>
            @endforeach
        </select>
    </form>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
    <div class="card">
        <div class="card-header">
            <h3>Class Subject Mappings</h3>
        </div>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>Section</th>
                        <th>Current Subject</th>
                        <th>Historical Snapshot</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($classSubjects as $cs)
                        <tr>
                            <td><strong>{{ $cs->schoolClass?->name }}</strong></td>
                            <td>{{ $cs->section?->name ?? 'All Sections' }}</td>
                            <td>{{ $cs->subject?->name }}</td>
                            <td><span style="font-family: monospace; color: var(--color-primary); font-weight: 600;">{{ $cs->subject_name_snapshot }}</span></td>
                            <td>
                                <span class="badge {{ $cs->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $cs->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                @can('update', $cs)
                                    <form method="POST" action="{{ route('class_subjects.update', $cs) }}" style="display: inline;">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="is_active" value="{{ $cs->is_active ? '0' : '1' }}">
                                        <button type="submit" class="btn btn-secondary btn-sm">
                                            {{ $cs->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                                No class subjects mapped for the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @can('create', App\Models\ClassSubject::class)
    <div class="card">
        <div class="card-header">
            <h3>Map Subject to Class</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('class_subjects.store') }}">
                @csrf

                <div class="form-group">
                    <label for="academic_year_id" class="form-label">Academic Year <span style="color: var(--color-danger);">*</span></label>
                    <select name="academic_year_id" id="academic_year_id" class="form-control" required>
                        @foreach($academicYears as $year)
                            <option value="{{ $year->id }}" {{ $selectedYearId == $year->id ? 'selected' : '' }}>
                                {{ $year->name }} {{ $year->is_current ? '(Current)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="class_id" class="form-label">Class <span style="color: var(--color-danger);">*</span></label>
                    <select name="class_id" id="class_id" class="form-control" required>
                        @foreach($classes as $c)
                            <option value="{{ $c->id }}" {{ $selectedClassId == $c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="section_id" class="form-label">Section (Optional)</label>
                    <select name="section_id" id="section_id" class="form-control">
                        <option value="">All Sections (Class-Wide)</option>
                        @foreach($availableSections as $sec)
                            <option value="{{ $sec->id }}">{{ $sec->name }} ({{ $sec->schoolClass?->name }})</option>
                        @endforeach
                    </select>
                    <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Leave blank if offering applies across all sections.</small>
                </div>

                <div class="form-group">
                    <label for="subject_id" class="form-label">Subject <span style="color: var(--color-danger);">*</span></label>
                    <select name="subject_id" id="subject_id" class="form-control" required>
                        @foreach($subjects as $s)
                            <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->code }})</option>
                        @endforeach
                    </select>
                    <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">A permanent snapshot of the subject name will be stored.</small>
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                    <label for="is_active" style="font-size: var(--font-size-sm); cursor: pointer;">Active</label>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                    Map Subject
                </button>
            </form>
        </div>
    </div>
    @endcan
</div>
@endsection
