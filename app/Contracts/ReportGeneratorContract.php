<?php

namespace App\Contracts;

interface ReportGeneratorContract
{
    /**
     * Compile raw HTML into binary PDF bytes.
     *
     * @param string $html Rendered Blade HTML template.
     * @return string Raw PDF binary string.
     */
    public function generatePdfFromHtml(string $html): string;
}
