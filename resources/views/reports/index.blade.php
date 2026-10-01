@extends('layouts.app')

@section('title', 'Report Cards & PDF Generation — School Report Card System')
@section('page_title', 'Report Cards & PDF Generation')

@section('content')
<div class="reports-container">
    @if(session('missing_assessments'))
        <div class="alert alert-danger" style="margin-bottom: 1.25rem;">
            <strong>Missing Assessments:</strong>
            <ul style="margin: 0.25rem 0 0 1.25rem; padding: 0;">
                @foreach(session('missing_assessments') as $missing)
                    <li>{{ $missing }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(isset($availableConfigs) && $availableConfigs->isEmpty() && $selectedYearId)
        <div class="alert alert-warning" style="margin-bottom: 1.25rem;">
            <strong>Configuration Notice:</strong> No active report configuration is available for this report context. Configure a report layout before generating.
        </div>
    @endif

    <!-- 1. Context Filter Panel -->
    <div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
        <form method="GET" action="{{ route('reports.index') }}" id="reports-filter-form">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: flex-end;">
                <!-- Academic Year -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter-academic-year" style="font-size: var(--font-size-xs, 0.75rem); font-weight: 600; color: var(--color-text-muted, #64748b); display: block; margin-bottom: 0.25rem;">
                        Academic Year
                    </label>
                    <select name="academic_year_id" id="filter-academic-year" class="form-control" onchange="this.form.submit()">
                        @foreach($academicYears as $year)
                            <option value="{{ $year->id }}" {{ $selectedYearId === $year->id ? 'selected' : '' }}>
                                {{ $year->name }} {{ $year->is_current ? '(Current)' : '' }} {{ $year->isClosed() ? '[CLOSED]' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Class -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter-class" style="font-size: var(--font-size-xs, 0.75rem); font-weight: 600; color: var(--color-text-muted, #64748b); display: block; margin-bottom: 0.25rem;">
                        Class
                    </label>
                    <select name="class_id" id="filter-class" class="form-control" onchange="this.form.submit()">
                        <option value="">-- Select Class --</option>
                        @foreach($availableClasses as $class)
                            <option value="{{ $class->id }}" {{ $selectedClassId === $class->id ? 'selected' : '' }}>
                                {{ $class->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Section -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter-section" style="font-size: var(--font-size-xs, 0.75rem); font-weight: 600; color: var(--color-text-muted, #64748b); display: block; margin-bottom: 0.25rem;">
                        Section
                    </label>
                    <select name="section_id" id="filter-section" class="form-control" onchange="this.form.submit()" {{ $availableSections->isEmpty() ? 'disabled' : '' }}>
                        <option value="">-- Select Section --</option>
                        @foreach($availableSections as $section)
                            <option value="{{ $section->id }}" {{ $selectedSectionId === $section->id ? 'selected' : '' }}>
                                {{ $section->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Report Type -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter-report-type" style="font-size: var(--font-size-xs, 0.75rem); font-weight: 600; color: var(--color-text-muted, #64748b); display: block; margin-bottom: 0.25rem;">
                        Report Type
                    </label>
                    <select name="report_type" id="filter-report-type" class="form-control" onchange="document.getElementById('filter-report-config') && (document.getElementById('filter-report-config').value = ''); this.form.submit()">
                        <option value="term" {{ $selectedReportType === 'term' ? 'selected' : '' }}>Term Report</option>
                        <option value="final" {{ $selectedReportType === 'final' ? 'selected' : '' }}>Final Report</option>
                        <option value="exam" {{ in_array($selectedReportType, ['exam', 'mid_term'], true) ? 'selected' : '' }}>Mid Term Assessments</option>
                        <option value="custom" {{ $selectedReportType === 'custom' ? 'selected' : '' }}>Custom Report</option>
                    </select>
                </div>


                <!-- Report Configuration -->
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="filter-report-config" style="font-size: var(--font-size-xs, 0.75rem); font-weight: 600; color: var(--color-text-muted, #64748b); display: block; margin-bottom: 0.25rem;">
                        Report Configuration
                    </label>
                    <select name="report_configuration_id" id="filter-report-config" class="form-control" onchange="this.form.submit()" {{ $availableConfigs->isEmpty() ? 'disabled' : '' }}>
                        @if($availableConfigs->isEmpty())
                            <option value="">-- No Configuration Available --</option>
                        @else
                            @if($availableConfigs->count() > 1 && ! $selectedConfigId)
                                <option value="">-- Select Configuration --</option>
                            @endif
                            @foreach($availableConfigs as $cfg)
                                <option value="{{ $cfg->id }}" {{ $selectedConfigId === $cfg->id ? 'selected' : '' }}>
                                    {{ $cfg->name }} {{ $cfg->academic_year_id ? '' : '(Global)' }}
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>
            </div>
        </form>
    </div>

    <!-- 2. Student Roster & Generation Table -->
    @if($selectedYearId && $selectedClassId && $selectedSectionId)
        <div class="card" style="padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--color-border, #e2e8f0); padding-bottom: 0.75rem;">
                <div>
                    <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: var(--color-text, #0f172a);">
                        Student Roster — 
                        @if($selectedReportType === 'term')
                            Term Report Card
                        @elseif($selectedReportType === 'final')
                            Final Report Card
                        @elseif($selectedReportType === 'mid_term' || $selectedReportType === 'exam')
                            Mid Term Assessments
                        @elseif($selectedReportType === 'custom')
                            Custom Report Card
                        @else
                            {{ ucfirst($selectedReportType) }} Report Card
                        @endif
                    </h3>
                    <p style="margin: 0.25rem 0 0 0; font-size: 0.825rem; color: var(--color-text-muted, #64748b);">
                        Generate official individual PDF report cards. Completion requires valid marks for all configured assessments.
                    </p>
                    @if($selectedConfig)
                        <div style="margin-top: 0.4rem; font-size: 0.8rem; display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                            <span style="font-weight: 700; background: #e0e7ff; color: #3730a3; padding: 0.15rem 0.5rem; border-radius: 4px;">
                                Config: {{ $selectedConfig->name }}
                            </span>
                            @php
                                $configuredAsmtNames = $selectedConfig->assessmentSelections->where('is_displayed', true)->map(fn ($s) => $s->assessment?->name)->filter()->values();
                            @endphp
                            <span style="color: var(--color-text-muted, #64748b);">
                                Configured Assessments: <strong>{{ $configuredAsmtNames->isNotEmpty() ? $configuredAsmtNames->implode(', ') : 'None' }}</strong>
                            </span>
                        </div>
                    @endif
                </div>
                <div style="font-size: 0.85rem; font-weight: 600; color: var(--color-text-muted, #64748b);">
                    Total Students: {{ $students->count() }}
                </div>
            </div>

            @if($students->isEmpty())
                <div style="text-align: center; padding: 2.5rem 1rem; color: var(--color-text-muted, #64748b);">
                    No enrolled students found in this classroom section.
                </div>
            @else
                <div style="overflow-x: auto;">
                    <table class="table" style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                        <thead>
                            <tr style="background-color: var(--color-bg-muted, #f8fafc); border-bottom: 2px solid var(--color-border, #e2e8f0); text-align: left;">
                                <th style="padding: 0.6rem 0.75rem; width: 60px;">Roll</th>
                                <th style="padding: 0.6rem 0.75rem; width: 110px;">Admission No</th>
                                <th style="padding: 0.6rem 0.75rem;">Student Name</th>
                                <th style="padding: 0.6rem 0.75rem; width: 140px; text-align: center;">Status</th>
                                <th style="padding: 0.6rem 0.75rem; width: 180px; text-align: center;">Actions</th>
                                <th style="padding: 0.6rem 0.75rem;">Generated Revisions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($students as $sar)
                                <tr style="border-bottom: 1px solid var(--color-border, #e2e8f0);">
                                    <td style="padding: 0.6rem 0.75rem; font-weight: 600;">
                                        {{ $sar->roll_number ?? '-' }}
                                    </td>
                                    <td style="padding: 0.6rem 0.75rem; font-variant-numeric: tabular-nums;">
                                        {{ $sar->student?->admission_number ?? '-' }}
                                    </td>
                                    <td style="padding: 0.6rem 0.75rem; font-weight: 600; color: var(--color-text, #0f172a);">
                                        {{ $sar->student?->student_name ?? 'Unknown' }}
                                    </td>
                                    <td style="padding: 0.6rem 0.75rem; text-align: center;">
                                        @if(! $selectedConfig)
                                            <span style="display: inline-block; padding: 0.2rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: var(--color-text-muted, #64748b);">
                                                —
                                            </span>
                                        @elseif($sar->is_complete)
                                            <span style="display: inline-block; padding: 0.2rem 0.5rem; font-size: 0.75rem; font-weight: 700; color: #166534; background-color: #dcfce7; border-radius: 9999px;">
                                                ✓ Ready
                                            </span>
                                        @else
                                            <span style="display: inline-block; padding: 0.2rem 0.5rem; font-size: 0.75rem; font-weight: 700; color: #991b1b; background-color: #fee2e2; border-radius: 9999px; cursor: help;"
                                                  title="{{ implode('; ', $sar->missing_assessments) }}">
                                                ⚠ Incomplete ({{ count($sar->missing_assessments) }})
                                            </span>
                                        @endif
                                    </td>
                                    <td style="padding: 0.6rem 0.75rem; text-align: center;">
                                        <div style="display: inline-flex; gap: 0.4rem; align-items: center;">
                                            <!-- Preview Button -->
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary"
                                                    onclick="previewReport({{ $sar->id }}, '{{ $selectedReportType }}', {{ $selectedReportType === 'term' && $selectedTermId ? $selectedTermId : 'null' }}, {{ $selectedConfigId ? $selectedConfigId : 'null' }})"
                                                    title="{{ ! $selectedConfigId ? 'Select a Report Configuration first' : 'Preview HTML Report Card' }}"
                                                    {{ ! $selectedConfigId ? 'disabled' : '' }}>
                                                Preview
                                            </button>

                                            <!-- Generate Form -->
                                            <form method="POST" action="{{ route('reports.generate') }}" style="display: inline;" onsubmit="return confirmGenerate({{ $sar->is_complete ? 'true' : 'false' }})">
                                                @csrf
                                                <input type="hidden" name="student_academic_record_id" value="{{ $sar->id }}">
                                                <input type="hidden" name="report_type" value="{{ $selectedReportType }}">
                                                @if($selectedReportType === 'term')
                                                    <input type="hidden" name="term_id" value="{{ $selectedTermId }}">
                                                @endif
                                                @if($selectedConfigId)
                                                    <input type="hidden" name="report_configuration_id" value="{{ $selectedConfigId }}">
                                                @endif

                                                <button type="submit" 
                                                        class="btn btn-sm btn-primary"
                                                        {{ (! $canGenerate || ! $sar->is_complete || ! $selectedConfigId) ? 'disabled' : '' }}
                                                        title="{{ ! $canGenerate ? 'Unauthorized' : (! $selectedConfigId ? 'No Report Configuration selected' : (! $sar->is_complete ? 'Cannot generate: mark missing' : 'Generate PDF Revision')) }}">
                                                    Generate PDF
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                    <td style="padding: 0.6rem 0.75rem;">
                                        @if($sar->generatedReports->isEmpty())
                                            <span style="font-size: 0.8rem; color: var(--color-text-muted, #94a3b8);">No revisions yet</span>
                                        @else
                                            <div style="display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center;">
                                                @foreach($sar->generatedReports as $rev)
                                                    <a href="{{ route('reports.download', $rev) }}" 
                                                       class="btn btn-sm"
                                                       style="padding: 0.15rem 0.45rem; font-size: 0.75rem; background-color: #f1f5f9; border: 1px solid #cbd5e1; color: #1e293b; text-decoration: none; border-radius: 3px;"
                                                       title="Generated on {{ $rev->generated_at->format('d M Y, h:i A') }} by {{ $rev->generatedByUser?->display_name ?? 'System' }}">
                                                        📄 Rev {{ $rev->revision_number }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @else
        <div class="card" style="padding: 2.5rem 1.5rem; text-align: center; color: var(--color-text-muted, #64748b);">
            Please select an Academic Year, Class, and Section above to view student report card statuses.
        </div>
    @endif
</div>

<!-- Modal Container for HTML Preview -->
<div id="preview-modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.6); overflow: auto;">
    <div style="background-color: #ffffff; margin: 2% auto; width: 92%; max-width: 1100px; border-radius: 6px; box-shadow: 0 10px 25px rgba(0,0,0,0.25); overflow: hidden; display: flex; flex-direction: column; max-height: 94vh;">
        <div style="padding: 0.75rem 1.25rem; background-color: #1e3a8a; color: #ffffff; display: flex; justify-content: space-between; align-items: center;">
            <h4 style="margin: 0; font-size: 1rem; font-weight: 600;">Report Card Draft Preview</h4>
            <button type="button" onclick="closePreviewModal()" style="background: transparent; border: none; color: #ffffff; font-size: 1.25rem; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        <div id="preview-content" style="padding: 1rem; overflow-y: auto; flex: 1; background-color: #f8fafc;">
            <div style="text-align: center; padding: 2rem; color: #64748b;">Loading draft preview...</div>
        </div>
        <div style="padding: 0.75rem 1.25rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; background-color: #ffffff;">
            <button type="button" class="btn btn-secondary" onclick="closePreviewModal()">Close</button>
        </div>
    </div>
</div>

<script>
function confirmGenerate(isComplete) {
    if (!isComplete) {
        alert('Cannot generate report: One or more required assessment marks are missing for this student.');
        return false;
    }
    return confirm('Generate an official PDF report revision for this student?');
}

function previewReport(sarId, reportType, termId, configId) {
    if (!configId) {
        alert('Please select a valid Report Configuration before previewing.');
        return;
    }
    const cleanTermId = (reportType === 'term' && termId) ? termId : null;
    const modal = document.getElementById('preview-modal');
    const content = document.getElementById('preview-content');
    modal.style.display = 'block';
    content.innerHTML = '<div style="text-align: center; padding: 2rem; color: #64748b;">Loading draft preview...</div>';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    fetch('{{ route("reports.preview") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'text/html',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            student_academic_record_id: sarId,
            report_type: reportType,
            term_id: cleanTermId,
            report_configuration_id: configId
        })
    })
    .then(async response => {
        const text = await response.text();
        if (!response.ok) {
            content.innerHTML = text;
        } else {
            content.innerHTML = '<iframe style="width: 100%; height: 75vh; border: 1px solid #cbd5e1; background: #fff;" srcdoc="' + 
                text.replace(/"/g, '&quot;') + '"></iframe>';
        }
    })
    .catch(err => {
        content.innerHTML = '<div class="alert alert-danger">Failed to load preview: ' + err.message + '</div>';
    });
}

function closePreviewModal() {
    document.getElementById('preview-modal').style.display = 'none';
    document.getElementById('preview-content').innerHTML = '';
}

// Close preview modal on escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closePreviewModal();
    }
});
</script>
@endsection
