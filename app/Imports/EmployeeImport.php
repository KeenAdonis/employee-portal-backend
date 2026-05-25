<?php

namespace App\Imports;

use App\Http\Controllers\Api\EmployeeController;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

use Maatwebsite\Excel\Concerns\ToCollection;

use Illuminate\Support\Collection;

class EmployeeImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        $headerSkipped = false;

        foreach ($rows as $row) {

            /*
            |--------------------------------------------------------------------------
            | SKIP HEADER
            |--------------------------------------------------------------------------
            */
            if (!$headerSkipped) {
                $headerSkipped = true;
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | DUPLICATE CHECK
            |--------------------------------------------------------------------------
            */
            $exists =
                Employee::where(
                    'EmployeeNo',
                    $row[0]
                )->exists()

                ||

                User::where(
                    'email',
                    $row[9]
                )->exists();

            if ($exists) {
                continue;
            }

            /*
|--------------------------------------------------------------------------
| CREATE EMPLOYEE
|--------------------------------------------------------------------------
*/

            $birthday = Carbon::parse(
                $row[5]
            )->format('Y-m-d');

            $dateHired = Carbon::parse(
                $row[10]
            )->format('Y-m-d');

            $employee = Employee::create([
                'Status' => 'ACTIVE',

                'EmployeeNo' => $row[0],
                'FirstName' => $row[1],
                'MiddleInitial' => $row[2],
                'LastName' => $row[3],
                'HomeAddress' => $row[4],

                'Birthday' => $birthday,

                'Gender' => $row[6],
                'CivilStatus' => $row[7],
                'ContactNumber' => $row[8],
                'EmailAddress' => $row[9],

                'DateHired' => $dateHired,

                'Department' => $row[11],
                'Company' => $row[12],
                'CompanyStatus' => $row[13],
                'Position' => $row[14],
                'JobLevel' => $row[15],
                'MonthlySalary' => $row[16],
                'SSSNumber' => $row[17],
                'PhilHealthNumber' => $row[18],
                'PagIbigNumber' => $row[19],
                'TIN' => $row[20],
            ]);

            /*
            |--------------------------------------------------------------------------
            | USER
            |--------------------------------------------------------------------------
            */
            $plainPassword = Str::random(10);

            $user = User::create([
                'name' =>
                    $row[1] . ' ' .
                    $row[3],

                'email' => $row[9],

                'password' => Hash::make(
                    $plainPassword
                ),

                'role' => 'employee',

                'employee_no' => $row[0],

                'status' => 'ACTIVE',

                'is_admin' => 0,

                'is_temp_password' => true,
            ]);

            /*
            |--------------------------------------------------------------------------
            | SEND EMAIL
            |--------------------------------------------------------------------------
            */
            app(
                EmployeeController::class
            )->sendToN8n([
                        'email' => $user->email,
                        'employee_name' => $user->name,
                        'employee_no' => $user->employee_no,
                        'temp_password' => $plainPassword,
                    ]);
        }
    }
}