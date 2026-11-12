<?php

namespace App\Core\Printing\Drivers;

use App\Core\Printing\Contracts\PdfGeneratorInterface;
use Illuminate\Support\Facades\View;
use Symfony\Component\Process\Process;

class BrowsershotDriver implements PdfGeneratorInterface
{
    protected ?string $resolvedBinary = null;
    protected ?string $fontBase64Cache = null;

    public function getName(): string
    {
        return 'browsershot';
    }

    /**
     * Locate Chrome / Chromium / Edge executable or detect Spatie Browsershot.
     */
    public function getBinaryPath(): ?string
    {
        if ($this->resolvedBinary !== null) {
            return $this->resolvedBinary;
        }

        $candidates = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                $this->resolvedBinary = $candidate;
                return $this->resolvedBinary;
            }
        }

        // Try where/which
        $whichCmd = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'where.exe chrome' : 'which google-chrome';
        $output = @shell_exec($whichCmd);
        if (!empty($output)) {
            $lines = explode("\n", trim($output));
            if (!empty($lines[0]) && file_exists(trim($lines[0]))) {
                $this->resolvedBinary = trim($lines[0]);
                return $this->resolvedBinary;
            }
        }

        return null;
    }

    public function isAvailable(): bool
    {
        if (class_exists(\Spatie\Browsershot\Browsershot::class)) {
            return true;
        }

        return $this->getBinaryPath() !== null;
    }

    public function getUnavailableReason(): ?string
    {
        if ($this->isAvailable()) {
            return null;
        }

        return 'Neither spatie/browsershot with Node/Puppeteer nor a headless Chrome/Edge binary was found on this system.';
    }

    /**
     * Get base64 encoded Noto Nastaliq Urdu font for self-contained embedding.
     */
    protected function getFontBase64(): ?string
    {
        if ($this->fontBase64Cache !== null) {
            return $this->fontBase64Cache;
        }

        $fontPath = resource_path('fonts/NotoNastaliqUrdu-Regular.ttf');
        if (file_exists($fontPath)) {
            $this->fontBase64Cache = base64_encode(file_get_contents($fontPath));
            return $this->fontBase64Cache;
        }

        return null;
    }

    /**
     * Renders a Blade view template with data into a raw PDF binary string.
     */
    public function render(string $view, array $data = [], array $options = []): string
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('Browsershot/Chromium driver is unavailable: ' . $this->getUnavailableReason());
        }

        // Inject font base64 into view data if available
        $data['urduFontBase64'] = $this->getFontBase64();

        // Render Blade template to HTML string
        $html = View::make($view, $data)->render();

        // If Spatie Browsershot package is installed, use it
        if (class_exists(\Spatie\Browsershot\Browsershot::class)) {
            return $this->renderWithSpatie($html, $options);
        }

        // Fallback to direct Chromium Headless execution
        return $this->renderWithChromiumCli($html, $options);
    }

    /**
     * Render via direct Chromium Headless CLI.
     */
    protected function renderWithChromiumCli(string $html, array $options = []): string
    {
        $binary = $this->getBinaryPath();
        if (!$binary) {
            throw new \RuntimeException('Chromium executable not found.');
        }

        $tempDir = storage_path('app/temp_pdf');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $id = uniqid('pdf_', true);
        $htmlFile = $tempDir . DIRECTORY_SEPARATOR . $id . '.html';
        $pdfFile = $tempDir . DIRECTORY_SEPARATOR . $id . '.pdf';

        file_put_contents($htmlFile, $html);

        $command = [
            $binary,
            '--headless=new',
            '--disable-gpu',
            '--allow-file-access-from-files',
            '--no-pdf-header-footer',
            '--print-to-pdf=' . $pdfFile,
            'file:///' . str_replace('\\', '/', $htmlFile),
        ];

        // Process execution with timeout
        $process = new Process($command);
        $process->setTimeout(30);
        $process->run();

        if (!$process->isSuccessful() && !file_exists($pdfFile)) {
            @unlink($htmlFile);
            throw new \RuntimeException('Chromium PDF generation failed: ' . $process->getErrorOutput());
        }

        // Check if companion screenshot is requested
        if (!empty($options['screenshot_path'])) {
            $screenshotFile = $options['screenshot_path'];
            $screenDir = dirname($screenshotFile);
            if (!is_dir($screenDir)) {
                mkdir($screenDir, 0755, true);
            }

            $width = $options['screenshot_width'] ?? 1400;
            $height = $options['screenshot_height'] ?? 900;

            $screenCmd = [
                $binary,
                '--headless=new',
                '--disable-gpu',
                '--allow-file-access-from-files',
                '--window-size=' . $width . ',' . $height,
                '--screenshot=' . $screenshotFile,
                'file:///' . str_replace('\\', '/', $htmlFile),
            ];

            $screenProc = new Process($screenCmd);
            $screenProc->setTimeout(15);
            $screenProc->run();
        }

        if (!file_exists($pdfFile)) {
            @unlink($htmlFile);
            throw new \RuntimeException('Expected PDF output file was not created: ' . $pdfFile);
        }

        $pdfBytes = file_get_contents($pdfFile);

        // Clean up temporary files
        @unlink($htmlFile);
        @unlink($pdfFile);

        return $pdfBytes;
    }

    /**
     * Render via Spatie Browsershot.
     */
    protected function renderWithSpatie(string $html, array $options = []): string
    {
        $browsershot = \Spatie\Browsershot\Browsershot::html($html);

        if (!empty($options['landscape'])) {
            $browsershot->landscape();
        }

        if (!empty($options['paper_size'])) {
            if ($options['paper_size'] === 'a4') {
                $browsershot->format('A4');
            } elseif (is_array($options['paper_size'])) {
                $browsershot->paperSize($options['paper_size']['width'], $options['paper_size']['height']);
            }
        }

        $browsershot->showBackground();
        $browsershot->margins(
            $options['margin_top'] ?? 0,
            $options['margin_right'] ?? 0,
            $options['margin_bottom'] ?? 0,
            $options['margin_left'] ?? 0
        );

        return $browsershot->pdf();
    }
}

