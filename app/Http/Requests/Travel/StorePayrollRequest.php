<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 👉 later pwede mo lagyan ng role check (admin/hr/accounting)
        return true;
    }

    public function rules(): array
    {
        return [

            /* =========================
               REQUIRED CORE
            ========================= */
            'employee_id' => 'required|exists:tb_employee_list,employee_id',
            'EmployeeNo' => ['required', 'string', 'max:50'],
            'EmployeeName' => ['required', 'string', 'max:150'],
            'PayDate' => ['required', 'date'],
            'MonthlySalary' => ['required', 'numeric', 'min:0'],

            /* =========================
               OPTIONAL BASIC
            ========================= */
            'Position' => ['nullable', 'string', 'max:150'],
            'Type' => ['nullable', 'string', 'max:50'],

            /* =========================
               OVERTIME
            ========================= */
            'OTRegularDay' => ['nullable', 'numeric', 'min:0'],
            'OTRestDay' => ['nullable', 'numeric', 'min:0'],
            'OTSpecialNonWorkingDay' => ['nullable', 'numeric', 'min:0'],
            'OTSpecialNonWorkingAndRestDay' => ['nullable', 'numeric', 'min:0'],
            'OTRegularHoliday' => ['nullable', 'numeric', 'min:0'],
            'OTRegularHolidayAndRestDay' => ['nullable', 'numeric', 'min:0'],

            /* =========================
               PER DAY
            ========================= */
            'PDRestDay' => ['nullable', 'numeric', 'min:0'],
            'PDSpecialNonWorkingDay' => ['nullable', 'numeric', 'min:0'],
            'PDSpecialNonWorkingAndRestDay' => ['nullable', 'numeric', 'min:0'],
            'PDRegularHoliday' => ['nullable', 'numeric', 'min:0'],
            'PDRegularHolidayAndRestDay' => ['nullable', 'numeric', 'min:0'],

            /* =========================
               DEDUCTIONS
            ========================= */
            'Absences' => ['nullable', 'numeric', 'min:0'],
            'Tardiness' => ['nullable', 'numeric', 'min:0'],
            'Undertime' => ['nullable', 'numeric', 'min:0'],

            /* =========================
               DE MINIMIS
            ========================= */
            'RiceSubsidy' => ['nullable', 'numeric', 'min:0'],
            'LoadAllowance' => ['nullable', 'numeric', 'min:0'],
            'MedicalReimbursement' => ['nullable', 'numeric', 'min:0'],
            'TripTicket' => ['nullable', 'numeric', 'min:0'],
            'Additional' => ['nullable', 'numeric', 'min:0'],

            /* =========================
               BENEFITS (OPTIONAL INPUTS)
            ========================= */
            'Tax' => ['nullable', 'numeric', 'min:0'],

            /* =========================
               LOANS
            ========================= */
            'SalaryLoanPayment' => ['nullable', 'numeric', 'min:0'],
            'LaptopLoanPayment' => ['nullable', 'numeric', 'min:0'],
            'DeductionPayment' => ['nullable', 'numeric', 'min:0'],
            'SSSPersonalLoanPayment' => ['nullable', 'numeric', 'min:0'],
            'SSSCalamityLoanPayment' => ['nullable', 'numeric', 'min:0'],
            'PagIbigPersonalLoanPayment' => ['nullable', 'numeric', 'min:0'],
            'PagIbigCalamityLoanPayment' => ['nullable', 'numeric', 'min:0'],

            /* =========================
               BASE RATE SUPPORT
            ========================= */
            'prev15Add' => ['nullable', 'numeric', 'min:0'],
            'prev15OTPD' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /* =========================
       SANITIZATION (VERY IMPORTANT)
    ========================= */
    protected function prepareForValidation(): void
    {
        $numericFields = [
            'MonthlySalary',

            'OTRegularDay',
            'OTRestDay',
            'OTSpecialNonWorkingDay',
            'OTSpecialNonWorkingAndRestDay',
            'OTRegularHoliday',
            'OTRegularHolidayAndRestDay',

            'PDRestDay',
            'PDSpecialNonWorkingDay',
            'PDSpecialNonWorkingAndRestDay',
            'PDRegularHoliday',
            'PDRegularHolidayAndRestDay',

            'Absences',
            'Tardiness',
            'Undertime',

            'RiceSubsidy',
            'LoadAllowance',
            'MedicalReimbursement',
            'TripTicket',
            'Additional',

            'Tax',

            'SalaryLoanPayment',
            'LaptopLoanPayment',
            'DeductionPayment',
            'SSSPersonalLoanPayment',
            'SSSCalamityLoanPayment',
            'PagIbigPersonalLoanPayment',
            'PagIbigCalamityLoanPayment',

            'prev15Add',
            'prev15OTPD'
        ];

        $cleaned = [];

        foreach ($numericFields as $field) {
            $cleaned[$field] = $this->sanitizeNumber($this->input($field));
        }

        $this->merge($cleaned);
    }

    private function sanitizeNumber($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        // remove commas (e.g. 1,000.50)
        $value = str_replace(',', '', $value);

        return is_numeric($value) ? (float)$value : 0;
    }

    /* =========================
       CUSTOM ERROR MESSAGES
    ========================= */
    public function messages(): array
    {
        return [
            'EmployeeNo.required' => 'Employee is required.',
            'EmployeeName.required' => 'Employee name is required.',
            'PayDate.required' => 'Pay date is required.',
            'MonthlySalary.required' => 'Monthly salary is required.',
        ];
    }
}