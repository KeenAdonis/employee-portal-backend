<?php

namespace App\Http\Requests\Travel;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTravelLiquidationRequest extends FormRequest
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
            | LIQUIDATION
            |--------------------------------------------------------------------------
            */
            'travel_liquidation_id' => [
                'required',
                'integer',
                'exists:tb_travel_liquidations,id'
            ],

            /*
            |--------------------------------------------------------------------------
            | EXPENSES
            |--------------------------------------------------------------------------
            */
            'toll_fee' => [
                'nullable',
                'numeric',
                'min:0'
            ],

            'parking_fee' => [
                'nullable',
                'numeric',
                'min:0'
            ],

            'other_expenses' => [
                'nullable',
                'numeric',
                'min:0'
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:2000'
            ],

            /*
            |--------------------------------------------------------------------------
            | STOPS
            |--------------------------------------------------------------------------
            */
            'stops' => [
                'required',
                'array',
                'min:1'
            ],

            'stops.*.from_location' => [
                'required',
                'string',
                'max:255'
            ],

            'stops.*.to_location' => [
                'required',
                'string',
                'max:255'
            ],

            'stops.*.odometer_start' => [
                'required',
                'numeric',
                'min:0'
            ],

            'stops.*.odometer_end' => [
                'required',
                'numeric',
                'gte:stops.*.odometer_start'
            ],

            /*
            |--------------------------------------------------------------------------
            | ATTACHMENTS
            |--------------------------------------------------------------------------
            */
            'attachments' => [
                'nullable',
                'array'
            ],

            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120'
            ],
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [

            'travel_liquidation_id.required' =>
                'Travel liquidation ID is required.',

            'travel_liquidation_id.exists' =>
                'Travel liquidation does not exist.',

            'stops.required' =>
                'At least one travel stop is required.',

            'stops.min' =>
                'At least one travel stop is required.',

            'stops.*.from_location.required' =>
                'Departure location is required.',

            'stops.*.to_location.required' =>
                'Arrival location is required.',

            'stops.*.odometer_start.required' =>
                'Starting odometer is required.',

            'stops.*.odometer_end.required' =>
                'Ending odometer is required.',

            'stops.*.odometer_end.gte' =>
                'Ending odometer must be greater than or equal to starting odometer.',

            'attachments.*.mimes' =>
                'Attachments must be JPG, PNG, or PDF files.',

            'attachments.*.max' =>
                'Each attachment must not exceed 5MB.',
        ];
    }
}