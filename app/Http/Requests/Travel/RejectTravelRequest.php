<?php

namespace App\Http\Requests\Travel;

use Illuminate\Foundation\Http\FormRequest;

class RejectTravelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized
     * to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules.
     */
    public function rules(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | TRAVEL REQUEST
            |--------------------------------------------------------------------------
            */
            'travel_request_id' => [
                'required',
                'integer',
                'exists:tb_travel_requests,id'
            ],

            /*
            |--------------------------------------------------------------------------
            | REJECTION
            |--------------------------------------------------------------------------
            */
            'rejection_reason' => [
                'required',
                'string',
                'max:1000'
            ],
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [

            'travel_request_id.required' =>
                'Travel request ID is required.',

            'travel_request_id.exists' =>
                'Travel request does not exist.',

            'rejection_reason.required' =>
                'Rejection reason is required.',
        ];
    }
}