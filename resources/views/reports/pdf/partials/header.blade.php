<div class="report-header">
    <div class="school-branding">
        @if ($payload->schoolLogoBase64 || $payload->schoolLogoDataUri)
            <img class="school-logo" src="{{ $payload->schoolLogoBase64 ?? $payload->schoolLogoDataUri }}" alt="{{ $payload->schoolName }}">
        @endif
        <div class="school-info">
            <h1>{{ $payload->schoolName }}</h1>
            <div class="report-type-badge">{{ $payload->reportTitle }}</div>
        </div>
    </div>
    <div class="report-meta-header">
        <div class="academic-year">Academic Year: {{ $payload->academicYearName }}</div>
        @if ($payload->termName)
            <div>{{ $payload->termName }}</div>
        @endif
    </div>
</div>
