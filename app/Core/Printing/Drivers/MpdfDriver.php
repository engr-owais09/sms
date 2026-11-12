<?php

namespace App\Core\Printing\Drivers;

use App\Core\Printing\Contracts\PdfGeneratorInterface;
use Illuminate\Support\Facades\View;

class MpdfDriver implements PdfGeneratorInterface
{
    public function getName(): string
    {
        return 'mpdf';
    }

    public function isAvailable(): bool
    {
        return class_exists(\Mpdf\Mpdf::class);
    }

    public function getUnavailableReason(): ?string
    {
        if ($this->isAvailable()) {
            return null;
        }

        return 'Package mpdf/mpdf is not installed in vendor. Install via composer require --dev mpdf/mpdf to enable in-process PHP rendering.';
    }

    public function render(string $view, array $data = [], array $options = []): string
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('mPDF driver is unavailable: ' . $this->getUnavailableReason());
        }

        $html = View::make($view, $data)->render();

        $mpdfConfig = [
            'mode' => 'utf-8',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'margin_left' => $options['margin_left'] ?? 5,
            'margin_right' => $options['margin_right'] ?? 5,
            'margin_top' => $options['margin_top'] ?? 5,
            'margin_bottom' => $options['margin_bottom'] ?? 5,
        ];

        if (!empty($options['landscape'])) {
            $mpdfConfig['orientation'] = 'L';
        }

        if (!empty($options['paper_size']) && is_array($options['paper_size'])) {
            $mpdfConfig['format'] = [$options['paper_size']['width'], $options['paper_size']['height']];
        } else {
            $mpdfConfig['format'] = $options['paper_size'] ?? 'A4';
        }

        // Add custom font directory for Noto Nastaliq Urdu if directory exists
        $fontDir = resource_path('fonts');
        if (is_dir($fontDir)) {
            $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
            $fontDirs = $defaultConfig['fontDir'];
            $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
            $fontData = $defaultFontConfig['fontdata'];

            $mpdfConfig['fontDir'] = array_merge($fontDirs, [$fontDir]);
            $mpdfConfig['fontdata'] = $fontData + [
                'notonastaliqurdu' => [
                    'R' => 'NotoNastaliqUrdu-Regular.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ];
        }

        /** @var \Mpdf\Mpdf $mpdf */
        $mpdfClass = \Mpdf\Mpdf::class;
        $mpdf = new $mpdfClass($mpdfConfig);

        $mpdf->SetDirectionality('rtl');
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }
}

