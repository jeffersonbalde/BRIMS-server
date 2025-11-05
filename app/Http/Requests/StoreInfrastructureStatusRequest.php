<?php
// app/Http/Requests/StoreInfrastructureStatusRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInfrastructureStatusRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Adjust authorization logic as needed
    }

    public function rules()
    {
        return [
            'roads_bridges_status' => 'required|in:PASSABLE,NOT_PASSABLE',
            'roads_reported_not_passable' => 'nullable|date',
            'roads_reported_passable' => 'nullable|date',
            'roads_remarks' => 'nullable|string|max:1000',
            
            'power_outage_time' => 'nullable|date',
            'power_restored_time' => 'nullable|date',
            'power_remarks' => 'nullable|string|max:1000',
            
            'communication_interruption_time' => 'nullable|date',
            'communication_restored_time' => 'nullable|date',
            'communication_remarks' => 'nullable|string|max:1000',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->roads_bridges_status === 'NOT_PASSABLE' && !$this->roads_reported_not_passable) {
                $validator->errors()->add(
                    'roads_reported_not_passable', 
                    'Date and time is required when road status is NOT PASSABLE.'
                );
            }
        });
    }
}