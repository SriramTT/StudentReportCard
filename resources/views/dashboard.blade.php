@extends('layouts.app')

@section('title', 'Dashboard - School Examination & Report Card Management System')
@section('page_title', 'System Dashboard')

@section('content')
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h3 style="color: var(--color-primary); margin-bottom: 0.25rem;">
                    Welcome back, {{ auth()->user()->display_name }}
                </h3>
            </div>
            <div>
                @php
                    $currentYear = \App\Models\AcademicYear::where('is_current', true)->first();
                @endphp
                <div style="text-align: right;">
                    <span style="font-size: var(--font-size-xs); color: var(--color-text-muted); display: block;">Active Academic Year</span>
                    <strong style="color: var(--color-text-main); font-size: var(--font-size-base);">
                        {{ $currentYear ? $currentYear->name : 'Not Configured' }}
                    </strong>
                </div>
            </div>
        </div>
    </div>
</div>

@if(auth()->user()->isAdmin() || auth()->user()->isOfficeStaff())
    <h3 style="font-size: var(--font-size-base); color: var(--color-text-main); margin-bottom: 1rem; font-weight: 700;">
        Core Academic & Configuration Modules
    </h3>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem;">
        <!-- Academic Years -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-body">
                <h4 style="font-size: var(--font-size-base); margin-bottom: 0.5rem; color: var(--color-primary);">Academic Calendar</h4>
                <p style="font-size: var(--font-size-xs); color: var(--color-text-muted); margin-bottom: 1rem;">
                    Manage annual academic calendars, open/closed lifecycle states, and current year status.
                </p>
                <a href="{{ route('academic_years.index') }}" class="btn btn-secondary btn-sm btn-block">
                    Configure Academic Years &rarr;
                </a>
            </div>
        </div>

        <!-- Terms -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-body">
                <h4 style="font-size: var(--font-size-base); margin-bottom: 0.5rem; color: var(--color-primary);">Academic Terms</h4>
                <p style="font-size: var(--font-size-xs); color: var(--color-text-muted); margin-bottom: 1rem;">
                    Configure dynamic N-terms (Term 1..N, Semesters) with ascending sequence numbers.
                </p>
                <a href="{{ route('terms.index') }}" class="btn btn-secondary btn-sm btn-block">
                    Configure Terms &rarr;
                </a>
            </div>
        </div>

        <!-- Classes & Sections -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-body">
                <h4 style="font-size: var(--font-size-base); margin-bottom: 0.5rem; color: var(--color-primary);">Classes & Sections</h4>
                <p style="font-size: var(--font-size-xs); color: var(--color-text-muted); margin-bottom: 1rem;">
                    Maintain classroom definitions, academic year sections, and class catalogs.
                </p>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('classes.index') }}" class="btn btn-secondary btn-sm" style="flex: 1;">Classes</a>
                    <a href="{{ route('sections.index') }}" class="btn btn-secondary btn-sm" style="flex: 1;">Sections</a>
                </div>
            </div>
        </div>

        <!-- Subjects & Class Subjects -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-body">
                <h4 style="font-size: var(--font-size-base); margin-bottom: 0.5rem; color: var(--color-primary);">Curriculum Catalog</h4>
                <p style="font-size: var(--font-size-xs); color: var(--color-text-muted); margin-bottom: 1rem;">
                    Manage master subjects and map offerings to classes with immutable snapshots.
                </p>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('subjects.index') }}" class="btn btn-secondary btn-sm" style="flex: 1;">Subjects</a>
                    <a href="{{ route('class_subjects.index') }}" class="btn btn-secondary btn-sm" style="flex: 1;">Class Offerings</a>
                </div>
            </div>
        </div>

        <!-- Assessments & Types -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-body">
                <h4 style="font-size: var(--font-size-base); margin-bottom: 0.5rem; color: var(--color-primary);">Assessment Milestones</h4>
                <p style="font-size: var(--font-size-xs); color: var(--color-text-muted); margin-bottom: 1rem;">
                    Set up assessment types, milestones, and subject-specific maximum marks.
                </p>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('assessments.types.index') }}" class="btn btn-secondary btn-sm" style="flex: 1;">Types</a>
                    <a href="{{ route('assessments.index') }}" class="btn btn-secondary btn-sm" style="flex: 1;">Assessments</a>
                </div>
            </div>
        </div>

        <!-- Calculations & Reports -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-body">
                <h4 style="font-size: var(--font-size-base); margin-bottom: 0.5rem; color: var(--color-primary);">Calculations & Layouts</h4>
                <p style="font-size: var(--font-size-xs); color: var(--color-text-muted); margin-bottom: 1rem;">
                    Configure average vs. combined marks methods and report card assessment display orders.
                </p>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="{{ route('calculations.settings.index') }}" class="btn btn-secondary btn-sm" style="flex: 1;">Calculations</a>
                    <a href="{{ route('reports.configurations.index') }}" class="btn btn-secondary btn-sm" style="flex: 1;">Reports</a>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection
