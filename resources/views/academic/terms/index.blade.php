@extends('layouts.app')

@section('title', 'Academic Terms - School Examination & Report Card Management System')
@section('page_title', 'Academic Terms')

@section('content')
<div class="filter-bar">
    <form method="GET" action="{{ route('terms.index') }}" style="display: flex; gap: 0.75rem; align-items: center;">
        <label for="academic_year_id" style="font-size: var(--font-size-sm); font-weight: 600;">Academic Year:</label>
        <select name="academic_year_id" id="academic_year_id" class="form-control" onchange="this.form.submit()">
            @foreach($academicYears as $year)
                <option value="{{ $year->id }}" {{ $selectedYearId == $year->id ? 'selected' : '' }}>
                    {{ $year->name }} {{ $year->is_current ? '(Current)' : '' }}
                </option>
            @endforeach
        </select>
    </form>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
    <!-- Terms Table -->
    <div class="card">
        <div class="card-header">
            <h3>Configured Terms</h3>
        </div>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sequence</th>
                        <th>Term Name</th>
                        <th>Academic Year</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($terms as $term)
                        <tr>
                            <td><strong>{{ $term->sequence_no }}</strong></td>
                            <td>{{ $term->name }}</td>
                            <td>{{ $term->academicYear?->name }}</td>
                            <td>
                                <span class="badge {{ $term->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $term->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                @can('update', $term)
                                    <form method="POST" action="{{ route('terms.update', $term) }}" style="display: inline-flex; gap: 0.5rem; align-items: center;">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="name" value="{{ $term->name }}">
                                        <input type="hidden" name="sequence_no" value="{{ $term->sequence_no }}">
                                        <input type="hidden" name="is_active" value="{{ $term->is_active ? '0' : '1' }}">
                                        <button type="submit" class="btn btn-secondary btn-sm">
                                            {{ $term->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                                No terms configured for this academic year. Add terms using the form.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Term Form -->
    @can('create', App\Models\Term::class)
    <div class="card">
        <div class="card-header">
            <h3>Add New Term</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('terms.store') }}">
                @csrf
                <input type="hidden" name="academic_year_id" value="{{ $selectedYearId }}">

                <div class="form-group">
                    <label for="name" class="form-label">Term Name <span style="color: var(--color-danger);">*</span></label>
                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Term 1, Semester 1, Pre-Final" value="{{ old('name') }}" required maxlength="50">
                    <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Flexible naming (e.g. Term 1..5, Semester 1..2).</small>
                    @error('name')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="sequence_no" class="form-label">Sequence Number <span style="color: var(--color-danger);">*</span></label>
                    <input type="number" min="1" name="sequence_no" id="sequence_no" class="form-control @error('sequence_no') is-invalid @enderror" value="{{ old('sequence_no', ($terms->max('sequence_no') ?? 0) + 1) }}" required>
                    <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Used strictly for chronological ascending order.</small>
                    @error('sequence_no')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                    <label for="is_active" style="font-size: var(--font-size-sm); cursor: pointer;">Active</label>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                    Create Term
                </button>
            </form>
        </div>
    </div>
    @endcan
</div>
@endsection
