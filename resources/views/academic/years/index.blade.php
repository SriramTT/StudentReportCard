@extends('layouts.app')

@section('title', 'Academic Years - School Examination & Report Card Management System')
@section('page_title', 'Academic Calendar Years')

@section('content')
<!-- List Card (Full Width) -->
<div class="card" style="width: 100%;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h3 style="margin: 0;">Academic Years List</h3>
        @can('create', App\Models\AcademicYear::class)
            <button type="button" class="btn btn-primary" id="btn-add-academic-year" onclick="openModal('create-year-modal')">
                + Add Academic Year
            </button>
        @endcan
    </div>
    <div class="data-table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Academic Year</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Terms</th>
                    <th>Lifecycle Status</th>
                    <th>Configuration Window</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($academicYears as $year)
                    <tr>
                        <td><strong>{{ $year->name }}</strong></td>
                        <td>
                            {{ $year->start_date ? $year->start_date->format('Y-m-d') : '—' }}
                        </td>
                        <td>
                            {{ $year->end_date ? $year->end_date->format('Y-m-d') : '—' }}
                        </td>
                        <td>{{ $year->terms_count }} terms</td>
                        <td>
                            @if($year->isDateExpired())
                                <span class="badge badge-secondary" title="Closed permanently: End date has passed">
                                    Closed (Expired)
                                </span>
                            @elseif($year->isDateCurrent())
                                <span class="badge badge-success" title="Operational current year derived from today's date">
                                    Current (Active)
                                </span>
                            @else
                                <span class="badge badge-info" title="Upcoming academic year">
                                    Upcoming
                                </span>
                            @endif
                        </td>
                        <td>
                            @if($year->isConfigurationWindowOpen())
                                <span class="badge badge-success" title="Academic setup and configuration is open for this year until {{ $year->configurationWindowEnd()?->toDateString() }}">
                                    Open (Until {{ $year->configurationWindowEnd()?->toDateString() }})
                                </span>
                            @elseif(now()->startOfDay()->lt($year->configurationWindowStart()))
                                <span class="badge badge-info" title="Academic setup opens 1 month before start date">
                                    Locked (Opens {{ $year->configurationWindowStart()?->toDateString() }})
                                </span>
                            @else
                                <span class="badge badge-secondary" title="Academic setup closed 2 months before end date">
                                    Locked (Closed {{ $year->configurationWindowEnd()?->toDateString() }})
                                </span>
                            @endif
                        </td>
                        <td>
                            <div class="row-actions">
                                @can('update', $year)
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="openEditYearModal({{ json_encode([
                                        'id' => $year->id,
                                        'name' => $year->name,
                                        'start_date' => $year->start_date ? $year->start_date->format('Y-m-d') : '',
                                        'end_date' => $year->end_date ? $year->end_date->format('Y-m-d') : '',
                                    ]) }})">
                                        Edit
                                    </button>
                                @endcan

                                @can('delete', $year)
                                    <form method="POST" action="{{ route('academic_years.destroy', $year) }}" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently remove this academic year?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm" title="Remove unused academic year">
                                            Delete
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                            No academic years found. Configure the first academic year using the + Add Academic Year button above.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Create Academic Year (Admin & Staff) -->
@can('create', App\Models\AcademicYear::class)
<div id="create-year-modal" class="modal-backdrop" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Add Academic Year</h3>
            <button type="button" class="modal-close" onclick="closeModal('create-year-modal')">&times;</button>
        </div>
        <form method="POST" action="{{ route('academic_years.store') }}">
            @csrf

            <div class="modal-body">
                <div class="form-group">
                    <label for="name" class="form-label">Year Name <span style="color: var(--color-danger);">*</span></label>
                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. 2026-2027" value="{{ old('name') }}" required maxlength="50">
                    @error('name')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                @php
                    $todayStr = now()->toDateString();
                    $maxStartStr = now()->addMonthsNoOverflow(6)->toDateString();
                    $maxEndStr = now()->addMonthsNoOverflow(18)->toDateString();
                @endphp

                <div class="form-group">
                    <label for="start_date" class="form-label">Start Date <span style="color: var(--color-danger);">*</span></label>
                    <input type="date" name="start_date" id="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date') }}" required min="{{ $todayStr }}" max="{{ $maxStartStr }}">
                    <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Allowed range: Today through today + 6 calendar months ({{ $maxStartStr }}).</small>
                    @error('start_date')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="end_date" class="form-label">End Date <span style="color: var(--color-danger);">*</span></label>
                    <input type="date" name="end_date" id="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date') }}" required max="{{ $maxEndStr }}">
                    <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Must be after start date and no later than today + 18 calendar months ({{ $maxEndStr }}).</small>
                    @error('end_date')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('create-year-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    Create Academic Year
                </button>
            </div>
        </form>
    </div>
</div>
@endcan

<!-- Modal: Edit Academic Year -->
<div id="edit-year-modal" class="modal-backdrop" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Edit Academic Year</h3>
            <button type="button" class="modal-close" onclick="closeModal('edit-year-modal')">&times;</button>
        </div>
        <form id="edit-year-form" method="POST" action="">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Year Name <span style="color: var(--color-danger);">*</span></label>
                    <input type="text" id="edit_year_name" name="name" class="form-control" required maxlength="50">
                </div>

                <div class="form-group">
                    <label class="form-label">Start Date <span style="color: var(--color-danger);">*</span></label>
                    <input type="date" id="edit_year_start_date" name="start_date" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">End Date <span style="color: var(--color-danger);">*</span></label>
                    <input type="date" id="edit_year_end_date" name="end_date" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit-year-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditYearModal(year) {
    const form = document.getElementById('edit-year-form');
    form.action = '/academic-years/' + year.id;

    document.getElementById('edit_year_name').value = year.name;
    document.getElementById('edit_year_start_date').value = year.start_date;
    document.getElementById('edit_year_end_date').value = year.end_date;

    openModal('edit-year-modal');
}

@if($errors->any() && (old('name') || old('start_date') || old('end_date')))
    document.addEventListener('DOMContentLoaded', function() {
        openModal('create-year-modal');
    });
@endif
</script>
@endsection
