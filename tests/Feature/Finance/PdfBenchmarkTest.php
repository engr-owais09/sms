<?php

namespace Tests\Feature\Finance;

use App\Core\Printing\Contracts\PdfGeneratorInterface;
use App\Core\Printing\Drivers\BrowsershotDriver;
use App\Core\Printing\Drivers\MpdfDriver;
use App\Core\Printing\Support\Barcode128;
use Tests\TestCase;

class PdfBenchmarkTest extends TestCase
{
    public function test_barcode_128_generates_valid_vector_svg(): void
    {
        $psid = '100120260001234567';
        $svg = Barcode128::getSvg($psid, 30, 2, true);

        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg"', $svg);
        $this->assertStringContainsString('shape-rendering="crispEdges"', $svg);
        $this->assertStringContainsString('<rect', $svg);
        $this->assertStringContainsString($psid, $svg);
    }

    public function test_browsershot_driver_implements_interface(): void
    {
        $driver = new BrowsershotDriver();
        $this->assertInstanceOf(PdfGeneratorInterface::class, $driver);
        $this->assertEquals('browsershot', $driver->getName());
    }

    public function test_mpdf_driver_implements_interface_and_reports_status_honestly(): void
    {
        $driver = new MpdfDriver();
        $this->assertInstanceOf(PdfGeneratorInterface::class, $driver);
        $this->assertEquals('mpdf', $driver->getName());

        if (!class_exists(\Mpdf\Mpdf::class)) {
            $this->assertFalse($driver->isAvailable());
            $this->assertNotNull($driver->getUnavailableReason());
            $this->assertStringContainsString('mpdf/mpdf is not installed', $driver->getUnavailableReason());

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('mPDF driver is unavailable');
            $driver->render('test-benchmarks.thermal-receipt');
        } else {
            $this->assertTrue($driver->isAvailable());
        }
    }

    public function test_browsershot_driver_renders_voucher_and_thermal_receipt(): void
    {
        $driver = new BrowsershotDriver();

        if (!$driver->isAvailable()) {
            $this->markTestSkipped('Browsershot/Chromium binary not available on this host.');
        }

        $voucherData = [
            'instituteNameEnglish' => 'The Educators Model Campus',
            'instituteNameUrdu' => 'دی ایجوکیٹرز ماڈل کیمپس',
            'branchName' => 'Canal Road Campus, Lahore',
            'challanNo' => 'CHL-2026-10-0042',
            'billingPeriod' => 'October 2026',
            'issueDate' => '01-Oct-2026',
            'dueDate' => '15-Oct-2026',
            'validityDate' => '31-Oct-2026',
            'studentNameEnglish' => 'Muhammad Owais',
            'studentNameUrdu' => 'محمد اویس',
            'fatherNameEnglish' => 'Abdul Rehman',
            'fatherNameUrdu' => 'عبدالرحمٰن',
            'rollNo' => 'STU-2026-0891',
            'classSection' => 'Grade 9 - Section A',
            'psidFormatted' => '1001 2026 0001 2345 67',
            'barcodeSvg' => Barcode128::getSvg('100120260001234567', 24, 2, false),
        ];

        $pdfBytes = $driver->render('test-benchmarks.voucher-three-copy', $voucherData, [
            'landscape' => true,
            'paper_size' => 'a4',
        ]);

        $this->assertNotEmpty($pdfBytes);
        $this->assertStringStartsWith('%PDF-', $pdfBytes);
        $this->assertGreaterThan(5000, strlen($pdfBytes));

        $thermalData = [
            'instituteNameEnglish' => 'The Educators Model Campus',
            'instituteNameUrdu' => 'دی ایجوکیٹرز ماڈل کیمپس',
            'branchName' => 'Canal Road Campus, Lahore',
            'receiptNo' => 'REC-2026-10-0928',
            'dateTime' => '10-Oct-2026 11:42 AM',
            'cashierName' => 'Tariq Mehmood',
            'studentNameEnglish' => 'Muhammad Owais',
            'studentNameUrdu' => 'محمد اویس',
            'fatherNameEnglish' => 'Abdul Rehman',
            'fatherNameUrdu' => 'عبدالرحمٰن',
            'rollNo' => 'STU-2026-0891',
            'classSection' => 'Grade 9 - Section A',
            'barcodeSvg' => Barcode128::getSvg('REC-2026-10-0928', 26, 2, false),
        ];

        $thermalPdfBytes = $driver->render('test-benchmarks.thermal-receipt', $thermalData, [
            'paper_size' => ['width' => 80, 'height' => 210],
        ]);

        $this->assertNotEmpty($thermalPdfBytes);
        $this->assertStringStartsWith('%PDF-', $thermalPdfBytes);
    }

    public function test_artisan_benchmark_command_runs_successfully(): void
    {
        $this->artisan('finance:benchmark-pdf', [
            '--engine' => 'all',
            '--iterations' => 1,
        ])->assertExitCode(0);

        $this->assertFileExists(storage_path('app/testing/pdf_benchmark/benchmark_report.md'));
    }

    public function test_artisan_benchmark_command_fails_safely_in_production_environment(): void
    {
        $originalEnv = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->artisan('finance:benchmark-pdf')
                ->expectsOutput('The finance:benchmark-pdf command is restricted to local and testing environments only.')
                ->assertExitCode(1);
        } finally {
            $this->app['env'] = $originalEnv;
        }

        $this->assertEquals($originalEnv, $this->app['env']);
    }

    public function test_noto_nastaliq_font_and_ofl_license_exist(): void
    {
        $this->assertFileExists(resource_path('fonts/NotoNastaliqUrdu-Regular.ttf'));
        $this->assertFileExists(resource_path('fonts/OFL.txt'));

        $licenseContent = file_get_contents(resource_path('fonts/OFL.txt'));
        $this->assertStringContainsString('SIL OPEN FONT LICENSE Version 1.1', $licenseContent);
        $this->assertStringContainsString('The Noto Project Authors', $licenseContent);
    }
}

