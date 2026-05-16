<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\LoanPayment;
use App\Models\EmployeeLoan;

class PayrollService
{
   public function compute(array $data)
   {
      $monthly = $this->num($data['MonthlySalary'] ?? 0);

      $dailyRate = ($monthly * 12) / 313;
      $hourlyRate = $dailyRate / 8;

      $payDate = $data['PayDate'] ?? null;
      $day = $payDate ? (int) date('d', strtotime($payDate)) : null;

      /* =========================
         RATES
      ========================= */
      $otRegRate = $hourlyRate * 1.25;
      $otRestRate = $hourlyRate * 1.69;
      $otSpecialRate = $hourlyRate * 1.69;
      $otSpecialRestRate = $hourlyRate * 1.95;
      $otHolidayRate = $hourlyRate * 2.60;
      $otHolidayRestRate = $hourlyRate * 3.38;

      $pdRestRate = $dailyRate * 1.30;
      $pdSpecialRate = $dailyRate * 1.30;
      $pdSpecialRestRate = $dailyRate * 1.50;
      $pdHolidayRate = $dailyRate * 2.00;
      $pdHolidayRestRate = $dailyRate * 2.60;

      /* =========================
         OVERTIME
      ========================= */
      $otRegular = $this->num($data['OTRegularDay'] ?? 0) * $otRegRate;
      $otRest = $this->num($data['OTRestDay'] ?? 0) * $otRestRate;
      $otSpecial = $this->num($data['OTSpecialNonWorkingDay'] ?? 0) * $otSpecialRate;
      $otSpecialRest = $this->num($data['OTSpecialNonWorkingAndRestDay'] ?? 0) * $otSpecialRestRate;
      $otHoliday = $this->num($data['OTRegularHoliday'] ?? 0) * $otHolidayRate;
      $otHolidayRest = $this->num($data['OTRegularHolidayAndRestDay'] ?? 0) * $otHolidayRestRate;

      $totalOT = $otRegular + $otRest + $otSpecial + $otSpecialRest + $otHoliday + $otHolidayRest;

      /* =========================
         PER DAY / HOLIDAY
      ========================= */
      $pdRest = $this->num($data['PDRestDay'] ?? 0) * $pdRestRate;
      $pdSpecial = $this->num($data['PDSpecialNonWorkingDay'] ?? 0) * $pdSpecialRate;
      $pdSpecialRest = $this->num($data['PDSpecialNonWorkingAndRestDay'] ?? 0) * $pdSpecialRestRate;
      $pdHoliday = $this->num($data['PDRegularHoliday'] ?? 0) * $pdHolidayRate;
      $pdHolidayRest = $this->num($data['PDRegularHolidayAndRestDay'] ?? 0) * $pdHolidayRestRate;

      $totalHolidayPay = $pdRest + $pdSpecial + $pdSpecialRest + $pdHoliday + $pdHolidayRest;

      /* =========================
         ALLOWANCES
      ========================= */
      $rice = $this->num($data['RiceSubsidy'] ?? 0);
      $load = $this->num($data['LoadAllowance'] ?? 0);
      $medical = $this->num($data['MedicalReimbursement'] ?? 0);
      $trip = $this->num($data['TripTicket'] ?? 0);
      $add = $this->num($data['Additional'] ?? 0);

      $deMinimis = $rice + $load + $medical + $trip + $add;

      /* =========================
         ATTENDANCE
      ========================= */
      $absences = $this->num($data['Absences'] ?? 0) * $dailyRate;

      // 🔥 your rule: 1 minute = 1 peso
      $tardiness = $this->num($data['Tardiness'] ?? 0);

      $undertime = $this->num($data['Undertime'] ?? 0) * $hourlyRate;

      $totalAttendanceDeduction = $absences + $tardiness + $undertime;

      /* =========================
         PREVIOUS CUT-OFF 🔥
      ========================= */
      $prev = null;

      if (in_array($day, [28, 29, 30, 31])) {
         $prev = Payroll::where('employee_id', $data['employee_id'] ?? null)
            ->whereMonth('PayDate', date('m', strtotime($payDate)))
            ->whereYear('PayDate', date('Y', strtotime($payDate)))
            ->whereDay('PayDate', 15)
            ->latest('PayDate')
            ->first();
      }

      $prevOT = $prev->TotalOvertime ?? 0;
      $prevPD = $prev->TotalPerDay ?? 0;
      $prevAdditional = $prev->Additional ?? 0;

      /* =========================
         GOVERNMENT BASE 🔥
      ========================= */
      if ($day === 15) {
         $governmentBase =
            $monthly +
            $totalOT +
            $totalHolidayPay +
            $add;
      } else {
         $governmentBase =
            $monthly +
            $prevOT + $prevPD + $prevAdditional +
            $totalOT + $totalHolidayPay + $add;
      }

      /* =========================
         GOVERNMENT COMPUTE
      ========================= */
      $sss = $this->getSSS($governmentBase);
      $sssWisp = $this->getSSSWisp($governmentBase);

      $philBase = $monthly + $add + $prevAdditional;

      if ($philBase < 10000)
         $philBase = 10000;
      if ($philBase > 100000)
         $philBase = 100000;

      $philHealth = $philBase * 0.025;

      $pagibig = 200;
      $hmo = 496.5;
      $tax = $this->num($data['Tax'] ?? 0);

      /* =========================
         APPLY PAY DATE RULE 🔥
      ========================= */
      $sssFinal = 0;
      $sssWispFinal = 0;
      $philHealthFinal = 0;
      $pagibigFinal = 0;
      $hmoFinal = 0;
      $taxFinal = 0;

      if ($day === 15) {
         $hmoFinal = $hmo;
      } elseif (in_array($day, [28, 29, 30, 31])) {
         $sssFinal = $sss;
         $sssWispFinal = $sssWisp;
         $philHealthFinal = $philHealth;
         $pagibigFinal = $pagibig;
         $hmoFinal = $hmo;
         $taxFinal = $tax;
      }

      $totalGovernmentDeduction =
         $sssFinal +
         $sssWispFinal +
         $philHealthFinal +
         $pagibigFinal +
         $hmoFinal +
         $taxFinal;

      /* =========================
         LOANS (WITH BREAKDOWN 🔥)
      ========================= */
      $totalLoans = 0;
      $loanBreakdown = [];

      $employeeId = $data['employee_id'] ?? null;

      if ($employeeId && $payDate) {

         $loans = EmployeeLoan::where('employee_id', $employeeId)
            ->where('status', 'Active')
            ->get();

         $lastDay = date('t', strtotime($payDate));

         foreach ($loans as $loan) {

            $perDeduction = $loan->cutoff_type === 'both'
               ? $loan->monthly_amortization / 2
               : $loan->monthly_amortization;

            // 🔥 MATCH SA LoanDeductionService
            $isEndCutoff = in_array($day, [28, 29, 30, 31]);

            $valid = match ($loan->cutoff_type) {
               '15' => $day == 15,
               '30' => $isEndCutoff,
               'both' => $day == 15 || $isEndCutoff,
               default => false,
            };

            if (!$valid)
               continue;

            $deduction = min($loan->balance, $perDeduction);

            if ($deduction <= 0)
               continue;

            $totalLoans += $deduction;

            // 🔥 BREAKDOWN FOR UI
            $loanBreakdown[] = [
               'loan_id' => $loan->id,
               'loan_type' => $loan->loan_type,
               'amount' => round($deduction, 2),
               'balance' => round($loan->balance - $deduction, 2),
               'cutoff' => $loan->cutoff_type,
               'pay_date' => $payDate,
            ];
         }
      }

      /* =========================
         TOTAL
      ========================= */
      $totalDeductions =
         $totalAttendanceDeduction +
         $totalGovernmentDeduction +
         $totalLoans;

      $gross =
         ($monthly / 2) +
         $totalOT +
         $totalHolidayPay +
         $deMinimis;

      $net = $gross - $totalDeductions;

      return [
         'OTRegularAmount' => $otRegular,
         'OTRestDayAmount' => $otRest,
         'OTSpecialAmount' => $otSpecial,
         'OTSpecialRestAmount' => $otSpecialRest,
         'OTHolidayAmount' => $otHoliday,
         'OTHolidayRestAmount' => $otHolidayRest,

         'PDRestDayAmount' => $pdRest,
         'PDSpecialAmount' => $pdSpecial,
         'PDSpecialRestAmount' => $pdSpecialRest,
         'PDHolidayAmount' => $pdHoliday,
         'PDHolidayRestAmount' => $pdHolidayRest,

         'AbsencesAmount' => $absences,
         'TardinessAmount' => $tardiness,
         'UndertimeAmount' => $undertime,

         'RiceSubsidyAmount' => $rice,
         'LoadAllowanceAmount' => $load,
         'MedicalReimbursementAmount' => $medical,
         'TripTicketAmount' => $trip,
         'AdditionalAmount' => $add,

         'sssAmount' => $sssFinal,
         'sssWispAmount' => $sssWispFinal,
         'philHealthAmount' => $philHealthFinal,
         'pagIbigAmount' => $pagibigFinal,
         'hmoAmount' => $hmoFinal,
         'taxAmount' => $taxFinal,

         'Gross' => $gross,
         'NetPay' => $net,
         'TotalOvertime' => $totalOT,
         'TotalHolidayPay' => $totalHolidayPay,
         'TotalAttendanceDeduction' => $totalAttendanceDeduction,
         'TotalGovernmentDeduction' => $totalGovernmentDeduction,
         'TotalDeMinimis' => $deMinimis,
         'TotalLoans' => $totalLoans,
         'loan_breakdown' => $loanBreakdown,
         'TotalDeductions' => $totalDeductions,
      ];
   }

   private function getSSS($salary)
   {
      $salary = $this->num($salary);

      // cap to max MSC
      if ($salary > 35000) {
         $salary = 35000;
      }

      // round down to nearest 500
      $msc = floor($salary / 500) * 500;

      // minimum MSC
      if ($msc < 5000) {
         $msc = 5000;
      }

      /**
       * REGULAR SSS
       * capped at 20,000 MSC only
       */
      $regularMSC = min($msc, 20000);

      // employee share = 5%
      $employeeShare = $regularMSC * 0.05;

      return round($employeeShare, 2);
   }

   private function getSSSWisp($salary)
   {
      $salary = $this->num($salary);

      // no WISP below or equal 20k
      if ($salary <= 20000) {
         return 0;
      }

      // cap total MSC
      if ($salary > 35000) {
         $salary = 35000;
      }

      // round down to nearest 500
      $msc = floor($salary / 500) * 500;

      /**
       * WISP portion
       * excess above 20k MSC
       */
      $wispMSC = $msc - 20000;

      // employee WISP share = 5%
      $wisp = $wispMSC * 0.05;

      return round($wisp, 2);
   }

   private function num($value)
   {
      return is_numeric($value) ? (float) $value : 0;
   }
}