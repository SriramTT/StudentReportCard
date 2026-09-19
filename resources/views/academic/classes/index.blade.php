@extends('layouts.app')

@section('title', 'Classes - School Examination & Report Card Management System')
@section('page_title', 'Class Catalog')

@section('content')
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
    <div class="card">
        <div class="card-header">
            <h3>Registered Classes</h3>
        </div>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Class Name</th>
                        <th>Sections</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($classes as $class)
                        <tr>
                            <td><strong>{{ $class->name }}</strong></td>
                            <td>{{ $class->sections_count }} configured</td>
                            <td>
                                <span class="badge {{ $class->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $class->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                @can('update', $class)
                                    <form method="POST" action="{{ route('classes.update', $class) }}" style="display: inline;">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="name" value="{{ $class->name }}">
                                        <input type="hidden" name="is_active" value="{{ $class->is_active ? '0' : '1' }}">
                                        <button type="submit" class="btn btn-secondary btn-sm">
                                            {{ $class->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                                No classes found. Create classes using the form.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @can('create', App\Models\SchoolClass::class)
    <div class="card">
        <div class="card-header">
            <h3>Add New Class</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('classes.store') }}">
                @csrf

                <div class="form-group">
                    <label for="name" class="form-label">Class Name <span style="color: var(--color-danger);">*</span></label>
                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Class 8, Grade 9" value="{{ old('name') }}" required maxlength="50">
                    @error('name')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                    <label for="is_active" style="font-size: var(--font-size-sm); cursor: pointer;">Active</label>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                    Create Class
                </button>
            </form>
        </div>
    </div>
    @endcan
</div>
@endsection
