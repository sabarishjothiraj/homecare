<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Contract - {{ $contract->contract_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .company-logo {
            max-width: 150px;
            margin-bottom: 10px;
        }
        .contract-title {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
        }
        .contract-number {
            font-size: 14px;
            color: #666;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #2c3e50;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .info-row {
            margin-bottom: 8px;
        }
        .label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }
        .value {
            display: inline-block;
        }
        .terms {
            background: #f8f9fa;
            padding: 15px;
            border-left: 3px solid #2c3e50;
            margin: 20px 0;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        .signature-section {
            margin-top: 40px;
        }
        .signature-box {
            display: inline-block;
            width: 45%;
            text-align: center;
            margin-top: 50px;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 40px;
            padding-top: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table td {
            padding: 8px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        @if($company_logo)
            <img src="{{ public_path('storage/' . $company_logo) }}" alt="{{ $company_name }}" class="company-logo">
        @endif
        <div class="contract-title">NURSING SERVICE CONTRACT</div>
        <div class="contract-number">Contract No: {{ $contract->contract_number }}</div>
        <div>Date: {{ $contract->created_at->format('F d, Y') }}</div>
    </div>

    <!-- Company Information -->
    <div class="section">
        <div class="section-title">Service Provider</div>
        <div class="info-row"><span class="label">Company Name:</span> <span class="value">{{ $company_name }}</span></div>
        <div class="info-row"><span class="label">Address:</span> <span class="value">{{ $company_address }}</span></div>
        <div class="info-row"><span class="label">Phone:</span> <span class="value">{{ $company_phone }}</span></div>
        <div class="info-row"><span class="label">Email:</span> <span class="value">{{ $company_email }}</span></div>
    </div>

    <!-- Patient Information -->
    <div class="section">
        <div class="section-title">Patient Information</div>
        <div class="info-row"><span class="label">Name:</span> <span class="value">{{ $contract->patient->name }}</span></div>
        <div class="info-row"><span class="label">Phone:</span> <span class="value">{{ $contract->patient->phone }}</span></div>
        @if($contract->patient->email)
        <div class="info-row"><span class="label">Email:</span> <span class="value">{{ $contract->patient->email }}</span></div>
        @endif
        <div class="info-row"><span class="label">Address:</span> <span class="value">{{ $contract->patient->full_address }}</span></div>
    </div>

    <!-- Service Details -->
    <div class="section">
        <div class="section-title">Service Details</div>
        <div class="info-row"><span class="label">Services:</span> <span class="value">{{ $contract->items->pluck('service.name')->filter()->unique()->join(', ') ?: ($contract->service->name ?? 'N/A') }}</span></div>
        <div class="info-row"><span class="label">Duration Type:</span> <span class="value">{{ ucfirst($contract->duration_type) }}</span></div>
        <div class="info-row"><span class="label">Start Date:</span> <span class="value">{{ $contract->start_date->format('F d, Y') }}</span></div>
        @if($contract->end_date)
        <div class="info-row"><span class="label">End Date:</span> <span class="value">{{ $contract->end_date->format('F d, Y') }}</span></div>
        @endif
        <div class="info-row"><span class="label">Contract Amount:</span> <span class="value">₹{{ number_format($contract->amount, 2) }}</span></div>
        <div class="info-row"><span class="label">Payment Method:</span> <span class="value">{{ ucfirst($contract->payment_method) }}</span></div>
    </div>

    <!-- Assigned Employees -->
    <div class="section">
        <div class="section-title">Assigned Employees</div>
        @if($contract->assignments->count() > 0)
            <table border="1" cellpadding="0" cellspacing="0">
                <thead>
                    <tr style="background: #f3f4f6;">
                        <td><strong>Employee</strong></td>
                        <td><strong>Service</strong></td>
                        <td><strong>Amount</strong></td>
                        <td><strong>Duration</strong></td>
                        <td><strong>Type</strong></td>
                        <td><strong>Notes</strong></td>
                    </tr>
                </thead>
                <tbody>
                    @foreach($contract->assignments as $assignment)
                        <tr>
                            <td>
                                {{ $assignment->employee->getField('name', 'Employee #' . $assignment->employee_id) }}
                                @if($assignment->employee->getField('phone'))
                                    <br>
                                    <small>{{ $assignment->employee->getField('phone') }}</small>
                                @endif
                            </td>
                            <td>{{ $assignment->service->name ?? $assignment->contractItem->service->name ?? ($contract->service->name ?? 'N/A') }}</td>
                            <td>{{ $assignment->isCover() ? '-' : '₹'.number_format($assignment->line_amount ?? $assignment->contractItem->amount ?? 0, 2) }}</td>
                            <td>
                                {{ $assignment->start_date->format('M d, Y') }} -
                                {{ $assignment->end_date ? $assignment->end_date->format('M d, Y') : 'Ongoing' }}
                            </td>
                            <td>
                                @if($assignment->isCover())
                                    Temporary cover (leave)
                                    @if($assignment->coverFor)
                                        <br>
                                        <small>For {{ $assignment->coverFor->employee->getField('name', 'N/A') }}</small>
                                    @endif
                                @elseif($assignment->is_emergency_replacement)
                                    Temporary replacement
                                    @if($assignment->replacedAssignment)
                                        <br>
                                        <small>For {{ $assignment->replacedAssignment->employee->getField('name', 'N/A') }}</small>
                                    @endif
                                @else
                                    Primary
                                @endif
                            </td>
                            <td>
                                @if($assignment->assignmentNotes->count() > 0)
                                    @foreach($assignment->assignmentNotes as $note)
                                        - {{ $note->note }}<br>
                                    @endforeach
                                @elseif($assignment->getAttribute('notes'))
                                    {{ $assignment->getAttribute('notes') }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>No employees assigned at the time of PDF generation.</p>
        @endif
    </div>

    <!-- Terms and Conditions -->
    <div class="section">
        <div class="section-title">Terms and Conditions</div>
        <div class="terms">
            {!! nl2br(e($contract->terms)) !!}
        </div>
    </div>

    <!-- Signatures -->
    <div class="signature-section">
        <table>
            <tr>
                <td style="width: 50%;">
                    <div class="signature-box">
                        <div class="signature-line">
                            Service Provider Signature
                        </div>
                        <div style="margin-top: 10px;">
                            <strong>{{ $company_name }}</strong>
                        </div>
                    </div>
                </td>
                <td style="width: 50%;">
                    <div class="signature-box">
                        <div class="signature-line">
                            Patient/Guardian Signature
                        </div>
                        <div style="margin-top: 10px;">
                            <strong>{{ $contract->patient->name }}</strong>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p style="text-align: center; font-size: 10px; color: #666;">
            This is a computer-generated contract. For any queries, please contact {{ $company_email }} or {{ $company_phone }}.
        </p>
    </div>
</body>
</html>

