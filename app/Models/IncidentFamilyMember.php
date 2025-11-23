<?php
// app/Models/IncidentFamilyMember.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncidentFamilyMember extends Model
{
    use HasFactory;

    // app/Models/IncidentFamilyMember.php
    protected $fillable = [
        'family_id',
        'last_name',
        'first_name',
        'middle_name',
        'position_in_family',
        'sex_gender_identity',
        'age',
        'category',
        'civil_status',
        'ethnicity',
        'vulnerable_groups',
        'casualty',
        'displaced',
        'pwd_type',
        'assistance_received',
        'food_assistance',
        'non_food_assistance',
        'medical_attention',
        'psychological_support',
        'other_remarks'
    ];

    protected $casts = [
        'vulnerable_groups' => 'array',
        'assistance_received' => 'boolean',
        'food_assistance' => 'boolean',
        'non_food_assistance' => 'boolean',
        'medical_attention' => 'boolean',
        'psychological_support' => 'boolean',
    ];

    public function family()
    {
        return $this->belongsTo(IncidentFamily::class, 'family_id');
    }

    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
    }

    public function getIsDisplacedAttribute()
    {
        return $this->displaced === 'Y';
    }
}
