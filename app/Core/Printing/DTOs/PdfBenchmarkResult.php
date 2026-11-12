<?php

namespace App\Core\Printing\DTOs;

class PdfBenchmarkResult
{
    public function __construct(
        public readonly string $engineName,
        public readonly string $testCase,
        public readonly bool $isSuccess,
        public readonly float $latencyMs,
        public readonly float $peakMemoryMb,
        public readonly int $fileSizeBytes = 0,
        public readonly ?string $pdfPath = null,
        public readonly ?string $screenshotPath = null,
        public readonly int $pageCount = 0,
        public readonly ?string $errorMessage = null,
        public readonly array $checks = [],
        public readonly array $details = []
    ) {}

    public static function failure(string $engineName, string $testCase, string $errorMessage): self
    {
        return new self(
            engineName: $engineName,
            testCase: $testCase,
            isSuccess: false,
            latencyMs: 0.0,
            peakMemoryMb: round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            errorMessage: $errorMessage
        );
    }

    public function toArray(): array
    {
        return [
            'engine' => $this->engineName,
            'test_case' => $this->testCase,
            'success' => $this->isSuccess,
            'latency_ms' => round($this->latencyMs, 2),
            'peak_memory_mb' => round($this->peakMemoryMb, 2),
            'file_size_bytes' => $this->fileSizeBytes,
            'file_size_kb' => round($this->fileSizeBytes / 1024, 2),
            'pdf_path' => $this->pdfPath,
            'screenshot_path' => $this->screenshotPath,
            'page_count' => $this->pageCount,
            'error' => $this->errorMessage,
            'checks' => $this->checks,
            'details' => $this->details,
        ];
    }
}

