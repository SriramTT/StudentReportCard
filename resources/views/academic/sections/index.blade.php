@extends('layouts.app')

@section('title', 'Sections - School Examination & Report Card Management System')
@section('page_title', 'Class Sections')

@section('content')
<div class="filter-bar">
    <form method="GET" action="{{ route('sections.index') }}" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
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
            <h3>Registered Sections</h3>
        </div>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Section Name</th>
                        <th>Class</th>
                        <th>Academic Year</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sections as $section)
                        <tr>
                            <td><strong>{{ $section->name }}</strong></td>
                            <td>{{ $section->schoolClass?->name }}</td>
                            <td>{{ $section->academicYear?->name }}</td>
                            <td>
                                <span class="badge {{ $section->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $section->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                @can('update', $section)
                                    <form method="POST" action="{{ route('sections.update', $section) }}" style="display: inline;">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="name" value="{{ $section->name }}">
                                        <input type="hidden" name="is_active" value="{{ $section->is_active ? '0' : '1' }}">
                                        <button type="submit" class="btn btn-secondary btn-sm">
                                            {{ $section->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                                No sections found for the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @can('create', App\Models\Section::class)
    <div class="card">
        <div class="card-header">
            <h3>Add New Section</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('sections.store') }}">
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
                    <label for="name" class="form-label">Section Name <span style="color: var(--color-danger);">*</span></label>
                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. A, B, Blue, Red" value="{{ old('name') }}" required maxlength="50">
                    @error('name')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                    <label for="is_active" style="font-size: var(--font-size-sm); cursor: pointer;">Active</label>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                    Create Section
                </button>
            </form>
        </div>
    </div>
    @endcan
</div>
@endsection
