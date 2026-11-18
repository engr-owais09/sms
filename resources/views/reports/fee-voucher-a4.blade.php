<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>3-Copy Fee Voucher — {{ $invoice->invoice_number }}</title>
    <style>
        @if (!empty($urduFontBase64))
        @font-face {
            font-family: 'Noto Nastaliq Urdu';
            src: url('data:font/truetype;charset=utf-8;base64,{{ $urduFontBase64 }}') format('truetype');
        }
        @endif
        @page { size: A4 landscape; margin: 5mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 9px; color: #1e293b; background: #fff; }
        .urdu { font-family: 'Noto Nastaliq Urdu', serif; direction: rtl; font-size: 11px; }
        .grid { display: flex; gap: 6mm; width: 100%; }
        .copy { flex: 1; border: 1px dashed #64748b; padding: 8px; border-radius: 4px; }
        .head { text-align: center; border-bottom: 1.5px solid #0f172a; padding-bottom: 5px; margin-bottom: 6px; }
        .head h2 { font-size: 12px; margin-bottom: 2px; }
        .badge { display: inline-block; background: #0f172a; color: #fff; font-size: 8px; padding: 1px 6px; border-radius: 3px; font-weight: bold; margin-top: 2px; }
        table.meta, table.items { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.meta td { padding: 2px 3px; }
        table.items th, table.items td { border: 1px solid #cbd5e1; padding: 3px 4px; }
        table.items th { background: #f1f5f9; text-align: left; }
        .amt { text-align: right; font-family: monospace; }
        .psid { background: #eff6ff; border: 1px solid #bfdbfe; text-align: center; padding: 4px; margin-bottom: 6px; border-radius: 3px; }
        .psid strong { font-size: 11px; letter-spacing: 1px; display: block; }
        .barcode { text-align: center; margin: 4px 0; }
        .foot { font-size: 7.5px; color: #475569; margin-top: 6px; border-top: 1px solid #e2e8f0; padding-top: 4px; }
    </style>
</head>
<body>
    <div class="grid">
        @foreach(['Bank Copy / بینک کاپی', 'Institute Copy / اسکول کاپی', 'Student Copy / طالب علم کاپی'] as $copyTitle)
            <div class="copy">
                <div class="head">
                    <h2>{{ $tenant->name ?? 'EduCore Institute' }}</h2>
                    <div>{{ $branch->name ?? 'Main Campus' }} ({{ $branch->city ?? '' }})</div>
                    <div class="urdu">فیس چالان واؤچر</div>
                    <span class="badge">{{ $copyTitle }}</span>
                </div>

                <table class="meta">
                    <tr>
                        <td><strong>Voucher #:</strong> {{ $invoice->invoice_number }}</td>
                        <td><strong>Period:</strong> {{ $invoice->billing_period ?? 'Custom' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Issue:</strong> {{ $invoice->issue_date->format('d-M-Y') }}</td>
                        <td><strong>Due:</strong> {{ $invoice->due_date->format('d-M-Y') }}</td>
                    </tr>
                    <tr>
                        <td><strong>Student:</strong> {{ $student->first_name ?? '' }} {{ $student->last_name ?? '' }}</td>
                        <td><strong>Adm #:</strong> {{ $student->admission_number ?? '' }}</td>
                    </tr>
                </table>

                <table class="items">
                    <thead>
                        <tr>
                            <th>Fee Head / تفصیل</th>
                            <th class="amt">Amount (PKR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->items as $item)
                            <tr>
                                <td>{{ $item->feeCategory->name ?? ($item->description ?? 'Fee Item') }}</td>
                                <td class="amt">{{ number_format($item->net_amount, 2) }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td><strong>Total Payable (Within Due Date)</strong></td>
                            <td class="amt"><strong>PKR {{ number_format($invoice->total_amount, 2) }}</strong></td>
                        </tr>
                        <tr>
                            <td>Paid / Adjusted</td>
                            <td class="amt">{{ number_format($invoice->paid_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td><strong>Balance Due</strong></td>
                            <td class="amt"><strong>PKR {{ number_format($invoice->balance, 2) }}</strong></td>
                        </tr>
                    </tbody>
                </table>

                <div class="psid">
                    <span>1Link / 1Bill PSID</span>
                    <strong>{{ $psidFormatted }}</strong>
                </div>

                <div class="barcode">
                    {!! $barcodeSvg !!}
                </div>

                <div class="foot">
                    Payable at any 1Link member bank, JazzCash, Easypaisa, or campus cashier counter.
                </div>
            </div>
        @endforeach
    </div>
</body>
</html>
