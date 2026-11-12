<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>3-Copy Fee Voucher - Benchmark</title>
    <style>
        @if (!empty($urduFontBase64))
        @font-face {
            font-family: 'Noto Nastaliq Urdu';
            src: url('data:font/truetype;charset=utf-8;base64,{{ $urduFontBase64 }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @endif

        @page {
            size: A4 landscape;
            margin: 4mm 5mm 4mm 5mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 8.5px;
            color: #1a202c;
            background: #ffffff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .urdu-text {
            font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
            font-size: 11px;
            line-height: 1.4;
            direction: rtl;
            unicode-bidi: embed;
        }

        .container {
            width: 100%;
            display: table;
            table-layout: fixed;
            height: 195mm;
        }

        .copy-column {
            display: table-cell;
            width: 33.333%;
            vertical-align: top;
            padding: 0 4.5mm;
            height: 100%;
        }

        .copy-column.border-right {
            border-right: 1.5px dashed #a0aec0;
        }

        .header {
            text-align: center;
            border-bottom: 1.5px solid #2b6cb0;
            padding-bottom: 3px;
            margin-bottom: 4px;
        }

        .inst-name-en {
            font-size: 11px;
            font-weight: 800;
            color: #2b6cb0;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .inst-name-ur {
            color: #2c5282;
            font-size: 12px;
            margin-top: 1px;
        }

        .inst-sub {
            font-size: 7.5px;
            color: #4a5568;
        }

        .copy-badge {
            display: inline-block;
            background-color: #2b6cb0;
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            padding: 1.5px 8px;
            border-radius: 2px;
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
            font-size: 8px;
        }

        .meta-table td {
            padding: 1px 2px;
        }

        .meta-table td.label {
            color: #4a5568;
            width: 32%;
        }

        .meta-table td.val {
            font-weight: bold;
            color: #1a202c;
        }

        .student-box {
            background-color: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 3px;
            padding: 3px 4px;
            margin-bottom: 4px;
        }

        .fee-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
            font-size: 7.5px;
        }

        .fee-table th {
            background-color: #edf2f7;
            border: 1px solid #cbd5e0;
            padding: 2.5px 3px;
            text-align: left;
            font-weight: bold;
            color: #2d3748;
        }

        .fee-table td {
            border: 1px solid #e2e8f0;
            padding: 2px 3px;
        }

        .fee-table td.amount {
            text-align: right;
            font-family: monospace;
            font-size: 8px;
        }

        .fee-table tr.total-row td {
            background-color: #edf2f7;
            font-weight: bold;
            border-top: 1.5px solid #a0aec0;
            color: #1a202c;
        }

        .fee-table tr.late-row td {
            background-color: #fffaf0;
            color: #c05621;
            font-weight: bold;
        }

        .psid-box {
            border: 1px solid #bee3f8;
            background-color: #ebf8ff;
            border-radius: 3px;
            padding: 3px;
            text-align: center;
            margin-bottom: 4px;
        }

        .psid-label {
            font-size: 7px;
            color: #2b6cb0;
            font-weight: bold;
            text-transform: uppercase;
        }

        .psid-number {
            font-size: 11px;
            font-family: monospace;
            font-weight: 800;
            color: #1a365d;
            letter-spacing: 1.2px;
            margin: 1px 0;
        }

        .psid-help {
            font-size: 6.5px;
            color: #4a5568;
        }

        .barcode-wrap {
            text-align: center;
            margin-bottom: 4px;
        }

        .barcode-wrap svg {
            max-width: 95%;
            height: 24px;
        }

        .instructions {
            font-size: 6.5px;
            color: #718096;
            line-height: 1.25;
            margin-bottom: 4px;
            border-left: 2px solid #cbd5e0;
            padding-left: 3px;
        }

        .stamp-row {
            display: table;
            width: 100%;
            margin-top: 5px;
            font-size: 6.5px;
            color: #718096;
        }

        .stamp-box {
            display: table-cell;
            width: 50%;
            text-align: center;
            border-top: 1px dashed #cbd5e0;
            padding-top: 2px;
        }
    </style>
</head>
<body>
    @php
        $copies = [
            ['title' => 'Bank Copy', 'badge_color' => '#2b6cb0'],
            ['title' => 'Institute Copy', 'badge_color' => '#2f855a'],
            ['title' => 'Student Copy', 'badge_color' => '#c05621']
        ];
    @endphp

    <div class="container">
        @foreach ($copies as $index => $copy)
            <div class="copy-column {{ $index < 2 ? 'border-right' : '' }}">
                <!-- Header -->
                <div class="header">
                    <div class="inst-name-ur urdu-text">{{ $instituteNameUrdu ?? 'دی ایجوکیٹرز ماڈل کیمپس' }}</div>
                    <div class="inst-name-en">{{ $instituteNameEnglish ?? 'The Educators Model Campus' }}</div>
                    <div class="inst-sub">{{ $branchName ?? 'Main Campus, Canal Road, Lahore' }}</div>
                    <div class="copy-badge" style="background-color: {{ $copy['badge_color'] }};">{{ $copy['title'] }}</div>
                </div>

                <!-- Voucher Meta -->
                <table class="meta-table">
                    <tr>
                        <td class="label">Challan No:</td>
                        <td class="val">{{ $challanNo ?? 'CHL-2026-10-0042' }}</td>
                        <td class="label">Issue Date:</td>
                        <td class="val">{{ $issueDate ?? '01-Oct-2026' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Billing Month:</td>
                        <td class="val">{{ $billingPeriod ?? 'October 2026' }}</td>
                        <td class="label">Due Date:</td>
                        <td class="val" style="color: #c53030;">{{ $dueDate ?? '15-Oct-2026' }}</td>
                    </tr>
                </table>

                <!-- Student Info with Urdu Nastaliq -->
                <div class="student-box">
                    <table class="meta-table" style="margin-bottom: 0;">
                        <tr>
                            <td class="label">Student Name:</td>
                            <td class="val">
                                <span class="urdu-text">{{ $studentNameUrdu ?? 'محمد اویس' }}</span>
                                <span style="font-size: 7.5px; font-weight: normal; color: #4a5568;">({{ $studentNameEnglish ?? 'M. Owais' }})</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="label">Father Name:</td>
                            <td class="val">
                                <span class="urdu-text">{{ $fatherNameUrdu ?? 'عبدالرحمٰن' }}</span>
                                <span style="font-size: 7.5px; font-weight: normal; color: #4a5568;">({{ $fatherNameEnglish ?? 'Abdul Rehman' }})</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="label">Roll No / ID:</td>
                            <td class="val">{{ $rollNo ?? 'STU-2026-0891' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Class & Sec:</td>
                            <td class="val">{{ $classSection ?? 'Grade 9 - Section A' }}</td>
                        </tr>
                    </table>
                </div>

                <!-- Itemized Fee Structure -->
                <table class="fee-table">
                    <thead>
                        <tr>
                            <th>Fee Description</th>
                            <th style="text-align: right; width: 30%;">Amount (PKR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Monthly Tuition Fee (<span class="urdu-text" style="font-size: 8px;">ٹیوشن فیس</span>)</td>
                            <td class="amount">8,500.00</td>
                        </tr>
                        <tr>
                            <td>Examination Fee (<span class="urdu-text" style="font-size: 8px;">امتحانی فیس</span>)</td>
                            <td class="amount">1,000.00</td>
                        </tr>
                        <tr>
                            <td>Computer & Science Lab (<span class="urdu-text" style="font-size: 8px;">لیب فیس</span>)</td>
                            <td class="amount">1,500.00</td>
                        </tr>
                        <tr style="color: #276749;">
                            <td>Sibling Concession (15% Waiver)</td>
                            <td class="amount">-1,275.00</td>
                        </tr>
                        <tr class="total-row">
                            <td>Payable by Due Date (<span class="urdu-text" style="font-size: 8px;">مقررہ تاریخ تک</span>)</td>
                            <td class="amount">9,725.00</td>
                        </tr>
                        <tr>
                            <td>Late Surcharge after {{ $dueDate ?? '15-Oct' }}</td>
                            <td class="amount">500.00</td>
                        </tr>
                        <tr class="late-row">
                            <td>Payable after Due Date (<span class="urdu-text" style="font-size: 8px;">بعد از تاریخ</span>)</td>
                            <td class="amount">10,225.00</td>
                        </tr>
                    </tbody>
                </table>

                <!-- 1Bill 18-digit PSID Box -->
                <div class="psid-box">
                    <div class="psid-label">1Bill Consumer ID / PSID</div>
                    <div class="psid-number">{{ $psidFormatted ?? '1001 2026 0001 2345 67' }}</div>
                    <div class="psid-help">Payable via 1Link 1Bill at any ATM, Mobile App, JazzCash & EasyPaisa</div>
                </div>

                <!-- Code-128 Barcode -->
                <div class="barcode-wrap">
                    {!! $barcodeSvg ?? '' !!}
                </div>

                <!-- Instructions -->
                <div class="instructions">
                    • Payment valid till {{ $validityDate ?? '31-Oct-2026' }}. Surcharge applies after {{ $dueDate ?? '15-Oct' }}.<br>
                    • Fee once deposited is non-refundable / non-transferable.<br>
                    • Bank must retain Bank Copy and stamp both Student & Institute copies.
                </div>

                <!-- Signatures / Stamps -->
                <div class="stamp-row">
                    <div class="stamp-box">Bank Officer / Teller Stamp</div>
                    <div class="stamp-box">Depositor Signature</div>
                </div>
            </div>
        @endforeach
    </div>
</body>
</html>

