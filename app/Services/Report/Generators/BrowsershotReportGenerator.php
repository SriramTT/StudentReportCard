<?php

namespace App\Services\Report\Generators;

use App\Contracts\ReportGeneratorContract;
use Spatie\Browsershot\Browsershot;

class BrowsershotReportGenerator implements ReportGeneratorContract
{
    /**
     * Compile raw HTML into binary PDF bytes using Spatie Browsershot.
     *
     * @param string $html Rendered Blade HTML template.
     * @return string Raw PDF binary string.
     */
    public function generatePdfFromHtml(string $html): string
    {
        $browsershot = Browsershot::html($html)
            ->format('A4')
            ->landscape(false)
            ->margins(12, 12, 12, 12)
            ->showBackground()
            ->setOption('args', [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
            ]);

        // Environment-configurable executable paths
        $nodeBinary = env('BROWSERSHOT_NODE_PATH') ?: env('NODE_PATH');
        if ($nodeBinary) {
            $browsershot->setNodeBinary($nodeBinary);
        }

        $npmBinary = env('BROWSERSHOT_NPM_PATH') ?: env('NPM_PATH');
        if ($npmBinary) {
            $browsershot->setNpmBinary($npmBinary);
        }

        $chromePath = env('BROWSERSHOT_CHROME_PATH') ?: env('CHROME_PATH');
        if (! $chromePath) {
            // Auto-detect standard Chrome installation if present on Windows
            $defaultChrome = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
            if (PHP_OS_FAMILY === 'Windows' && file_exists($defaultChrome)) {
                $chromePath = $defaultChrome;
            }
        }

        if ($chromePath) {
            $browsershot->setChromePath($chromePath);
        }

        $nodeModulePath = base_path('node_modules');
        if (file_exists($nodeModulePath)) {
            $browsershot->setNodeModulePath($nodeModulePath);
        }

        return $browsershot->pdf();
    }
}
