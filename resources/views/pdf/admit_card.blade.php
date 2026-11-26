<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Admit Card - {{ $registration->student->registration_number }}</title>
    <style>
        @font-face {
            font-family: 'kalpurush';
            font-style: normal;
            font-weight: normal;
            src: url('{{ storage_path('fonts/kalpurush.ttf') }}') format('truetype');
        }
        @font-face {
            font-family: 'kalpurush';
            font-style: normal;
            font-weight: bold;
            src: url('{{ storage_path('fonts/kalpurush.ttf') }}') format('truetype');
        }
        @page {
            size: A4 portrait;
            margin: 12mm 15mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 11.5px;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }
        .container {
            border: 2px solid #0f172a;
            padding: 18px 20px;
            position: relative;
        }
        .watermark {
            position: absolute;
            top: 40%;
            left: 18%;
            font-size: 55px;
            color: rgba(15, 23, 42, 0.04);
            transform: rotate(-30deg);
            z-index: -1;
            font-weight: bold;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .exam-title {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            letter-spacing: 1px;
        }
        .doc-badge {
            display: inline-block;
            background-color: #0f172a;
            color: #ffffff;
            font-size: 10.5px;
            font-weight: bold;
            padding: 2.5px 12px;
            border-radius: 3px;
            margin-top: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .info-table td {
            vertical-align: top;
            padding: 3px 4px;
        }
        .photo-box {
            width: 100px;
            height: 120px;
            border: 1px dashed #64748b;
            text-align: center;
            line-height: 120px;
            background-color: #f8fafc;
            color: #64748b;
            font-size: 9.5px;
        }
        .field-label {
            font-size: 9.5px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
        }
        .field-val {
            font-size: 11.5px;
            font-weight: bold;
            color: #0f172a;
        }
        .section-heading {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            background-color: #f1f5f9;
            padding: 4px 8px;
            border-left: 4px solid #2563eb;
            margin-bottom: 7px;
            color: #0f172a;
        }
        .schedule-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .schedule-grid th, .schedule-grid td {
            border: 1px solid #cbd5e1;
            padding: 5px 8px;
            text-align: left;
        }
        .schedule-grid th {
            background-color: #f8fafc;
            font-size: 9.5px;
            text-transform: uppercase;
            color: #475569;
        }
        .instructions {
            font-family: 'kalpurush', 'DejaVu Sans', sans-serif;
            font-size: 9px;
            color: #1e293b;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 7px 10px;
            margin-bottom: 12px;
            line-height: 1.5;
        }
        .footer-table {
            width: 100%;
            margin-top: 8px;
        }
        .signature-line {
            border-top: 1px solid #0f172a;
            width: 160px;
            text-align: center;
            padding-top: 4px;
            font-size: 9.5px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="watermark">NEXT MEDICALIAN</div>

        <div class="header">
            <h1 class="exam-title">NEXT MEDICALIAN</h1>
            <div style="font-size: 13px; font-weight: 600; color: #334155; margin-top: 2px;">{{ $registration->exam->title }}</div>
            <div class="doc-badge">Official Admit Card</div>
        </div>

        <table class="info-table">
            <tr>
                <td style="width: 76%;">
                    <table style="width: 100%;">
                        <tr>
                            <td style="width: 50%; padding-bottom: 8px;">
                                <div class="field-label">Student Name</div>
                                <div class="field-val" style="font-size: 13.5px;">{{ $registration->student->user->name }}</div>
                            </td>
                            <td style="width: 50%; padding-bottom: 8px;">
                                <div class="field-label">Registration Number</div>
                                <div class="field-val" style="font-size: 15.5px; color: #2563eb;">{{ $registration->student->registration_number }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 50%; padding-bottom: 8px;">
                                <div class="field-label">Mobile Number</div>
                                <div class="field-val">{{ $registration->student->canonical_phone }}</div>
                            </td>
                            <td style="width: 50%; padding-bottom: 8px;">
                                <div class="field-label">Institution / College</div>
                                <div class="field-val">{{ $registration->student->institution ?? 'N/A' }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 50%;">
                                <div class="field-label">HSC Batch</div>
                                <div class="field-val">{{ $registration->student->hsc_batch ?? 'N/A' }}</div>
                            </td>
                            <td style="width: 50%;">
                                <div class="field-label">Examination Code</div>
                                <div class="field-val">{{ $registration->exam->code }}</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width: 24%; text-align: right;">
                    <div class="photo-box">
                        CANDIDATE PHOTO
                    </div>
                </td>
            </tr>
        </table>

        <div class="section-heading">Examination Schedule & Center Allocation</div>
        <table class="schedule-grid">
            <tr>
                <th>Exam Date</th>
                <th>Exam Timing</th>
                <th>Duration</th>
                <th>Assigned Zone</th>
            </tr>
            <tr>
                <td style="font-weight: bold;">{{ \Carbon\Carbon::parse($registration->exam->exam_date)->format('d F, Y (l)') }}</td>
                <td style="font-weight: bold; color: #2563eb;">{{ date('h:i A', strtotime($registration->exam->start_time)) }} - {{ date('h:i A', strtotime($registration->exam->end_time)) }}</td>
                <td>{{ $registration->exam->duration_minutes }} Minutes</td>
                <td>{{ $registration->center->zone->name ?? 'Dhaka' }}</td>
            </tr>
            <tr>
                <th colspan="2">Assigned Examination Center & Address</th>
                <th>Room Number</th>
                <th>Seat Number</th>
            </tr>
            <tr>
                <td colspan="2">
                    <strong style="font-size: 12.5px;">{{ $registration->center->name ?? 'Assigned Center' }}</strong><br>
                    <span style="font-size: 9.5px; color: #475569;">{{ $registration->center->address ?? '' }}</span>
                </td>
                <td style="font-weight: bold; font-size: 13.5px; color: #2563eb;">{{ $registration->room->room_number ?? 'Assigned at Center' }}</td>
                <td style="font-weight: bold; font-size: 14.5px; color: #0f172a;">{{ $registration->seat_number ?? 'Auto-Allocated' }}</td>
            </tr>
        </table>

        @php
            $rawInstructions = trim($registration->exam->instructions ?? '');
            if (empty($rawInstructions)) {
                $rawInstructions = \App\Models\SystemSetting::where('key', 'admit_card_instructions')->value('value') ?? '';
            }

            $defaultInstructions = [
                '১. পরীক্ষা শুরুর অন্তত ৩০ মিনিট আগে নির্ধারিত ভেন্যুতে উপস্থিত হতে হবে।',
                '২. উত্তরপত্র পূরণের জন্য কালো বলপয়েন্ট কলম সঙ্গে আনতে হবে।',
                '৩. পরীক্ষার হলে মোবাইল ফোন, ক্যালকুলেটর, স্মার্টওয়াচ কিংবা কোনো ধরনের ইলেকট্রনিক ডিভাইস ব্যবহার করা যাবে না। এনে থাকলে তা পরীক্ষা শুরুর পূর্বেই invigilator এর নিকট জমা দিতে হবে।',
                '৪. OMR শিটে নাম, রোল নম্বর ও প্রয়োজনীয় তথ্য নির্ভুলভাবে পূরণ করতে হবে। রোল নম্বর তোমাদের admit card এ আছে।',
                '৫. উত্তর প্রদানের ক্ষেত্রে OMR শিটের নির্ধারিত বৃত্ত সম্পূর্ণ ভরাট করতে হবে।',
                '৬. পরীক্ষার হলে কোনো ধরনের অসদুপায় অবলম্বন করা যাবে না।',
                '৭. পরীক্ষা শেষ হওয়ার আগে অনুমতি ছাড়া পরীক্ষার হল ত্যাগ করা যাবে না।',
            ];

            if (!empty($rawInstructions)) {
                $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $rawInstructions)));
            } else {
                $lines = $defaultInstructions;
            }
        @endphp

        <div class="section-heading" style="font-family: 'kalpurush', 'DejaVu Sans', sans-serif;">পরীক্ষার্থীদের জন্য বিশেষ নির্দেশনাবলী / Instructions for Candidates</div>
        <div class="instructions">
            @foreach($lines as $line)
                @if(!empty($line))
                    <div style="margin-bottom: 2.5px;">{{ $line }}</div>
                @endif
            @endforeach
        </div>

        <table class="footer-table">
            <tr>
                <td style="width: 38%; vertical-align: bottom;">
                    <div style="display: inline-block; width: 84px; height: 84px; border: 1.5px solid #0f172a; padding: 2px; background: #ffffff; text-align: center; box-sizing: border-box;">
                        <img src="{{ $qrDataUri }}" alt="Verification QR Code" style="width: 80px; height: 80px; display: block; margin: 0 auto; border: 0;">
                    </div>
                    <div style="font-size: 8px; font-weight: bold; color: #475569; margin-top: 2px;">Scan QR to verify authenticity</div>
                </td>
                <td style="width: 27%; text-align: center; vertical-align: bottom;">
                    <div style="font-size: 8.5px; color: #94a3b8; padding-bottom: 6px;">Issued on: {{ now()->format('d/m/Y h:i A') }}</div>
                </td>
                <td style="width: 35%; text-align: right; vertical-align: bottom;">
                    <div style="float: right; text-align: center; width: 170px;">
                        @if(!empty($signatureDataUri))
                            <div style="height: 42px; margin-bottom: 2px;">
                                <img src="{{ $signatureDataUri }}" alt="Signature" style="max-height: 40px; max-width: 140px; display: inline-block;">
                            </div>
                        @else
                            <div style="height: 32px;"></div>
                        @endif
                        <div class="signature-line" style="margin: 0 auto; width: 100%;">
                            {{ $signatureTitle ?? 'Controller of Examinations' }}<br>
                            <span style="font-weight: normal; font-size: 8px; color: #64748b;">NEXT MEDICALIAN</span>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
    </div>
</body>
</html>
