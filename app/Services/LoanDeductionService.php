<?php

namespace App\Services;

use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanPayment;
use Carbon\Carbon;

class LoanDeductionService
{
    public function processLoan(EmployeeLoan $loan, $payrollDate, $payrollReference = null)
    {
        /* =========================
           VALIDATE STATUS (FIXED)
        ========================= */
        if ($loan->status !== 'Active') {
            return;
        }

        $date = Carbon::parse($payrollDate);
        $day = $date->day;
        $lastDay = $date->copy()->endOfMonth()->day;

        /* =========================
           CHECK CUTOFF MATCH (FIXED)
        ========================= */
        if (!$this->isValidCutoff($loan->cutoff_type, $day)) {
            return;
        }

        /* =========================
           PREVENT DUPLICATE DEDUCTION (IMPROVED)
        ========================= */
        $alreadyDeducted = EmployeeLoanPayment::where('loan_id', $loan->id)
            ->when($payrollReference, function ($q) use ($payrollReference) {
                $q->where('payroll_reference', $payrollReference);
            }, function ($q) use ($date) {
                $q->whereDate('deduction_date', $date);
            })
            ->exists();

        if ($alreadyDeducted) {
            return;
        }

        /* =========================
           COMPUTE PER DEDUCTION
        ========================= */
        $perDeduction = $loan->cutoff_type === 'both'
            ? $loan->monthly_amortization / 2
            : $loan->monthly_amortization;

        /* =========================
           NO NEGATIVE BALANCE
        ========================= */
        $deduction = min($loan->balance, $perDeduction);

        if ($deduction <= 0) {
            return;
        }

        /* =========================
           SAVE PAYMENT
        ========================= */
        EmployeeLoanPayment::create([
            'loan_id' => $loan->id,
            'amount' => round($deduction, 2),
            'deduction_date' => $date,
            'payroll_reference' => $payrollReference,
        ]);

        /* =========================
           UPDATE LOAN BALANCE
        ========================= */
        $loan->balance -= $deduction;

        if ($loan->balance <= 0) {
            $loan->balance = 0;
            $loan->status = 'Completed';
        }

        $loan->save();
    }

    /* =========================
       HELPER: CHECK CUTOFF (FIXED)
    ========================= */
    private function isValidCutoff($cutoffType, $day)
    {
        $isEndCutoff = in_array($day, [28, 29, 30, 31]);

        return match ($cutoffType) {
            '15' => $day == 15,
            '30' => $isEndCutoff,
            'both' => $day == 15 || $isEndCutoff,
            default => false,
        };
    }
}