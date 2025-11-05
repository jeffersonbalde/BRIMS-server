<?php
// app/Http/Resources/PopulationDataResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PopulationDataResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'incident_id' => $this->incident_id,
            
            // Displacement and Assistance
            'displaced_families' => $this->displaced_families,
            'displaced_persons' => $this->displaced_persons,
            'families_requiring_assistance' => $this->families_requiring_assistance,
            'families_assisted' => $this->families_assisted,
            
            // Gender Distribution
            'male_count' => $this->male_count,
            'female_count' => $this->female_count,
            'lgbtqia_count' => $this->lgbtqia_count,
            
            // Civil Status
            'single_count' => $this->single_count,
            'married_count' => $this->married_count,
            'widowed_count' => $this->widowed_count,
            'separated_count' => $this->separated_count,
            'live_in_count' => $this->live_in_count,
            
            // Special Groups
            'pwd_count' => $this->pwd_count,
            'pregnant_count' => $this->pregnant_count,
            'elderly_count' => $this->elderly_count,
            'lactating_mother_count' => $this->lactating_mother_count,
            'solo_parent_count' => $this->solo_parent_count,
            'indigenous_people_count' => $this->indigenous_people_count,
            'lgbtqia_persons_count' => $this->lgbtqia_persons_count,
            'child_headed_household_count' => $this->child_headed_household_count,
            'gbv_victims_count' => $this->gbv_victims_count,
            'four_ps_beneficiaries_count' => $this->four_ps_beneficiaries_count,
            'single_headed_family_count' => $this->single_headed_family_count,
            
            // Age Distribution
            'infant_count' => $this->infant_count,
            'toddler_count' => $this->toddler_count,
            'preschooler_count' => $this->preschooler_count,
            'school_age_count' => $this->school_age_count,
            'teen_age_count' => $this->teen_age_count,
            'adult_count' => $this->adult_count,
            'elderly_age_count' => $this->elderly_age_count,
            
            // Religion
            'christian_count' => $this->christian_count,
            'subanen_ip_count' => $this->subanen_ip_count,
            'moro_count' => $this->moro_count,
            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}