@extends('reports.pdf.layouts.report-canvas')

@section('content')
    @include('reports.pdf.partials.header')
    @include('reports.pdf.partials.student-info')

    <table class="academic-table">
        <thead>
            <tr>
                <th class="col-subject">Subject</th>
                @foreach ($payload->assessmentColumns as $col)
                    <th>
                        {{ $col->assessmentName }}
                        @if ($col->maxMarks !== null)
                            <br><span style="font-size: 7pt; font-weight: normal; color: #64748b;">(Max: {{ $col->maxMarks }})</span>
                        @endif
                    </th>
                @endforeach
                <th style="white-space: nowrap;">Term %</th>
                <th style="white-space: nowrap;">Result</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($payload->subjectRows as $row)
                <tr>
                    <td class="cell-subject">{{ $row->subjectName }}</td>
                    @foreach ($payload->assessmentColumns as $col)
                        @php
                            $mark = $row->marks[$col->id] ?? ($row->assessmentMarks[$col->id] ?? null);
                            $displayMark = $mark['display_mark'] ?? '—';
                        @endphp
                        <td class="cell-number">
                            @if ($mark === null || ($mark['result_status'] ?? '') === 'blank')
                                <span class="status-na">—</span>
                            @elseif (!empty($mark['is_absent']) || ($mark['result_status'] ?? '') === 'absent')
                                <span class="status-absent">A</span>
                            @else
                                {{ $displayMark }}
                            @endif
                        </td>
                    @endforeach
                    <td class="cell-number" style="font-weight: 700;">
                        @if ($row->termPercentage !== null)
                            {{ number_format((float) $row->termPercentage, 2) }}%
                        @elseif ($row->percentage !== null)
                            {{ number_format((float) $row->percentage, 2) }}%
                        @else
                            <span class="status-na">N/A</span>
                        @endif
                    </td>
                    <td class="cell-result">
                        @if (strtoupper($row->termStatus ?? ($row->result ?? '')) === 'PASS')
                            <span class="status-pass">PASS</span>
                        @elseif (strtoupper($row->termStatus ?? ($row->result ?? '')) === 'FAIL')
                            <span class="status-fail">FAIL</span>
                        @else
                            <span class="status-na">N/A</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="row-total" style="font-weight: 700; background-color: #f8fafc; border-top: 1.5px solid #0f172a;">
                <td class="cell-subject" style="font-weight: 700;">TOTAL</td>
                @foreach ($payload->assessmentColumns as $col)
                    @php
                        $colTotal = $payload->assessmentColumnTotals[$col->id] ?? null;
                    @endphp
                    <td class="cell-number" style="font-weight: 700;">
                        @if ($colTotal)
                            {{ $colTotal['formatted_obtained'] }}
                            @if ($colTotal['maximum'] > 0)
                                <br><span style="font-size: 7pt; font-weight: normal; color: #64748b;">(Max: {{ $colTotal['formatted_maximum'] }})</span>
                            @endif
                        @else
                            —
                        @endif
                    </td>
                @endforeach
                <td class="cell-number" style="font-weight: 700;">
                    {{ $payload->formattedOverallPercentage }}
                </td>
                <td class="cell-result" style="font-weight: 700;">
                    @if (strtoupper($payload->overallResult) === 'PASS')
                        <span class="status-pass">PASS</span>
                    @elseif (strtoupper($payload->overallResult) === 'FAIL')
                        <span class="status-fail">FAIL</span>
                    @else
                        <span class="status-na">{{ $payload->overallResult }}</span>
                    @endif
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- Portrait layout: attendance + overall result stacked above full-width signatures --}}
    <div class="summary-container">
        <div class="summary-top-row">
            @if ($payload->attendance)
                <div class="attendance-box">
                    <div class="attendance-title">Term Attendance Summary</div>
                    <div class="attendance-metrics">
                        <div class="attendance-metric-item">
                            <span class="attendance-metric-label">Days Attended</span>
                            <span class="attendance-metric-value">{{ $payload->attendance['days_attended'] }}</span>
                        </div>
                        <div class="attendance-metric-item">
                            <span class="attendance-metric-label">Total Working Days</span>
                            <span class="attendance-metric-value">{{ $payload->attendance['total_working_days'] }}</span>
                        </div>
                        <div class="attendance-metric-item">
                            <span class="attendance-metric-label">Attendance Percentage</span>
                            <span class="attendance-metric-value">{{ $payload->attendance['formatted_percentage'] }}</span>
                        </div>
                    </div>
                </div>
            @endif

            <div class="overall-result-box" style="padding: 6px 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; display: inline-flex; align-items: center; gap: 8px; font-size: 8.5pt; align-self: flex-end;">
                <span style="font-weight: 700; color: #1e293b; text-transform: uppercase;">Overall Result:</span>
                @if (strtoupper($payload->overallResult) === 'PASS')
                    <span class="status-pass" style="font-size: 9pt;">PASS</span>
                @elseif (strtoupper($payload->overallResult) === 'FAIL')
                    <span class="status-fail" style="font-size: 9pt;">FAIL</span>
                @else
                    <span class="status-na" style="font-size: 9pt;">{{ $payload->overallResult }}</span>
                @endif
            </div>
        </div>

        {{-- Signatures: full width, Class Teacher left — Principal right --}}
        <div class="signatures-container">
            <div class="signature-block">
                @if (!empty($payload->classTeacherSignatureDataUri))
                    <div class="signature-image-container">
                        <img src="{{ $payload->classTeacherSignatureDataUri }}" class="signature-image" alt="Class Teacher Signature">
                    </div>
                @else
                    <div class="signature-image-placeholder"></div>
                @endif
                <div class="signature-line"></div>
                <div class="signature-label">Class Teacher</div>
            </div>
            <div class="signature-block">
                @if (!empty($payload->principalSignatureDataUri))
                    <div class="signature-image-container">
                        <img src="{{ $payload->principalSignatureDataUri }}" class="signature-image" alt="Principal Signature">
                    </div>
                @else
                    <div class="signature-image-placeholder"></div>
                @endif
                <div class="signature-line"></div>
                <div class="signature-label">Principal</div>
            </div>
        </div>
    </div>

    @include('reports.pdf.partials.footer')
@endsection
