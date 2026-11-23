<?php

namespace App\Core\Import\Services;

use InvalidArgumentException;

class CsvImportService
{
    /**
     * Parse CSV content into normalized associative rows.
     *
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>}
     */
    public function parse(string $csvContent, int $maxRows = 5000): array
    {
        $clean = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent) ?? $csvContent;
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $clean);
        rewind($stream);

        $rawHeaders = fgetcsv($stream);
        if (!is_array($rawHeaders) || count($rawHeaders) === 0) {
            fclose($stream);
            throw new InvalidArgumentException('CSV file is empty or missing a header row.');
        }

        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $rawHeaders);
        $rows = [];
        $lineNumber = 1;

        while (($data = fgetcsv($stream)) !== false) {
            $lineNumber++;
            if ($data === [null] || count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            if (count($rows) >= $maxRows) {
                fclose($stream);
                throw new InvalidArgumentException("CSV exceeds maximum allowed {$maxRows} rows per import.");
            }

            $assoc = ['_line' => (string) $lineNumber];
            foreach ($headers as $idx => $key) {
                if ($key !== '') {
                    $assoc[$key] = trim((string) ($data[$idx] ?? ''));
                }
            }
            $rows[] = $assoc;
        }

        fclose($stream);

        return ['headers' => $headers, 'rows' => $rows];
    }
}
