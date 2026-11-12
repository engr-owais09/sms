<?php

namespace App\Core\Printing\Contracts;

interface PdfGeneratorInterface
{
    /**
     * Get the identifier name of the PDF driver (e.g., 'browsershot', 'mpdf').
     */
    public function getName(): string;

    /**
     * Determine whether the underlying engine/binaries/extensions are available in this environment.
     */
    public function isAvailable(): bool;

    /**
     * Get the diagnostic reason if the engine is unavailable, or null if available.
     */
    public function getUnavailableReason(): ?string;

    /**
     * Renders a Blade view template with data into a raw PDF binary string.
     *
     * @param string $view Blade view name (e.g. 'test-benchmarks.voucher-three-copy')
     * @param array $data View payload
     * @param array $options Options (e.g. ['orientation' => 'landscape', 'paper_size' => 'a4', 'screenshot' => true])
     * @return string Raw PDF binary string
     * @throws \RuntimeException If rendering fails or engine is unavailable
     */
    public function render(string $view, array $data = [], array $options = []): string;
}

