<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $payload->reportTitle }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 12mm 15mm 12mm;
            @bottom-right {
                content: "Page " counter(page) " of " counter(pages);
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                font-size: 8pt;
                color: #64748b;
            }
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.35;
            color: #1e293b;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }

        .report-page {
            width: 100%;
            background-color: #ffffff;
        }

        /* ─── Header ─────────────────────────────────────────── */
        .report-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .school-branding {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .school-logo {
            max-height: 52px;
            max-width: 110px;
            object-fit: contain;
        }

        .school-info h1 {
            margin: 0;
            font-size: 13pt;
            font-weight: 700;
            color: #1e3a8a;
            letter-spacing: -0.01em;
            text-transform: uppercase;
        }

        .school-info .report-type-badge {
            font-size: 10pt;
            font-weight: 600;
            color: #475569;
            margin-top: 2px;
        }

        .report-meta-header {
            text-align: right;
            font-size: 8.5pt;
            color: #475569;
        }

        .report-meta-header .academic-year {
            font-size: 10pt;
            font-weight: 700;
            color: #0f172a;
        }

        /* ─── Student Information Grid (3-col × 2-row for portrait) ── */
        .student-info-card {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 8px 12px;
            margin-bottom: 14px;
        }

        .student-info-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px 14px;
        }

        .info-field {
            font-size: 8.5pt;
        }

        .info-label {
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 7.5pt;
            letter-spacing: 0.02em;
        }

        .info-value {
            color: #0f172a;
            font-weight: 700;
            margin-top: 1px;
            word-break: break-word;
        }

        /* ─── Academic Table ──────────────────────────────────── */
        .academic-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            page-break-inside: auto;
            table-layout: auto;
        }

        /* Repeat table header on multi-page output */
        .academic-table thead {
            display: table-header-group;
        }

        .academic-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        .academic-table th {
            background-color: #f1f5f9;
            color: #1e293b;
            font-weight: 700;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            padding: 6px 6px;
            border: 1px solid #cbd5e1;
            text-align: center;
            vertical-align: middle;
            /* allow long header text to wrap naturally */
            white-space: normal;
            word-break: break-word;
        }

        /* Subject column: left-aligned, wider share via min-content */
        .academic-table th.col-subject {
            text-align: left;
            width: 28%;
            min-width: 70px;
        }

        .academic-table td {
            padding: 5px 6px;
            border: 1px solid #e2e8f0;
            font-size: 8.5pt;
            vertical-align: middle;
        }

        .academic-table td.cell-subject {
            font-weight: 600;
            color: #0f172a;
            text-align: left;
            word-break: break-word;
        }

        .academic-table td.cell-number {
            text-align: center;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .academic-table td.cell-result {
            text-align: center;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-pass {
            color: #15803d;
            font-weight: 700;
        }

        .status-fail {
            color: #b91c1c;
            font-weight: 700;
        }

        .status-absent {
            color: #c2410c;
            font-weight: 700;
        }

        .status-na {
            color: #94a3b8;
            font-style: italic;
        }

        /* ─── Summary section (portrait: stacked) ──────────────── */
        /*
         * In landscape the summary-container held attendance (left, max 50-55%)
         * and signatures (right). In portrait we stack them vertically so neither
         * is squashed. The outer wrapper just provides vertical spacing and
         * keeps both children from being split across pages.
         */
        .summary-container {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-top: 12px;
            page-break-inside: avoid;
        }

        /* ─── Attendance & Overall Result row ──────────────────── */
        .summary-top-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
        }

        .attendance-box {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 8px 12px;
            flex: 1 1 auto;
        }

        .attendance-title {
            font-size: 8pt;
            font-weight: 700;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            margin-bottom: 4px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 2px;
        }

        .attendance-metrics {
            display: flex;
            gap: 20px;
            font-size: 8.5pt;
            flex-wrap: wrap;
        }

        .attendance-metric-item {
            display: flex;
            flex-direction: column;
        }

        .attendance-metric-label {
            font-size: 7.5pt;
            color: #64748b;
        }

        .attendance-metric-value {
            font-weight: 700;
            color: #0f172a;
        }

        /* ─── Signatures (full width, space-between) ────────────── */
        .signatures-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
            page-break-inside: avoid;
        }

        .signature-block {
            text-align: center;
            width: 150px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
        }

        .signature-image-container {
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 4px;
        }

        .signature-image {
            max-height: 45px;
            max-width: 140px;
            object-fit: contain;
        }

        .signature-image-placeholder {
            height: 45px;
        }

        .signature-line {
            border-top: 1px solid #475569;
            margin-bottom: 4px;
            width: 100%;
        }

        .signature-label {
            font-size: 8pt;
            font-weight: 600;
            color: #475569;
        }

        /* ─── Footer ────────────────────────────────────────────── */
        .report-footer {
            margin-top: 18px;
            padding-top: 6px;
            border-top: 1px solid #cbd5e1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 7.5pt;
            color: #64748b;
            page-break-inside: avoid;
        }

        .footer-left {
            font-weight: 500;
        }

        .footer-right {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="report-page">
        @yield('content')
    </div>
</body>
</html>
