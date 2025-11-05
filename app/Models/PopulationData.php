<?php
// app/Models/PopulationData.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PopulationData extends Model
{
    use HasFactory;

    protected $fillable = [
        'incident_id',
        // Displacement and Assistance
        'displaced_families',
        'displaced_persons',
        'families_requiring_assistance',
        'families_assisted',
        
        // Gender Distribution
        'male_count',
        'female_count',
        'lgbtqia_count',
        
        // Civil Status
        'single_count',
        'married_count',
        'widowed_count',
        'separated_count',
        'live_in_count',
        
        // Special Groups
        'pwd_count',
        'pregnant_count',
        'elderly_count',
        'lactating_mother_count',
        'solo_parent_count',
        'indigenous_people_count',
        'lgbtqia_persons_count',
        'child_headed_household_count',
        'gbv_victims_count',
        'four_ps_beneficiaries_count',
        'single_headed_family_count',
        
        // Age Distribution
        'infant_count',
        'toddler_count',
        'preschooler_count',
        'school_age_count',
        'teen_age_count',
        'adult_count',
        'elderly_age_count',
        
        // Religion
        'christian_count',
        'subanen_ip_count',
        'moro_count',
    ];

    protected $casts = [
        'displaced_families' => 'integer',
        'displaced_persons' => 'integer',
        'families_requiring_assistance' => 'integer',
        'families_assisted' => 'integer',
        // ... cast all integer fields
    ];

    /**
     * Get the incident that owns the population data.
     */
    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }

    public function getTotalPopulationAttribute()
{
    return $this->male_count + $this->female_count + $this->lgbtqia_count;
}

public function getTotalFamiliesAttribute()
{
    return $this->displaced_families;
}

public function getAssistancePercentageAttribute()
{
    if ($this->families_requiring_assistance == 0) return 0;
    return round(($this->families_assisted / $this->families_requiring_assistance) * 100, 2);
}

public function getAgeDistributionAttribute()
{
    return [
        'infant' => $this->infant_count,
        'toddler' => $this->toddler_count,
        'preschooler' => $this->preschooler_count,
        'school_age' => $this->school_age_count,
        'teen_age' => $this->teen_age_count,
        'adult' => $this->adult_count,
        'elderly' => $this->elderly_age_count,
    ];
}
}