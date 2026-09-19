@extends('layouts.app')

@section('title', 'Assessment Types - School Examination & Report Card Management System')
@section('page_title', 'Assessment Types')

@section('content')
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
    <div class="card">
        <div class="card-header">
            <h3>Registered Assessment Types</h3>
        </div>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Type Name</th>
                        <th>Assessments</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assessmentTypes as $type)
                        <tr>
                            <td><strong>{{ $type->name }}</strong></td>
                            <td>{{ $type->assessments_count }} created</td>
                            <td>
                                <span class="badge {{ $type->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $type->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                @can('update', $type)
                                    <form method="POST" action="{{ route('assessments.types.update', $type) }}" style="display: inline;">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="name" value="{{ $type->name }}">
                                        <input type="hidden" name="is_active" value="{{ $type->is_active ? '0' : '1' }}">
                                        <button type="submit" class="btn btn-secondary btn-sm">
                                            {{ $type->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                                No assessment types found. Create types using the form.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @can('create', App\Models\AssessmentType::class)
    <div class="card">
        <div class="card-header">
            <h3>Add Assessment Type</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('assessments.types.store') }}">
                @csrf

                <div class="form-group">
                    <label for="name" class="form-label">Type Name <span style="color: var(--color-danger);">*</span></label>
                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Unit Test, Term Exam, Class Test" value="{{ old('name') }}" required maxlength="50">
                    <small style="color: var(--color-text-muted); font-size: var(--font-size-xs);">Database-driven category of evaluations.</small>
                    @error('name')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                    <label for="is_active" style="font-size: var(--font-size-sm); cursor: pointer;">Active</label>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                    Create Assessment Type
                </button>
            </form>
        </div>
    </div>
    @endcan
</div>
@endsection
