@extends('layouts.app')

@section('title', 'Subjects - School Examination & Report Card Management System')
@section('page_title', 'Subject Master Catalog')

@section('content')
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
    <div class="card">
        <div class="card-header">
            <h3>Registered Subjects</h3>
        </div>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Subject Name</th>
                        <th>Code</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subjects as $subject)
                        <tr>
                            <td><strong>{{ $subject->name }}</strong></td>
                            <td><code>{{ $subject->code }}</code></td>
                            <td>
                                <span class="badge {{ $subject->category->value === 'main' ? 'badge-primary' : 'badge-warning' }}">
                                    {{ ucfirst($subject->category->value) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $subject->is_active ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $subject->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                @can('update', $subject)
                                    <form method="POST" action="{{ route('subjects.update', $subject) }}" style="display: inline;">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="name" value="{{ $subject->name }}">
                                        <input type="hidden" name="code" value="{{ $subject->code }}">
                                        <input type="hidden" name="category" value="{{ $subject->category->value }}">
                                        <input type="hidden" name="is_active" value="{{ $subject->is_active ? '0' : '1' }}">
                                        <button type="submit" class="btn btn-secondary btn-sm">
                                            {{ $subject->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--color-text-muted); padding: 2rem;">
                                No subjects found. Create subjects using the form.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @can('create', App\Models\Subject::class)
    <div class="card">
        <div class="card-header">
            <h3>Add New Subject</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('subjects.store') }}">
                @csrf

                <div class="form-group">
                    <label for="name" class="form-label">Subject Name <span style="color: var(--color-danger);">*</span></label>
                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" placeholder="e.g. Mathematics, English" value="{{ old('name') }}" required maxlength="100">
                    @error('name')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="code" class="form-label">Subject Code <span style="color: var(--color-danger);">*</span></label>
                    <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" placeholder="e.g. MATH101, ENG01" value="{{ old('code') }}" required maxlength="20">
                    @error('code')
                        <div class="form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="category" class="form-label">Category <span style="color: var(--color-danger);">*</span></label>
                    <select name="category" id="category" class="form-control" required>
                        <option value="main" {{ old('category') == 'main' ? 'selected' : '' }}>Main Subject</option>
                        <option value="elective" {{ old('category') == 'elective' ? 'selected' : '' }}>Elective Subject</option>
                    </select>
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                    <label for="is_active" style="font-size: var(--font-size-sm); cursor: pointer;">Active</label>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                    Create Subject
                </button>
            </form>
        </div>
    </div>
    @endcan
</div>
@endsection
