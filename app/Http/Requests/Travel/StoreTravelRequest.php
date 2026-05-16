<?php

namespace App\Http\Requests\Travel;

use Illuminate\Foundation\Http\FormRequest;

class StoreTravelRequest extends FormRequest
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
            | TRAVEL DETAILS
            |--------------------------------------------------------------------------
            */
            'destination' => [
                'required',
                'string',
                'max:255'
            ],

            'purpose' => [
                'required',
                'string'
            ],

            'transportation_type' => [
                'required',
                'in:company_vehicle,personal_vehicle,commute,air_travel'
            ],

            /*
            |--------------------------------------------------------------------------
            | PERSONAL VEHICLE
            |--------------------------------------------------------------------------
            */
            'plate_number' => [
                'required_if:transportation_type,personal_vehicle',
                'nullable',
                'string',
                'max:50'
            ],

            'fuel_consumption' => [
                'required_if:transportation_type,personal_vehicle',
                'nullable',
                'numeric',
                'min:1'
            ],

            'fuel_type' => [
                'required_if:transportation_type,personal_vehicle',
                'nullable',
                'in:diesel,premium,regular'
            ],

            /*
            |--------------------------------------------------------------------------
            | SCHEDULE
            |--------------------------------------------------------------------------
            */
            'departure_datetime' => [
                'required',
                'date',
                'after_or_equal:today'
            ],

            'return_datetime' => [
                'required',
                'date',
                'after:departure_datetime'
            ],
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [

            'destination.required' =>
                'Destination is required.',

            'purpose.required' =>
                'Purpose is required.',

            'transportation_type.required' =>
                'Transportation type is required.',

            'transportation_type.in' =>
                'Invalid transportation type selected.',

            'plate_number.required_if' =>
                'Plate number is required for personal vehicle.',

            'fuel_consumption.required_if' =>
                'Fuel consumption is required for personal vehicle.',

            'fuel_type.required_if' =>
                'Fuel type is required for personal vehicle.',

            'departure_datetime.after_or_equal' =>
                'Departure date must be today or later.',

            'return_datetime.after' =>
                'Return date must be after departure date.',
        ];
    }
}