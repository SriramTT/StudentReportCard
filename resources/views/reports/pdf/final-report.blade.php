@extends('reports.pdf.layouts.report-canvas')

@section('content')
    @include('reports.pdf.partials.header')
    @include('reports.pdf.partials.student-info')

    @if (!empty($payload->annualExamData))
        <div style="margin-bottom: 12px;">
            <div style="font-size: 8.5pt; font-weight: 700; color: #1e3a8a; text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.04em;">
                {{ $payload->annualExamData['assessment_name'] ?? 'Annual Exam' }}
            </div>
            <table class="academic-table">
                <thead>
                    <tr>
                        <th class="col-subject">Subject</th>
                        <th>Marks</th>
                        <th>Percentage</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payload->annualExamData['rows'] as $row)
                        <tr>
                            <td class="cell-subject">{{ $row['subject_name'] }}</td>
                            <td class="cell-number" style="font-weight: 600;">
                                {{ $row['display_mark'] }}
                                @if (!empty($row['maximum_marks']))
                                    <span style="font-size: 7.5pt; font-weight: normal; color: #64748b;"> / {{ number_format((float) $row['maximum_marks'], 2) }}</span>
                                @endif
                            </td>
                            <td class="cell-number" style="font-weight: 600;">
                                @if ($row['percentage'] !== null)
                                    {{ number_format((float) $row['percentage'], 2) }}%
                                @else
                                    <span class="status-na">N/A</span>
                                @endif
                            </td>
                            <td class="cell-result">
                                @php
                                    $resStatus = strtoupper($row['status'] ?? ($row['result'] ?? ''));
                                @endphp
                                @if ($resStatus === 'PASS')
                                    <span class="status-pass">PASS</span>
                                @elseif ($resStatus === 'FAIL')
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
                        <td class="cell-number" style="font-weight: 700;">
                            {{ $payload->annualExamData['formatted_total_marks'] }}
                        </td>
                        <td class="cell-number" style="font-weight: 700;">
                            @if (($payload->annualExamData['percentage'] ?? null) !== null)
                                {{ $payload->annualExamData['formatted_percentage'] }}
                            @else
                                <span class="status-na">—</span>
                            @endif
                        </td>
                        <td class="cell-result" style="font-weight: 700;">
                            @php
                                $annualRes = strtoupper($payload->annualExamData['result'] ?? ($payload->annualExamData['status'] ?? ''));
                            @endphp
                            @if ($annualRes === 'PASS')
                                <span class="status-pass">PASS</span>
                            @elseif ($annualRes === 'FAIL')
                                <span class="status-fail">FAIL</span>
                            @else
                                <span class="status-na">{{ $payload->annualExamData['result'] ?? '—' }}</span>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    <div style="font-size: 8.5pt; font-weight: 700; color: #1e3a8a; text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.04em;">
        Term Reports Summary
    </div>
    <table class="academic-table">
        <thead>
            <tr>
                <th class="col-subject" rowspan="2">Subject</th>
                @foreach ($payload->terms as $term)
                    <th colspan="2">{{ $term['name'] }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach ($payload->terms as $term)
                    {{--
                        Portrait: distribute term columns across remaining width.
                        With col-subject at 28%, remaining ~72% shared across
                        (termCount × 2) data columns. Let table-layout:auto decide
                        and use font-size to keep headers readable when terms > 2.
                    --}}
                    <th style="font-size: 7.5pt; white-space: nowrap;">%</th>
                    <th style="font-size: 7.5pt; white-space: nowrap;">Result</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($payload->subjectRows as $row)
                <tr>
                    <td class="cell-subject">{{ $row->subjectName }}</td>
                    @foreach ($payload->terms as $term)
                        @php
                            $summary = $row->termSummaries[$term['id']] ?? null;
                        @endphp
                        <td class="cell-number" style="font-weight: 600;">
                            @if ($summary && $summary['percentage'] !== null)
                                {{ number_format((float) $summary['percentage'], 2) }}%
                            @else
                                <span class="status-na">N/A</span>
                            @endif
                        </td>
                        <td class="cell-result">
                            @php
                                $resStatus = strtoupper($summary['status'] ?? ($summary['result'] ?? ''));
                            @endphp
                            @if ($summary && $resStatus === 'PASS')
                                <span class="status-pass">PASS</span>
                            @elseif ($summary && $resStatus === 'FAIL')
                                <span class="status-fail">FAIL</span>
                            @else
                                <span class="status-na">N/A</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Portrait layout: attendance records + overall result above full-width signatures --}}
    <div class="summary-container">
        <div class="summary-top-row">
            @if (!empty($payload->termAttendances))
                <div class="attendance-box">
                    <div class="attendance-title">Term Attendance Records</div>
                    <div class="attendance-metrics" style="flex-wrap: wrap; gap: 10px 20px;">
                        @foreach ($payload->termAttendances as $termId => $att)
                            <div class="attendance-metric-item">
                                <span class="attendance-metric-label">{{ $att['term_name'] }}</span>
                                <span class="attendance-metric-value">
                                    {{ $att['days_attended'] }} / {{ $att['total_working_days'] }}
                                    ({{ $att['formatted_percentage'] }})
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="overall-result-box" style="padding: 6px 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; display: inline-flex; align-items: center; gap: 8px; font-size: 8.5pt; align-self: flex-end; white-space: nowrap;">
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
