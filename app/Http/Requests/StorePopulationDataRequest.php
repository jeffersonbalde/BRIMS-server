<?php
// app/Http/Requests/StorePopulationDataRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePopulationDataRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Adjust authorization logic as needed
    }

    public function rules()
    {
        return [
            'displaced_families' => 'nullable|integer|min:0',
            'displaced_persons' => 'nullable|integer|min:0',
            'families_requiring_assistance' => 'nullable|integer|min:0',
            'families_assisted' => 'nullable|integer|min:0',
            
            // Gender Distribution
            'male_count' => 'nullable|integer|min:0',
            'female_count' => 'nullable|integer|min:0',
            'lgbtqia_count' => 'nullable|integer|min:0',
            
            // Civil Status
            'single_count' => 'nullable|integer|min:0',
            'married_count' => 'nullable|integer|min:0',
            'widowed_count' => 'nullable|integer|min:0',
            'separated_count' => 'nullable|integer|min:0',
            'live_in_count' => 'nullable|integer|min:0',
            
            // Special Groups - all nullable integers
            'pwd_count' => 'nullable|integer|min:0',
            'pregnant_count' => 'nullable|integer|min:0',
            'elderly_count' => 'nullable|integer|min:0',
            'lactating_mother_count' => 'nullable|integer|min:0',
            'solo_parent_count' => 'nullable|integer|min:0',
            'indigenous_people_count' => 'nullable|integer|min:0',
            'lgbtqia_persons_count' => 'nullable|integer|min:0',
            'child_headed_household_count' => 'nullable|integer|min:0',
            'gbv_victims_count' => 'nullable|integer|min:0',
            'four_ps_beneficiaries_count' => 'nullable|integer|min:0',
            'single_headed_family_count' => 'nullable|integer|min:0',
            
            // Age Distribution
            'infant_count' => 'nullable|integer|min:0',
            'toddler_count' => 'nullable|integer|min:0',
            'preschooler_count' => 'nullable|integer|min:0',
            'school_age_count' => 'nullable|integer|min:0',
            'teen_age_count' => 'nullable|integer|min:0',
            'adult_count' => 'nullable|integer|min:0',
            'elderly_age_count' => 'nullable|integer|min:0',
            
            // Religion
            'christian_count' => 'nullable|integer|min:0',
            'subanen_ip_count' => 'nullable|integer|min:0',
            'moro_count' => 'nullable|integer|min:0',
        ];
    }

    public function messages()
    {
        return [
            '*.integer' => 'The :attribute must be a whole number.',
            '*.min' => 'The :attribute cannot be negative.',
        ];
    }
}