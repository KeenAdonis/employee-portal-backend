<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Loan Schedule</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
        }

        .sub {
            font-size: 12px;
            color: #666;
        }

        .section {
            margin-bottom: 15px;
        }

        .info-table {
            width: 100%;
            margin-bottom: 10px;
        }

        .info-table td {
            padding: 4px 0;
        }

        .label {
            font-weight: bold;
            width: 150px;
        }

        table.schedule {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table.schedule th {
            background: #f5f5f5;
            text-align: left;
            padding: 8px;
            border: 1px solid #ddd;
        }

        table.schedule td {
            padding: 8px;
            border: 1px solid #ddd;
        }

        .right {
            text-align: right;
        }

        .footer {
            margin-top: 20px;
            font-size: 10px;
            text-align: center;
            color: #888;
        }
    </style>
</head>

<body>

    <div class="header">
        <div class="title">Loan Payment Schedule</div>
        <div class="sub">Employee Loan Summary</div>
    </div>

    <div class="section">
        <table class="info-table">
            <tr>
                <td class="label">Employee:</td>
                <td>{{ $loan->employee->FirstName }} {{ $loan->employee->LastName }}</td>
            </tr>
            <tr>
                <td class="label">Loan Type:</td>
                <td>{{ $loan->loan_type }}</td>
            </tr>
            <tr>
                <td class="label">Total Amount:</td>
                <td>&#8369; {{ number_format($loan->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Monthly:</td>
                <td>&#8369; {{ number_format($loan->monthly_amortization, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Cutoff Type:</td>
                <td>{{ strtoupper($loan->cutoff_type) }}</td>
            </tr>
        </table>
    </div>

    <table class="schedule">
        <thead>
            <tr>
                <th width="50">#</th>
                <th>Deduction Date</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($schedule as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($item['date'])->format('M d, Y') }}</td>
                    <td class="right">&#8369; {{ number_format($item['amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Generated on {{ now()->format('M d, Y h:i A') }}
    </div>

</body>

</html>