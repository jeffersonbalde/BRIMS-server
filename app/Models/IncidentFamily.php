<?php
// app/Models/IncidentFamily.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncidentFamily extends Model
{
    use HasFactory;

    // app/Models/IncidentFamily.php
    protected $fillable = [
        'incident_id',
        'family_number',
        'family_size',
        'evacuation_center',
        'alternative_location',
        'assistance_given',
        'assistance_received',
        'food_assistance',
        'non_food_assistance',
        'shelter_assistance',
        'medical_assistance',
        'other_remarks'
    ];

    protected $casts = [
        'assistance_received' => 'boolean',
        'food_assistance' => 'boolean',
        'non_food_assistance' => 'boolean',
        'shelter_assistance' => 'boolean',
        'medical_assistance' => 'boolean',
    ];

    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }

    public function members()
    {
        return $this->hasMany(IncidentFamilyMember::class, 'family_id');
    }

    public function getAssistanceGivenTextAttribute()
    {
        return match ($this->assistance_given) {
            'F' => 'Food',
            'NFI' => 'Non-Food Items',
            default => 'None'
        };
    }
}
