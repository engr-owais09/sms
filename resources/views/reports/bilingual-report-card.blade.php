<!DOCTYPE html>
<html lang="{{ $isRtl ? 'ur' : 'en' }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $isRtl ? 'رزلٹ کارڈ' : 'Academic Report Card' }} — {{ $result->student->admission_number ?? '' }}</title>
    <style>
        @font-face {
            font-family: 'NotoNastaliqUrdu';
            src: url('file://{{ str_replace('\\', '/', resource_path('fonts/NotoNastaliqUrdu-Regular.ttf')) }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        body {
            font-family: {{ $isRtl ? "'NotoNastaliqUrdu', sans-serif" : "'Inter', system-ui, sans-serif" }};
            margin: 24px;
            color: #0f172a;
            font-size: 13px;
        }
        .card {
            border: 2px solid #1e293b;
            padding: 20px;
            border-radius: 8px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #cbd5e1;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 4px 0 0; color: #475569; }
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .meta td { padding: 4px 8px; }
        table.marks {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        table.marks th, table.marks td {
            border: 1px solid #94a3b8;
            padding: 6px 10px;
            text-align: {{ $isRtl ? 'right' : 'left' }};
        }
        table.marks th { background: #f1f5f9; }
        .summary {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 12px;
            display: flex;
            justify-content: space-between;
            font-weight: bold;
        }
        .signatures {
            margin-top: 36px;
            display: flex;
            justify-content: space-between;
        }
        .sig-box {
            width: 200px;
            border-top: 1px solid #334155;
            text-align: center;
            padding-top: 6px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>{{ $tenant->name ?? 'EduCore School' }}</h1>
            <p>{{ $result->examTerm->name ?? 'Term Examination' }} — {{ $isRtl ? 'تعلیمی رزلٹ کارڈ' : 'Official Academic Report Card' }}</p>
        </div>

        <table class="meta">
            <tr>
                <td><strong>{{ $isRtl ? 'طالب علم کا نام:' : 'Student Name:' }}</strong> {{ $result->student->first_name }} {{ $result->student->last_name }}</td>
                <td><strong>{{ $isRtl ? 'داخلہ نمبر:' : 'Admission #:' }}</strong> {{ $result->student->admission_number }}</td>
            </tr>
            <tr>
                <td><strong>{{ $isRtl ? 'جماعت / سیکشن:' : 'Class / Section:' }}</strong> {{ $result->section->schoolClass->name ?? '' }} - {{ $result->section->name ?? '' }}</td>
                <td><strong>{{ $isRtl ? 'پوزیشن:' : 'Class Rank:' }}</strong> #{{ $result->section_rank }}</td>
            </tr>
        </table>

        <table class="marks">
            <thead>
                <tr>
                    <th>{{ $isRtl ? 'مضمون' : 'Subject' }}</th>
                    <th>{{ $isRtl ? 'کل نمبر' : 'Max Marks' }}</th>
                    <th>{{ $isRtl ? 'پاسنگ نمبر' : 'Passing' }}</th>
                    <th>{{ $isRtl ? 'حاصل کردہ نمبر' : 'Obtained' }}</th>
                    <th>{{ $isRtl ? 'حیثیت' : 'Status' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($marks as $m)
                    <tr>
                        <td>{{ $m->paper->subject->name ?? 'Subject' }}</td>
                        <td>{{ number_format($m->paper->max_marks, 0) }}</td>
                        <td>{{ number_format($m->paper->passing_marks, 0) }}</td>
                        <td>{{ $m->is_absent ? ($isRtl ? 'غیر حاضر' : 'ABSENT') : number_format($m->marks_obtained, 2) }}</td>
                        <td>{{ (!$m->is_absent && $m->marks_obtained >= $m->paper->passing_marks) ? ($isRtl ? 'پاس' : 'Pass') : ($isRtl ? 'فیل' : 'Fail') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary">
            <span>{{ $isRtl ? 'کل نمبر:' : 'Total:' }} {{ number_format($result->total_obtained_marks, 2) }} / {{ number_format($result->total_max_marks, 2) }}</span>
            <span>{{ $isRtl ? 'فیصد:' : 'Percentage:' }} {{ number_format($result->percentage, 2) }}%</span>
            <span>{{ $isRtl ? 'گریڈ:' : 'Grade:' }} {{ $result->grade_letter }}</span>
            <span>{{ $isRtl ? 'نتیجہ:' : 'Result:' }} {{ strtoupper($result->result_status) }}</span>
        </div>

        <div class="signatures">
            <div class="sig-box">{{ $isRtl ? 'دستخط کلاس ٹیچر' : 'Class Teacher Signature' }}</div>
            <div class="sig-box">{{ $isRtl ? 'دستخط پرنسپل' : 'Principal Signature' }}</div>
        </div>
    </div>
</body>
</html>
