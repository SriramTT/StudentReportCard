<div class="student-info-card">
    <div class="student-info-grid">
        <div class="info-field">
            <div class="info-label">Student Name</div>
            <div class="info-value">{{ $payload->studentName }}</div>
        </div>
        <div class="info-field">
            <div class="info-label">Admission Number</div>
            <div class="info-value">{{ $payload->admissionNumber }}</div>
        </div>
        <div class="info-field">
            <div class="info-label">Class & Section</div>
            <div class="info-value">{{ $payload->className }} - {{ $payload->sectionName }}</div>
        </div>
        <div class="info-field">
            <div class="info-label">Roll Number</div>
            <div class="info-value">{{ $payload->rollNumber !== null ? $payload->rollNumber : '-' }}</div>
        </div>
        <div class="info-field">
            <div class="info-label">Academic Year</div>
            <div class="info-value">{{ $payload->academicYearName }}</div>
        </div>
        <div class="info-field">
            <div class="info-label">Report Period</div>
            <div class="info-value">{{ $payload->termName ?: 'Full Academic Year' }}</div>
        </div>
    </div>
</div>
