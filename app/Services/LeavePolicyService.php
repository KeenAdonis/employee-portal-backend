<?php

namespace App\Services;

use Carbon\Carbon;

class LeavePolicyService
{
    public static function validateVacationLeave($dateFrom, $totalDays)
    {
        $requiredLeadDays = self::getVacationLeadDays($totalDays);

        if ($requiredLeadDays <= 0) {
            return [
                'valid' => true,
                'message' => null,
            ];
        }

        $today = Carbon::today();
        $minimumDate = $today->copy()->addDays($requiredLeadDays);

        $from = Carbon::parse($dateFrom);

        if ($from->lt($minimumDate)) {
            return [
                'valid' => false,
                'message' =>
                    "Vacation Leave for {$totalDays} day(s) must be filed at least {$requiredLeadDays} day(s) in advance.",
            ];
        }

        return [
            'valid' => true,
            'message' => null,
        ];
    }

    public static function getVacationLeadDays($totalDays)
    {
        if ($totalDays >= 1 && $totalDays <= 2)
            return 2;
        if ($totalDays >= 3 && $totalDays <= 5)
            return 4;
        if ($totalDays >= 6 && $totalDays <= 7)
            return 6;
        if ($totalDays >= 8 && $totalDays <= 10)
            return 9;

        return 0;
    }

    public static function validateSickLeaveAttachment(
        $totalDays,
        $attachment
    ) {
        if ($totalDays >= 1 && !$attachment) {

            return [
                'valid' => false,
                'message' =>
                    $totalDays >= 2
                    ? 'Medical Certificate is required for 2 or more sick leave days.'
                    : 'Excuse Letter is required for 1 sick leave day.',
            ];
        }

        return [
            'valid' => true,
            'message' => null,
        ];
    }
}