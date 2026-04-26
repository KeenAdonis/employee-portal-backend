<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->EmployeeNo,
            'name' => $this->FirstName . ' ' . $this->LastName,
            'email' => $this->EmailAddress,
            'department' => $this->Department,
            'status' => $this->Status,
        ];
    }
}