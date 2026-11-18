<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt — {{ $payment->receipt_number }}</title>
    <style>
        @if (!empty($urduFontBase64))
        @font-face {
            font-family: 'Noto Nastaliq Urdu';
            src: url('data:font/truetype;charset=utf-8;base64,{{ $urduFontBase64 }}') format('truetype');
        }
        @endif
        @page { size: 80mm 210mm; margin: 3mm; }
        body { font-family: monospace; font-size: 10px; width: 74mm; margin: 0 auto; color: #0f172a; }
        .urdu { font-family: 'Noto Nastaliq Urdu', serif; direction: rtl; font-size: 11px; }
        .center { text-align: center; }
        .line { border-top: 1px dashed #334155; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <div class="center">
        <strong>{{ $tenant->name ?? 'EduCore Institute' }}</strong><br>
        <span>{{ $branch->name ?? 'Campus Counter' }}</span><br>
        <span class="urdu">فیس ادائیگی رسید</span>
    </div>

    <div class="line"></div>

    <table>
        <tr><td>Receipt #:</td><td class="right"><strong>{{ $payment->receipt_number }}</strong></td></tr>
        <tr><td>Date:</td><td class="right">{{ $payment->payment_date->format('d-M-Y') }}</td></tr>
        <tr><td>Cashier:</td><td class="right">{{ $payment->receivedByUser->name ?? 'Cashier' }}</td></tr>
        <tr><td>Method:</td><td class="right">{{ strtoupper($payment->payment_method) }}</td></tr>
    </table>

    <div class="line"></div>

    <table>
        <tr><td>Student:</td><td class="right"><strong>{{ $student->first_name ?? '' }} {{ $student->last_name ?? '' }}</strong></td></tr>
        <tr><td>Adm #:</td><td class="right">{{ $student->admission_number ?? '' }}</td></tr>
    </table>

    <div class="line"></div>

    <table>
        <tr><td>Invoices Settled:</td><td class="right">PKR {{ number_format($payment->allocated_to_invoices, 2) }}</td></tr>
        <tr><td>Advance Tuition:</td><td class="right">PKR {{ number_format($payment->allocated_to_advances, 2) }}</td></tr>
        <tr><td>Wallet Credit:</td><td class="right">PKR {{ number_format($payment->credited_to_wallet, 2) }}</td></tr>
        <tr><td><strong>TOTAL PAID:</strong></td><td class="right"><strong>PKR {{ number_format($payment->amount_tendered, 2) }}</strong></td></tr>
    </table>

    <div class="line"></div>

    <div class="center" style="margin-top: 6px;">
        {!! $barcodeSvg !!}
        <div style="font-size: 8px; margin-top: 4px;">Computerized receipt — EduCore SaaS</div>
    </div>
</body>
</html>
