<div class="report-footer">
    <div class="footer-left">
        <strong>Report Revision {{ $payload->revisionNumber }}</strong> | Generated on {{ is_string($payload->generatedAt) ? \Carbon\CarbonImmutable::parse($payload->generatedAt)->format('d M Y, h:i A') : $payload->generatedAt->format('d M Y, h:i A') }}
    </div>
    <div class="footer-right">
        Official School Report Card
    </div>
</div>
