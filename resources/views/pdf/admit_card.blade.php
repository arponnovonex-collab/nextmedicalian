<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admit Card - {{ $registration->student->registration_number }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .container {
            border: 2px solid #0f172a;
            padding: 20px;
            position: relative;
        }
        .watermark {
            position: absolute;
            top: 40%;
            left: 20%;
            font-size: 60px;
            color: rgba(15, 23, 42, 0.04);
            transform: rotate(-30deg);
            z-index: -1;
            font-weight: bold;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .org-name {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #2563eb;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .exam-title {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
        }
        .doc-badge {
            display: inline-block;
            background-color: #0f172a;
            color: #ffffff;
            font-size: 11px;
            font-weight: bold;
            padding: 3px 12px;
            border-radius: 3px;
            margin-top: 6px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .info-table td {
            vertical-align: top;
            padding: 4px;
        }
        .photo-box {
            width: 110px;
            height: 130px;
            border: 1px dashed #64748b;
            text-align: center;
            line-height: 130px;
            background-color: #f8fafc;
            color: #64748b;
            font-size: 10px;
        }
        .field-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
        }
        .field-val {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
        }
        .section-heading {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            background-color: #f1f5f9;
            padding: 5px 8px;
            border-left: 4px solid #2563eb;
            margin-bottom: 8px;
            color: #0f172a;
        }
        .schedule-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .schedule-grid th, .schedule-grid td {
            border: 1px solid #cbd5e1;
            padding: 6px 10px;
            text-align: left;
        }
        .schedule-grid th {
            background-color: #f8fafc;
            font-size: 10px;
            text-transform: uppercase;
            color: #475569;
        }
        .instructions {
            font-size: 9.5px;
            color: #334155;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .instructions ol {
            margin: 0;
            padding-left: 15px;
        }
        .footer-table {
            width: 100%;
            margin-top: 15px;
        }
        .signature-line {
            border-top: 1px solid #0f172a;
            width: 160px;
            text-align: center;
            padding-top: 4px;
            font-size: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="watermark">NEXT MEDICALIAN</div>

        <div class="header">
            <div class="org-name">EdGenie Medical Academy</div>
            <h1 class="exam-title">NEXT MEDICALIAN</h1>
            <div style="font-size: 13px; font-weight: 600; color: #334155; margin-top: 2px;">{{ $registration->exam->title }}</div>
            <div class="doc-badge">Official Admit Card</div>
        </div>

        <table class="info-table">
            <tr>
                <td style="width: 75%;">
                    <table style="width: 100%;">
                        <tr>
                            <td style="width: 50%; padding-bottom: 10px;">
                                <div class="field-label">Student Name</div>
                                <div class="field-val" style="font-size: 14px;">{{ $registration->student->user->name }}</div>
                            </td>
                            <td style="width: 50%; padding-bottom: 10px;">
                                <div class="field-label">Registration Number</div>
                                <div class="field-val" style="font-size: 16px; color: #2563eb;">{{ $registration->student->registration_number }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 50%; padding-bottom: 10px;">
                                <div class="field-label">Mobile Number</div>
                                <div class="field-val">{{ $registration->student->canonical_phone }}</div>
                            </td>
                            <td style="width: 50%; padding-bottom: 10px;">
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
                <td style="width: 25%; text-align: right;">
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
                    <strong style="font-size: 13px;">{{ $registration->center->name ?? 'Assigned Center' }}</strong><br>
                    <span style="font-size: 10px; color: #475569;">{{ $registration->center->address ?? '' }}</span>
                </td>
                <td style="font-weight: bold; font-size: 14px; color: #2563eb;">{{ $registration->room->room_number ?? 'Assigned at Center' }}</td>
                <td style="font-weight: bold; font-size: 15px; color: #0f172a;">{{ $registration->seat_number ?? 'Auto-Allocated' }}</td>
            </tr>
        </table>

        <div class="section-heading">Important Instructions for Candidates</div>
        <div class="instructions">
            <ol>
                <li>This physical Admit Card along with HSC Registration Card must be presented at the examination hall entrance.</li>
                <li>Examinees must occupy their assigned seats at least 20 minutes prior to the commencement of the examination.</li>
                <li>Strictly use Black Ballpoint Pen for bubbling answer scripts. Pencils, gel pens, and correction fluids are prohibited.</li>
                <li>Digital smartwatches, mobile phones, calculators, Bluetooth devices, and bags are strictly forbidden.</li>
                <li>Do not tear, fold, staple, or write outside designated margins on the physical OMR answer script.</li>
            </ol>
        </div>

        <table class="footer-table">
            <tr>
                <td style="width: 40%; vertical-align: bottom;">
                    <div style="display: inline-block; width: 84px; height: 84px; border: 1.5px solid #0f172a; padding: 2px; background: #ffffff; text-align: center; box-sizing: border-box;">
                        <img src="{{ $qrDataUri }}" alt="Verification QR Code" style="width: 80px; height: 80px; display: block; margin: 0 auto; border: 0;">
                    </div>
                    <div style="font-size: 8px; font-weight: bold; color: #475569; margin-top: 3px;">Scan QR to verify authenticity</div>
                </td>
                <td style="width: 30%; text-align: center; vertical-align: bottom;">
                    <div style="font-size: 9px; color: #94a3b8;">Issued on: {{ now()->format('d/m/Y h:i A') }}</div>
                </td>
                <td style="width: 30%; text-align: right; vertical-align: bottom;">
                    <div class="signature-line" style="float: right;">
                        Controller of Examinations<br>
                        <span style="font-weight: normal; font-size: 8px; color: #64748b;">NEXT MEDICALIAN / EdGenie</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
