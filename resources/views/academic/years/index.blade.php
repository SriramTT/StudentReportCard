@extends('layouts.app')

@section('title', 'Academic Years - School Examination & Report Card Management System')
@section('page_title', 'Academic Calendar Years')

@section('content')
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
    <!-- List Card -->
    <div class="card">
        <div class="card-header">
            <h3>Academic Years List</h3>
        </div>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Academic Year</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Terms</th>
                        <th>Status</th>
                        <th>Current</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($academicYears as $year)
                        <tr>
                            <td><strong>{{ $year->name }}</strong></td>
                            <td>{{ $year->start_date->format('Y-m-d') }}</td>
                            <td>{{ $year->end_date->format('Y-m-d') }}</td>
                            <td>{{ $year->terms_count }} terms</td>
                            <td>
                                <span class="badge {{ $year->status->value === 'open' ? 'badge-success' : 'badge-secondary' }}">
                                    {{ ucfirst($year->status->value) }}
                                </span>
                            </td>
                            <td>
                                @if($year->is_current)
                                    <span class="badge badge-primary">Current</span>
                                @else
                                    @can('update', $year)
                                        <form method="POST" action="{{ route('academic_years.update', $year) }}" style="display: inline;">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="name" value="{{ $year->name }}">
                                            <input type="hidden" name="start_date" value="{{ $year->start_date->format('Y-m-d') }}">
                                            <input type="hidden" name="end_date" value="{{ $year->end_date->format('Y-m-d') }}">
                                            <input type="hidden" name="is_current" value="1">
                                            <button type="submit" class="btn btn-secondary btn-sm">Set Current</button>
                                        </form>
                                    @endcan
                                @endif
                            </td>
                            <td>
                                @can('close', $year)
                                    @if($year->status->value === 'open')
                                        <form method="POST" action="{{ route('academic_years.close', $year) }}" style="display: inline;" onsubmit="return confirm('Are you sure you want to close this academic year?');">
                                            @csrf
                                            <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--color-danger);">Close</button>
                                        </form>
                                    @endif
                                @endcan

                                @can('reopen', $year)
                                    @if($year->status->value === 'closed')
                                        <form method="POST" action="{{ route('academic_years.reopen', $year) }}" style="display: inline;" onsubmit="return confirm('Are you sure you want to reopen this academic year?');">
                                            @csrf
                                            <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--color-success);">Reopen</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                                No academic years found. Configure the first academic year below.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Card (Admin Only) -->
    @can('create', App\Models\AcademicYear::class)
    <div class="card">
        <div class="card-header">
            <h3>Add Academic Year</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('academic_years.store') }}">
                @csrf

                <div class="form-group">
                    <label for="name" class="form-label">Year Name <span style="color: var(--color-danger);">*</span></label>
                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. 2026-2027" value="{{ old('name') }}" required maxlength="50">
                    @error('name')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="start_date" class="form-label">Start Date <span style="color: var(--color-danger);">*</span></label>
                    <input type="date" name="start_date" id="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date') }}" required>
                    @error('start_date')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="end_date" class="form-label">End Date <span style="color: var(--color-danger);">*</span></label>
                    <input type="date" name="end_date" id="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date') }}" required>
                    @error('end_date')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="is_current" id="is_current" value="1" {{ old('is_current') ? 'checked' : '' }}>
                    <label for="is_current" style="font-size: var(--font-size-sm); cursor: pointer;">Set as Current Academic Year</label>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                    Create Academic Year
                </button>
            </form>
        </div>
    </div>
    @endcan
</div>
@endsection
