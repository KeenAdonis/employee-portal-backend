<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Loan Payment Schedule</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1f2937;
            margin: 0;
            padding: 30px;
            background: #ffffff;
        }

        .container {
            width: 100%;
        }

        /* =========================================
           HEADER
        ========================================= */

        .header {
            margin-bottom: 25px;
            padding-bottom: 18px;
            border-bottom: 3px solid #d1d5db;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .logo-cell {
            width: 120px;
            vertical-align: middle;
        }

        .logo {
            width: 105px;
            height: auto;
        }

        .company-info {
            vertical-align: middle;
            padding-left: 10px;
        }

        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #e10600;
            line-height: 1.1;
            margin-bottom: 6px;
        }

        .company-tagline {
            font-size: 14px;
            font-weight: bold;
            color: #002060;
            line-height: 1.2;
            margin-bottom: 6px;
        }

        .company-address {
            font-size: 10px;
            color: #111827;
        }

        .title-wrapper {
            margin-top: 18px;
        }

        .title {
            font-size: 20px;
            font-weight: bold;
            color: #111827;
        }

        .subtitle {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }

        /* =========================================
           SUMMARY
        ========================================= */

        .summary-wrapper {
            margin-bottom: 25px;
        }

        .summary-title {
            font-size: 13px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 10px;
            padding-left: 8px;
            border-left: 4px solid #1e40af;
        }

        .info-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .info-table td {
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
        }

        .info-table tr:nth-child(odd) td {
            background: #f9fafb;
        }

        .label {
            width: 190px;
            font-weight: bold;
            color: #374151;
            background: #f3f4f6 !important;
        }

        .value {
            color: #111827;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            background: #dbeafe;
            color: #1d4ed8;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
        }

        /* =========================================
           PAYMENT SCHEDULE
        ========================================= */

        .schedule-title {
            font-size: 13px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 10px;
            padding-left: 8px;
            border-left: 4px solid #1e40af;
        }

        table.schedule {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table.schedule thead th {
            background: #1e3a8a;
            color: #ffffff;
            font-size: 11px;
            font-weight: bold;
            padding: 12px 10px;
            border: 1px solid #1e3a8a;
        }

        table.schedule tbody td {
            padding: 10px;
            border: 1px solid #e5e7eb;
        }

        table.schedule tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .amount {
            font-weight: bold;
            color: #111827;
        }

        .total-row td {
            background: #eff6ff !important;
            font-weight: bold;
            border-top: 2px solid #1e40af;
        }

        /* =========================================
           FOOTER
        ========================================= */

        .footer {
            margin-top: 30px;
            border-top: 1px solid #e5e7eb;
            padding-top: 12px;
            text-align: center;
            font-size: 10px;
            color: #6b7280;
        }
    </style>
</head>

<body>

    <div class="container">

        <!-- =====================================
             HEADER
        ====================================== -->

        <div class="header">

            <table class="header-table">
                <tr>

                    <!-- LOGO -->
                    <td class="logo-cell">

                        <img
                            src="file://{{ public_path('images/logo.png') }}"
                            alt="Company Logo"
                            class="logo"
                        >

                    </td>

                    <!-- COMPANY INFO -->
                    <td class="company-info">

                        <div class="company-name">
                            Psy Systems and Innovations, OPC
                        </div>

                        <div class="company-tagline">
                            Your development is our achievement
                        </div>

                        <div class="company-address">
                            3F Framar Center, 111 A. Mabini St.,
                            Brgy. Kapasigan, Pasig City
                        </div>

                    </td>

                </tr>
            </table>

            <div class="title-wrapper">

                <div class="title">
                    Loan Payment Schedule
                </div>

                <div class="subtitle">
                    Employee Loan Amortization Summary
                </div>

            </div>

        </div>

        <!-- =====================================
             LOAN SUMMARY
        ====================================== -->

        <div class="summary-wrapper">

            <div class="summary-title">
                Loan Information
            </div>

            <table class="info-table">

                <tr>
                    <td class="label">
                        Employee Name
                    </td>

                    <td class="value">
                        {{ $loan->employee->FirstName }}
                        {{ $loan->employee->LastName }}
                    </td>
                </tr>

                <tr>
                    <td class="label">
                        Loan Type
                    </td>

                    <td class="value">
                        {{ $loan->loan_type }}
                    </td>
                </tr>

                <tr>
                    <td class="label">
                        Total Loan Amount
                    </td>

                    <td class="value">
                        &#8369;
                        {{ number_format($loan->total_amount, 2) }}
                    </td>
                </tr>

                <tr>
                    <td class="label">
                        Monthly Amortization
                    </td>

                    <td class="value">
                        &#8369;
                        {{ number_format($loan->monthly_amortization, 2) }}
                    </td>
                </tr>

                <tr>
                    <td class="label">
                        Cutoff Type
                    </td>

                    <td class="value">

                        <span class="badge">
                            {{ strtoupper($loan->cutoff_type) }}
                        </span>

                    </td>
                </tr>

                <tr>
                    <td class="label">
                        Total Payments
                    </td>

                    <td class="value">
                        {{ count($schedule) }} deduction(s)
                    </td>
                </tr>

            </table>

        </div>

        <!-- =====================================
             PAYMENT SCHEDULE
        ====================================== -->

        <div class="schedule-title">
            Payment Schedule
        </div>

        <table class="schedule">

            <thead>
                <tr>
                    <th width="60" class="text-center">
                        #
                    </th>

                    <th>
                        Deduction Date
                    </th>

                    <th width="180" class="text-right">
                        Amount
                    </th>
                </tr>
            </thead>

            <tbody>

                @foreach($schedule as $index => $item)

                    <tr>

                        <td class="text-center">
                            {{ $index + 1 }}
                        </td>

                        <td>
                            {{ \Carbon\Carbon::parse($item['date'])->format('F d, Y') }}
                        </td>

                        <td class="text-right amount">
                            &#8369;
                            {{ number_format($item['amount'], 2) }}
                        </td>

                    </tr>

                @endforeach

                <!-- TOTAL -->

                <tr class="total-row">

                    <td colspan="2" class="text-right">
                        TOTAL
                    </td>

                    <td class="text-right">
                        &#8369;
                        {{ number_format(collect($schedule)->sum('amount'), 2) }}
                    </td>

                </tr>

            </tbody>

        </table>

        <!-- =====================================
             FOOTER
        ====================================== -->

        <div class="footer">

            This document was system generated on

            {{ now()->format('F d, Y h:i A') }}

        </div>

    </div>

</body>

</html>