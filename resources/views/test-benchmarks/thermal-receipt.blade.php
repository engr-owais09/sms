<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>80mm Cashier Thermal Receipt</title>
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
            size: 80mm 210mm;
            margin: 2mm 3mm 2mm 3mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, monospace, sans-serif;
            font-size: 9px;
            color: #000000;
            background: #ffffff;
            width: 74mm;
            line-height: 1.3;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .urdu-text {
            font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', serif;
            font-size: 11px;
            direction: rtl;
            unicode-bidi: embed;
        }

        .header {
            text-align: center;
            border-bottom: 1px dashed #000000;
            padding-bottom: 5px;
            margin-bottom: 5px;
        }

        .inst-title-ur {
            font-size: 13px;
            font-weight: bold;
        }

        .inst-title-en {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sub-header {
            font-size: 8px;
            margin-top: 1px;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            font-size: 8.5px;
            margin-bottom: 2px;
        }

        .meta-label {
            color: #333333;
        }

        .meta-value {
            font-weight: bold;
        }

        .divider {
            border-top: 1px dashed #000000;
            margin: 4px 0;
        }

        .double-divider {
            border-top: 2px solid #000000;
            margin: 5px 0;
        }

        .student-section {
            background-color: #f5f5f5;
            padding: 4px;
            border-radius: 2px;
            margin-bottom: 5px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
            margin: 4px 0;
        }

        .items-table th {
            text-align: left;
            border-bottom: 1px solid #000000;
            padding-bottom: 2px;
            font-size: 8px;
            text-transform: uppercase;
        }

        .items-table td {
            padding: 2.5px 0;
        }

        .items-table td.amount {
            text-align: right;
            font-family: monospace;
            font-weight: bold;
        }

        .total-box {
            margin-top: 4px;
            font-size: 9.5px;
        }

        .barcode-section {
            text-align: center;
            margin: 8px 0 4px 0;
        }

        .barcode-section svg {
            max-width: 90%;
            height: 30px;
        }

        .footer {
            text-align: center;
            font-size: 7.5px;
            margin-top: 6px;
            color: #333333;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="inst-title-ur urdu-text">{{ $instituteNameUrdu ?? 'دی ایجوکیٹرز ماڈل کیمپس' }}</div>
        <div class="inst-title-en">{{ $instituteNameEnglish ?? 'The Educators Model Campus' }}</div>
        <div class="sub-header">{{ $branchName ?? 'Canal Road Campus, Lahore' }} | Ph: 042-35890000</div>
        <div class="sub-header" style="font-weight: bold; margin-top: 2px;">CASHIER COUNTER PAYMENT RECEIPT</div>
    </div>

    <!-- Shift & Counter Meta -->
    <div class="meta-row">
        <span class="meta-label">Receipt No:</span>
        <span class="meta-value">{{ $receiptNo ?? 'REC-2026-10-0928' }}</span>
    </div>
    <div class="meta-row">
        <span class="meta-label">Date & Time:</span>
        <span class="meta-value">{{ $dateTime ?? '10-Oct-2026 11:42 AM' }}</span>
    </div>
    <div class="meta-row">
        <span class="meta-label">Cashier / Shift:</span>
        <span class="meta-value">{{ $cashierName ?? 'Tariq Mehmood' }} (Shift #04)</span>
    </div>
    <div class="meta-row">
        <span class="meta-label">Payment Mode:</span>
        <span class="meta-value">CASH (Drawer Register #01)</span>
    </div>

    <div class="divider"></div>

    <!-- Student Credentials -->
    <div class="student-section">
        <div class="meta-row">
            <span class="meta-label">Student:</span>
            <span class="meta-value">
                <span class="urdu-text">{{ $studentNameUrdu ?? 'محمد اویس' }}</span>
                <span>({{ $studentNameEnglish ?? 'Muhammad Owais' }})</span>
            </span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Father:</span>
            <span class="meta-value">
                <span class="urdu-text">{{ $fatherNameUrdu ?? 'عبدالرحمٰن' }}</span>
            </span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Roll / Reg No:</span>
            <span class="meta-value">{{ $rollNo ?? 'STU-2026-0891' }}</span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Class & Sec:</span>
            <span class="meta-value">{{ $classSection ?? 'Grade 9 - Section A' }}</span>
        </div>
    </div>

    <div class="divider"></div>

    <!-- Payment Allocations Breakdown -->
    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align: right;">Amount (PKR)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Oct 2026 Tuition Fee</td>
                <td class="amount">8,500.00</td>
            </tr>
            <tr>
                <td>Oct 2026 Lab Charges</td>
                <td class="amount">1,500.00</td>
            </tr>
            <tr>
                <td>Advance Nov 2026 Tuition (GL 2010)</td>
                <td class="amount">5,000.00</td>
            </tr>
        </tbody>
    </table>

    <div class="double-divider"></div>

    <!-- Settlement Totals -->
    <div class="total-box">
        <div class="meta-row" style="font-size: 11px;">
            <span class="meta-label" style="font-weight: bold;">TOTAL PAID:</span>
            <span class="meta-value">PKR 15,000.00</span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Cash Tendered:</span>
            <span class="meta-value">PKR 15,000.00</span>
        </div>
        <div class="meta-row">
            <span class="meta-label">Change Returned:</span>
            <span class="meta-value">PKR 0.00</span>
        </div>
        <div class="meta-row" style="color: #2b6cb0;">
            <span class="meta-label">Advance Prepayment Balance:</span>
            <span class="meta-value">PKR 5,000.00</span>
        </div>
    </div>

    <!-- Code-128 Barcode -->
    <div class="barcode-section">
        {!! $barcodeSvg ?? '' !!}
    </div>

    <div class="divider"></div>

    <div class="footer">
        <div>Thank you for choosing EduCore Partner Campus.</div>
        <div style="margin-top: 2px;">This is a computer generated legal receipt and requires no physical seal.</div>
        <div style="font-size: 6.5px; margin-top: 2px;">EduCore Cloud Finance Engine v1.1.7 | Hash: 8f4c2e1</div>
    </div>
</body>
</html>

