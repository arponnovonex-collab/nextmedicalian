<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Result Sheet - {{ $registration->student->registration_number }}</title>
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
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .info-grid td {
            padding: 5px;
            vertical-align: top;
        }
        .field-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
        }
        .field-val {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
        }
        .score-banner {
            background-color: #f8fafc;
            border: 2px solid {{ $result->is_passed ? '#059669' : '#e11d48' }};
            border-radius: 6px;
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
        }
        .score-val {
            font-size: 32px;
            font-weight: 800;
            color: {{ $result->is_passed ? '#059669' : '#e11d48' }};
        }
        .score-status {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: {{ $result->is_passed ? '#059669' : '#e11d48' }};
        }
        .rank-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .rank-grid th, .rank-grid td {
            border: 1px solid #cbd5e1;
            padding: 8px 12px;
            text-align: center;
        }
        .rank-grid th {
            background-color: #f1f5f9;
            font-size: 10px;
            text-transform: uppercase;
            color: #475569;
        }
        .rank-num {
            font-size: 18px;
            font-weight: 800;
            color: #2563eb;
        }
        .breakdown-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .breakdown-table th, .breakdown-table td {
            border: 1px solid #cbd5e1;
            padding: 6px 10px;
            text-align: left;
        }
        .breakdown-table th {
            background-color: #f8fafc;
            font-size: 10px;
            color: #475569;
            text-transform: uppercase;
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
        <div class="header">
            <div class="org-name">EdGenie Medical Academy</div>
            <h1 class="exam-title">NEXT MEDICALIAN</h1>
            <div style="font-size: 13px; font-weight: 600; color: #334155; margin-top: 2px;">{{ $registration->exam->title }}</div>
            <div class="doc-badge">Official Result Sheet</div>
        </div>

        <table class="info-grid">
            <tr>
                <td style="width: 50%;">
                    <div class="field-label">Student Name</div>
                    <div class="field-val">{{ $registration->student->user->name }}</div>
                </td>
                <td style="width: 50%;">
                    <div class="field-label">Registration Number</div>
                    <div class="field-val" style="color: #2563eb;">{{ $registration->student->registration_number }}</div>
                </td>
            </tr>
            <tr>
                <td style="width: 50%;">
                    <div class="field-label">Institution / College</div>
                    <div class="field-val">{{ $registration->student->institution ?? 'N/A' }}</div>
                </td>
                <td style="width: 50%;">
                    <div class="field-label">HSC Batch</div>
                    <div class="field-val">{{ $registration->student->hsc_batch ?? 'N/A' }}</div>
                </td>
            </tr>
            <tr>
                <td style="width: 50%;">
                    <div class="field-label">Examination Center</div>
                    <div class="field-val">{{ $registration->center->name ?? 'N/A' }}</div>
                </td>
                <td style="width: 50%;">
                    <div class="field-label">Assigned Zone</div>
                    <div class="field-val">{{ $registration->center->zone->name ?? 'N/A' }}</div>
                </td>
            </tr>
        </table>

        <div class="score-banner">
            <div class="score-val">{{ number_format($result->obtained_marks, 2) }} / {{ number_format($registration->exam->total_marks, 2) }}</div>
            <div class="score-status">{{ $result->is_passed ? 'Passed (Qualified)' : 'Did Not Qualify' }} &bull; {{ number_format($result->percentage, 2) }}%</div>
        </div>

        <table class="rank-grid">
            <tr>
                <th>Overall Merit Position</th>
                <th>Zone-wise Merit Position</th>
                <th>Center-wise Merit Position</th>
            </tr>
            <tr>
                <td><div class="rank-num">{{ $result->overall_merit }}</div></td>
                <td><div class="rank-num">{{ $result->zone_merit }}</div></td>
                <td><div class="rank-num">{{ $result->center_merit }}</div></td>
            </tr>
        </table>

        @if($result->correct_count !== null)
        <table class="breakdown-table">
            <tr>
                <th>Question Analytics</th>
                <th>Count</th>
                <th>Weightage / Impact</th>
            </tr>
            <tr>
                <td>Correct Answers</td>
                <td style="font-weight: bold; color: #059669;">{{ $result->correct_count }}</td>
                <td>+{{ number_format($result->correct_count * $registration->exam->marks_per_correct, 2) }} Marks</td>
            </tr>
            <tr>
                <td>Incorrect Answers</td>
                <td style="font-weight: bold; color: #e11d48;">{{ $result->incorrect_count }}</td>
                <td>-{{ number_format($result->incorrect_count * $registration->exam->negative_marks_per_incorrect, 2) }} Marks (Penalty)</td>
            </tr>
            <tr>
                <td>Unanswered Questions</td>
                <td>{{ $result->unanswered_count }}</td>
                <td>0.00 Marks</td>
            </tr>
        </table>
        @endif

        <table style="width: 100%; margin-top: 20px;">
            <tr>
                <td style="width: 50%; vertical-align: bottom;">
                    <div style="display: inline-block; width: 84px; height: 84px; border: 1.5px solid #0f172a; padding: 2px; background: #ffffff; text-align: center; box-sizing: border-box;">
                        <img src="{{ $qrDataUri }}" alt="Verification QR Code" style="width: 80px; height: 80px; display: block; margin: 0 auto; border: 0;">
                    </div>
                    <div style="font-size: 8px; font-weight: bold; color: #475569; margin-top: 3px;">Scan QR to verify official result</div>
                </td>
                <td style="width: 50%; text-align: right; vertical-align: bottom;">
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
